<?php

require_once 'vendor/autoload.php';

use Psy\Command\ProfileCommand;
use Psy\Shell;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// Set debug mode
putenv('PSYSH_DEBUG=1');

// Create a shell
$shell = new Shell();
$shell->setScopeVariables(['test' => fn ($a) => "Dine ".$a, 'name' => 'World']);

// Create profile command
$command = new ProfileCommand();
$command->setApplication($shell);

// Test with echo - check if error occurs during output process
$input = new ArrayInput(['code' => 'echo "Hello " . $name . "!";']);
$output = new BufferedOutput();

// Check if output process would work
$testOutputProcess = new \Symfony\Component\Process\Process([
    PHP_BINARY,
    '-r', '$name = unserialize("s:5:\"World\";");echo "Hello " . $name . "!";',
]);
$testOutputProcess->run();
echo "Test output process result: '" . $testOutputProcess->getOutput() . "'\n";

echo "Testing ProfileCommand with echo (debug mode)...\n";

try {
    $exitCode = $command->run($input, $output);
    echo "Exit code: $exitCode\n";
    echo "Output:\n" . $output->fetch();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
