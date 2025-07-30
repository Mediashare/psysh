<?php

// Test script to verify profile --debug fix

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;

$config = new Configuration();
$shell = new Shell($config);

// Simulate user input
$shell->addInput('$a = function () { echo "a"; sleep(1); echo "b"; }');
$shell->addInput('profile --debug $a()');
$shell->addInput('exit');

try {
    $shell->run();
} catch (\Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
