<?php

namespace Psy\Profiling;

use Psy\Shell;

/**
 * Interface for profiling engines.
 *
 * Profiling engines are responsible for executing code with profiling enabled
 * and returning normalized profiling data.
 */
interface ProfilerEngine
{
    /**
     * Profile the execution of a code string.
     *
     * Normalized profile data schema:
     *   function_name => [
     *     'calls' => int,
     *     'time' => int (μs inclusive),
     *     'exclusive_time' => int (μs),
     *     'memory' => int (bytes),
     *     'peak_memory' => int (bytes),
     *     'cpu_time' => int (μs),
     *     'is_user' => bool,
     *     'params' => string|null
     *   ]
     *
     * @param string $code  The PHP code to profile
     * @param Shell  $shell The shell instance for executing code in REPL scope
     * @param bool   $debug Whether to enable debug output
     *
     * @return array Normalized profiling data
     *
     * @throws \Psy\Exception\RuntimeException If profiling fails
     */
    public function profile(string $code, Shell $shell, bool $debug = false): array;

    /**
     * Check if this profiling engine is available.
     *
     * @return bool True if the required extension is loaded
     */
    public static function isAvailable(): bool;

    /**
     * Get the name of this profiling engine.
     *
     * @return string The engine name (e.g., "xhprof", "xdebug")
     */
    public function getName(): string;
}
