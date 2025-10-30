#!/bin/bash

if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 5
spawn bin/psysh
expect "> "

send "class MyCalculator { public function sumAndHash(array \$numbers) { \$sum = array_sum(\$numbers); \$hash = md5(\$sum); return strlen(\$hash); } }\r"
expect "> "

send "\$calc = new MyCalculator()\r"
expect "> "

send "profile --debug --full '\$calc->sumAndHash(range(1, 100))'\r"
expect {
    "Debug script saved to:" {
        puts "\n✅ Debug script saved"
        exp_continue
    }
    "> " {
        puts "Done"
    }
    timeout {
        puts "Timeout"
    }
}

send "exit\r"
expect eof
EOF
else
    echo "expect not found"
fi

# Afficher le dernier script de debug
echo ""
echo "=== CONTENU DU SCRIPT DE DEBUG ==="
ls -t /tmp/psysh_profile_debug_*.php 2>/dev/null | head -1 | xargs cat 2>/dev/null || echo "Aucun script de debug trouvé"
