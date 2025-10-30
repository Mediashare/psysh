#!/bin/bash

# Simple test with one command only
if command -v expect >/dev/null 2>&1; then
    echo "Test: profile --full --debug avec range(1, 10)"
    echo "==============================================="
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 3
spawn bin/psysh
expect "> "
send "profile --full --debug \"range(1, 10)\"\r"
expect {
    "=== DEBUG MODE ENABLED ===" {
        puts "\n✅ DEBUG MODE DÉTECTÉ!"
        exp_continue
    }
    "all functions" {
        puts "\n✅ TITRE 'all functions' DÉTECTÉ!"
        exp_continue
    }
    "user code only" {
        puts "\n❌ ERREUR: Affiche toujours 'user code only'"
        exp_continue
    }
    timeout {
        puts "\n❌ TIMEOUT"
    }
}
send "exit\r"
expect eof
EOF
else
    echo "expect not found, skipping test"
fi
