#!/bin/bash

echo "=== TEST FINAL COMPLET ==="
echo ""

if command -v expect >/dev/null 2>&1; then
    PSYSH_PAGER=cat expect << 'EOF'
set timeout 10
spawn bin/psysh
expect "> "

# Définir la classe sur une seule ligne
send "class MyCalculator { public function sumAndHash(array \$numbers) { \$sum = array_sum(\$numbers); \$hash = md5(\$sum); return strlen(\$hash); } }\r"
expect "> "

# Créer l'instance
send "\$calc = new MyCalculator()\r"
expect "> "

# Test 1: Profile avec --full --debug
puts "\n=== Test 1: profile --full --debug ==="
send "profile --full --debug '\$calc->sumAndHash(range(10, 1000))'\r"
expect {
    "=== DEBUG MODE ENABLED ===" {
        puts "✅ Mode debug activé"
        exp_continue
    }
    "all functions" {
        puts "✅ Titre 'all functions' affiché"
        exp_continue
    }
    "MyCalculator->sumAndHash" {
        puts "✅ Méthode MyCalculator détectée"
        exp_continue
    }
    "array_sum" {
        puts "✅ Fonction array_sum détectée"
        exp_continue
    }
    "md5" {
        puts "✅ Fonction md5 détectée"
        exp_continue
    }
    "range" {
        puts "✅ Fonction range détectée"
        exp_continue
    }
    "Profiling execution failed" {
        puts "❌ ERREUR: Profiling a échoué"
        exp_continue
    }
    "> " {
        puts "✅ Test terminé"
    }
    timeout {
        puts "❌ TIMEOUT"
    }
}

send "exit\r"
expect eof
EOF
else
    echo "expect not found, test skipped"
fi

echo ""
echo "Test terminé !"
