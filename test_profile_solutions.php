<?php

require_once __DIR__ . '/vendor/autoload.php';

use Psy\Shell;
use Psy\Configuration;

echo "=== Test des solutions pour le contexte de profilage ===\n\n";

// Solution 1: Reconstruction basée sur l'historique du code exécuté
echo "1. TEST: Reconstruction via code source historique\n";
echo "-------------------------------------------\n";

// Simuler un shell avec du code exécuté
$config = new Configuration();
$shell = new Shell($config);

// Code de test avec classes, closures et variables
$testCode = <<<'PHP'
class TestClass {
    public function process($value) {
        return $value * 2;
    }
}

$simpleVar = 42;
$arrayVar = ['a' => 1, 'b' => 2];

// Closure simple
$simpleClosure = function($x) {
    return $x + 10;
};

// Closure avec capture de variables
$capturedVar = 100;
$complexClosure = function($x) use ($capturedVar) {
    return $x * $capturedVar;
};

// Fonction globale
function testFunction($param) {
    return "Processed: " . $param;
}
PHP;

// Exécuter le code dans le shell
echo "Exécution du code de test dans le shell...\n";
eval($testCode);

// Récupérer l'historique du code exécuté
$executedCode = '';
// Simuler ce que ferait getExecutedCodeAsString()
$executedCode .= "class TestClass {\n    public function process(\$value) {\n        return \$value * 2;\n    }\n}\n";
$executedCode .= "function testFunction(\$param) {\n    return \"Processed: \" . \$param;\n}\n";

echo "Code capturé depuis l'historique:\n";
echo $executedCode . "\n";

// Test de reconstruction dans un nouveau contexte
echo "\nReconstruction dans un contexte isolé:\n";
$isolatedCode = <<<EOPHP
// Contexte reconstruit
{$executedCode}

// Variables simples
\$simpleVar = 42;
\$arrayVar = ['a' => 1, 'b' => 2];

// Test d'utilisation
\$obj = new TestClass();
echo "TestClass->process(5) = " . \$obj->process(5) . PHP_EOL;
echo "testFunction('hello') = " . testFunction('hello') . PHP_EOL;
echo "simpleVar = " . \$simpleVar . PHP_EOL;
EOPHP;

// Exécuter dans un processus séparé pour simuler l'isolation
$tempFile = tempnam(sys_get_temp_dir(), 'psysh_test_');
file_put_contents($tempFile, "<?php\n" . $isolatedCode);
$output = shell_exec("php $tempFile 2>&1");
echo "Résultat:\n$output\n";
unlink($tempFile);

// Solution 7: Clone/Fork du Shell
echo "\n2. TEST: Solution avec clone/fork du Shell\n";
echo "-------------------------------------------\n";

// Simuler les propriétés importantes d'un Shell
class MockShell {
    private $scopeVariables = [];
    private $executedCode = [];
    private $includes = [];
    
    public function __construct() {
        $this->scopeVariables = get_defined_vars();
        $this->includes = get_included_files();
    }
    
    public function addExecutedCode($code) {
        $this->executedCode[] = $code;
    }
    
    public function getScopeVariables() {
        return $this->scopeVariables;
    }
    
    public function getExecutedCode() {
        return implode("\n", $this->executedCode);
    }
    
    public function getIncludes() {
        return $this->includes;
    }
    
    // Méthode pour créer un contexte de profilage
    public function createProfilingContext() {
        $context = [];
        
        // 1. Includes/Autoloaders
        foreach ($this->includes as $file) {
            if (strpos($file, 'vendor/autoload.php') !== false) {
                $context[] = "require_once " . var_export($file, true) . ";";
            }
        }
        
        // 2. Code exécuté (classes, fonctions)
        $context[] = $this->getExecutedCode();
        
        // 3. Variables simples uniquement
        foreach ($this->scopeVariables as $name => $value) {
            if (is_scalar($value) || is_array($value)) {
                try {
                    $context[] = '$' . $name . ' = ' . var_export($value, true) . ';';
                } catch (\Exception $e) {
                    $context[] = "// Variable $name could not be exported";
                }
            }
        }
        
        return implode("\n", $context);
    }
}

$mockShell = new MockShell();
$mockShell->addExecutedCode("class ProfilingTest { public function run() { return 'OK'; } }");

echo "Contexte généré pour le profilage:\n";
echo $mockShell->createProfilingContext() . "\n";

// Solution avec variables de substitution pour les closures
echo "\n3. TEST: Substitution des closures par des placeholders\n";
echo "--------------------------------------------------------\n";

$contextWithClosures = [];
$closureRegistry = [];
$closureCounter = 0;

// Enregistrer les closures avec des identifiants
if (isset($simpleClosure)) {
    $closureId = 'closure_' . $closureCounter++;
    $closureRegistry[$closureId] = [
        'type' => 'simple',
        'code' => 'function($x) { return $x + 10; }'
    ];
    $contextWithClosures[] = "// Closure: $closureId (simple)";
    $contextWithClosures[] = "\$$closureId = null; // Placeholder for closure";
}

if (isset($complexClosure)) {
    $closureId = 'closure_' . $closureCounter++;
    $closureRegistry[$closureId] = [
        'type' => 'with_use',
        'code' => 'function($x) use ($capturedVar) { return $x * $capturedVar; }',
        'captured' => ['capturedVar' => 100]
    ];
    $contextWithClosures[] = "// Closure: $closureId (with captured vars)";
    $contextWithClosures[] = "\$$closureId = null; // Placeholder for closure";
}

echo "Registre des closures:\n";
print_r($closureRegistry);
echo "\nContexte avec placeholders:\n";
echo implode("\n", $contextWithClosures) . "\n";

echo "\n=== Analyse des solutions ===\n";
echo "1. Reconstruction via historique: ✓ Fonctionne pour classes/fonctions, ✗ Perd les closures\n";
echo "2. Clone/Fork du Shell: ✓ Préserve le contexte, ? Complexité d'implémentation\n";
echo "3. Placeholders pour closures: ✓ Évite la sérialisation, ✗ Perd la fonctionnalité des closures\n";
echo "4. Option --debug suggérée: ✓ Aide au diagnostic\n";
echo "5. SuperClosure: ? À tester comme alternative à Opis\n";
