<?php

namespace Psy\Profiling;

use Psy\Shell;
use Psy\Exception\RuntimeException;

/**
 * XdebugSubprocessEngine
 *
 * Profiling engine using Xdebug trace in a subprocess.
 * This engine runs code in a separate process with xdebug.mode=trace enabled,
 * allowing it to capture detailed trace data including all function calls.
 *
 * Features:
 * - Uses ContextBuilder to generate PHP prelude script
 * - Builds full script: prelude + xdebug trace setup + user code + echo trace path
 * - Writes to temp file via tempnam(sys_get_temp_dir(), 'psysh_profile_')
 * - Executes via subprocess: PHP_BINARY -d xdebug.mode=trace script_path
 * - Captures stdout (first line = trace file path), handles stderr errors
 * - Parses trace file using Xdebug v3 parser logic
 * - Normalizes to standard schema
 * - Cleans up temp script and trace file in finally block
 * - Supports --debug option to show script/command/trace-path
 */
class XdebugSubprocessEngine implements ProfilerEngine
{
    private const PSYSH_NAMESPACES = [
        'Psy\\',
        'PhpParser\\',
        'Symfony\\Component\\Console\\',
        'Symfony\\Component\\VarDumper\\',
    ];

    /**
     * {@inheritdoc}
     */
    public function profile(string $code, Shell $shell, bool $debug = false): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Xdebug extension is not available.');
        }

        $scriptPath = tempnam(sys_get_temp_dir(), 'psysh_profile_');
        if ($scriptPath === false) {
            throw new RuntimeException('Failed to create temporary script file.');
        }

        $traceFile = '';

        try {
            // 1. Build context reconstruction script using ContextBuilder
            $contextScript = ContextBuilder::buildContextScript($shell);

            // 2. Build Xdebug trace setup and user code
            $code = $this->normalizeInlineCode($code);
            $traceBaseName = 'trace.' . date('Ymd_His') . '.' . getmypid();

            $profilingCode = sprintf(
                'ini_set("xdebug.trace_output_name", %s);' . PHP_EOL .
                'ini_set("xdebug.trace_format", "1");' . PHP_EOL .
                'ini_set("xdebug.collect_params", "4");' . PHP_EOL .
                'ini_set("xdebug.collect_return", "1");' . PHP_EOL .
                'ini_set("xdebug.collect_assignments", "0");' . PHP_EOL .
                '$__psysh_thrown = null;' . PHP_EOL .
                'ob_start();' . PHP_EOL .
                '$__psysh_trace_file = xdebug_start_trace();' . PHP_EOL .
                'try { %s } catch (\Throwable $__psysh_e) { $__psysh_thrown = $__psysh_e; }' . PHP_EOL .
                'xdebug_stop_trace();' . PHP_EOL .
                'ob_end_clean();' . PHP_EOL .
                'echo $__psysh_trace_file . PHP_EOL;' . PHP_EOL .
                'if ($__psysh_thrown) { fwrite(STDERR, "Error: " . $__psysh_thrown->getMessage() . PHP_EOL); exit(1); }',
                var_export($traceBaseName, true),
                $code
            );

            // 3. Write complete script to temp file
            $fullScript = "<?php\n" . $contextScript . "\n" . $profilingCode;

            // DEBUG: Save script for inspection if debug mode
            if ($debug) {
                $debugPath = sys_get_temp_dir() . '/psysh_profile_debug_' . date('His') . '.php';
                file_put_contents($debugPath, $fullScript);
            }

            if (file_put_contents($scriptPath, $fullScript) === false) {
                throw new RuntimeException('Failed to write profiling script to temporary file.');
            }

            // 4. Execute via subprocess with xdebug.mode=trace
            $command = sprintf(
                '%s -d xdebug.mode=trace %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($scriptPath)
            );

            $traceOutput = shell_exec($command);
            if ($traceOutput === null) {
                throw new RuntimeException('Failed to execute profiling script.');
            }

            // 5. Capture stdout (first line = trace file path) and handle stderr errors
            $lines = explode("\n", $traceOutput);
            $traceFile = trim($lines[0]);

            // Check for errors in output
            if (strpos($traceOutput, 'Error:') !== false) {
                $errorMsg = implode("\n", array_slice($lines, 1));
                throw new RuntimeException(sprintf('Profiling script execution failed: %s', $errorMsg));
            }

            if (empty($traceFile) || !file_exists($traceFile)) {
                throw new RuntimeException(
                    sprintf('Xdebug trace file was not generated. Expected file: %s', $traceFile ?: 'none')
                );
            }

            // 6. Parse trace file using Xdebug v3 parser
            $profileData = $this->parseXdebugTrace($traceFile, $debug);

            // 7. Normalize to standard schema and return ProfileResult
            return $this->createProfileResult($profileData)->toArray();
        } finally {
            // 8. Clean up temp script and trace file (keep in debug mode)
            if (!$debug && !empty($scriptPath) && file_exists($scriptPath)) {
                @unlink($scriptPath);
            }
            if (!$debug && !empty($traceFile) && file_exists($traceFile)) {
                @unlink($traceFile);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('xdebug') && function_exists('xdebug_start_trace');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Xdebug Subprocess';
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
     * Parse Xdebug trace file and return raw profiling data.
     *
     * Uses same Xdebug v3 parser logic as in-process profiling.
     *
     * @param string $traceFile Path to trace file
     * @param bool $debug Debug mode
     *
     * @return array Raw profiling data
     *
     * @throws RuntimeException If trace file cannot be read
     */
    private function parseXdebugTrace(string $traceFile, bool $debug = false): array
    {
        $content = @file_get_contents($traceFile);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read trace file: %s', $traceFile));
        }

        if (str_ends_with($traceFile, '.gz') || substr($content, 0, 2) === "\x1f\x8b") {
            $decompressed = @gzdecode($content);
            if ($decompressed === false) {
                throw new RuntimeException(sprintf('Failed to decompress trace file: %s', $traceFile));
            }
            $content = $decompressed;
        }

        $lines = preg_split('/\r?\n/', $content);
        $functions = [];
        $callStack = [];
        $levelToFuncNum = []; // For Xdebug 3 format

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

            if ($fileFormat === 4) {
                $parts = preg_split('/\t+/', $t);
                if (count($parts) < 3) continue;

                $level = (int) $parts[0];
                $funcNum = (int) $parts[1];
                $type = (int) $parts[2];

                if ($type === 0 && count($parts) >= 6) {
                    $time = (float) $parts[3] * 1000000;
                    $memory = (int) $parts[4];
                    $fn = $parts[5];

                    // Extract parameters from trace (parts[6] onwards)
                    // Format 4 structure: level, funcNum, type, time, memory, function, is_user, file, line, param_count, param1, param2, ...
                    $params = [];
                    if (count($parts) > 9) {
                        // Skip: is_user (6), file (7), line (8), param_count (9)
                        // Real parameters start at index 10
                        $params = array_slice($parts, 10);
                    }

                    $callStack[$funcNum] = [
                        'name' => $fn,
                        'start_time' => $time,
                        'start_memory' => $memory,
                        'level' => $level,
                        'params' => $params
                    ];
                    $levelToFuncNum[$level] = $funcNum;
                } elseif ($type === 1 && count($parts) >= 4) {
                    if (isset($callStack[$funcNum])) {
                        $call = $callStack[$funcNum];
                        $time = (float) $parts[3] * 1000000;
                        $memory = (int) $parts[4];
                        $duration = max(0, $time - $call['start_time']);
                        $memoryDelta = $memory - $call['start_memory'];

                        $parentFuncNum = $levelToFuncNum[$call['level'] - 1] ?? null;
                        $parent = $parentFuncNum ? ($callStack[$parentFuncNum]['name'] ?? null) : null;
                        $entryName = ($parent ? $parent . '==>' : '') . $call['name'];

                        if (!isset($functions[$entryName])) {
                            $functions[$entryName] = [
                                'calls' => 0,
                                'time' => 0,
                                'memory' => 0,
                                'peak_memory' => 0,
                                'cpu_time' => 0,
                                'params' => []
                            ];
                        }

                        $functions[$entryName]['calls']++;
                        $functions[$entryName]['time'] += $duration;
                        $functions[$entryName]['memory'] += $memoryDelta;
                        $functions[$entryName]['cpu_time'] += $duration;

                        // Store params from the first call
                        if (empty($functions[$entryName]['params']) && !empty($call['params'])) {
                            $functions[$entryName]['params'] = $call['params'];
                        }

                        unset($callStack[$funcNum]);
                    }
                }
            } else {
                // Legacy format: level time memory op function location params
                // Example: "   1     0.0002    114872   232    -> function(arg1, arg2) /path/file.php:10"
                if (preg_match('/^\s*(\d+)\s+(\d+\.\d+)\s+(\d+)(?:\s+\d+)?\s+(->|<-)\s+(.+?)\s+([^\s]+:\d+)(?:\s+(.*))?$/', $t, $m)) {
                    $level = (int) $m[1];
                    $time = (float) $m[2] * 1000000;
                    $memory = (int) $m[3];
                    $op = $m[4];
                    $fnWithParams = $m[5];
                    $location = $m[6];
                    $extraParams = $m[7] ?? '';

                    // Extract function name and inline params
                    $fn = $fnWithParams;
                    $params = [];

                    // Check if function has parameters in its name: func(param1, param2)
                    if (preg_match('/^(.+?)\((.*)\)$/', $fnWithParams, $fnMatch)) {
                        $fn = $fnMatch[1];
                        if (!empty($fnMatch[2])) {
                            $params = $this->parseXdebugParams($fnMatch[2]);
                        }
                    }

                    if ($op === '->') {
                        $callStack[$level] = [
                            'name' => $fn,
                            'start_time' => $time,
                            'start_memory' => $memory,
                            'params' => $params
                        ];
                    } elseif ($op === '<-') {
                        if (isset($callStack[$level])) {
                            $call = $callStack[$level];
                            $duration = max(0, $time - $call['start_time']);
                            $memoryDelta = $memory - $call['start_memory'];

                            $parent = $callStack[$level - 1]['name'] ?? null;
                            $entryName = ($parent ? $parent . '==>' : '') . $call['name'];

                            if (!isset($functions[$entryName])) {
                                $functions[$entryName] = [
                                    'calls' => 0,
                                    'time' => 0,
                                    'memory' => 0,
                                    'peak_memory' => 0,
                                    'cpu_time' => 0,
                                    'params' => []
                                ];
                            }

                            $functions[$entryName]['calls']++;
                            $functions[$entryName]['time'] += $duration;
                            $functions[$entryName]['memory'] += $memoryDelta;
                            $functions[$entryName]['cpu_time'] += $duration;

                            // Store params from the first call
                            if (empty($functions[$entryName]['params']) && !empty($call['params'])) {
                                $functions[$entryName]['params'] = $call['params'];
                            }

                            unset($callStack[$level]);
                        }
                    }
                }
            }
        }

        return $functions;
    }

    /**
     * Create ProfileResult from raw function data.
     *
     * Normalizes to standard schema with percentages and metadata.
     *
     * @param array $functions Raw function profiling data
     *
     * @return ProfileResult Normalized profiling result
     */
    private function createProfileResult(array $functions): ProfileResult
    {
        $entries = [];
        // Recalculate total time and memory from aggregated child metrics
        $totalTime = 0;
        $totalMemory = 0;
        foreach ($functions as $name => $data) {
            $childName = str_contains($name, '==>') ? explode('==>', $name)[1] : $name;
            // Aggregate totals from top-level calls (no parent)
            if (!str_contains($name, '==>')) {
                $totalTime += $data['time'];
                $totalMemory += $data['memory'];
            }
        }

        foreach ($functions as $name => $data) {
            $timePercent = $totalTime > 0 ? ($data['time'] / $totalTime) * 100 : 0;
            $memoryPercent = $totalMemory > 0 ? ($data['memory'] / $totalMemory) * 100 : 0;

            $childName = str_contains($name, '==>') ? explode('==>', $name)[1] : $name;

            $entries[$name] = new ProfileEntry(
                $name,
                $data['calls'],
                (int) round($data['time']),
                $data['memory'],
                $data['peak_memory'] ?? 0,
                (int) round($data['cpu_time']),
                $timePercent,
                $memoryPercent,
                $this->isUserFunction($childName),
                $data['params'] ?? []
            );
        }

        return new ProfileResult($entries);
    }

    /**
     * Check if a function is user-defined code.
     *
     * @param string $name Function name
     *
     * @return bool True if user-defined
     */
    private function isUserFunction(string $name): bool
    {
        // Check if it's PsySH internal code
        foreach (self::PSYSH_NAMESPACES as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return false;
            }
        }

        // Check if it's internal PHP function
        if ($this->isInternalFunction($name)) {
            return false;
        }

        // Code from eval is user code
        if (str_contains($name, "eval()'d code")) {
            return true;
        }

        // Everything else is considered user code
        return true;
    }

    /**
     * Check if a function is a PHP internal function.
     *
     * @param string $name Function name
     *
     * @return bool True if internal
     */
    private function isInternalFunction(string $name): bool
    {
        // Methods and namespaced functions are not internal
        if (str_contains($name, '::') || str_contains($name, '\\')) {
            return false;
        }

        // Check if it's a built-in PHP function
        if (!function_exists($name)) {
            return false;
        }

        try {
            $reflection = new \ReflectionFunction($name);

            return $reflection->isInternal();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Parse Xdebug parameter string and extract parameter values.
     *
     * Xdebug with collect_params=4 formats parameters as:
     * - Strings: 'value'
     * - Numbers: value
     * - Arrays: array(size)
     * - Objects: class name
     *
     * @param string $paramsStr Raw parameter string from trace
     *
     * @return array Array of parameter values
     */
    private function parseXdebugParams(string $paramsStr): array
    {
        if (empty($paramsStr)) {
            return [];
        }

        $params = [];

        // Remove leading/trailing whitespace and parentheses
        $paramsStr = trim($paramsStr);

        // Handle format: (param1, param2, ...)
        if (preg_match('/^\((.*)\)$/', $paramsStr, $matches)) {
            $paramsStr = $matches[1];
        }

        // Split by comma, but respect quoted strings
        $currentParam = '';
        $inQuote = false;
        $quoteChar = '';
        $depth = 0;

        for ($i = 0; $i < strlen($paramsStr); $i++) {
            $char = $paramsStr[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $paramsStr[$i - 1] !== '\\')) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuote = false;
                }
                $currentParam .= $char;
            } elseif ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                $currentParam .= $char;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
                $currentParam .= $char;
            } elseif ($char === ',' && !$inQuote && $depth === 0) {
                $params[] = trim($currentParam);
                $currentParam = '';
            } else {
                $currentParam .= $char;
            }
        }

        if (!empty($currentParam)) {
            $params[] = trim($currentParam);
        }

        return $params;
    }
}
