<?php
require_once __DIR__ . '/vendor/autoload.php';

// Start a PsySH session with our test code
$shell = new \Psy\Shell();

// Test inputs that should now work correctly
$testInputs = [
    'timeit echo "hello";',
    'timeit if (true) {
echo "hello";
}',
    'timeit for ($i = 0; $i < 3; $i++) {
echo $i;
}'
];

foreach ($testInputs as $index => $input) {
    echo "\n=== Test " . ($index + 1) . " ===\n";
    echo "Input:\n$input\n";
    
    try {
        // Simulate the shell command execution
        $lines = explode("\n", $input);
        foreach ($lines as $line) {
            $shell->addInput($line);
        }
        
        // Get input and execute
        $shell->getInput(false);
        if ($shell->hasCode()) {
            $shell->execute($shell->flushCode());
            echo "✓ Executed successfully\n";
        }
    } catch (\Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}
