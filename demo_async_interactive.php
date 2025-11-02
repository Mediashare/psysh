#!/usr/bin/env php
<?php

/*
 * Interactive demo script for async features
 */

require __DIR__ . '/vendor/autoload.php';

use Psy\Configuration;
use Psy\Shell;

echo "\033[2J\033[H"; // Clear screen
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   PsySH Async Metrics & Status Bar - Interactive Demo   ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Create a configuration with async features enabled
$config = new Configuration([
    'useAsyncMetrics' => true,
    'useStatusBar' => true,
]);

$shell = new Shell($config);

echo "✓ Shell initialized with async features enabled\n";
echo "✓ Status bar will appear during code execution\n\n";

// Test 1: Simple computation
echo "═══ Test 1: Simple Computation (3 seconds) ═══\n";
echo "Running: Calculating sum of 10 million numbers...\n\n";

$wrapper = $shell->getAsyncExecutionWrapper();

$result = $wrapper->execute(function () {
    $sum = 0;
    for ($i = 0; $i < 10000000; $i++) {
        $sum += $i;
        if ($i % 1000000 === 0) {
            usleep(300000); // 300ms pause every million iterations
        }
    }
    return $sum;
});

echo "\n✓ Result: " . number_format($result) . "\n";
$metrics = $wrapper->getMetricsManager()->getMetrics();
echo "✓ Execution Time: " . $wrapper->getMetricsManager()->getFormattedExecutionTime() . "\n";
echo "✓ Memory Used: " . $wrapper->getMetricsManager()->getFormattedMemoryUsage() . "\n\n";

sleep(2);

// Test 2: Memory allocation
echo "═══ Test 2: Memory Allocation (2 seconds) ═══\n";
echo "Running: Creating large array...\n\n";

$result = $wrapper->execute(function () {
    $data = [];
    for ($i = 0; $i < 100000; $i++) {
        $data[] = str_repeat('x', 100);
        if ($i % 20000 === 0) {
            usleep(400000); // 400ms pause
        }
    }
    return count($data);
});

echo "\n✓ Created array with " . number_format($result) . " elements\n";
echo "✓ Execution Time: " . $wrapper->getMetricsManager()->getFormattedExecutionTime() . "\n";
echo "✓ Peak Memory: " . $wrapper->getMetricsManager()->getFormattedPeakMemoryUsage() . "\n\n";

sleep(2);

// Test 3: Simulated I/O operation
echo "═══ Test 3: Simulated I/O Operation (2 seconds) ═══\n";
echo "Running: Simulating file operations...\n\n";

$result = $wrapper->execute(function () {
    $operations = 0;
    for ($i = 0; $i < 10; $i++) {
        usleep(200000); // 200ms per operation
        $operations++;
    }
    return $operations;
});

echo "\n✓ Completed " . $result . " operations\n";
echo "✓ Execution Time: " . $wrapper->getMetricsManager()->getFormattedExecutionTime() . "\n\n";

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║              All Tests Completed Successfully!           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

echo "💡 Tip: You can enable async features in PsySH with:\n";
echo "   >>> async --enable\n";
echo "   >>> async --statusbar=on\n\n";

echo "📖 Read more in ASYNC.md documentation\n";
