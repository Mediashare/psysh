#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;
use Psy\Input\ShellInput;

echo "=== TEST SHELL COMMAND PARSING (FIXED) ===\n\n";

$shell = new Shell(new Configuration());
$command = $shell->get('profile');

// Test 1: Remove command name before parsing
echo "Test 1: Parsing de '--debug \"range(1, 10)\"' (sans 'profile')\n";
echo "================================================================\n";
$input1 = new ShellInput('--debug "range(1, 10)"');
$input1->bind($command->getDefinition());

echo "Options parsées:\n";
var_dump($input1->getOptions());
echo "Arguments parsés:\n";
var_dump($input1->getArguments());

echo "\n\n";

// Test 2
echo "Test 2: Parsing de '--full \"array_sum([1,2,3])\"'\n";
echo "===================================================\n";
$input2 = new ShellInput('--full "array_sum([1,2,3])"');
$input2->bind($command->getDefinition());

echo "Options parsées:\n";
var_dump($input2->getOptions());
echo "Arguments parsés:\n";
var_dump($input2->getArguments());

echo "\n\n";

// Test 3
echo "Test 3: Parsing de '--filter=php \"1+1\"'\n";
echo "==========================================\n";
$input3 = new ShellInput('--filter=php "1+1"');
$input3->bind($command->getDefinition());

echo "Options parsées:\n";
var_dump($input3->getOptions());
echo "Arguments parsés:\n";
var_dump($input3->getArguments());
