<?php

require_once __DIR__ . '/vendor/autoload.php';

// Simuler le contexte comme le fait ProfileCommand
$context = [];

// Classes et fonctions définies 
$context[] = <<<'PHP'
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
PHP;

// Variables simples
$context[] = '$x = 10;';
$context[] = '$y = 20;';

// Construire le code complet
$fullContext = implode("\n", $context);
$userCode = '($calc = new BinaryCalculator())->toBinary(10)';

$fullCode = <<<EOPHP
{$fullContext}

// Code utilisateur
echo "Résultat: " . {$userCode} . PHP_EOL;
EOPHP;

echo "=== Code qui sera exécuté ===\n";
echo $fullCode;
echo "\n=== Exécution ===\n";

// Tester l'exécution
eval($fullCode);
