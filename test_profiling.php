#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;
use Psy\Input\ShellInput;

// Create a test shell that exposes runCommand
class TestShell extends Shell {
    public function testRunCommand(string $input) {
        return $this->runCommand($input);
    }
    
    public function testHandleMultiLineCodeArgument(string $input) {
        $shellInput = new ShellInput(\str_replace('\\', '\\\\', \rtrim($input, " \t\n\r\0\x0B;")));
        $command = $this->getCommand($input);
        
        echo "Original input: " . $input . "\n";
        echo "Command name: " . $command->getName() . "\n";
        
        // Test the parsing
        try {
            $shellInput->bind($command->getDefinition());
            if ($shellInput->hasCodeArgument()) {
                $codeArgument = null;
                foreach ($command->getDefinition()->getArguments() as $arg) {
                    if ($arg instanceof \Psy\Input\CodeArgument) {
                        $codeArgument = $arg->getName();
                        break;
                    }
                }
                if ($codeArgument) {
                    $code = $shellInput->getArgument($codeArgument);
                    echo "Raw code argument: " . $code . "\n";
                }
            }
        } catch (\Exception $e) {
            echo "Binding error: " . $e->getMessage() . "\n";
        }
    }
}

$config = new Configuration();
$shell = new TestShell($config);
// Initialize the shell output
$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$shell->setOutput($output);

// Test the profiling command with --debug option
$testInput = 'profile --debug $a = 1; $b = 2; $a + $b';

echo "Testing input: $testInput\n";
echo "---\n";

// First test the parsing
$shell->testHandleMultiLineCodeArgument($testInput);

echo "\n--- Running command ---\n";

try {
    // This should parse the code argument correctly without including the command and options
    $result = $shell->testRunCommand($testInput);
    echo "Command executed successfully\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'PHP Parse error') !== false) {
        echo "This is the parse error we're trying to fix!\n";
    }
}
