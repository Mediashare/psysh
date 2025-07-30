<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Input\ShellInput;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

// Test the ShellInput parsing

$definition = new InputDefinition([
    new InputOption('debug', null, InputOption::VALUE_NONE, 'Debug mode'),
    new CodeArgument('code', CodeArgument::REQUIRED, 'The code to execute'),
]);

$inputs = [
    'profile --debug $a()',
    '--debug $a()',
    ' --debug $a()',
    '$a()'
];

foreach ($inputs as $input) {
    echo "\n=== Testing input: '$input' ===\n";
    
    $shellInput = new ShellInput($input);

    // Use reflection to examine tokenPairs before binding
    $reflection = new ReflectionClass($shellInput);
    $tokenPairsProperty = $reflection->getProperty('tokenPairs');
    $tokenPairsProperty->setAccessible(true);
    $tokenPairs = $tokenPairsProperty->getValue($shellInput);

    echo "Token pairs:\n";
    foreach ($tokenPairs as $i => $pair) {
        echo "  $i: token='{$pair[0]}', rest='{$pair[1]}'\n";
    }
    echo "\n";

    // Now bind and see what happens
    try {
        $shellInput->bind($definition);
        
        echo "After binding:\n";
        echo "  Has --debug option: " . ($shellInput->getOption('debug') ? 'yes' : 'no') . "\n";
        echo "  Code argument: '" . $shellInput->getArgument('code') . "'\n";
    } catch (Exception $e) {
        echo "Binding failed: " . $e->getMessage() . "\n";
    }
}
