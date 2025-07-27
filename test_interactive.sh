#!/bin/bash

cd /Users/fullname/Desktop/psysh

# Test interactively with timeout using expect if available
if command -v expect >/dev/null 2>&1; then
    expect << 'EOF'
spawn bin/psysh
expect ">>> "
send "$test = fn (\$a) => \"Dine \".\$a\r"
expect ">>> "
send "profile 1+1\r"
expect "Total Time"
send "exit\r"
expect eof
EOF
else
    echo "Testing profile command with closures manually..."
    echo "Run the following commands in psysh:"
    echo '1. $test = fn ($a) => "Dine ".$a'
    echo '2. profile 1+1'
    echo '3. exit'
    echo ""
    echo "The closure should be excluded and profiling should work."
fi
