#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;

// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}

echo "=== TEST APRÈS FIX DU SHELL.PHP ===\n\n";

$shell = new Shell(new Configuration());

// Define test class
$shell->execute('class MyCalculator {
    public function sumAndHash(array $numbers) {
        $sum = array_sum($numbers);
        $hash = md5($sum);
        return strlen($hash);
    }
}');
$shell->execute('$calc = new MyCalculator()');

echo "Test 1: profile --debug \"range(1, 10)\"\n";
echo "=========================================\n";
$shell->execute('profile --debug "range(1, 10)"');

echo "\n\n";

echo "Test 2: profile --full \"array_sum([1,2,3])\"\n";
echo "=============================================\n";
$shell->execute('profile --full "array_sum([1,2,3])"');

echo "\n\n";

echo "Test 3: profile --filter=php \"\$calc->sumAndHash(range(1, 100))\"\n";
echo "=================================================================\n";
$shell->execute('profile --filter=php "$calc->sumAndHash(range(1, 100))"');

echo "\n\n";

echo "Test 4: profile --full --debug \"\$calc->sumAndHash(range(1, 100))\"\n";
echo "====================================================================\n";
$shell->execute('profile --full --debug "$calc->sumAndHash(range(1, 100))"');
