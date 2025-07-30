<?php

namespace Psy\Command;

use Psy\Input\CodeArgument;
use Psy\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProfileCommand extends Command
{
    private const IGNORED_FUNCTIONS = [
        // Fonctions internes de PHP qu'on veut toujours ignorer
        'php::zend_call_function',
        'php::zend_call_method',
        'php::call_user_func',
        'php::call_user_func_array',
        // Fonctions de PsySH qu'on veut ignorer sauf en mode --full
        'Psy\\Shell::handleInput',
        'Psy\\Shell::execute',
        'Psy\\ExecutionClosure::execute',
    ];

    private const PSYSH_NAMESPACES = [
        'Psy\\',
        'PhpParser\\',
        'Symfony\\Component\\Console\\',
        'Symfony\\Component\\VarDumper\\',
    ];

    protected function configure()
    {
        $this
            ->setName('profile')
            ->setDefinition([
                new InputOption('out', '', InputOption::VALUE_REQUIRED, 'Path to the output file for the profiling data.'),
                new InputOption('full', '', InputOption::VALUE_NONE, 'Show full profiling data including PsySH overhead.'),
                new InputOption('filter', '', InputOption::VALUE_REQUIRED, 'Filter level: user (default), php, all', 'user'),
                new InputOption('threshold', '', InputOption::VALUE_REQUIRED, 'Minimum time threshold in microseconds', 0),
                new InputOption('show-params', '', InputOption::VALUE_NONE, 'Show function parameters in profiling results.'),
                new InputOption('full-namespaces', '', InputOption::VALUE_NONE, 'Show complete namespaces without truncation.'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to profile.'),
            ])
            ->setDescription('Profile a string of PHP code and display the execution summary.')
            ->setHelp(
                <<<'HELP'
Profile a string of PHP code and display the execution summary.

Filter levels:
- user: Shows only user code and project dependencies
- php: Shows user code + PHP internal functions  
- all: Shows everything including PsySH initialization

Options:
- --threshold: Minimum execution time to display (default: 1000μs)
- --out: Export full cachegrind data to file

Examples:
> profile $calc->toBinary(1000)
> profile --threshold=100 $service->process($data)
> profile --full --out=profile.grind complex_operation()
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xhprof')) {
            throw new RuntimeException(
                'XHProf extension is not loaded. The profile command requires XHProf to be installed and enabled.\n' .
                'Install it with: pecl install xhprof\n' .
                'Then add "extension=xhprof.so" to your php.ini'
            );
        }

        $code = $input->getArgument('code');
        $outFile = $input->getOption('out');
        $filterLevel = $input->getOption('full') ? 'all' : $input->getOption('filter');
        $threshold = (int) $input->getOption('threshold');
        $showParams = $input->getOption('show-params');
        $fullNamespaces = $input->getOption('full-namespaces');

        // Préparer le contexte d'exécution
        $shell = $this->getShell();
        $context = $this->prepareContext($shell);
        
        // Démarrer le profilage
        xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
        
        // Exécuter le code dans le contexte préparé
        try {
            extract($context['variables']);
            foreach ($context['includes'] as $file) {
                require_once $file;
            }
            
            // Use a closure to properly scope the code
            $closure = function() use ($code) {
                return eval(str_ends_with(rtrim($code, ';'), ';') ? $code : 'return ' . $code . ';');
            };
            
            $result = $closure();
            $success = true;
        } catch (\Throwable $e) {
            $result = $e;
            $success = false;
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }
        
        // Arrêter le profilage et récupérer les données
        $profile_data = xhprof_disable();
        
        if ($success) {
            // Filtrer et formater les résultats
            $filtered_data = $this->filterProfileData($profile_data, $filterLevel === 'all');
            
            // Afficher les résultats
            $this->displayResults($filtered_data, $output, $filterLevel, $threshold, $showParams, $fullNamespaces);
            
            // Sauvegarder le rapport complet si demandé
            if ($outFile) {
                $this->saveProfileData($profile_data, $outFile, $output);
            }
        }
        
        return $success ? 0 : 1;
    }

    private function createProfileScript(string $code, string $filterLevel, string $metricsFile): string
    {
        $shell = $this->getShell();
        
        // Récupérer le contexte actuel (variables, includes, etc.)
        $context = $this->serializeContext($shell);
        
        // Créer un script qui:
        // 1. Restaure le contexte
        // 2. Définit un marqueur de début
        // 3. Exécute le code utilisateur
        // 4. Définit un marqueur de fin
        
        $script = <<<'PHP'
<?php
// Restoration du contexte
%s

// Marqueur de début du code utilisateur
$__profile_start = microtime(true);
$__profile_start_memory = memory_get_usage(true);

// === DÉBUT CODE UTILISATEUR ===
try {
    $__profile_result = eval(%s);
    $__profile_success = true;
} catch (\Throwable $__profile_exception) {
    $__profile_result = $__profile_exception;
    $__profile_success = false;
}
// === FIN CODE UTILISATEUR ===

$__profile_end = microtime(true);
$__profile_end_memory = memory_get_usage(true);

// Sauvegarder les métriques
file_put_contents(%s, json_encode([
    'success' => $__profile_success,
    'start_time' => $__profile_start,
    'end_time' => $__profile_end,
    'duration' => ($__profile_end - $__profile_start) * 1000000,
    'start_memory' => $__profile_start_memory,
    'end_memory' => $__profile_end_memory,
    'memory_delta' => $__profile_end_memory - $__profile_start_memory,
    'result' => $__profile_success ? var_export($__profile_result, true) : $__profile_exception->getMessage(),
]));
PHP;
        
        return sprintf(
            $script,
            $context,
            var_export($code, true),
            var_export($metricsFile, true)
        );
    }

    private function serializeContext($shell): string
    {
        $context = [];
        
        // Récupérer toutes les variables du contexte
        $vars = $shell->getScopeVariables();
        foreach ($vars as $name => $value) {
            if (!in_array($name, ['this', '_', '_e'])) {
                $context[] = sprintf('$%s = %s;', $name, var_export($value, true));
            }
        }
        
        // Récupérer les includes
        $includes = $shell->getIncludes();
        foreach ($includes as $file) {
            $context[] = sprintf("require_once %s;", var_export($file, true));
        }
        
        return implode("\n", $context);
    }

    private function executeWithProfiling(string $code, string $filterLevel, OutputInterface $output): array
    {
        $tmpDir = sys_get_temp_dir();
        $scriptFile = tempnam($tmpDir, 'psysh_profile_script_');
        
        // Créer le fichier de métriques avec le même nom de base que le script
        $metricsFile = str_replace('_script_', '_metrics_', $scriptFile);
        
        // Debugging: Check if the directory is writable
        if (!is_writable($tmpDir)) {
            throw new \RuntimeException('Le répertoire temporaire n\'est pas accessible en écriture: ' . $tmpDir);
        }
        
        // Créer le script de profilage avec le bon chemin de fichier de métriques
        $script = $this->createProfileScript($code, $filterLevel, $metricsFile);
        
        // Debugging: Afficher le script généré
        $output->writeln('Generated script:');
        $output->writeln('================');
        $output->writeln($script);
        $output->writeln('================');
        
        file_put_contents($scriptFile, $script);
        
        // Configuration Xdebug optimisée
        $process = new Process([
            PHP_BINARY,
            '-d', 'memory_limit=-1',
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir=' . $tmpDir,
            '-d', 'xdebug.profiler_output_name=cachegrind.out.%t.%p',
            '-d', 'xdebug.collect_params=1',
            '-d', 'xdebug.collect_return=1',
            $scriptFile
        ]);
        
        $process->run();
        
        // Debugging: Afficher la sortie du process
        $output->writeln('Process output: ' . $process->getOutput());
        $output->writeln('Process error output: ' . $process->getErrorOutput());
        $output->writeln('Process exit code: ' . $process->getExitCode());
        
        // Vérifier si le process s'est bien exécuté
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur lors de l\'exécution du profiling: ' . $process->getErrorOutput());
        }
        
        // Trouver le fichier cachegrind généré
        $files = glob($tmpDir . '/cachegrind.out.*');
        $output->writeln('Cachegrind files found: ' . print_r($files, true));
        $cachegrindFile = end($files);
        $output->writeln('Selected cachegrind file: ' . ($cachegrindFile ?: 'NONE'));
        
        // Vérifier que le fichier de métriques existe avant de le lire
        if (!file_exists($metricsFile)) {
            throw new \RuntimeException('Le fichier de métriques n\'a pas été créé: ' . $metricsFile);
        }
        
        $output->writeln('Metrics file exists and contains: ' . substr(file_get_contents($metricsFile), 0, 200) . '...');
        
        // Lire les métriques avec gestion d'erreur
        $metricsContent = file_get_contents($metricsFile);
        if ($metricsContent === false) {
            throw new \RuntimeException('Impossible de lire le fichier de métriques: ' . $metricsFile);
        }
        
        $metrics = json_decode($metricsContent, true);
        if ($metrics === null) {
            throw new \RuntimeException('Impossible de décoder les métriques JSON: ' . json_last_error_msg());
        }
        
        // Parser le fichier cachegrind
        $profileData = $this->parseCachegrindEnhanced($cachegrindFile, $metrics);
        
        // Nettoyer
        unlink($scriptFile);
        if (file_exists($metricsFile)) {
            unlink($metricsFile);
        }
        
        return [
            'success' => $metrics['success'],
            'data' => $profileData,
            'cachegrind' => $cachegrindFile,
            'metrics' => $metrics
        ];
    }

    private function parseCachegrindEnhanced(string $file, array $metrics): array
    {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        $functions = [];
        $currentFunction = null;
        $files = [];
        
        foreach ($lines as $line) {
            // Parser les définitions de fichiers
            if (preg_match('/^fl=\((\d+)\)\s+(.+)$/', $line, $matches)) {
                $files[$matches[1]] = $matches[2];
                continue;
            }
            
            // Parser les définitions de fonctions
            if (preg_match('/^fn=\((\d+)\)\s+(.+)$/', $line, $matches)) {
                $currentFunction = [
                    'name' => $matches[2],
                    'calls' => 0,
                    'time' => 0,
                    'memory' => 0,
                    'file_id' => null,
                    'is_user' => false,
                ];
                continue;
            }
            
            // Parser les données de temps/mémoire
            if ($currentFunction && preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $line, $matches)) {
                $currentFunction['time'] += (int)$matches[2];
                $currentFunction['memory'] += (int)$matches[3];
                $currentFunction['calls']++;
                
                // Déterminer si c'est du code utilisateur
                if (isset($files[$currentFunction['file_id']])) {
                    $file = $files[$currentFunction['file_id']];
                    $currentFunction['is_user'] = !$this->isPsyshCode($file, $currentFunction['name']);
                }
                
                $functions[$currentFunction['name']] = $currentFunction;
            }
        }
        
        return $this->enhanceWithCallGraph($functions);
    }

    private function isPsyshCode(string $file, string $function): bool
    {
        // Vérifier si c'est du code PsySH par le namespace
        foreach (self::PSYSH_NAMESPACES as $namespace) {
            if (str_starts_with($function, $namespace)) {
                return true;
            }
        }
        
        // Vérifier par le fichier
        if (str_contains($file, 'vendor/psy/psysh')) {
            return true;
        }
        
        return false;
    }

    private function enhanceWithCallGraph(array $functions): array
    {
        // Calculer les temps exclusifs et construire l'arbre d'appels
        $enhanced = [];
        $totalTime = array_sum(array_column($functions, 'time'));
        $totalMemory = array_sum(array_column($functions, 'memory'));
        
        foreach ($functions as $name => $data) {
            $enhanced[$name] = $data + [
                'time_percent' => $totalTime > 0 ? ($data['time'] / $totalTime) * 100 : 0,
                'memory_percent' => $totalMemory > 0 ? ($data['memory'] / $totalMemory) * 100 : 0,
            ];
        }
        
        return $enhanced;
    }

    private function displayResults(array $data, OutputInterface $output, string $filterLevel, int $threshold, bool $showParams = false, bool $fullNamespaces = false): void
    {
        // Filtrer selon le niveau
        $filtered = $this->filterFunctions($data, $filterLevel, $threshold);
        
        if (empty($filtered)) {
            $output->writeln('<comment>No functions exceeded the threshold.</comment>');
            return;
        }
        
        // Trier par temps décroissant
        uasort($filtered, fn($a, $b) => $b['time'] <=> $a['time']);
        
        // Afficher le tableau
        $table = new Table($output);
        $headers = ['Function', 'Calls', 'Time (μs)', 'Time %', 'Memory (B)', 'Memory %'];
        if ($showParams) {
            $headers[] = 'Parameters';
        }
        $table->setHeaders($headers);
        
        foreach (array_slice($filtered, 0, 20) as $name => $func) {
            $row = [
                $fullNamespaces ? $name : $this->formatFunctionName($name),
                $func['calls'],
                number_format($func['time'], 1), // Afficher en microsecondes
                number_format($func['time_percent'], 1) . '%',
                number_format($func['memory'], 0), // Afficher en bytes
                number_format($func['memory_percent'], 1) . '%',
            ];
            
            if ($showParams) {
                $row[] = $this->extractFunctionParams($name);
            }
            
            $table->addRow($row);
        }
        
        $output->writeln(sprintf(
            "\n<info>Profiling results (%s):</info>",
            $filterLevel === 'user' ? 'user code only' : $filterLevel
        ));
        
        $table->render();
        
        // Résumé
        $totalTime = array_sum(array_column($filtered, 'time')); // en microsecondes
        $totalMemory = array_sum(array_column($filtered, 'memory')); // en bytes
        
        $output->writeln(sprintf(
            "\n<comment>Total execution: Time: %.1f μs (%.3f ms), Memory: %s bytes (%s)</comment>",
            $totalTime,
            $totalTime / 1000,
            number_format($totalMemory),
            $this->formatBytes($totalMemory)
        ));
    }

    private function filterFunctions(array $functions, string $filterLevel, int $threshold): array
    {
        return array_filter($functions, function($func) use ($filterLevel, $threshold) {
            // Filtre par seuil de temps
            if ($func['time'] < $threshold) {
                return false;
            }
            
            // Filtre par niveau
            switch ($filterLevel) {
                case 'user':
                    return $func['is_user'];
                case 'php':
                    return $func['is_user'] || !$this->isInternalFunction($func['name']);
                case 'all':
                    return true;
            }
            
            return false;
        });
    }

    private function isInternalFunction(string $name): bool
    {
        // Fonctions PHP internes n'ont pas de namespace
        return !str_contains($name, '\\') && !str_contains($name, '::');
    }

    private function formatFunctionName(string $name): string
    {
        // Raccourcir les noms trop longs
        if (strlen($name) > 60) {
            // Garder le début et la fin
            $parts = explode('\\', $name);
            if (count($parts) > 3) {
                return $parts[0] . '\\...\\' . end($parts);
            }
        }
        
        return $name;
    }

    private function prepareContext($shell): array
    {
        // Récupérer toutes les variables du contexte actuel
        $variables = $shell->getScopeVariables();
        
        // Exclure les variables spéciales de PsySH
        $excludedVars = ['this', '_', '_e'];
        $variables = array_diff_key($variables, array_flip($excludedVars));
        
        // Récupérer les fichiers inclus
        $includes = $shell->getIncludes();
        
        return [
            'variables' => $variables,
            'includes' => $includes,
        ];
    }

    private function filterProfileData(array $data, bool $showAll = false): array
    {
        $filtered = [];
        
        foreach ($data as $parentChild => $metrics) {
            // Séparer parent et enfant - gérer le cas où il n'y a pas de '==>'
            if (str_contains($parentChild, '==>')) {
                [$parent, $child] = explode('==>', $parentChild, 2);
            } else {
                $parent = null;
                $child = $parentChild;
            }
            
            // Ignorer les clés vides ou nulles
            if (empty($child) || $child === null) {
                continue;
            }
            
            // Toujours ignorer certaines fonctions internes
            if (in_array($child, self::IGNORED_FUNCTIONS)) {
                continue;
            }
            
            // En mode non-full, appliquer des filtres intelligents
            if (!$showAll) {
                // Ignorer les fonctions PsySH
                $isPsyshFunction = false;
                foreach (self::PSYSH_NAMESPACES as $namespace) {
                    if (str_starts_with((string)$child, $namespace)) {
                        $isPsyshFunction = true;
                        break;
                    }
                }
                if ($isPsyshFunction) {
                    continue;
                }
                
                // Ignorer seulement les fonctions internes appelées par le système de profilage,
                // pas celles appelées par le code utilisateur
                if ($this->isProfilingSystemCall($parent, $child)) {
                    continue;
                }
            }
            
            $filtered[$child] = [
                'calls' => $metrics['ct'] ?? 0,
                'time' => ($metrics['wt'] ?? 0), // Garder en microsecondes
                'memory' => $metrics['mu'] ?? 0,
                'peak_memory' => $metrics['pmu'] ?? 0,
                'cpu_time' => ($metrics['cpu'] ?? 0),
            ];
        }
        
        return $this->enhanceWithCallGraph($filtered);
    }

    private function isProfilingSystemCall(?string $parent, string $child): bool
    {
        // Liste des fonctions internes au profilage
        $profilingFunctions = [
            'Psy\\Command\\ProfileCommand::execute',
            'Psy\\Command\\ProfileCommand::displayResults',
            'Psy\\Command\\ProfileCommand::filterProfileData',
            'Psy\\Command\\ProfileCommand::enhanceWithCallGraph',
            'Psy\\Command\\ProfileCommand::filterFunctions',
        ];

        if (in_array($parent, $profilingFunctions)) {
            // Si le parent est une fonction de profilage, on ignore l'enfant
            return true;
        }

        // Ignorer les appels à `eval` qui viennent de notre commande
        if ($child === 'eval' && $parent !== null && str_starts_with($parent, 'Psy\\Command\\ProfileCommand')) {
            return true;
        }

        return false;
    }

    private function extractFunctionParams(string $functionName): string
    {
        // Tenter d'extraire les paramètres de la fonction
        if (preg_match('/(?<name>.*?)(?:\((?<params>.*?)\))?$/', $functionName, $matches)) {
            return $matches['params'] ?? '';
        }
        return '';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function saveProfileData(array $data, string $outFile, OutputInterface $output): void
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);
        if (file_put_contents($outFile, $jsonData) !== false) {
            $output->writeln(sprintf('<info>Profile data saved to: %s</info>', $outFile));
        } else {
            $output->writeln('<error>Failed to save profile data</error>');
        }
    }
}