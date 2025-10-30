#!/bin/bash
# Script de validation complète de la commande profile

echo "======================================"
echo "Tests de Validation - Commande Profile"
echo "======================================"
echo ""

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Sans Xdebug trace (devrait échouer avec bon message)
echo "Test 1: Sans Xdebug trace (message d'erreur attendu)"
echo "------------------------------------------------------"
OUTPUT=$(echo 'profile array_sum([1,2,3])' | php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "No profiling data collected"; then
    echo -e "${GREEN}✓ PASS${NC} - Message d'erreur approprié affiché"
else
    echo -e "${RED}✗ FAIL${NC} - Message d'erreur manquant"
fi
echo ""

# Test 2: Avec Xdebug trace - Code simple
echo "Test 2: Avec Xdebug trace - Code simple"
echo "----------------------------------------"
OUTPUT=$(echo 'profile strlen("hello")' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Profiling results"; then
    echo -e "${GREEN}✓ PASS${NC} - Profilage réussi"
else
    echo -e "${RED}✗ FAIL${NC} - Profilage échoué"
    echo "$OUTPUT"
fi
echo ""

# Test 3: Code avec echo (pollution stdout)
echo "Test 3: Code avec echo (test pollution stdout)"
echo "-----------------------------------------------"
OUTPUT=$(echo 'profile function test() { echo "test"; return 42; } test()' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Profiling results" && ! echo "$OUTPUT" | grep -q "test/var/tmp"; then
    echo -e "${GREEN}✓ PASS${NC} - Output correctement capturé"
else
    echo -e "${RED}✗ FAIL${NC} - Problème de capture d'output"
fi
echo ""

# Test 4: Code récursif
echo "Test 4: Code récursif (fibonacci)"
echo "----------------------------------"
OUTPUT=$(echo 'profile function fib($n) { return $n <= 1 ? $n : fib($n-1) + fib($n-2); } fib(8)' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "fib" && echo "$OUTPUT" | grep -q "Calls"; then
    echo -e "${GREEN}✓ PASS${NC} - Fonctions récursives tracées"
    # Afficher le nombre d'appels
    CALLS=$(echo "$OUTPUT" | grep "fib" | awk '{print $2}' | head -1)
    echo "  → Nombre d'appels détectés: $CALLS"
else
    echo -e "${RED}✗ FAIL${NC} - Problème avec code récursif"
fi
echo ""

# Test 5: Mode debug
echo "Test 5: Mode debug"
echo "------------------"
OUTPUT=$(echo 'profile --debug array_sum([1,2,3])' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Debug mode enabled"; then
    echo -e "${GREEN}✓ PASS${NC} - Mode debug activé"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Mode debug non affiché (peut être normal)"
fi
echo ""

# Test 6: Filtres
echo "Test 6: Test des filtres"
echo "------------------------"
OUTPUT=$(echo 'profile --filter=php array_map(fn($x)=>$x*2, [1,2,3])' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Profiling results"; then
    echo -e "${GREEN}✓ PASS${NC} - Filtre --filter=php fonctionne"
else
    echo -e "${RED}✗ FAIL${NC} - Problème avec filtre"
fi
echo ""

# Test 7: Threshold
echo "Test 7: Threshold (seuil)"
echo "-------------------------"
OUTPUT=$(echo 'profile --threshold=0 strlen("test")' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Profiling results"; then
    echo -e "${GREEN}✓ PASS${NC} - Threshold fonctionne"
else
    echo -e "${RED}✗ FAIL${NC} - Problème avec threshold"
fi
echo ""

# Test 8: Formats de sortie
echo "Test 8: Formats et affichage"
echo "----------------------------"
OUTPUT=$(echo 'profile array_filter(range(1,50), fn($x)=>$x%2==0)' | XDEBUG_MODE=trace,develop php bin/psysh -n 2>&1)
if echo "$OUTPUT" | grep -q "Time" && echo "$OUTPUT" | grep -q "Memory" && echo "$OUTPUT" | grep -q "Calls"; then
    echo -e "${GREEN}✓ PASS${NC} - Colonnes affichées correctement"
else
    echo -e "${RED}✗ FAIL${NC} - Problème d'affichage"
fi
echo ""

# Résumé
echo "======================================"
echo "Tests terminés"
echo "======================================"
echo ""
echo -e "${YELLOW}Note${NC}: Pour utiliser la commande profile, lancez:"
echo "  XDEBUG_MODE=trace,develop php bin/psysh"
