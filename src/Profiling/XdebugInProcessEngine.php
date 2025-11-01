<?php

namespace Psy\Profiling;

use Psy\Shell;
use Psy\Exception\RuntimeException;

/**
 * XdebugInProcessEngine
 *
 * Profiling engine using Xdebug trace in the current process.
 * Requires xdebug.mode to include 'trace' in the current PHP process.
 * This is a lightweight alternative to subprocess execution.
 */
class XdebugInProcessEngine implements ProfilerEngine
{
    public function profile(string $code, Shell $shell, bool $debug = false): array
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('Xdebug extension with trace mode is not available in current process.');
        }

        $traceFile = '';

        try {
            // Configure trace settings
            ini_set('xdebug.trace_output_name', 'trace.' . date('Ymd_His') . '.' . getmypid());
            ini_set('xdebug.trace_format', '1');
            ini_set('xdebug.collect_params', '4');

            // Start trace
            $traceFile = xdebug_start_trace();

            if ($traceFile === false) {
                throw new RuntimeException('Xdebug failed to start trace. Check Xdebug configuration.');
            }

            // Execute code
            try {
                $code = $this->normalizeInlineCode($code);
                $shell->execute($code);
            } finally {
                // Always stop trace
                xdebug_stop_trace();
            }

            if (!$traceFile || !file_exists($traceFile)) {
                throw new RuntimeException(
                    sprintf('Xdebug trace file was not generated. Expected file: %s', $traceFile ?: 'none')
                );
            }

            // Parse trace file
            return $this->parseXdebugTrace($traceFile);

        } finally {
            // Clean up trace file
            if ($traceFile && file_exists($traceFile)) {
                @unlink($traceFile);
            }
        }

        return [];
    }

    public static function isAvailable(): bool
    {
        if (!extension_loaded('xdebug') || !function_exists('xdebug_start_trace')) {
            return false;
        }

        // Check if trace mode is enabled
        $mode = ini_get('xdebug.mode');
        if (!($mode && str_contains($mode, 'trace'))) {
            return false;
        }

        // Attempt to start and stop a trace to verify functionality
        $traceFile = @xdebug_start_trace();
        if ($traceFile === false) {
            return false;
        }
        @xdebug_stop_trace();

        $success = false;
        if (is_string($traceFile) && file_exists($traceFile)) {
            $success = @unlink($traceFile);
        }

        return $success;
    }

    public function getName(): string
    {
        return 'Xdebug In-Process';
    }


    /**
     * Normalize inline code by adding semicolon if needed.
     */
    private function normalizeInlineCode(string $code): string
    {
        $trimmed = rtrim($code);
        if ($trimmed === '') {
            return $code;
        }
        $last = substr($trimmed, -1);
        if ($last !== ';' && $last !== '}' && $last !== ':') {
            return $trimmed . ';';
        }
        return $trimmed;
    }

    /**
     * Parse Xdebug trace file and return normalized profiling data.
     */
    private function parseXdebugTrace(string $traceFile): array
    {
        $content = @file_get_contents($traceFile);
        if ($content === false) {
            return [];
        }
        
        // Detect gzip compression and decompress if needed
        if (str_ends_with($traceFile, '.gz') || substr($content, 0, 2) === "\x1f\x8b") {
            $decompressed = @gzdecode($content);
            if ($decompressed === false) {
                return [];
            }
            $content = $decompressed;
        }

        $lines = preg_split('/\r?\n/', $content);
        $functions = [];
        $callStack = [];
        
        // Detect file format
        $fileFormat = null;
        foreach ($lines as $line) {
            if (preg_match('/^File format: (\d+)/', $line, $m)) {
                $fileFormat = (int) $m[1];
                break;
            }
        }

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || str_contains($t, 'TRACE START') || str_contains($t, 'TRACE END') || str_contains($t, 'Version:') || str_contains($t, 'File format:')) {
                continue;
            }

            // Try human-readable format first (time memory INDENT-> function or time memory INDENT<-)
            if (preg_match('/^(\d+\.\d+)\s+(\d+)(\s+)(->|<-)\s*(.*)$/', $t, $m)) {
                $time = (float) $m[1] * 1000000;
                $memory = (int) $m[2];
                $indent = $m[3];
                $op = $m[4];
                $fnPart = trim($m[5]);

                // Calculate level by indentation (spaces before ->)
                $level = (int) (strlen($indent) / 2); // Assuming 2 spaces per level

                if ($op === '->') {
                    // Function entry - Clean up function name
                    $fn = preg_replace('/\s+\/.*$/', '', $fnPart); // Remove file path
                    $fn = preg_replace('/\(\)\s*$/', '', $fn); // Remove () at end

                    $callStack[$level] = [
                        'name' => $fn,
                        'start_time' => $time,
                        'start_memory' => $memory,
                        'peak_memory' => $memory,
                    ];

                    // Initialize function entry if it doesn't exist yet
                    if (!isset($functions[$fn])) {
                        $functions[$fn] = [
                            'calls' => 0,
                            'time' => 0,
                            'exclusive_time' => 0,
                            'memory' => 0,
                            'peak_memory' => $memory,
                            'cpu_time' => 0,
                            'is_user' => $this->isUserFunction($fn),
                            'params' => null,
                        ];
                    }
                    // Track peak memory for all active functions in call stack
                    foreach ($callStack as $stackLevel => &$stackEntry) {
                        $stackEntry['peak_memory'] = max($stackEntry['peak_memory'], $memory);
                        if (isset($functions[$stackEntry['name']])) {
                            $functions[$stackEntry['name']]['peak_memory'] = max($functions[$stackEntry['name']]['peak_memory'], $memory);
                        }
                    }
                } elseif ($op === '<-') {
                    // Function exit - Find matching entry in call stack
                    // Note: $fnPart might be a return value (like "11") or empty
                    if (isset($callStack[$level])) {
                        $call = $callStack[$level];
                        $duration = max(0, $time - $call['start_time']);
                        $memoryDelta = $memory - $call['start_memory'];

                        // Update peak memory for all active functions
                        foreach ($callStack as $stackLevel => &$stackEntry) {
                            $stackEntry['peak_memory'] = max($stackEntry['peak_memory'], $memory);
                            if (isset($functions[$stackEntry['name']])) {
                                $functions[$stackEntry['name']]['peak_memory'] = max($functions[$stackEntry['name']]['peak_memory'], $memory);
                            }
                        }

                        $functions[$call['name']]['calls']++;
                        $functions[$call['name']]['time'] += $duration;
                        $functions[$call['name']]['exclusive_time'] += $duration;
                        $functions[$call['name']]['memory'] += $memoryDelta;
                        $functions[$call['name']]['cpu_time'] += $duration;

                        unset($callStack[$level]);
                    }
                }
            }
            // Fallback: Try tab-separated format (Xdebug 3 computerized format)
            elseif (preg_match('/^(\d+)\t+(\d+)\t+(\d+)\t+(.*)$/', $t, $m)) {
                $level = (int) $m[1];
                $funcNum = (int) $m[2];
                $type = (int) $m[3];
                $rest = $m[4];

                $parts = preg_split('/\t+/', $rest);

                if ($type === 0 && count($parts) >= 3) {
                    // Function entry
                    $time = (float) $parts[0] * 1000000;
                    $memory = (int) $parts[1];
                    $fn = $parts[2];

                    $callStack[$funcNum] = [
                        'name' => $fn,
                        'start_time' => $time,
                        'start_memory' => $memory,
                        'level' => $level,
                    ];
                } elseif ($type === 1 && count($parts) >= 2) {
                    // Function exit
                    if (isset($callStack[$funcNum])) {
                        $call = $callStack[$funcNum];
                        $time = (float) $parts[0] * 1000000;
                        $memory = (int) $parts[1];
                        $duration = max(0, $time - $call['start_time']);
                        $memoryDelta = $memory - $call['start_memory'];

                        if (!isset($functions[$call['name']])) {
                            $functions[$call['name']] = [
                                'calls' => 0,
                                'time' => 0,
                                'exclusive_time' => 0,
                                'memory' => 0,
                                'peak_memory' => $memory,
                                'cpu_time' => 0,
                                'is_user' => $this->isUserFunction($call['name']),
                                'params' => null,
                            ];
                        }

                        $functions[$call['name']]['calls']++;
                        $functions[$call['name']]['time'] += $duration;
                        $functions[$call['name']]['exclusive_time'] += $duration;
                        $functions[$call['name']]['memory'] += $memoryDelta;
                        $functions[$call['name']]['cpu_time'] += $duration;
                        $functions[$call['name']]['peak_memory'] = max($functions[$call['name']]['peak_memory'], $memory);

                        unset($callStack[$funcNum]);
                    }
                }
            }
        }

        // Convert float time values to integers and ensure cpu_time matches time
        foreach ($functions as &$func) {
            $func['time'] = (int) $func['time'];
            $func['exclusive_time'] = (int) $func['exclusive_time'];
            $func['cpu_time'] = 0; // Not tracked in human-readable format
        }

        return $functions;
    }

    /**
     * Check if a function is user code.
     */
    private function isUserFunction(string $name): bool
    {
        $psyshNamespaces = ['Psy\\', 'PhpParser\\', 'Symfony\\Component\\Console\\', 'Symfony\\Component\\VarDumper\\'];
        foreach ($psyshNamespaces as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return false;
            }
        }

        // Check if it's a PHP internal function
        if (!str_contains($name, '::') && !str_contains($name, '\\') && function_exists($name)) {
            try {
                $reflection = new \ReflectionFunction($name);
                if ($reflection->isInternal()) {
                    return false;
                }
            } catch (\ReflectionException $e) {
                // Ignore
            }
        }

        return true;
    }
}
