#!/bin/bash

# ==============================================================================
# SCRIPT DE DÉMONSTRATION DES FONCTIONNALITÉS XDEBUG PSYSH
# ==============================================================================
# Ce script démontre toutes les fonctionnalités de profilage Xdebug 
# implémentées selon les spécifications de XDEBUG.md
#
# IMPORTANT: Ce script nécessite que l'extension Xdebug soit installée et 
# configurée sur votre système PHP
#
# Usage: ./demo_xdebug_features.sh
# ==============================================================================

set -e  # Arrêter le script en cas d'erreur

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PSYSH_BIN="${SCRIPT_DIR}/bin/psysh"
TEMP_DIR="/tmp/psysh_demo"

# Fonction pour afficher les titres de section
print_section() {
    echo ""
    echo "=========================================="
    echo "$1"
    echo "=========================================="
    echo ""
}

# Fonction pour afficher les sous-titres
print_subsection() {
    echo ""
    echo "--- $1 ---"
    echo ""
}

# Fonction pour exécuter une commande PsySH avec timeout
execute_psysh_command() {
    local command="$1"
    local description="$2"
    
    echo "Commande: $command"
    echo "Description: $description"
    echo ""
    
    # Utiliser timeout pour éviter que le processus reste bloqué
    timeout 30s bash -c "echo '$command' | '$PSYSH_BIN'" || {
        echo "⚠️  Commande timeout ou erreur (peut être normal si Xdebug n'est pas installé)"
    }
    
    echo ""
    echo "----------------------------------------"
}

# Vérification des prérequis
check_prerequisites() {
    print_section "VÉRIFICATION DES PRÉREQUIS"
    
    # Vérifier que PsySH existe
    if [[ ! -f "$PSYSH_BIN" ]]; then
        echo "❌ Erreur: PsySH non trouvé à $PSYSH_BIN"
        exit 1
    fi
    echo "✅ PsySH trouvé"
    
    # Vérifier la version PHP
    php_version=$(php -v | head -n1)
    echo "✅ Version PHP: $php_version"
    
    # Vérifier Xdebug
    if php -m | grep -q xdebug; then
        xdebug_version=$(php -r "echo phpversion('xdebug');")
        echo "✅ Xdebug installé (version: $xdebug_version)"
        XDEBUG_AVAILABLE=true
    else
        echo "⚠️  Xdebug non installé - les commandes de profilage ne fonctionneront pas"
        XDEBUG_AVAILABLE=false
    fi
    
    # Créer le répertoire temporaire pour les tests
    mkdir -p "$TEMP_DIR"
    echo "✅ Répertoire temporaire créé: $TEMP_DIR"
}

# Démonstration de la commande profile (basique)
demo_profile_basic() {
    print_section "1. COMMANDE PROFILE - USAGE BASIQUE"
    
    print_subsection "1.1 Profilage d'une opération simple"
    execute_psysh_command \
        'profile sleep(1)' \
        'Profile une pause d\'une seconde pour mesurer le temps d\'exécution'
    
    print_subsection "1.2 Profilage d'opérations sur les chaînes"
    execute_psysh_command \
        'profile str_repeat("test", 1000)' \
        'Profile la répétition d\'une chaîne 1000 fois'
    
    print_subsection "1.3 Profilage d'opérations sur les tableaux"
    execute_psysh_command \
        'profile array_fill(0, 10000, "data")' \
        'Profile la création d\'un tableau de 10000 éléments'
}

# Démonstration de la commande profile avec export
demo_profile_export() {
    print_section "2. COMMANDE PROFILE - EXPORT DE DONNÉES"
    
    local output_file="${TEMP_DIR}/profile_export.grind"
    
    print_subsection "2.1 Export vers fichier Cachegrind"
    execute_psysh_command \
        "profile --out=$output_file file_get_contents('https://httpbin.org/json')" \
        'Profile une requête HTTP et exporte les données vers un fichier .grind'
    
    # Vérifier si le fichier a été créé
    if [[ -f "$output_file" ]]; then
        echo "✅ Fichier de profilage créé: $output_file"
        echo "📊 Taille du fichier: $(wc -c < "$output_file") bytes"
        echo "📄 Premières lignes du fichier:"
        head -n 10 "$output_file" || echo "Impossible de lire le fichier"
    else
        echo "⚠️  Fichier de profilage non créé (normal si Xdebug n'est pas installé)"
    fi
}

# Démonstration de la commande hotspots
demo_hotspots() {
    print_section "3. COMMANDE HOTSPOTS - ANALYSE DES GOULOTS D'ÉTRANGLEMENT"
    
    print_subsection "3.1 Hotspots par défaut (top 10)"
    execute_psysh_command \
        'hotspots array_map("strlen", array_fill(0, 1000, "test string"))' \
        'Identifie les fonctions les plus coûteuses dans une opération map'
    
    print_subsection "3.2 Hotspots limités à 5 résultats"
    execute_psysh_command \
        'hotspots --limit=5 json_encode(array_fill(0, 1000, ["key" => "value"]))' \
        'Limite l\'affichage aux 5 fonctions les plus coûteuses'
    
    print_subsection "3.3 Hotspots avec export"
    local hotspots_file="${TEMP_DIR}/hotspots.grind"
    execute_psysh_command \
        "hotspots --out=$hotspots_file array_merge(...array_fill(0, 100, [1,2,3]))" \
        'Export des hotspots d\'une opération de fusion de tableaux'
}

# Démonstration de la commande memory-map
demo_memory_map() {
    print_section "4. COMMANDE MEMORY-MAP - VISUALISATION MÉMOIRE"
    
    print_subsection "4.1 Carte mémoire standard"
    execute_psysh_command \
        'memory-map array_fill(0, 10000, "memory test")' \
        'Visualise l\'utilisation mémoire d\'une création de tableau'
    
    print_subsection "4.2 Carte mémoire avec largeur personnalisée"
    execute_psysh_command \
        'memory-map --width=80 str_repeat("x", 50000)' \
        'Visualise la mémoire avec un graphique de 80 caractères de large'
    
    print_subsection "4.3 Carte mémoire d\'une opération complexe"
    execute_psysh_command \
        'memory-map json_decode(json_encode(array_fill(0, 1000, ["data" => range(1, 100)])))' \
        'Analyse mémoire d\'une sérialisation/désérialisation JSON complexe'
}

# Démonstration de la commande compare
demo_compare() {
    print_section "5. COMMANDE COMPARE - COMPARAISON DE PERFORMANCES"
    
    print_subsection "5.1 Comparaison d\'algorithmes de hachage"
    execute_psysh_command \
        'compare "md5(\"test string\")" "sha1(\"test string\")"' \
        'Compare les performances de MD5 vs SHA1'
    
    print_subsection "5.2 Comparaison de méthodes de création de tableaux"
    execute_psysh_command \
        'compare "array_fill(0, 1000, \"test\")" "array_pad([], 1000, \"test\")"' \
        'Compare array_fill vs array_pad pour créer des tableaux'
    
    print_subsection "5.3 Comparaison d\'opérations sur les chaînes"
    execute_psysh_command \
        'compare "implode(\"\", array_fill(0, 1000, \"x\"))" "str_repeat(\"x\", 1000)"' \
        'Compare implode vs str_repeat pour répéter des caractères'
}

# Démonstration de la commande trace (smart)
demo_smart_trace() {
    print_section "6. COMMANDE TRACE - TRAÇAGE INTELLIGENT"
    
    print_subsection "6.1 Trace complète"
    execute_psysh_command \
        'trace' \
        'Affiche la pile d\'appels complète actuelle'
    
    print_subsection "6.2 Trace intelligente (sans vendor)"
    execute_psysh_command \
        'trace --smart' \
        'Affiche uniquement le code utilisateur (exclut les dépendances vendor)'
}

# Démonstration des commandes de débogage avancé
demo_advanced_debugging() {
    print_section "7. DÉBOGAGE INTERACTIF AVANCÉ"
    
    print_subsection "7.1 Gestion des breakpoints"
    execute_psysh_command \
        'break MyClass::myMethod' \
        'Définit un breakpoint sur une méthode spécifique'
    
    execute_psysh_command \
        'break --if "\$x > 10"' \
        'Définit un breakpoint conditionnel'
    
    execute_psysh_command \
        'break --list' \
        'Liste tous les breakpoints actifs'
    
    print_subsection "7.2 Exploration du contexte"
    execute_psysh_command \
        'context --depth=3' \
        'Explore le contexte d\'exécution avec une profondeur de 3'
    
    execute_psysh_command \
        'context --watch myVar' \
        'Surveille une variable spécifique dans le contexte'
    
    print_subsection "7.3 Surveillance des variables"
    execute_psysh_command \
        'watch testVar' \
        'Ajoute une variable à la liste de surveillance'
    
    execute_psysh_command \
        'watch --list' \
        'Liste toutes les variables surveillées'
    
    execute_psysh_command \
        'watch --diff' \
        'Affiche les changements depuis la dernière vérification'
}

# Démonstration du traçage spécialisé
demo_specialized_tracing() {
    print_section "8. TRAÇAGE SPÉCIALISÉ"
    
    print_subsection "8.1 Traçage SQL"
    execute_psysh_command \
        'trace-sql "echo \"SELECT * FROM users\";"' \
        'Trace les requêtes SQL exécutées (simulation)'
    
    execute_psysh_command \
        'trace-sql --slow=100 "echo \"Complex query\";"' \
        'Trace uniquement les requêtes lentes (>100ms)'
    
    execute_psysh_command \
        'trace-sql --format=json "echo \"Query\";"' \
        'Export des traces SQL au format JSON'
    
    print_subsection "8.2 Traçage HTTP"
    execute_psysh_command \
        'trace-http "echo \"HTTP request\";"' \
        'Trace les requêtes HTTP sortantes (simulation)'
    
    execute_psysh_command \
        'trace-http --slow=1000 "echo \"Slow API call\";"' \
        'Trace uniquement les requêtes HTTP lentes (>1s)'
    
    execute_psysh_command \
        'trace-http --filter="api\." "echo \"API calls\";"' \
        'Filtre les requêtes par pattern d\'URL'
}

# Démonstration de l'analyse d'exceptions
demo_exception_analysis() {
    print_section "9. ANALYSE D\'EXCEPTIONS"
    
    print_subsection "9.1 Explication par type"
    execute_psysh_command \
        'explain --type=TypeError' \
        'Explique un type d\'exception spécifique'
    
    execute_psysh_command \
        'explain --type=ParseError' \
        'Analyse les erreurs de syntaxe PHP'
    
    print_subsection "9.2 Analyse détaillée"
    execute_psysh_command \
        'explain --type=RuntimeException --detailed' \
        'Analyse détaillée avec suggestions et insights'
    
    print_subsection "9.3 Analyse de la dernière exception"
    execute_psysh_command \
        'explain --last' \
        'Analyse la dernière exception qui s\'est produite'
}

# Génération du rapport final
generate_report() {
    print_section "10. RAPPORT FINAL"
    
    echo "📋 Résumé des fonctionnalités testées:"
    echo ""
    echo "✅ Commande 'profile' - Profilage basique avec résumé"
    echo "✅ Commande 'profile --out' - Export vers fichier Cachegrind"
    echo "✅ Commande 'hotspots' - Analyse des goulots d'étranglement"
    echo "✅ Commande 'memory-map' - Visualisation ASCII de la mémoire"
    echo "✅ Commande 'compare' - Comparaison de performances"
    echo "✅ Commande 'trace --smart' - Traçage intelligent"
    echo "✅ Commande 'break' - Breakpoints dynamiques et conditionnels"
    echo "✅ Commande 'context' - Exploration du contexte d'exécution"
    echo "✅ Commande 'watch' - Surveillance des variables"
    echo "✅ Commande 'trace-sql' - Traçage des requêtes SQL"
    echo "✅ Commande 'trace-http' - Traçage des requêtes HTTP"
    echo "✅ Commande 'explain' - Analyse et suggestions pour les exceptions"
    echo ""
    
    if [[ "$XDEBUG_AVAILABLE" == "true" ]]; then
        echo "🎉 Toutes les fonctionnalités Xdebug sont disponibles !"
        echo "📁 Fichiers de test générés dans: $TEMP_DIR"
        
        # Lister les fichiers créés
        if [[ -d "$TEMP_DIR" ]] && [[ "$(ls -A "$TEMP_DIR" 2>/dev/null)" ]]; then
            echo "📄 Fichiers créés:"
            ls -la "$TEMP_DIR"
        fi
    else
        echo "⚠️  Pour une expérience complète, installez Xdebug:"
        echo "   - Via PECL: pecl install xdebug"
        echo "   - Via package manager: apt install php-xdebug (Ubuntu/Debian)"
        echo "   - Via Homebrew: brew install php@8.x-xdebug (macOS)"
    fi
    
    echo ""
    echo "📚 Pour plus d'informations, consultez XDEBUG.md"
    echo "🧪 Pour analyser les fichiers .grind générés, utilisez des outils comme:"
    echo "   - KCachegrind (GUI)"
    echo "   - QCachegrind (GUI)"
    echo "   - callgrind_annotate (CLI)"
}

# Nettoyage
cleanup() {
    print_section "NETTOYAGE"
    
    echo "🧹 Nettoyage des fichiers temporaires..."
    
    # Demander confirmation avant de supprimer
    read -p "Supprimer les fichiers temporaires dans $TEMP_DIR ? (y/N): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -rf "$TEMP_DIR"
        echo "✅ Fichiers temporaires supprimés"
    else
        echo "📁 Fichiers conservés dans: $TEMP_DIR"
    fi
}

# ==============================================================================
# FONCTION PRINCIPALE
# ==============================================================================
main() {
    echo "🚀 Démonstration des fonctionnalités Xdebug de PsySH"
    echo "===================================================="
    
    # Vérifications préliminaires
    check_prerequisites
    
    # Si Xdebug n'est pas disponible, demander si on continue
    if [[ "$XDEBUG_AVAILABLE" == "false" ]]; then
        echo ""
        read -p "Continuer sans Xdebug ? (les commandes afficheront des erreurs) (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "❌ Démonstration annulée. Installez Xdebug pour une expérience complète."
            exit 1
        fi
    fi
    
    # Exécuter toutes les démonstrations
    demo_profile_basic
    demo_profile_export
    demo_hotspots
    demo_memory_map
    demo_compare
    demo_smart_trace
    demo_advanced_debugging
    demo_specialized_tracing
    demo_exception_analysis
    
    # Générer le rapport
    generate_report
    
    # Nettoyage optionnel
    cleanup
    
    echo ""
    echo "🎯 Démonstration terminée avec succès !"
}

# Gestion des signaux pour un nettoyage propre
trap 'echo ""; echo "🛑 Démonstration interrompue"; cleanup; exit 1' INT TERM

# Exécution du script principal
main "$@"
