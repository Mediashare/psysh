<?php

namespace Psy\Command;

use Psy\Input\CodeArgument;
use Psy\Exception\RuntimeException;
use Psy\Profiling\ProfilerEngine;
use Psy\Profiling\XhprofEngine;
use Psy\Profiling\XdebugInProcessEngine;
use Psy\Profiling\XdebugSubprocessEngine;
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
                new InputOption('filter', '', InputOption::VALUE_REQUIRED, 'Filter level: user (default), php, all', 'user'),
                new InputOption('threshold', '', InputOption::VALUE_REQUIRED, 'Minimum time threshold in microseconds', 0),
                new InputOption('show-params', '', InputOption::VALUE_NONE, 'Show function parameters in profiling results.'),
                new InputOption('full-namespaces', '', InputOption::VALUE_NONE, 'Show complete namespaces without truncation.'),
                new InputOption('debug', '', InputOption::VALUE_NONE, 'Show debug information about profiling execution'),
                new InputOption('engine', null, InputOption::VALUE_REQUIRED, 'The profiling engine to use (auto, xhprof, xdebug, xdebug-subprocess).', 'auto'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to profile.'),
            ])
            ->setDescription('Profile a string of PHP code and display the execution summary.')
            ->setHelp(
                <<<'HELP'
Profile a string of PHP code and display the execution summary.

Filter levels (--filter):
  user (default): Shows only your code and project dependencies
  php:            Shows your code + PHP native functions (array_sum, md5, etc.)
  all:            Shows everything including PsySH internal functions

Options:
  --threshold N:  Only show functions taking more than N microseconds
  --out FILE:     Export profiling data to JSON file
  --show-params:  Display function parameters in results
  --debug:        Show detailed profiling information

Examples:
  profile $calc->toBinary(1000)
  profile --filter=php $service->process($data)
  profile --filter=all --debug complex_operation()
  profile --threshold=100 --out=profile.json my_function()
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('code');
        if (strpos($code, '@') === 0) {
            $filePath = substr($code, 1);
            if (!file_exists($filePath)) {
                throw new RuntimeException(sprintf('File not found: %s', $filePath));
            }
            $code = file_get_contents($filePath);
        }

        // Remove surrounding quotes if present (from shell parsing)
        $code = trim($code);
        if ((str_starts_with($code, '"') && str_ends_with($code, '"')) ||
            (str_starts_with($code, "'") && str_ends_with($code, "'"))) {
            $code = substr($code, 1, -1);
        }

        // Parse options
        $code = $this->normalizeInlineCode($code);
        $outFile = $input->getOption('out');
        $filterLevel = $input->getOption('filter');
        $threshold = max(0, (int) $input->getOption('threshold'));
        $showParams = $input->getOption('show-params');
        $fullNamespaces = $input->getOption('full-namespaces');
        $debug = $input->getOption('debug');

        // Validate filter level
        if (!in_array($filterLevel, ['user', 'php', 'all'], true)) {
            throw new RuntimeException(sprintf(
                'Invalid filter level "%s". Valid options are: user, php, all',
                $filterLevel
            ));
        }

        $engineName = $input->getOption('engine');
        if (!in_array($engineName, ['auto', 'xhprof', 'xdebug', 'xdebug-subprocess'], true)) {
            throw new RuntimeException(sprintf(
                'Invalid engine name "%s". Valid options are: auto, xhprof, xdebug, xdebug-subprocess',
                $engineName
            ));
        }

        $shell = $this->getShell();

        if ($debug) {
            $output->writeln('');
            $output->writeln('<info>=== DEBUG MODE ENABLED ===</info>');
            $output->writeln('');
            $output->writeln('<comment>Options Configuration:</comment>');
            $filterDesc = match ($filterLevel) {
                'user' => '(user code only)',
                'php' => '(user code + PHP native functions)',
                'all' => '(all functions including PsySH internal)',
                default => ''
            };
            $output->writeln(sprintf('  • Filter level:      <info>%s</info> %s', $filterLevel, $filterDesc));
            $output->writeln(sprintf('  • Time threshold:    <info>%d μs</info>', $threshold));
            $output->writeln(sprintf('  • Show parameters:   <info>%s</info>', $showParams ? 'yes' : 'no'));
            $output->writeln(sprintf('  • Full namespaces:   <info>%s</info>', $fullNamespaces ? 'yes' : 'no'));
            $output->writeln('');
        }

        // Select engine
        try {
            $engine = $this->selectEngine($engineName, $debug, $output);
        } catch (RuntimeException $e) {
            throw new RuntimeException(
                'Profiling not available: ' . $e->getMessage() . "\n\n" .
                "Available options:\n" .
                "1. Install XHProf: pecl install xhprof\n" .
                "2. Enable Xdebug trace mode: XDEBUG_MODE=trace,develop php ...\n" .
                "3. Use subprocess engine (enabled automatically when available)\n\n" .
                "Current Xdebug mode: " . (extension_loaded('xdebug') ? ini_get('xdebug.mode') : 'not loaded')
            );
        }

        if ($debug) {
            $output->writeln('<comment>Profiling Engine:</comment>');
            $output->writeln(sprintf('  • Engine type:       <info>%s</info>', $engine->getName()));
            if ($engine->getName() === 'XHProf') {
                $output->writeln('  • Mode:              <info>In-process profiling</info>');
                $output->writeln('  • Features:          <info>High performance, low overhead</info>');
            } elseif ($engine->getName() === 'Xdebug Subprocess') {
                $output->writeln('  • Mode:              <info>Subprocess with trace mode</info>');
                $output->writeln('  • Features:          <info>Complete call tracing, all functions captured</info>');
            } elseif ($engine->getName() === 'Xdebug') {
                $output->writeln('  • Mode:              <info>In-process with trace mode</info>');
                $output->writeln('  • Features:          <info>Standard tracing within current process</info>');
            }
            $output->writeln('');
        }

        // Execute profiling
        try {
            $profileData = $engine->profile($code, $shell, $debug);
            if ($debug) {
                $output->writeln('<comment>Profiling Execution:</comment>');
                $output->writeln(sprintf('  • Status:            <info>Completed successfully</info>'));
                $output->writeln(sprintf('  • Functions captured: <info>%d</info>', count($profileData)));

                // Count function types
                $userFunctions = array_filter($profileData, fn($f) => $f['is_user'] ?? false);
                $internalFunctions = array_filter($profileData, fn($f) => !($f['is_user'] ?? true));
                $output->writeln(sprintf('  • User functions:    <info>%d</info>', count($userFunctions)));
                $output->writeln(sprintf('  • PHP native funcs:  <info>%d</info>', count($internalFunctions)));
                $output->writeln('');
            }
        } catch (RuntimeException $e) {
            $this->displayProfilingError($e, $code, $output);
            return 1;
        }

        if (empty($profileData)) {
            $output->writeln('<warning>No profiling data collected</warning>');
            $output->writeln('');
            $output->writeln('<comment>This may happen because:</comment>');
            $output->writeln('<comment>1. The code executed too quickly (try more complex code)</comment>');
            $output->writeln('<comment>2. Xdebug trace mode is not properly enabled</comment>');
            $output->writeln('<comment>3. The profiler is filtering out all functions</comment>');
            $output->writeln('');
            $output->writeln('<info>Try running with --debug flag for more information:</info>');
            $output->writeln('<info>  profile --debug your_code()</info>');
            return 0;
        }

        $filteredData = $this->filterFunctions($profileData, $filterLevel, $threshold);

        // Display results
        $this->displayResults($profileData, $filteredData, $output, $filterLevel, $threshold, $showParams, $fullNamespaces);

        // Save if requested
        if ($outFile) {
            $this->saveProfileData($profileData, $outFile, $output);
        }

        return 0;
    }

    /**
     * Select the best available profiling engine.
     */
    private function selectEngine(string $engineName, bool $debug, OutputInterface $output): ProfilerEngine
    {
        if ($engineName === 'auto') {
            // 1. If XhprofEngine::isAvailable(): use XhprofEngine (in-process, preferred)
            if (XhprofEngine::isAvailable()) {
                if ($debug) {
                    $output->writeln('<comment>Using XHProf (in-process, high performance)</comment>');
                }
                return new XhprofEngine();
            }

            // 2. Else if XdebugInProcessEngine::isAvailable(): use XdebugInProcessEngine
            if (XdebugInProcessEngine::isAvailable()) {
                if ($debug) {
                    $output->writeln('<comment>Using Xdebug in-process tracing</comment>');
                }
                return new XdebugInProcessEngine();
            }

            // 3. Else if XdebugSubprocessEngine::isAvailable(): use XdebugSubprocessEngine
            if (XdebugSubprocessEngine::isAvailable()) {
                if ($debug) {
                    $output->writeln('<comment>Using Xdebug subprocess mode (captures all functions)</comment>');
                }
                return new XdebugSubprocessEngine();
            }
        } elseif ($engineName === 'xhprof') {
            if (!XhprofEngine::isAvailable()) {
                throw new RuntimeException('XHProf engine is not available. Please install the xhprof extension.');
            }
            if ($debug) {
                $output->writeln('<comment>Using XHProf (in-process, high performance)</comment>');
            }
            return new XhprofEngine();
        } elseif ($engineName === 'xdebug') {
            if (!XdebugInProcessEngine::isAvailable()) {
                throw new RuntimeException('Xdebug in-process engine is not available. Check your Xdebug mode.');
            }
            if ($debug) {
                $output->writeln('<comment>Using Xdebug in-process tracing</comment>');
            }
            return new XdebugInProcessEngine();
        } elseif ($engineName === 'xdebug-subprocess') {
            if (!XdebugSubprocessEngine::isAvailable()) {
                throw new RuntimeException('Xdebug subprocess engine is not available. Check if Xdebug is loaded.');
            }
            if ($debug) {
                $output->writeln('<comment>Using Xdebug subprocess mode (captures all functions)</comment>');
            }
            return new XdebugSubprocessEngine();
        }

        // 4. Else: throw RuntimeException with detailed diagnostics
        $diagnostics = $this->getDiagnostics();
        throw new RuntimeException('No suitable profiling engine found.' . "\n\n" . $diagnostics);
    }

    private function normalizeInlineCode(string $code): string
    {
        $trimmed = rtrim($code);
        if ($trimmed === '') {
            return $code;
        }
        $last = substr($trimmed, -1);
        if ($last !== ';' && $last !== '}' && $last !== ':') {
            return $trimmed . ';';
        }
        return $trimmed;
    }

    private function displayResults(array $data, array $filtered, OutputInterface $output, string $filterLevel, int $threshold, bool $showParams = false, bool $fullNamespaces = false): void
    {
        // Debug pour voir ce qui est filtré
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $output->writeln(sprintf('<comment>Filter level: %s, Original: %d functions, Filtered: %d functions</comment>',
                $filterLevel, count($data), count($filtered)));
        }

        // Debug info for options
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $output->writeln(sprintf('<comment>Options: showParams=%s, fullNamespaces=%s</comment>',
                $showParams ? 'true' : 'false', $fullNamespaces ? 'true' : 'false'));
        }

        // Si pas de données après filtrage, essayer sans le threshold mais en respectant le filtre de niveau
        if (empty($filtered)) {
            // Ré-essayer le filtrage sans le threshold (threshold = 0)
            $filteredWithNoThreshold = $this->filterFunctions($data, $filterLevel, 0);

            if (!empty($filteredWithNoThreshold)) {
                // Il y avait des données mais elles ne dépassaient pas le threshold
                $filtered = array_slice($filteredWithNoThreshold, 0, 5); // Top 5
                $output->writeln(sprintf('<comment>No functions exceeded the %d μs threshold. Showing top functions:</comment>', $threshold));
            } else {
                // Aucune fonction ne correspond au filtre (ex: code utilisateur seul mais aucune fonction utilisateur appelée)
                $filterMessage = match ($filterLevel) {
                    'user' => 'No user-defined functions were called. Use --filter=php to see PHP native functions.',
                    'php' => 'No user or PHP native functions were called.',
                    'all' => 'No functions were profiled.',
                    default => 'No functions match the filter criteria.',
                };
                $output->writeln(sprintf('<comment>%s</comment>', $filterMessage));

                // Afficher quand même le résumé total
                $totalTime = array_sum(array_column($data, 'time'));
                $totalMemory = array_sum(array_column($data, 'memory'));

                $output->writeln(sprintf(
                    "\n<comment>Total execution: Time: %s, Memory: %s</comment>",
                    $this->formatTime($totalTime),
                    $this->formatMemory($totalMemory)
                ));
                return;
            }
        }

        // Organiser les données de manière hiérarchique
        $hierarchy = $this->buildCallHierarchy($filtered);

        // Trier par temps décroissant
        uasort($hierarchy, fn($a, $b) => $b['time'] <=> $a['time']);

        // Build the appropriate display title based on filter level
        $displayTitle = match ($filterLevel) {
            'user' => 'user code only',
            'php' => 'user code + PHP native functions',
            'all' => 'all functions (including PsySH internal)',
            default => $filterLevel,
        };

        $output->writeln(sprintf(
            "\n<info>📊 Profiling results (%s):</info>",
            $displayTitle
        ));
        $output->writeln('');

        // Détecter la largeur du terminal
        $terminalWidth = $this->getTerminalWidth();
        $maxFunctionWidth = $showParams ? 30 : 50;
        $maxParamWidth = max(20, $terminalWidth - 80); // Largeur dynamique pour les params

        // Afficher le tableau avec hiérarchie
        $table = new Table($output);
        $headers = ['Function Call', 'Calls', 'Time', 'Time %', 'Memory', 'Memory %'];
        if ($showParams) {
            $headers[] = 'Parameters';
        }
        $table->setHeaders($headers);

        $rowCount = 0;
        $maxRows = 20;

        foreach ($hierarchy as $name => $func) {
            if ($rowCount >= $maxRows) {
                break;
            }

            // Afficher la fonction parent
            $displayName = $fullNamespaces ? $name : $this->formatFunctionName($name);
            $displayName = $this->truncateString($displayName, $maxFunctionWidth);

            $row = [
                $displayName,
                $func['calls'],
                $this->formatTime($func['time']),
                number_format($func['time_percent'], 1) . '%',
                $this->formatMemory($func['memory']),
                $this->formatMemoryPercent($func['memory_percent']),
            ];

            if ($showParams) {
                $params = $this->formatFunctionParams($name, $func);
                $params = $this->truncateString($params ?: 'N/A', $maxParamWidth);
                $row[] = $params;
            }

            $table->addRow($row);
            $rowCount++;

            // Afficher les appels enfants avec indentation
            if (!empty($func['children'])) {
                $childCount = count($func['children']);
                $childIndex = 0;

                foreach ($func['children'] as $childName => $childFunc) {
                    if ($rowCount >= $maxRows) {
                        break;
                    }

                    $childIndex++;
                    $isLast = $childIndex === $childCount;
                    $prefix = $isLast ? '  └─ ' : '  ├─ ';

                    $childDisplayName = $fullNamespaces ? $childName : $this->formatFunctionName($childName);
                    $childDisplayName = $this->truncateString($childDisplayName, $maxFunctionWidth - 5);

                    $row = [
                        $prefix . '<fg=cyan>' . $childDisplayName . '</>',
                        $childFunc['calls'],
                        $this->formatTime($childFunc['time']),
                        number_format($childFunc['time_percent'], 1) . '%',
                        $this->formatMemory($childFunc['memory']),
                        $this->formatMemoryPercent($childFunc['memory_percent']),
                    ];

                    if ($showParams) {
                        $params = $this->formatFunctionParams($childName, $childFunc);
                        $params = $this->truncateString($params ?: 'N/A', $maxParamWidth);
                        $row[] = $params;
                    }

                    $table->addRow($row);
                    $rowCount++;
                }
            }
        }

        $table->render();

        // Résumé
        $totalTime = array_sum(array_column($filtered, 'time')); // en microsecondes
        $totalMemory = array_sum(array_column($filtered, 'memory')); // en bytes

        $output->writeln('');
        $output->writeln(sprintf(
            "<comment>⏱  Total execution: %s  |  💾 Memory: %s</comment>",
            $this->formatTime($totalTime),
            $this->formatMemory($totalMemory)
        ));
    }

    /**
     * Organise les fonctions en hiérarchie parent/enfant
     */
    private function buildCallHierarchy(array $functions): array
    {
        $hierarchy = [];
        $children = [];

        // Séparer les parents et enfants
        foreach ($functions as $name => $func) {
            if (str_contains($name, '==>')) {
                [$parent, $child] = explode('==>', $name, 2);
                if (!isset($children[$parent])) {
                    $children[$parent] = [];
                }
                $children[$parent][$child] = $func;
            } else {
                // Fonction racine
                if (!isset($hierarchy[$name])) {
                    $hierarchy[$name] = $func;
                    $hierarchy[$name]['children'] = [];
                }
            }
        }

        // Attacher les enfants aux parents
        foreach ($children as $parent => $childList) {
            if (isset($hierarchy[$parent])) {
                $hierarchy[$parent]['children'] = $childList;
            } else {
                // Si le parent n'est pas dans la hiérarchie, l'ajouter
                // Note: On ne peut pas sommer les pourcentages, on utilise donc les valeurs par défaut
                // Les pourcentages seront incorrects pour les parents synthétiques
                $hierarchy[$parent] = [
                    'calls' => 0,
                    'time' => array_sum(array_column($childList, 'time')),
                    'memory' => array_sum(array_column($childList, 'memory')),
                    'time_percent' => 0.0,  // Sera recalculé si nécessaire
                    'memory_percent' => 0.0,  // Sera recalculé si nécessaire
                    'children' => $childList,
                    'is_user' => true,
                    'params' => [],
                ];
            }
        }

        return $hierarchy;
    }

    private function filterFunctions(array $functions, string $filterLevel, int $threshold): array
    {
        return array_filter($functions, function($func, $name) use ($filterLevel, $threshold) {
            // Filter by time threshold
            if ($func['time'] < $threshold) {
                return false;
            }

            $childName = str_contains($name, '==>') ? explode('==>', $name)[1] : $name;

            // Filter by level
            switch ($filterLevel) {
                case 'user':
                    // Show only user-defined functions and methods.
                    return $func['is_user'];
                case 'php':
                    // Show user code + all native PHP functions.
                    return $func['is_user'] || $this->isInternalFunction($childName);
                case 'all':
                    return true;
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function isInternalFunction(string $name): bool
    {
        // Methods and namespaced functions are not considered internal built-in functions.
        if (str_contains($name, '::') || str_contains($name, '\\')) {
            return false;
        }

        // Check if it's a built-in PHP function using reflection.
        if (!function_exists($name)) {
            return false;
        }

        try {
            $reflection = new \ReflectionFunction($name);
            return $reflection->isInternal();
        } catch (\ReflectionException $e) {
            return false;
        }
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



    private function formatFunctionName(string $name): string
    {
        // Raccourcir les noms trop longs seulement si pas en mode full-namespaces
        if (strlen($name) > 80) {
            // Garder le début et la fin pour les très longs noms
            $parts = explode('\\', $name);
            if (count($parts) > 3) {
                return $parts[0] . '\\...\\' . end($parts);
            }
        }
        
        // Raccourcir les namespaces communs
        $shortcuts = [
            'Psy\\ExecutionClosure::' => 'ExecutionClosure::',
            'Psy\\Command\\' => 'Command::',
            '{closure:' => '{closure:',
        ];
        
        foreach ($shortcuts as $long => $short) {
            if (str_starts_with($name, $long)) {
                return str_replace($long, $short, $name);
            }
        }
        
        return $name;
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
            
            // En mode non-full, ignorer certaines fonctions internes
            if (!$showAll && in_array($child, self::IGNORED_FUNCTIONS)) {
                continue;
            }
            
            // En mode non-full, appliquer des filtres intelligents
            if (!$showAll) {
                // Ignorer complètement les appels système de PsySH
                if ($this->isPsyshSystemCall($parent, $child)) {
                    continue;
                }
                
                // Ignorer les fonctions PsySH sauf si appelées depuis du code utilisateur
                $isPsyshFunction = false;
                foreach (self::PSYSH_NAMESPACES as $namespace) {
                    if (str_starts_with((string)$child, $namespace)) {
                        $isPsyshFunction = true;
                        break;
                    }
                }
                
                if ($isPsyshFunction && !$this->isUserCodeContext($parent)) {
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


    /**
     * Obtenir la largeur du terminal.
     */
    private function getTerminalWidth(): int
    {
        // Essayer de détecter la largeur du terminal
        if (function_exists('exec')) {
            $output = [];
            @exec('tput cols 2>/dev/null', $output);
            if (!empty($output[0]) && is_numeric($output[0])) {
                return (int) $output[0];
            }
        }

        // Valeur par défaut
        return 120;
    }

    /**
     * Tronquer une chaîne avec ellipse.
     */
    private function truncateString(string $str, int $maxLength): string
    {
        if (mb_strlen($str) <= $maxLength) {
            return $str;
        }

        return mb_substr($str, 0, $maxLength - 3) . '...';
    }

    /**
     * Formater le pourcentage de mémoire avec maximum 3 décimales.
     */
    private function formatMemoryPercent(float $percent): string
    {
        // Si le pourcentage est invalide (0 ou > 100), afficher N/A
        if ($percent <= 0 || $percent > 100) {
            return 'N/A';
        }

        if ($percent >= 10) {
            return number_format($percent, 2) . '%';
        } else {
            return number_format($percent, 3) . '%';
        }
    }

    /**
     * Format function parameters for display.
     * Uses actual values from profiling data if available, otherwise uses signature.
     */
    private function formatFunctionParams(string $functionName, array $functionData): string
    {
        // If we have actual parameter values from the profiler, use them
        if (!empty($functionData['params'])) {
            return $this->formatParamValues($functionData['params']);
        }

        // Fallback: use function signature
        return $this->extractFunctionSignature($functionName);
    }

    /**
     * Format parameter values captured by the profiler.
     */
    private function formatParamValues(array $params): string
    {
        if (empty($params)) {
            return '';
        }

        // Filter and format params
        $formattedParams = [];
        foreach ($params as $param) {
            $param = trim($param);

            // Skip empty params
            if ($param === '') {
                continue;
            }

            // Skip file paths - more comprehensive check
            // Match: /path/to/file.php or /path/to/file:123
            if (preg_match('#^[/\\\\].*[/\\\\]#', $param) ||
                preg_match('#^[a-zA-Z]:[/\\\\]#', $param) ||
                preg_match('#:\d+$#', $param)) {
                continue;
            }

            // Clean up quoted strings
            if (preg_match('/^["\'](.+)["\']$/', $param, $matches)) {
                $param = $matches[1];
            }

            // Handle array notation with full content
            if (preg_match('/^\[(.+)\]$/', $param, $matches)) {
                // Array with content: [0 => 1, 1 => 2, ...]
                $content = $matches[1];
                if (strlen($content) > 50) {
                    $param = 'array(' . substr($content, 0, 47) . '...)';
                } else {
                    $param = 'array(' . $content . ')';
                }
            } elseif (preg_match('/^array\((\d+)\)$/', $param, $matches)) {
                // Simple array notation: array(100)
                $param = "array({$matches[1]} items)";
            }

            // Handle object notation
            if (preg_match('/^class\s+(.+)$/', $param, $matches)) {
                $param = $matches[1];
            }

            $formattedParams[] = $param;
        }

        if (empty($formattedParams)) {
            return '';
        }

        return implode(', ', $formattedParams);
    }

    /**
     * Extract function signature using reflection (fallback).
     */
    private function extractFunctionSignature(string $functionName): string
    {
        // Pour les fonctions avec paramètres capturés par XHProf/Xdebug
        if (preg_match('/(?<name>.*?)\((?<params>.*?)\)$/', $functionName, $matches)) {
            return $matches['params'] ?? '';
        }

        // Extraire le nom de fonction réel (sans namespace)
        $cleanName = $functionName;
        if (str_contains($functionName, '::')) {
            $parts = explode('::', $functionName);
            $cleanName = end($parts);
        } elseif (str_contains($functionName, '\\')) {
            $parts = explode('\\', $functionName);
            $cleanName = end($parts);
        }

        // Pour les fonctions PHP natives, essayer de récupérer la signature
        if (function_exists($cleanName)) {
            try {
                $reflection = new \ReflectionFunction($cleanName);
                $params = [];
                foreach ($reflection->getParameters() as $param) {
                    $paramStr = '$' . $param->getName();
                    if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                        try {
                            $default = $param->getDefaultValue();
                            if (is_string($default)) {
                                $paramStr .= '="' . addslashes($default) . '"';
                            } elseif (is_bool($default)) {
                                $paramStr .= '=' . ($default ? 'true' : 'false');
                            } elseif (is_null($default)) {
                                $paramStr .= '=null';
                            } else {
                                $paramStr .= '=' . $default;
                            }
                        } catch (\ReflectionException $e) {
                            $paramStr .= '=?';
                        }
                    }
                    $params[] = $paramStr;
                }
                return implode(', ', $params);
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }

        // For closures and user functions, provide basic info
        if (str_contains($functionName, 'closure')) {
            return 'closure params';
        }

        return '';
    }

    
    private function normalizeProfile(array $profile, bool $showAll): array
    {
        // Cas XHProf: entrées de type parent==>child avec clés ct/wt/mu
        $isXhprof = false;
        if (!empty($profile)) {
            $first = reset($profile);
            if (is_array($first) && (array_key_exists('ct', $first) || array_key_exists('wt', $first))) {
                $isXhprof = true;
            }
        }

        if ($isXhprof) {
            return $this->filterProfileData($profile, $showAll);
        }

        // Déjà agrégé (Xdebug parser): s'assurer des pourcentages
        $hasPercents = false;
        if (!empty($profile)) {
            $first = reset($profile);
            $hasPercents = is_array($first) && array_key_exists('time_percent', $first);
        }

        return $hasPercents ? $profile : $this->enhanceWithCallGraph($profile);
    }
    
    /**
     * Formate le temps d'exécution de manière adaptative selon l'ordre de grandeur
     * 
     * @param int $microseconds Temps en microsecondes
     * @return string Temps formaté avec l'unité appropriée
     */
    private function formatTime(int $microseconds): string
    {
        if ($microseconds == 0) {
            return '0 μs';
        }
        
        // Définir les seuils et unités (du plus grand au plus petit)
        $units = [
            ['threshold' => 60000000, 'divisor' => 60000000, 'unit' => 'min', 'decimals' => 2], // >= 1 minute (60s * 1M μs)
            ['threshold' => 1000000, 'divisor' => 1000000, 'unit' => 's', 'decimals' => 2],     // >= 1s (1M μs)
            ['threshold' => 1000, 'divisor' => 1000, 'unit' => 'ms', 'decimals' => 1],         // >= 1ms (1K μs)
            ['threshold' => 0, 'divisor' => 1, 'unit' => 'μs', 'decimals' => 0],               // < 1ms
        ];
        
        foreach ($units as $config) {
            if ($microseconds >= $config['threshold']) {
                $value = $microseconds / $config['divisor'];
                $formatted = number_format($value, $config['decimals']);
                
                // Supprimer les zéros inutiles après la virgule
                if ($config['decimals'] > 0) {
                    $formatted = rtrim($formatted, '0');
                    $formatted = rtrim($formatted, '.');
                }
                
                return $formatted . ' ' . $config['unit'];
            }
        }
        
        return $microseconds . ' μs';
    }
    
    /**
     * Formate la mémoire de manière adaptative selon l'ordre de grandeur
     * 
     * @param int $bytes Mémoire en bytes
     * @return string Mémoire formatée avec l'unité appropriée
     */
    private function formatMemory(int $bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }
        
        if ($bytes < 0) {
            return '-' . $this->formatMemory(-$bytes);
        }
        
        // Définir les seuils et unités pour la mémoire
        $units = [
            ['threshold' => 1073741824, 'divisor' => 1073741824, 'unit' => 'GB', 'decimals' => 2], // >= 1GB
            ['threshold' => 1048576, 'divisor' => 1048576, 'unit' => 'MB', 'decimals' => 2],       // >= 1MB
            ['threshold' => 1024, 'divisor' => 1024, 'unit' => 'KB', 'decimals' => 1],             // >= 1KB
            ['threshold' => 0, 'divisor' => 1, 'unit' => 'B', 'decimals' => 0],                    // < 1KB
        ];
        
        foreach ($units as $config) {
            if ($bytes >= $config['threshold']) {
                $value = $bytes / $config['divisor'];
                $formatted = number_format($value, $config['decimals']);
                
                // Supprimer les zéros inutiles après la virgule
                if ($config['decimals'] > 0) {
                    $formatted = rtrim($formatted, '0');
                    $formatted = rtrim($formatted, '.');
                }
                
                return $formatted . ' ' . $config['unit'];
            }
        }
        
        return $bytes . ' B';
    }

    private function saveProfileData(array $data, string $outFile, OutputInterface $output): void
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);
        if (file_put_contents($outFile, $jsonData) !== false) {
            $output->writeln(sprintf('<info>Profile data saved to: %s</info>', $outFile));
        } else {
            $output->writeln('<error>failed to save profile data</error>');
        }
    }
    
    private function isPsyshSystemCall(?string $parent, string $child): bool
    {
        // Fonctions système de PsySH à toujours ignorer
        $systemFunctions = [
            'Psy\\Shell::handleInput',
            'Psy\\Shell::execute', 
            'Psy\\Shell::getLastException',
            'Psy\\ExecutionClosure::execute',
            'Psy\\Command\\Command::run',
            'eval', // quand appelé par PsySH
        ];
        
        // Ignorer les polyfills Symfony (toujours, car ce sont des détails d'implémentation)
        if (str_starts_with($child, 'Symfony\\Polyfill\\')) {
            return true;
        }
        
        // Ignorer les fonctions système de PsySH
        if (in_array($child, $systemFunctions)) {
            return true;
        }
        
        // Ignorer SEULEMENT les appels qui viennent directement de ProfileCommand
        // Cela permet de filtrer le post-processing tout en gardant les appels utilisateur
        if ($parent && str_starts_with($parent, 'Psy\\Command\\ProfileCommand::')) {
            return true;
        }
        
        // Ignorer les appels internes de formatage qui viennent après l'exécution
        $profileCommandMethods = [
            'Psy\\Command\\ProfileCommand::displayResults',
            'Psy\\Command\\ProfileCommand::filterProfileData', 
            'Psy\\Command\\ProfileCommand::filterFunctions',
            'Psy\\Command\\ProfileCommand::formatFunctionName',
            'Psy\\Command\\ProfileCommand::formatTime',
            'Psy\\Command\\ProfileCommand::formatMemory',
            'Psy\\Command\\ProfileCommand::saveProfileData',
            'Psy\\Command\\ProfileCommand::enhanceWithCallGraph',
        ];
        
        if ($parent && in_array($parent, $profileCommandMethods)) {
            return true;
        }
        
        // Ignorer les appels qui viennent des systèmes internes de PsySH (historique, formatage, etc.)
        $psyshInternalParents = [
            'Psy\\Shell::addCodeBufferToHistory',  // array_filter, implode
            'Psy\\Shell::onExecute',               // formatage console
            'Psy\\Shell::writeStdout',             // gestion sortie
            'Psy\\Shell::flushCode',               // nettoyage code
            'Psy\\Context::getAll',                // contexte variables
            'Psy\\Context::getSpecialVariables',   // variables spéciales
            'Symfony\\Component\\Console\\Formatter\\OutputFormatter::escape', // preg_replace
            'Symfony\\Component\\Console\\Formatter\\OutputFormatter::escapeTrailingBackslash',
        ];
        
        if ($parent && in_array($parent, $psyshInternalParents)) {
            return true;
        }
        
        // Ignorer les appels qui viennent des namespaces système de Symfony Console
        if ($parent && str_starts_with($parent, 'Symfony\\Component\\Console\\')) {
            return true;
        }
        
        return false;
    }

    private function isUserCodeContext(?string $parent): bool
    {
        if (!$parent) {
            return true; // Code au niveau racine = utilisateur
        }
        
        // Si le parent n'est pas une fonction PsySH, c'est du code utilisateur
        foreach (self::PSYSH_NAMESPACES as $namespace) {
            if (str_starts_with($parent, $namespace)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get diagnostic information about available profiling engines.
     */
    private function getDiagnostics(): string
    {
        $lines = ['Diagnostic information:'];
        
        // Check Xdebug
        if (extension_loaded('xdebug')) {
            $lines[] = '✓ Xdebug extension is loaded';
            $mode = ini_get('xdebug.mode');
            $lines[] = sprintf('  Current mode: %s', $mode ?: '(none)');
            
            if (!str_contains($mode, 'trace')) {
                $lines[] = '  ⚠️  PROBLEM: Xdebug trace mode is NOT enabled';
                $lines[] = '';
                $lines[] = '  To fix this, restart PHP with trace mode:';
                $lines[] = '    XDEBUG_MODE=trace,develop php your-script.php';
                $lines[] = '  Or for PsySH:';
                $lines[] = '    XDEBUG_MODE=trace,develop ./bin/psysh';
            } else {
                $lines[] = '  ✓ Trace mode is enabled';
            }
            
            // Check if xdebug_start_trace function exists
            if (function_exists('xdebug_start_trace')) {
                $lines[] = '  ✓ xdebug_start_trace() is available';
            } else {
                $lines[] = '  ✗ xdebug_start_trace() is NOT available';
            }
        } else {
            $lines[] = '✗ Xdebug extension is NOT loaded';
        }
        
        $lines[] = '';
        
        // Check XHProf
        if (extension_loaded('xhprof')) {
            $lines[] = '✓ XHProf extension is loaded';
        } else {
            $lines[] = '✗ XHProf extension is NOT loaded';
            $lines[] = '  Install with: pecl install xhprof';
        }
        
        return implode("\n", $lines);
    }

    /**
     * Display a formatted error message when profiling fails.
     *
     * @param RuntimeException $e      The exception thrown
     * @param string           $code   The code that was being profiled
     * @param OutputInterface  $output Output interface
     */
    private function displayProfilingError(RuntimeException $e, string $code, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<error>Profiling execution failed!</error>');
        $output->writeln('');

        // Parse error message to extract line number and file
        $errorMessage = $e->getMessage();
        $errorLine = null;
        $errorFile = null;

        // Try to extract line number from error message
        // Format: "on line XX" or "in file.php:XX"
        if (preg_match('/on line (\d+)/', $errorMessage, $matches)) {
            $errorLine = (int) $matches[1];
        } elseif (preg_match('/:(\d+)/', $errorMessage, $matches)) {
            $errorLine = (int) $matches[1];
        }

        // Extract the actual error message (before "Stack trace:" or "thrown in")
        $shortError = $errorMessage;
        if (preg_match('/^(.*?)(?:Stack trace:|thrown in)/s', $errorMessage, $matches)) {
            $shortError = trim($matches[1]);
        }

        // Display the error
        $output->writeln('<comment>Error:</comment>');
        $output->writeln('  ' . $shortError);
        $output->writeln('');

        // Display the code being profiled with line numbers
        $output->writeln('<comment>Code being profiled:</comment>');
        $codeLines = explode("\n", $code);
        $lineCount = count($codeLines);
        $maxLineNumWidth = strlen((string) $lineCount);

        foreach ($codeLines as $i => $line) {
            $lineNum = $i + 1;
            $prefix = str_pad($lineNum, $maxLineNumWidth, ' ', STR_PAD_LEFT);

            // Highlight the error line if we found it
            if ($errorLine !== null && $lineNum === $errorLine) {
                $output->writeln(sprintf('  <error>→ %s │ %s</error>', $prefix, $line));
            } else {
                $output->writeln(sprintf('    %s │ %s', $prefix, $line));
            }
        }

        $output->writeln('');

        // Provide helpful hints
        $output->writeln('<comment>Common causes:</comment>');

        if (strpos($errorMessage, 'Call to undefined function') !== false) {
            $output->writeln('  • <info>Undefined function:</info> The function or class may not be defined in the profiling context.');
            $output->writeln('    Try defining the class/function in the REPL before profiling.');
        } elseif (strpos($errorMessage, 'Undefined variable') !== false) {
            $output->writeln('  • <info>Undefined variable:</info> Variables from the REPL context may not be available.');
            $output->writeln('    Ensure all variables are defined before profiling.');
        } elseif (strpos($errorMessage, 'syntax error') !== false) {
            $output->writeln('  • <info>Syntax error:</info> Check your code syntax.');
        } else {
            $output->writeln('  • Check that all classes, functions, and variables are properly defined');
            $output->writeln('  • Use --debug flag for more detailed information');
        }

        $output->writeln('');
    }
}