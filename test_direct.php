#!/usr/bin/env php
<?php
/**
 * Test direct pour vérifier que les modifications sont bien chargées
 */

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;

echo "=== TEST DIRECT DES MODIFICATIONS ===\n\n";

// Clear OPcache if enabled
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache cleared\n";
}

$shell = new Shell(new Configuration());

// Test 1: Profile with --debug
echo "\n1. Test avec --debug (devrait afficher '=== DEBUG MODE ENABLED ===')\n";
echo "-------------------------------------------------------------------\n";
$shell->addInput('profile --debug "1 + 1"');
$shell->run();

echo "\n\n";

// Test 2: Profile with --full
echo "2. Test avec --full (devrait afficher 'all functions')\n";
echo "--------------------------------------------------------\n";
$shell->addInput('profile --full "range(1, 5)"');
$shell->run();

echo "\n\n";

// Test 3: Profile with --filter=php
echo "3. Test avec --filter=php (devrait afficher 'user code + PHP native')\n";
echo "----------------------------------------------------------------------\n";
$shell->addInput('profile --filter=php "array_sum([1,2,3])"');
$shell->run();
