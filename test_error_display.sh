#!/bin/bash

echo "=== TEST 1: Affichage d'erreur amélioré ==="
echo ""

if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 5
spawn bin/psysh
expect "> "

# Test avec une erreur volontaire
send "profile --full --debug \"undefined_function()\"\r"
expect {
    "Profiling execution failed!" {
        puts "\n✅ Message d'erreur affiché"
        exp_continue
    }
    "Code being profiled:" {
        puts "✅ Code affiché"
        exp_continue
    }
    "Common causes:" {
        puts "✅ Suggestions affichées"
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
    echo "expect not found, test skipped"
fi

echo ""
echo ""
echo "=== TEST 2: Contexte avec variable ==="
echo ""

if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 5
spawn bin/psysh
expect "> "

# Définir une classe
send "class MyCalculator { public function sumAndHash(array \$numbers) { \$sum = array_sum(\$numbers); \$hash = md5(\$sum); return strlen(\$hash); } }\r"
expect "> "

# Créer une instance
send "\$calc = new MyCalculator()\r"
expect "> "

# Profiler avec la variable (utiliser des quotes simples pour éviter l'interpolation bash)
send "profile --filter=php '\$calc->sumAndHash(range(1, 100))'\r"
expect {
    "MyCalculator->sumAndHash" {
        puts "\n✅ Méthode MyCalculator détectée"
        exp_continue
    }
    "array_sum" {
        puts "✅ Fonction array_sum détectée"
        exp_continue
    }
    "Call to undefined" {
        puts "\n❌ ERREUR: Contexte non reconstruit"
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
    echo "expect not found, test skipped"
fi

echo ""
echo "Tests terminés !"
