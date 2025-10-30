#!/bin/bash
# Script de test pour la commande profile avec diagnostics

echo "=== Test 1: Profile sans Xdebug trace (devrait échouer avec diagnostics) ==="
echo 'profile array_sum([1,2,3])' | php bin/psysh --no-history

echo ""
echo "=== Test 2: Profile avec mode debug (sans trace activé) ==="
echo 'profile --debug array_sum([1,2,3])' | php bin/psysh --no-history

echo ""
echo "=== Test 3: Profile avec Xdebug trace activé ==="
echo 'profile array_sum([1,2,3])' | XDEBUG_MODE=trace,develop php bin/psysh --no-history

echo ""
echo "=== Test 4: Profile avec debug et trace activé ==="
echo 'profile --debug array_sum([1,2,3])' | XDEBUG_MODE=trace,develop php bin/psysh --no-history

echo ""
echo "=== Test 5: Code plus complexe avec trace activé ==="
cat <<'EOF' | XDEBUG_MODE=trace,develop php bin/psysh --no-history
profile function test() { 
    $sum = 0; 
    for($i=0; $i<100; $i++) { 
        $sum += $i; 
    } 
    return $sum; 
} 
test()
EOF
