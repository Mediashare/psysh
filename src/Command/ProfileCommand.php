<?php

namespace Psy\Command;

use Psy\Input\CodeArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ProfileCommand extends Command
{
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
                new InputOption('threshold', '', InputOption::VALUE_REQUIRED, 'Minimum time threshold in microseconds', '1000'),
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
        if (!\extension_loaded('xdebug')) {
            $output->writeln('<error>Xdebug extension is not loaded. The profile command is unavailable.</error>');
            return 1;
        }

        $code = $input->getArgument('code');
        $outFile = $input->getOption('out');
        $filterLevel = $input->getOption('full') ? 'all' : $input->getOption('filter');
        $threshold = (int) $input->getOption('threshold');

        // Exécuter avec profilage
        $result = $this->executeWithProfiling($code, $filterLevel, $output);
        
        if (!$result['success']) {
            return 1;
        }

        // Analyser et afficher les résultats
        $this->displayResults($result['data'], $output, $filterLevel, $threshold);

        // Exporter si demandé
        if ($outFile) {
            $this->exportProfile($result['cachegrind'], $outFile, $output);
        }

        return 0;
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
            if (strpos($function, $namespace) === 0) {
                return true;
            }
        }
        
        // Vérifier par le fichier
        if (strpos($file, 'vendor/psy/psysh') !== false) {
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
                'time_ms' => $data['time'] / 1000,
                'memory_kb' => $data['memory'] / 1024,
            ];
        }
        
        return $enhanced;
    }

    private function displayResults(array $data, OutputInterface $output, string $filterLevel, int $threshold): void
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
        $table->setHeaders(['Function', 'Calls', 'Time (ms)', 'Time %', 'Memory (KB)', 'Memory %']);
        
        foreach (array_slice($filtered, 0, 20) as $name => $func) {
            $table->addRow([
                $this->formatFunctionName($name),
                $func['calls'],
                number_format($func['time_ms'], 3),
                number_format($func['time_percent'], 1) . '%',
                number_format($func['memory_kb'], 2),
                number_format($func['memory_percent'], 1) . '%',
            ]);
        }
        
        $output->writeln(sprintf(
            "\n<info>Profiling results (%s):</info>",
            $filterLevel === 'user' ? 'user code only' : $filterLevel
        ));
        
        $table->render();
        
        // Résumé
        $output->writeln(sprintf(
            "\n<comment>Total execution: Time: %.3f ms, Memory: %.2f KB</comment>",
            array_sum(array_column($filtered, 'time_ms')),
            array_sum(array_column($filtered, 'memory_kb'))
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
        return !strpos($name, '\\') && !strpos($name, '::');
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

    private function exportProfile(string $source, string $target, OutputInterface $output): void
    {
        if (copy($source, $target)) {
            $output->writeln(sprintf('<info>Profile data exported to: %s</info>', $target));
        } else {
            $output->writeln('<error>Failed to export profile data</error>');
        }
    }
}