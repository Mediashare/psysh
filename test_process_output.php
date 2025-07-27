<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Process\Process;

$tmpDir = sys_get_temp_dir();
$code = '$name = unserialize("s:5:\"World\";"); echo "Hello " . $name . "!";';

$process = new Process([
    PHP_BINARY,
    '-d', 'xdebug.mode=profile',
    '-d', 'xdebug.start_with_request=yes',
    '-d', 'xdebug.output_dir='.$tmpDir,
    '-r', $code,
]);

$process->run();

echo "Exit code: " . $process->getExitCode() . "\n";
echo "Output: '" . $process->getOutput() . "'\n";
echo "Error: '" . $process->getErrorOutput() . "'\n";
echo "Output length: " . strlen($process->getOutput()) . "\n";
