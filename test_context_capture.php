#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Psy\Command\ProfileCommand;
use Psy\Shell;
use ReflectionClass;

echo "Test de capture de contexte pour ProfileCommand\n";
echo "==============================================\n\n";

// Créer un shell et définir une classe
$shell = new Shell();

// Simuler la définition d'une classe dans le shell
eval('
class BinaryCalculator {
    public function toBinary($num) {
        return $this->convert($num);
    }

    private function convert($num) {
        return decbin($num);
    }

    public function fromBinary($binary) {
        return bindec($binary);
    }

    public function binaryAdd($a, $b) {
        return decbin(bindec($a) + bindec($b));
    }
}
');

// Créer le ProfileCommand et tester la capture de contexte
$command = new ProfileCommand();
$command->setApplication($shell);

// Accéder à la méthode privée pour tester
$reflection = new ReflectionClass($command);
$method = $reflection->getMethod('captureExecutedCodeAlternative');
$method->setAccessible(true);

$capturedContext = $method->invoke($command, $shell);

echo "Contexte capturé :\n";
echo "==================\n";
echo $capturedContext;
echo "\n\n";

// Tester si BinaryCalculator existe
if (class_exists('BinaryCalculator')) {
    echo "✅ BinaryCalculator existe dans le contexte actuel\n";
    $calc = new BinaryCalculator();
    echo "✅ Test toBinary(10): " . $calc->toBinary(10) . "\n";
} else {
    echo "❌ BinaryCalculator n'existe pas\n";
}

echo "\n";

// Maintenant tester le ProfileCommand complet
echo "Test du ProfileCommand avec BinaryCalculator :\n";
echo "==============================================\n";

try {
    // Utiliser la réflection pour tester la méthode prepareCodeWithContext
    $prepareMethod = $reflection->getMethod('prepareCodeWithContext');
    $prepareMethod->setAccessible(true);
    
    $fullCode = $prepareMethod->invoke($command, '($calc = new BinaryCalculator())->toBinary(10);');
    
    echo "Code généré pour le profiling :\n";
    echo "-------------------------------\n";
    echo $fullCode;
    echo "\n-------------------------------\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}