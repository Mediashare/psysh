#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;
use Psy\Input\ShellInput;

echo "=== TEST SHELL COMMAND PARSING ===\n\n";

$shell = new Shell(new Configuration());
$shell->execute('class MyCalculator {
    public function sumAndHash(array $numbers) {
        $sum = array_sum($numbers);
        $hash = md5($sum);
        return strlen($hash);
    }
}');
$shell->execute('$calc = new MyCalculator()');

// Test parsing of --debug option
echo "Test 1: Parsing de 'profile --debug \"range(1, 10)\"'\n";
echo "======================================================\n";
$input1 = new ShellInput('profile --debug "range(1, 10)"');
$command = $shell->get('profile');
$input1->bind($command->getDefinition());

echo "Options parsées:\n";
print_r($input1->getOptions());
echo "Arguments parsés:\n";
print_r($input1->getArguments());

echo "\n\n";

// Test parsing of --full option
echo "Test 2: Parsing de 'profile --full \"array_sum([1,2,3])\"'\n";
echo "===========================================================\n";
$input2 = new ShellInput('profile --full "array_sum([1,2,3])"');
$input2->bind($command->getDefinition());

echo "Options parsées:\n";
print_r($input2->getOptions());
echo "Arguments parsés:\n";
print_r($input2->getArguments());

echo "\n\n";

// Test parsing of --filter=php option
echo "Test 3: Parsing de 'profile --filter=php \"\$calc->sumAndHash(range(1, 100))\"'\n";
echo "=============================================================================\n";
$input3 = new ShellInput('profile --filter=php "$calc->sumAndHash(range(1, 100))"');
$input3->bind($command->getDefinition());

echo "Options parsées:\n";
print_r($input3->getOptions());
echo "Arguments parsés:\n";
print_r($input3->getArguments());

echo "\n\n";

// Now execute the commands
echo "=== EXECUTION DES COMMANDES ===\n\n";

echo "Exécution 1: profile --debug\n";
echo "-----------------------------\n";
$shell->execute('profile --debug "range(1, 10)"');

echo "\n\n";

echo "Exécution 2: profile --full\n";
echo "----------------------------\n";
$shell->execute('profile --full "array_sum([1,2,3])"');

echo "\n\n";

echo "Exécution 3: profile --filter=php\n";
echo "----------------------------------\n";
$shell->execute('profile --filter=php "$calc->sumAndHash(range(1, 100))"');
