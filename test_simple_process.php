<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Process\Process;

$code = '$name = unserialize("s:5:\"World\";");echo "Hello " . $name . "!";';

$process = new Process([
    PHP_BINARY,
    '-r', $code,
]);

$process->run();

echo "Exit code: " . $process->getExitCode() . "\n";
echo "Raw output: '" . $process->getOutput() . "'\n";
echo "Trimmed output: '" . trim($process->getOutput()) . "'\n";
echo "Error: '" . $process->getErrorOutput() . "'\n";
echo "Output length: " . strlen($process->getOutput()) . "\n";
echo "Is successful: " . ($process->isSuccessful() ? "yes" : "no") . "\n";
