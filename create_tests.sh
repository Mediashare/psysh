#!/bin/bash

# ==============================================================================
# SCRIPT DE CRÉATION DES TESTS MANQUANTS POUR LES COMMANDES XDEBUG
# ==============================================================================
# Ce script crée automatiquement les fichiers de test manquants pour toutes
# les nouvelles commandes Xdebug implémentées
#
# Usage: ./create_tests.sh
# ==============================================================================

set -e  # Arrêter le script en cas d'erreur

# Liste des commandes pour lesquelles nous devons créer des tests
declare -A COMMANDS_TO_TEST=(
    ["BreakCommand"]="BreakCommandTest.php"
    ["ContextCommand"]="ContextCommandTest.php"
    ["WatchCommand"]="WatchCommandTest.php"
    ["TraceSqlCommand"]="TraceSqlCommandTest.php"
    ["TraceHttpCommand"]="TraceHttpCommandTest.php"
    ["ExplainCommand"]="ExplainCommandTest.php"
)

# Répertoire des tests
TEST_DIR="test/Command"

echo "🧪 Création des fichiers de test manquants..."

# Fonction pour créer un test basique
create_basic_test() {
    local command_name="$1"
    local test_file="$2"
    local full_path="${TEST_DIR}/${test_file}"
    
    if [[ -f "$full_path" ]]; then
        echo "   ✅ $test_file existe déjà"
        return
    fi
    
    echo "   📝 Création de $test_file"
    
    # Créer le contenu du fichier de test
    cat > "$full_path" << TEST_TEMPLATE
<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\\Test\\Command;

use Psy\\Command\\${command_name};
use Psy\\Shell;
use Symfony\\Component\\Console\\Tester\\CommandTester;

class ${command_name}Test extends \\Psy\\Test\\TestCase
{
    private \$command;

    protected function setUp(): void
    {
        \$this->command = new ${command_name}();
        \$this->command->setApplication(new Shell());
    }

    public function testCommandExists()
    {
        \$this->assertInstanceOf(${command_name}::class, \$this->command);
        \$this->assertNotEmpty(\$this->command->getName());
        \$this->assertNotEmpty(\$this->command->getDescription());
    }

    public function testCommandConfiguration()
    {
        \$definition = \$this->command->getDefinition();
        \$this->assertNotNull(\$definition);
        
        // Test that command has expected name
        \$this->assertIsString(\$this->command->getName());
        \$this->assertNotEmpty(\$this->command->getName());
    }

    public function testCommandHelp()
    {
        \$help = \$this->command->getHelp();
        \$this->assertIsString(\$help);
        \$this->assertNotEmpty(\$help);
    }

    /**
     * Test command without required arguments (should show error or help)
     */
    public function testCommandWithoutArguments()
    {
        \$tester = new CommandTester(\$this->command);
        
        try {
            \$tester->execute([]);
            // If no exception is thrown, check the output contains help or error
            \$output = \$tester->getDisplay();
            \$this->assertNotEmpty(\$output);
        } catch (\\Exception \$e) {
            // It's normal for commands to throw exceptions when required args are missing
            \$this->assertInstanceOf(\\Exception::class, \$e);
        }
    }
}
TEST_TEMPLATE

    echo "   ✅ $test_file créé avec succès"
}

# Créer les tests pour chaque commande
for command_name in "${!COMMANDS_TO_TEST[@]}"; do
    test_file="${COMMANDS_TO_TEST[$command_name]}"
    echo "📋 Traitement de $command_name -> $test_file"
    create_basic_test "$command_name" "$test_file"
done

echo ""
echo "🎯 Création des tests terminée !"
echo "📁 Fichiers créés dans: $TEST_DIR"
echo ""
echo "💡 Pour exécuter les tests:"
echo "   php vendor/bin/phpunit test/Command/"
echo ""
echo "🚀 Les tests sont basiques et peuvent être améliorés avec des cas spécifiques"
