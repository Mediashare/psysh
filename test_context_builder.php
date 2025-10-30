<?php
require __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;
use Psy\Profiling\ContextBuilder;

$shell = new Shell(new Configuration());
$shell->execute('class MyCalculator { public function test() { return 42; } }');
$shell->execute('$calc = new MyCalculator()');

echo "=== CONTEXT SCRIPT ===\n";
$context = ContextBuilder::buildContextScript($shell);
echo $context;
echo "\n=== END ===\n";

// Test that the context can recreate the variable
echo "\n=== TEST EXECUTION ===\n";
$testScript = "<?php\n" . $context . "\nvar_dump(\$calc);\n";
file_put_contents('/tmp/test_psysh_context.php', $testScript);
echo "Executing context script...\n";
system('php /tmp/test_psysh_context.php');
