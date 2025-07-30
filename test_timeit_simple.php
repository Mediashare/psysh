<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psy\Command\TimeitCommand;
use Psy\Command\CodeArgumentParser;

// Test parsing code without command name
$parser = new CodeArgumentParser();

$testCases = [
    'echo "hello";',
    'timeit echo "hello";',
    'if (true) { echo "hello"; }',
    'timeit if (true) { echo "hello"; }'
];

foreach ($testCases as $code) {
    echo "Testing code: '$code'\n";
    try {
        $ast = $parser->parse($code);
        echo "  ✓ Parsed successfully\n";
    } catch (\Exception $e) {
        echo "  ✗ Parse error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
