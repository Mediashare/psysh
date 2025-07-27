<?php

require_once 'vendor/autoload.php';

use Psy\Command\ProfileCommand;
use Psy\Shell;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// Create a shell with closure in scope
$shell = new Shell();
$test = fn ($a) => "Dine ".$a;
$shell->setScopeVariables(['test' => $test, 'name' => 'World']);

// Create profile command
$command = new ProfileCommand();
$command->setApplication($shell);

// Test with output
$input = new ArrayInput(['code' => 'echo "Hello " . $name . "!";']);
$output = new BufferedOutput();

echo "Testing ProfileCommand with output...\n";

try {
    $exitCode = $command->run($input, $output);
    echo "Exit code: $exitCode\n";
    echo "Output:\n" . $output->fetch();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
