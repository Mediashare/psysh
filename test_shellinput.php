<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psy\Input\ShellInput;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputDefinition;

// Test case 1: Simple command
$input1 = new ShellInput('timeit echo "hello";');
echo "Test 1 - Simple command:\n";
echo "Input string: " . $input1->__toString() . "\n";

// Test case 2: Multi-line command
$input2 = new ShellInput('timeit if (true) { echo "hello"; }');
echo "\nTest 2 - Multi-line command:\n";
echo "Input string: " . $input2->__toString() . "\n";

// Create a definition with a CodeArgument
$definition = new InputDefinition([
    new CodeArgument('code', CodeArgument::REQUIRED, 'Code to execute'),
]);

// Bind and check what the code argument contains
try {
    $input1->bind($definition);
    echo "\nAfter binding input1:\n";
    echo "Code argument: '" . $input1->getArgument('code') . "'\n";
} catch (\Exception $e) {
    echo "Error binding input1: " . $e->getMessage() . "\n";
}

try {
    $input2->bind($definition);
    echo "\nAfter binding input2:\n";
    echo "Code argument: '" . $input2->getArgument('code') . "'\n";
} catch (\Exception $e) {
    echo "Error binding input2: " . $e->getMessage() . "\n";
}

// Test what happens when we reconstruct with clean code
$cleanCode = 'if (true) { echo "hello"; }';
$input3 = new ShellInput('timeit ' . $cleanCode);
echo "\nTest 3 - Reconstructed with clean code:\n";
echo "Input string: " . $input3->__toString() . "\n";

try {
    $input3->bind($definition);
    echo "Code argument after binding: '" . $input3->getArgument('code') . "'\n";
} catch (\Exception $e) {
    echo "Error binding input3: " . $e->getMessage() . "\n";
}
