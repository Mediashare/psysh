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

            // Parse based on detected file format
            if ($fileFormat === 4) {
                // Xdebug 3 format: level func_num type time memory function is_user filename lineno params...
                $parts = preg_split('/\t+/', $t);
                if (count($parts) < 3) {
                    continue;
                }
                
                $level = (int) $parts[0];
                $funcNum = (int) $parts[1];
                $type = (int) $parts[2];
                
                if ($type === 0 && count($parts) >= 6) {
                    // Function entry
                    $time = (float) $parts[3] * 1000000;
                    $memory = (int) $parts[4];
                    $fn = $parts[5];
                    
                    $callStack[$funcNum] = [
                        'name' => $fn,
                        'start_time' => $time,
                        'start_memory' => $memory,
                        'level' => $level,
                    ];
                } elseif ($type === 1 && count($parts) >= 4) {
                    // Function exit
                    if (isset($callStack[$funcNum])) {
                        $call = $callStack[$funcNum];
                        $time = (float) $parts[3] * 1000000;
                        $memory = (int) $parts[4];
                        $duration = max(0, $time - $call['start_time']);
                        $memoryDelta = $memory - $call['start_memory'];

                        if (!isset($functions[$call['name']])) {
                            $functions[$call['name']] = [
                                'calls' => 0,
                                'time' => 0,
                                'exclusive_time' => 0,
                                'memory' => 0,
                                'peak_memory' => 0,
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

                        unset($callStack[$funcNum]);
                    }
                }
            } else {
                // Legacy format (format 1): Match Xdebug computerized trace format
                if (preg_match('/^\s*(\d+)\s+(\d+\.\d+)\s+(\d+)\s+(->|<-)\s+(.+?)(?:\s+\(.+\))?(?:\s+.*)?$/', $t, $m)) {
                    $level = (int) $m[1];
                    $time = (float) $m[2] * 1000000;
                    $memory = (int) $m[3];
                    $op = $m[4];
                    $fn = $m[5];

                    if ($op === '->') {
                        // Function entry
                        $callStack[$level] = [
                            'name' => $fn,
                            'start_time' => $time,
                            'start_memory' => $memory,
                        ];
                    } elseif ($op === '<-') {
                        // Function exit
                        if (isset($callStack[$level])) {
                            $call = $callStack[$level];
                            $duration = max(0, $time - $call['start_time']);
                            $memoryDelta = $memory - $call['start_memory'];

                            if (!isset($functions[$call['name']])) {
                                $functions[$call['name']] = [
                                    'calls' => 0,
                                    'time' => 0,
                                    'exclusive_time' => 0,
                                    'memory' => 0,
                                    'peak_memory' => 0,
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

                            unset($callStack[$level]);
                        }
                    }
                }
            }
        }

        return $this->addPercentages($functions);
    }

    /**
     * Add time_percent and memory_percent to each function.
     */
    private function addPercentages(array $functions): array
    {
        $totalTime = array_sum(array_column($functions, 'time'));
        $totalMemory = array_sum(array_column($functions, 'memory'));

        foreach ($functions as $name => &$data) {
            $data['time_percent'] = $totalTime > 0 ? ($data['time'] / $totalTime) * 100 : 0;
            $data['memory_percent'] = $totalMemory > 0 ? ($data['memory'] / $totalMemory) * 100 : 0;
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
