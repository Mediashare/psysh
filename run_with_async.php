#!/usr/bin/env php
<?php

/**
 * Test script to run PsySH with async features enabled
 */

require __DIR__ . '/vendor/autoload.php';

use Psy\Configuration;
use Psy\Shell;

// Create configuration with async enabled
$config = new Configuration([
    'useAsyncMetrics' => true,
    'useStatusBar' => true,
    'colorMode' => \Psy\Configuration::COLOR_MODE_FORCED,
]);

$shell = new Shell($config);

// Print welcome message
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║            PsySH with Async Metrics Enabled                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "✓ Async metrics: ENABLED\n";
echo "✓ Status bar: ENABLED\n";
echo "\n";
echo "Try these commands:\n";
echo "  • async --status          - Show async status\n";
echo "  • async --disable         - Disable async metrics\n";
echo "  • async --enable          - Enable async metrics\n";
echo "  • async --statusbar=off   - Disable status bar\n";
echo "\n";
echo "Try some code with metrics:\n";
echo "  • sleep(2)                - Watch execution time count up\n";
echo "  • for(\$i=0; \$i<10000000; \$i++) { \$sum += \$i; }\n";
echo "  • \$big = range(1, 1000000) - Watch memory usage\n";
echo "\n";
echo "Press Ctrl+D or type 'exit' to quit.\n";
echo "────────────────────────────────────────────────────────────────\n";
echo "\n";

// Run the shell
$shell->run();
