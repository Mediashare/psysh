#!/usr/bin/env php
<?php

/**
 * PsySH Performance Benchmark Suite
 *
 * Comprehensive performance testing for:
 * - Startup time
 * - Memory usage
 * - Command execution
 * - Autoloading efficiency
 * - Code evaluation
 */

namespace Psy\Performance;

require_once __DIR__ . '/../../vendor/autoload.php';

use Psy\Configuration;
use Psy\Shell;

class BenchmarkSuite
{
    private array $results = [];
    private int $iterations = 100;
    private const STARTUP_TARGET_MS = 100;
    private const EXEC_TARGET_MS = 50;
    private const MEMORY_TARGET_MB = 50;

    public function run(): void
    {
        echo "🚀 PsySH Performance Benchmark Suite\n";
        echo str_repeat('=', 60) . "\n\n";

        $this->benchmarkStartupTime();
        $this->benchmarkMemoryUsage();
        $this->benchmarkAutoloading();
        $this->benchmarkCommandExecution();
        $this->benchmarkCodeEvaluation();
        $this->benchmarkContextOperations();

        $this->displayResults();
        $this->saveResults();
    }

    private function benchmarkStartupTime(): void
    {
        echo "📊 Benchmarking Startup Time...\n";

        $times = [];
        for ($i = 0; $i < $this->iterations; $i++) {
            $start = microtime(true);

            // Simulate shell initialization
            $config = new Configuration();
            $shell = new Shell($config);

            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // Convert to ms

            unset($shell, $config);

            if ($i % 10 === 0) {
                echo ".";
            }
        }
        echo "\n";

        $this->results['startup'] = [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'median' => $this->median($times),
            'p95' => $this->percentile($times, 95),
            'p99' => $this->percentile($times, 99),
            'target' => self::STARTUP_TARGET_MS,
            'unit' => 'ms'
        ];
    }

    private function benchmarkMemoryUsage(): void
    {
        echo "📊 Benchmarking Memory Usage...\n";

        gc_collect_cycles();
        $baseMemory = memory_get_usage(true);

        $config = new Configuration();
        $shell = new Shell($config);

        // Simulate typical session
        $shell->setScopeVariables([
            'data' => range(1, 1000),
            'text' => str_repeat('test', 100),
        ]);

        gc_collect_cycles();
        $usedMemory = memory_get_usage(true) - $baseMemory;
        $peakMemory = memory_get_peak_usage(true);

        $this->results['memory'] = [
            'base_mb' => round($baseMemory / 1024 / 1024, 2),
            'used_mb' => round($usedMemory / 1024 / 1024, 2),
            'peak_mb' => round($peakMemory / 1024 / 1024, 2),
            'target_mb' => self::MEMORY_TARGET_MB,
            'unit' => 'MB'
        ];

        echo "  Base: {$this->results['memory']['base_mb']} MB\n";
        echo "  Used: {$this->results['memory']['used_mb']} MB\n";
        echo "  Peak: {$this->results['memory']['peak_mb']} MB\n";
    }

    private function benchmarkAutoloading(): void
    {
        echo "📊 Benchmarking Autoloading Efficiency...\n";

        $times = [];

        // Test classes from different namespaces
        $classes = [
            'Psy\Shell',
            'Psy\Configuration',
            'Psy\Context',
            'Psy\CodeCleaner',
            'Psy\Command\ListCommand',
            'Psy\Command\DocCommand',
            'Psy\TabCompletion\AutoCompleter',
            'Psy\Readline\Readline',
        ];

        foreach ($classes as $class) {
            $start = microtime(true);
            class_exists($class);
            $end = microtime(true);
            $times[] = ($end - $start) * 1000000; // microseconds
        }

        $this->results['autoloading'] = [
            'avg_us' => array_sum($times) / count($times),
            'total_us' => array_sum($times),
            'classes_tested' => count($classes),
            'unit' => 'μs'
        ];

        echo "  Average: " . round($this->results['autoloading']['avg_us'], 2) . " μs\n";
        echo "  Total: " . round($this->results['autoloading']['total_us'], 2) . " μs\n";
    }

    private function benchmarkCommandExecution(): void
    {
        echo "📊 Benchmarking Command Execution...\n";

        $config = new Configuration();
        $shell = new Shell($config);

        $commands = [
            'ls',
            'help',
            'show \$_',
        ];

        $commandTimes = [];

        foreach ($commands as $cmd) {
            $times = [];
            for ($i = 0; $i < 10; $i++) {
                $start = microtime(true);

                try {
                    // Simulate command parsing and execution
                    $shell->addCode($cmd);
                } catch (\Exception $e) {
                    // Ignore exceptions for benchmarking
                }

                $end = microtime(true);
                $times[] = ($end - $start) * 1000;
            }

            $commandTimes[$cmd] = [
                'avg' => array_sum($times) / count($times),
                'min' => min($times),
                'max' => max($times)
            ];

            echo "  {$cmd}: " . round($commandTimes[$cmd]['avg'], 2) . " ms\n";
        }

        $this->results['commands'] = $commandTimes;
    }

    private function benchmarkCodeEvaluation(): void
    {
        echo "📊 Benchmarking Code Evaluation...\n";

        $config = new Configuration();
        $shell = new Shell($config);

        $codeSnippets = [
            'simple' => '1 + 1',
            'array' => 'array_map(fn($x) => $x * 2, range(1, 100))',
            'string' => 'str_repeat("test", 100)',
            'function' => 'function test() { return "hello"; } test()',
        ];

        $evalTimes = [];

        foreach ($codeSnippets as $name => $code) {
            $times = [];
            for ($i = 0; $i < 50; $i++) {
                $start = microtime(true);

                try {
                    $shell->addCode($code);
                } catch (\Exception $e) {
                    // Ignore exceptions
                }

                $end = microtime(true);
                $times[] = ($end - $start) * 1000;
            }

            $evalTimes[$name] = [
                'avg' => array_sum($times) / count($times),
                'p95' => $this->percentile($times, 95)
            ];

            echo "  {$name}: " . round($evalTimes[$name]['avg'], 2) . " ms\n";
        }

        $this->results['evaluation'] = $evalTimes;
    }

    private function benchmarkContextOperations(): void
    {
        echo "📊 Benchmarking Context Operations...\n";

        $config = new Configuration();
        $shell = new Shell($config);

        // Test variable setting
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $shell->setScopeVariables(['var' . $i => $i]);
        }
        $setTime = (microtime(true) - $start) * 1000;

        // Test variable retrieval
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $shell->getScopeVariables();
        }
        $getTime = (microtime(true) - $start) * 1000;

        $this->results['context'] = [
            'set_1000_vars_ms' => $setTime,
            'get_1000_times_ms' => $getTime,
            'avg_set_us' => ($setTime / 1000) * 1000,
            'avg_get_us' => ($getTime / 1000) * 1000,
        ];

        echo "  Set 1000 vars: " . round($setTime, 2) . " ms\n";
        echo "  Get 1000 times: " . round($getTime, 2) . " ms\n";
    }

    private function displayResults(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 BENCHMARK RESULTS SUMMARY\n";
        echo str_repeat('=', 60) . "\n\n";

        // Startup Time
        echo "🚀 Startup Time:\n";
        $startup = $this->results['startup'];
        echo "  Average: " . round($startup['avg'], 2) . " ms";
        $this->showStatus($startup['avg'], $startup['target'], false);
        echo "  Median:  " . round($startup['median'], 2) . " ms\n";
        echo "  P95:     " . round($startup['p95'], 2) . " ms\n";
        echo "  P99:     " . round($startup['p99'], 2) . " ms\n";
        echo "  Target:  < {$startup['target']} ms\n\n";

        // Memory Usage
        echo "💾 Memory Usage:\n";
        $memory = $this->results['memory'];
        echo "  Used:   {$memory['used_mb']} MB";
        $this->showStatus($memory['used_mb'], $memory['target_mb'], false);
        echo "  Peak:   {$memory['peak_mb']} MB\n";
        echo "  Target: < {$memory['target_mb']} MB\n\n";

        // Autoloading
        echo "⚡ Autoloading:\n";
        $autoload = $this->results['autoloading'];
        echo "  Average: " . round($autoload['avg_us'], 2) . " μs per class\n";
        echo "  Classes: {$autoload['classes_tested']}\n\n";

        // Performance Score
        $score = $this->calculatePerformanceScore();
        echo "📈 Overall Performance Score: {$score}/100\n";
        $this->showScoreGrade($score);
    }

    private function calculatePerformanceScore(): int
    {
        $score = 100;

        // Startup time penalty (40 points max)
        $startupRatio = $this->results['startup']['avg'] / self::STARTUP_TARGET_MS;
        if ($startupRatio > 1) {
            $score -= min(40, ($startupRatio - 1) * 40);
        }

        // Memory usage penalty (30 points max)
        $memoryRatio = $this->results['memory']['used_mb'] / self::MEMORY_TARGET_MB;
        if ($memoryRatio > 1) {
            $score -= min(30, ($memoryRatio - 1) * 30);
        }

        // Autoloading penalty (30 points max)
        if ($this->results['autoloading']['avg_us'] > 1000) {
            $score -= min(30, ($this->results['autoloading']['avg_us'] - 1000) / 100);
        }

        return max(0, (int)round($score));
    }

    private function showStatus(float $actual, float $target, bool $lowerIsBetter = true): void
    {
        $status = $lowerIsBetter
            ? ($actual <= $target ? " ✅" : " ❌")
            : ($actual >= $target ? " ✅" : " ❌");
        echo $status . "\n";
    }

    private function showScoreGrade(int $score): void
    {
        if ($score >= 90) {
            echo "  Grade: A+ (Excellent) 🏆\n";
        } elseif ($score >= 80) {
            echo "  Grade: A (Very Good) ⭐\n";
        } elseif ($score >= 70) {
            echo "  Grade: B (Good) 👍\n";
        } elseif ($score >= 60) {
            echo "  Grade: C (Acceptable) 👌\n";
        } else {
            echo "  Grade: D (Needs Improvement) ⚠️\n";
        }
    }

    private function saveResults(): void
    {
        $filename = __DIR__ . '/benchmark-results-' . date('Y-m-d-His') . '.json';

        $fullResults = [
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'results' => $this->results,
            'score' => $this->calculatePerformanceScore(),
        ];

        file_put_contents($filename, json_encode($fullResults, JSON_PRETTY_PRINT));

        echo "\n📝 Results saved to: {$filename}\n";
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    private function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil((count($values) * $percentile) / 100) - 1;
        return $values[$index];
    }
}

// Run the benchmark
$benchmark = new BenchmarkSuite();
$benchmark->run();
