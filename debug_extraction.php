<?php

require_once 'vendor/autoload.php';

use Psy\Input\ShellInput;
use Psy\Command\TimeitCommand;

echo "=== Debug Code Extraction Issues ===\n\n";

// Test case 1: Simple command
echo "1. Testing: timeit echo \"hello\";\n";
$input1 = 'timeit echo "hello";';
$command = new TimeitCommand();
$shellInput1 = new ShellInput($input1);
$shellInput1->bind($command->getDefinition());

echo "Original input: '$input1'\n";
echo "Extracted code: '" . $shellInput1->getArgument('code') . "'\n";

// Test manual extraction like our code does
$originalCode = $shellInput1->getArgument('code');
$commandName = 'timeit';
if (strpos($originalCode, $commandName) === 0) {
    $extractedCode = ltrim(substr($originalCode, strlen($commandName)));
    echo "After command removal: '$extractedCode'\n";
} else {
    echo "No extraction needed: '$originalCode'\n";
}

echo "\n";

// Test case 2: Multi-line case
echo "2. Testing: timeit if (true) {\n";
$input2 = 'timeit if (true) {';
$shellInput2 = new ShellInput($input2);
$shellInput2->bind($command->getDefinition());

echo "Original input: '$input2'\n";
echo "Extracted code: '" . $shellInput2->getArgument('code') . "'\n";

$originalCode2 = $shellInput2->getArgument('code');
if (strpos($originalCode2, $commandName) === 0) {
    $extractedCode2 = ltrim(substr($originalCode2, strlen($commandName)));
    echo "After command removal: '$extractedCode2'\n";
} else {
    echo "No extraction needed: '$originalCode2'\n";
}

// Test the parser on the extracted code
echo "\nTesting parser on extracted code:\n";
$parser = new \Psy\Command\CodeArgumentParser();
try {
    $parser->parse($extractedCode2);
    echo "Code is complete\n";
} catch (\Psy\Exception\ParseErrorException $e) {
    echo "Parse error: " . $e->getRawMessage() . "\n";
}

echo "\n";

// Test case 3: Test how the input is reconstructed after multi-line
echo "3. Testing input reconstruction:\n";
$codeBuffer = ['if (true) {', 'echo "hello";', '}'];
$completeCode = implode("\n", $codeBuffer);
echo "Complete code:\n$completeCode\n";

// Test how this gets reconstructed into the input
$originalInput = 'timeit if (true) {';
$incompleteCode = 'if (true) {';
$newInputString = preg_replace('/'.preg_quote($incompleteCode, '/').'$/', $completeCode, $originalInput);
echo "Reconstructed input: '$newInputString'\n";

// Test parsing the reconstructed input
$shellInput3 = new ShellInput($newInputString);
try {
    $shellInput3->bind($command->getDefinition());
    echo "Final extracted code: '" . $shellInput3->getArgument('code') . "'\n";
} catch (\Exception $e) {
    echo "Error binding reconstructed input: " . $e->getMessage() . "\n";
}
