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
                new InputOption('trace-all', '', InputOption::VALUE_NONE, 'Use Xdebug tracing to capture ALL function calls (including strlen, etc.)'),
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

        // Exécuter le code avec profilage isolé
        try {
            $result = $this->executeWithProfiling($code, $filterLevel, $output);
            $success = $result['success'];
            $profile_data = $result['data'];
        } catch (\Throwable $e) {
            $success = false;
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return 1;
        }
        
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

    private function prepareCodeWithContext(string $code): string
    {
        $shell = $this->getShell();
        $context = $this->serializeContext($shell);
        
        // S'assurer que le code se termine par un point-virgule
        $code = rtrim($code);
        if (!str_ends_with($code, ';')) {
            $code = $code . ';';
        }
        
        // Utiliser un heredoc pour éviter les problèmes de parsing avec les accolades
        $fullCode = '
// Vérifier que XHProf est disponible
if (!extension_loaded(\'xhprof\')) {
    echo "XHProf extension is not loaded\n";
    exit(1);
}

// Restaurer le contexte du shell
' . $context . '

// Commencer le profilage XHProf
xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

// Exécuter le code utilisateur
' . $code . '

// Arrêter le profilage et sauvegarder
$profile_data = xhprof_disable();

// Sauvegarder dans un fichier temporaire pour récupération
$tmp_file = sys_get_temp_dir() . \'/psysh_profile_\' . getmypid() . \'.dat\';
file_put_contents($tmp_file, serialize($profile_data));
fprintf(STDERR, "PSYSH_PROFILE_FILE:%s\n", $tmp_file);
';
        
        return $fullCode;
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
        // Préparer le code avec contexte et XHProf
        $fullCode = $this->prepareCodeWithContext($code);
        
        // Exécuter avec PHP -r pour éviter le bruit de PsySH
        $process = new \Symfony\Component\Process\Process([
            PHP_BINARY,
            '-d', 'memory_limit=-1',
            '-r', $fullCode
        ]);
        
        $process->run();
        
        // Vérifier si le process s'est bien exécuté
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur lors de l\'exécution du profiling: ' . $process->getErrorOutput());
        }
        
        // Récupérer le nom du fichier de données depuis stderr
        $errorOutput = $process->getErrorOutput();
        if (preg_match('/PSYSH_PROFILE_FILE:(.+)/', $errorOutput, $matches)) {
            $profileFile = trim($matches[1]);
        } else {
            throw new \RuntimeException('Impossible de trouver le fichier de profiling dans la sortie: ' . $errorOutput);
        }
        
        // Vérifier que le fichier existe
        if (!file_exists($profileFile)) {
            throw new \RuntimeException('Le fichier de profilage n\'a pas été créé: "' . $profileFile . '"');
        }
        
        // Lire les données XHProf
        $profileData = unserialize(file_get_contents($profileFile));
        if ($profileData === false) {
            throw new \RuntimeException('Impossible de lire les données de profilage');
        }
        
        // Nettoyer le fichier temporaire
        unlink($profileFile);
        
        return [
            'success' => true,
            'data' => $profileData
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
                'is_user' => $this->isUserFunction($name),
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
        return array_filter($functions, function($func, $name) use ($filterLevel, $threshold) {
            // Filtre par seuil de temps
            if ($func['time'] < $threshold) {
                return false;
            }
            
            // Filtre par niveau
            switch ($filterLevel) {
                case 'user':
                    // En mode user, inclure le code utilisateur ET les fonctions PHP appelées directement
                    return $func['is_user'] || $this->isDirectlyCalledFunction($name);
                case 'php':
                    return $func['is_user'] || !$this->isInternalFunction($func['name']);
                case 'all':
                    return true;
            }
            
            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function isInternalFunction(string $name): bool
    {
        // Fonctions PHP internes n'ont pas de namespace
        return !str_contains($name, '\\') && !str_contains($name, '::');
    }

    private function isUserFunction(string $name): bool
    {
        // Vérifier si c'est du code PsySH par le namespace
        foreach (self::PSYSH_NAMESPACES as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return false;
            }
        }
        
        // Les fonctions internes PHP ne sont pas du code utilisateur
        if ($this->isInternalFunction($name)) {
            return false;
        }
        
        // Les fonctions eval générées par le script sont du code utilisateur
        if (str_contains($name, 'eval()\'d code')) {
            return true;
        }
        
        // Tout le reste est considéré comme du code utilisateur
        return true;
    }

    private function isDirectlyCalledFunction(string $name): bool
    {
        // Liste des fonctions PHP internes couramment utilisées qu'on veut voir par défaut
        $commonFunctions = [
            'sleep', 'usleep', 'time_nanosleep',
            'strlen', 'substr', 'strpos', 'str_replace',
            'array_map', 'array_filter', 'array_reduce',
            'json_encode', 'json_decode',
            'file_get_contents', 'file_put_contents',
            'curl_exec', 'curl_init',
            'mysqli_query', 'mysql_query',
            'hash', 'hash_hmac',
            'openssl_encrypt', 'openssl_decrypt',
            'preg_match', 'preg_replace',
            'explode', 'implode',
            'count', 'sizeof',
            'microtime', 'gettimeofday'
        ];
        
        return in_array($name, $commonFunctions);
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