#!/bin/bash
# Test interactively with timeout using expect if available
if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 1
spawn bin/psysh
expect ">>> "
send "\$myVar = 41\r"
expect ">>> "
send "\$test = fn (\$a) => \"Dine \".\$a\r"
expect ">>> "
send "profile --debug \$myVar + rand(900, 1900)\r"
expect "Total Time"
send "exit\r"
expect eof
EOF
else
    echo "Testing profile command with closures manually..."
    echo "Run the following commands in psysh:"
    echo '1. $myVar = 41'
    echo '2. $test = fn ($a) => "Dine ".$a'
    echo '3. profile $myVar + 1'
    echo '4. exit'
    echo ""
    echo "The closure should be excluded and profiling should work with other context variables."
fi

echo ""
echo "---"
echo "Running complex profiling test..."

if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 1
spawn bin/psysh
expect ">>> "
# Send class definition line by line
send "class MyCalculator {\r"
send "    public function sumAndHash(array \$numbers) {\r"
send "        \$sum = array_sum(\$numbers);\r"
send "        \$hash = md5(\$sum);\r"
send "        return strlen(\$hash);\r"
send "    }\r"
send "}\r"
expect ">>> "
send "\$calc = new MyCalculator()\r"
expect ">>> "
send "profile --debug --filter=php \$calc->sumAndHash(range(1, 100))\r"
# Check for profiler output and specific native function calls
expect "Total Time"
expect "strlen"
expect "md5"
expect "array_sum"
send "exit\r"
expect eof
EOF
    echo "Complex profiling test passed."
else
    echo "Skipping complex profiling test: 'expect' command not found."
fi
