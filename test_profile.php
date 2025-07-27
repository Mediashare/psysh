<?php

require_once 'vendor/autoload.php';

use Psy\Shell;

$shell = new Shell();

// Set a closure in scope
$test = fn ($a) => "Dine ".$a;

// Start shell with this context
$shell->setScopeVariables(['test' => $test]);

// Run profile command
echo "Testing profile with closure in scope...\n";
try {
    // This should work without the serialization error now
    $shell->addCode('profile 1+1');
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
