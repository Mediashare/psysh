#!/usr/bin/env php
<?php

/*
 * Demo script to showcase async metrics and status bar functionality
 */

require __DIR__ . '/vendor/autoload.php';

use Psy\Configuration;
use Psy\Shell;

echo "=== PsySH Async Metrics Demo ===\n\n";

// Create a configuration with async features enabled
$config = new Configuration([
    'useAsyncMetrics' => true,
    'useStatusBar' => true,
]);

echo "Async features enabled:\n";
echo "- Async Metrics: " . ($config->useAsyncMetrics() ? 'Yes' : 'No') . "\n";
echo "- Status Bar: " . ($config->useStatusBar() ? 'Yes' : 'No') . "\n\n";

// Create the shell
$shell = new Shell($config);

echo "Shell initialized successfully!\n";
echo "Async components available:\n";
echo "- Metrics Manager: " . ($shell->getAsyncMetricsManager() !== null ? 'Yes' : 'No') . "\n";
echo "- Status Bar: " . ($shell->getStatusBar() !== null ? 'Yes' : 'No') . "\n";
echo "- Execution Wrapper: " . ($shell->getAsyncExecutionWrapper() !== null ? 'Yes' : 'No') . "\n\n";

// Test the async execution wrapper
if ($shell->getAsyncExecutionWrapper() !== null) {
    echo "Testing async execution wrapper...\n";
    
    $wrapper = $shell->getAsyncExecutionWrapper();
    
    $result = $wrapper->execute(function () {
        // Simulate some work
        usleep(100000); // 100ms
        $sum = 0;
        for ($i = 0; $i < 1000000; $i++) {
            $sum += $i;
        }
        return $sum;
    });
    
    echo "Execution completed!\n";
    echo "Result: " . $result . "\n";
    
    $metrics = $wrapper->getMetricsManager()->getMetrics();
    echo "\nMetrics:\n";
    echo "- Execution Time: " . $wrapper->getMetricsManager()->getFormattedExecutionTime() . "\n";
    echo "- Memory Usage: " . $wrapper->getMetricsManager()->getFormattedMemoryUsage() . "\n";
    echo "- Peak Memory: " . $wrapper->getMetricsManager()->getFormattedPeakMemoryUsage() . "\n";
}

echo "\n=== Demo Complete ===\n";
