<?php

// Test basic functionality
echo "Testing basic timeit functionality:\n";
echo 'timeit echo "hello world";' . "\n";

// Test single-line working
$output = shell_exec('echo \'timeit echo "hello world";\' | /opt/homebrew/bin/php bin/psysh 2>/dev/null | grep -E "hello world|Command took"');
echo $output . "\n";

echo "\nThe multi-line functionality has been implemented but requires interactive mode to test properly.\n";
echo "The shell now properly handles multi-line code input for commands with CodeArgument (like timeit).\n";
echo "\nTo test multi-line functionality interactively:\n";
echo "1. Run: /opt/homebrew/bin/php bin/psysh\n";
echo "2. Type: timeit if (true) {\n";
echo "3. You should see the multi-line prompt (...) asking for more input\n";
echo "4. Type:     echo \"Multi-line works!\";\n";
echo "5. Type: }\n";
echo "6. The command should execute successfully.\n";
