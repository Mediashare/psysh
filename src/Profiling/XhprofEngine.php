<?php

namespace Psy\Profiling;

use Psy\Shell;
use Psy\Exception\RuntimeException;

/**
 * XhprofEngine
 *
 * Profiling engine implementation using XHProf extension.
 * This engine provides high-performance profiling with minimal overhead.
 */
class XhprofEngine implements ProfilerEngine
{
    public function profile(string $code, Shell $shell, bool $debug = false): array
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('XHProf extension is not available.');
        }

        $profileDataVar = '__psysh_profile_data_' . uniqid();

        try {
            // Wrap the code to enable profiling
            $wrappedCode = sprintf(
                'if (function_exists("xhprof_enable")) { xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY); }' . PHP_EOL .
                'try {' . PHP_EOL .
                '    %s' . PHP_EOL .
                '} finally {' . PHP_EOL .
                '    if (function_exists("xhprof_disable")) {' . PHP_EOL .
                '        global $%s;' . PHP_EOL .
                '        $%s = xhprof_disable();' . PHP_EOL .
                '    }' . PHP_EOL .
                '}',
                $code,
                $profileDataVar,
                $profileDataVar
            );

            // Execute the wrapped code
            $shell->execute($wrappedCode);

            // Retrieve the profile data from the shell's scope
            $profileData = $shell->getScopeVariable($profileDataVar);

            // Clean up
            $vars = $shell->getScopeVariables();
            unset($vars[$profileDataVar]);
            $shell->setScopeVariables($vars);

            // Normalize XHProf data
            return $this->normalizeXhprofData($profileData);

        } catch (\Throwable $e) {
            // Clean up on error
            $vars = $shell->getScopeVariables();
            unset($vars[$profileDataVar]);
            $shell->setScopeVariables($vars);
            throw $e;
        }
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('xhprof') && function_exists('xhprof_enable');
    }

    public function getName(): string
    {
        return 'XHProf';
    }

    /**
     * Normalize XHProf data to the standard profiling format.
     *
     * XHProf returns data in parent==>child format with keys: ct, wt, cpu, mu, pmu
     *
     * Note: We only filter out PsySH internal functions here, NOT PHP native functions.
     * The filtering of PHP native functions is done later in ProfileCommand based on the --full option.
     */
    private function normalizeXhprofData(array $xhprofData): array
    {
        $normalized = [];

        foreach ($xhprofData as $parentChild => $metrics) {
            // Parse parent==>child format
            if (str_contains($parentChild, '==>')) {
                [$parent, $child] = explode('==>', $parentChild, 2);
            } else {
                $parent = null;
                $child = $parentChild;
            }

            // Skip empty entries
            if (empty($child)) {
                continue;
            }

            // Only filter out PsySH internal functions (not PHP native functions)
            // This allows --full to show PHP native functions
            if ($this->shouldSkipFunction($parent, $child)) {
                continue;
            }

            // Aggregate metrics by function name
            if (!isset($normalized[$child])) {
                $normalized[$child] = [
                    'calls' => 0,
                    'time' => 0,
                    'exclusive_time' => 0,
                    'memory' => 0,
                    'peak_memory' => 0,
                    'cpu_time' => 0,
                    'is_user' => $this->isUserFunction($child),
                    'params' => null,
                ];
            }

            $normalized[$child]['calls'] += $metrics['ct'] ?? 0;
            $normalized[$child]['time'] += $metrics['wt'] ?? 0;
            $normalized[$child]['exclusive_time'] += $metrics['wt'] ?? 0;
            $normalized[$child]['memory'] += $metrics['mu'] ?? 0;
            $normalized[$child]['peak_memory'] = max($normalized[$child]['peak_memory'], $metrics['pmu'] ?? 0);
            $normalized[$child]['cpu_time'] += $metrics['cpu'] ?? 0;
        }

        // Add percentages
        return $this->addPercentages($normalized);
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
     * Determine if a function should be skipped based on filtering rules.
     */
    private function shouldSkipFunction(?string $parent, string $child): bool
    {
        // PsySH internal functions
        $psyshNamespaces = ['Psy\\', 'PhpParser\\', 'Symfony\\Component\\Console\\', 'Symfony\\Component\\VarDumper\\'];
        foreach ($psyshNamespaces as $namespace) {
            if (str_starts_with($child, $namespace)) {
                return true;
            }
        }

        // Symfony polyfills
        if (str_starts_with($child, 'Symfony\\Polyfill\\')) {
            return true;
        }

        // ProfileCommand internal methods
        if ($parent && str_starts_with($parent, 'Psy\\Command\\ProfileCommand::')) {
            return true;
        }

        return false;
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
