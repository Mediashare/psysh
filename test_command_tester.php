#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Command\ProfileCommand;
use Symfony\Component\Console\Tester\CommandTester;

echo "=== TEST AVEC COMMANDTESTER (comme les tests unitaires) ===\n\n";

// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}

$shell = new Shell();
$command = new ProfileCommand();
$command->setApplication($shell);

// Define test class
$shell->execute('class MyCalculator {
    public function sumAndHash(array $numbers) {
        $sum = array_sum($numbers);
        $hash = md5($sum);
        return strlen($hash);
    }
}');
$shell->execute('$calc = new MyCalculator()');

// Test 1: --debug option
echo "Test 1: profile --debug\n";
echo "========================\n";
$tester = new CommandTester($command);
$tester->execute([
    'code' => 'range(1, 10);',
    '--debug' => true,
]);
echo $tester->getDisplay();

echo "\n\n";

// Test 2: --full option
echo "Test 2: profile --full\n";
echo "=======================\n";
$tester = new CommandTester($command);
$tester->execute([
    'code' => 'array_sum([1,2,3]);',
    '--full' => true,
]);
echo $tester->getDisplay();

echo "\n\n";

// Test 3: --filter=php option
echo "Test 3: profile --filter=php\n";
echo "=============================\n";
$tester = new CommandTester($command);
$tester->execute([
    'code' => '$calc->sumAndHash(range(1, 100));',
    '--filter' => 'php',
]);
echo $tester->getDisplay();

echo "\n\n";

// Test 4: --full --debug
echo "Test 4: profile --full --debug\n";
echo "===============================\n";
$tester = new CommandTester($command);
$tester->execute([
    'code' => '$calc->sumAndHash(range(1, 100));',
    '--full' => true,
    '--debug' => true,
]);
echo $tester->getDisplay();
