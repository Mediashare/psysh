<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Input\ShellInput;
use Psy\Shell;
use Psy\TabCompletion\Matcher\LaravelServiceMatcher;
use Psy\TabCompletion\Matcher\SymfonyParameterMatcher;
use Psy\TabCompletion\Matcher\SymfonyServiceMatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande autoload pour PsySH
 * Détecte automatiquement l'environnement de projet et charge les variables appropriées
 */
class AutoloadCommand extends Command
{
    private $detectedFramework = null;
    private $projectRoot = null;
    private $loadedVariables = [];

    protected function configure()
    {
        $this
            ->setName('autoload')
            ->setDescription('Détecte et charge automatiquement l\'environnement du projet')
            ->addOption(
                'framework',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force un framework spécifique (symfony, laravel, wordpress)'
            )
            ->addOption(
                'no-env',
                null,
                InputOption::VALUE_NONE,
                'Ne charge pas les variables d\'environnement .env'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'Liste les frameworks détectés sans les charger'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int  
    {
        $this->projectRoot = $this->findProjectRoot();
        
        if ($input->getOption('list')) {
            return $this->listDetectedFrameworks($output);
        }

        $framework = $input->getOption('framework') ?: $this->detectFramework();
        
        if (!$framework) {
            $output->writeln('<error>Aucun framework détecté. Utilisez --framework pour forcer.</error>');
            return 1;
        }

        $this->detectedFramework = $framework;
        $output->writeln("<info>Framework détecté: {$framework}</info>");

        // Charge les variables d'environnement
        if (!$input->getOption('no-env')) {
            $this->loadEnvironmentVariables($output);
        }

        // Charge le framework
        $this->loadFramework($framework, $output);

        // Configure l'autocomplétion
        $this->setupTabCompletion();

        $output->writeln('<comment>Environnement chargé avec succès!</comment>');
        $this->displayLoadedVariables($output);

        return 0;
    }

    private function findProjectRoot()
    {
        $current = getcwd();
        
        while ($current !== '/') {
            if (file_exists($current . '/composer.json')) {
                return $current;
            }
            $current = dirname($current);
        }
        
        return getcwd();
    }

    private function detectFramework()
    {
        $detectors = [
            'symfony' => function() {
                return file_exists($this->projectRoot . '/symfony.lock') ||
                       file_exists($this->projectRoot . '/config/bundles.php') ||
                       file_exists($this->projectRoot . '/app/AppKernel.php') ||
                       file_exists($this->projectRoot . '/bin/console');
            },
            'laravel' => function() {
                return file_exists($this->projectRoot . '/artisan') ||
                       file_exists($this->projectRoot . '/bootstrap/app.php');
            },
            'wordpress' => function() {
                return file_exists($this->projectRoot . '/wp-config.php') ||
                       file_exists($this->projectRoot . '/wp-load.php');
            },
            'composer' => function() {
                return file_exists($this->projectRoot . '/composer.json');
            }
        ];

        foreach ($detectors as $framework => $detector) {
            if ($detector()) {
                return $framework;
            }
        }

        return null;
    }

    private function listDetectedFrameworks(OutputInterface $output)
    {
        $frameworks = ['symfony', 'laravel', 'wordpress', 'composer'];
        $detected = [];

        foreach ($frameworks as $framework) {
            $method = 'detect' . ucfirst($framework);
            if (method_exists($this, $method) && $this->$method()) {
                $detected[] = $framework;
            }
        }

        if (empty($detected)) {
            $output->writeln('<error>Aucun framework détecté dans ce répertoire.</error>');
        } else {
            $output->writeln('<info>Frameworks détectés:</info>');
            foreach ($detected as $framework) {
                $output->writeln("  - {$framework}");
            }
        }

        return 0;
    }

    private function loadEnvironmentVariables(OutputInterface $output)
    {
        $envFiles = ['.env.local', '.env', '.env.dist'];
        
        foreach ($envFiles as $envFile) {
            $envPath = $this->projectRoot . '/' . $envFile;
            if (file_exists($envPath)) {
                $this->parseEnvFile($envPath);
                $output->writeln("<comment>Chargé: {$envFile}</comment>");
                break;
            }
        }
    }

    private function parseEnvFile($envPath)
    {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // Commentaire
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    private function loadFramework($framework, OutputInterface $output)
    {
        switch ($framework) {
            case 'symfony':
                $this->loadSymfony($output);
                break;
            case 'laravel':
                $this->loadLaravel($output);
                break;
            case 'wordpress':
                $this->loadWordPress($output);
                break;
            case 'composer':
                $this->loadComposer($output);
                break;
        }
    }

    private function loadSymfony(OutputInterface $output)
    {
        // Charge l'autoloader Composer
        require_once $this->projectRoot . '/vendor/autoload.php';

        // Détermine la structure Symfony (ancienne vs nouvelle)
        if (file_exists($this->projectRoot . '/bin/console')) {
            // Symfony 4+ structure
            $kernelPath = $this->projectRoot . '/src/Kernel.php';
            if (!file_exists($kernelPath)) {
                $kernelPath = $this->projectRoot . '/config/bootstrap.php';
                if (file_exists($kernelPath)) {
                    require_once $kernelPath;
                }
            }
        } else {
            // Symfony 3- structure
            $kernelPath = $this->projectRoot . '/app/AppKernel.php';
            if (file_exists($kernelPath)) {
                require_once $kernelPath;
            }
        }

        try {
            // Essaie de créer le kernel
            $env = $_ENV['APP_ENV'] ?? $_ENV['SYMFONY_ENV'] ?? 'dev';
            $debug = ($_ENV['APP_DEBUG'] ?? $_ENV['SYMFONY_DEBUG'] ?? 'true') === 'true';

            if (class_exists('App\Kernel')) {
                $kernel = new \App\Kernel($env, $debug);
            } elseif (class_exists('AppKernel')) {
                $kernel = new \AppKernel($env, $debug);
            } else {
                throw new \Exception('Kernel class not found');
            }

            $kernel->boot();
            $container = $kernel->getContainer();

            // Variables Symfony disponibles
            $this->loadedVariables['kernel'] = $kernel;
            $this->loadedVariables['container'] = $container;
            $this->loadedVariables['env'] = $env;
            $this->loadedVariables['debug'] = $debug;

            // Essaie de charger Doctrine
            if ($container->has('doctrine.orm.entity_manager')) {
                $this->loadedVariables['em'] = $container->get('doctrine.orm.entity_manager');
            }

            // Router
            if ($container->has('router')) {
                $this->loadedVariables['router'] = $container->get('router');
            }

            // Logger
            if ($container->has('logger')) {
                $this->loadedVariables['logger'] = $container->get('logger');
            }

            $output->writeln('<info>Kernel Symfony initialisé</info>');

        } catch (\Exception $e) {
            $output->writeln("<error>Erreur lors du chargement Symfony: {$e->getMessage()}</error>");
        }
    }

    private function loadLaravel(OutputInterface $output)
    {
        require_once $this->projectRoot . '/vendor/autoload.php';

        try {
            // Charge l'application Laravel
            $app = require_once $this->projectRoot . '/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            $this->loadedVariables['app'] = $app;
            $this->loadedVariables['db'] = $app->make('db');
            
            if (class_exists('\Illuminate\Support\Facades\Schema')) {
                $this->loadedVariables['schema'] = \Illuminate\Support\Facades\Schema::class;
            }

            $output->writeln('<info>Application Laravel initialisée</info>');

        } catch (\Exception $e) {
            $output->writeln("<error>Erreur lors du chargement Laravel: {$e->getMessage()}</error>");
        }
    }

    private function loadWordPress(OutputInterface $output)
    {
        try {
            // Charge WordPress
            if (file_exists($this->projectRoot . '/wp-config.php')) {
                require_once $this->projectRoot . '/wp-config.php';
            }
            
            if (file_exists($this->projectRoot . '/wp-load.php')) {
                require_once $this->projectRoot . '/wp-load.php';
            }

            global $wpdb;
            if (isset($wpdb)) {
                $this->loadedVariables['wpdb'] = $wpdb;
            }

            $output->writeln('<info>WordPress initialisé</info>');

        } catch (\Exception $e) {
            $output->writeln("<error>Erreur lors du chargement WordPress: {$e->getMessage()}</error>");
        }
    }

    private function loadComposer(OutputInterface $output)
    {
        if (file_exists($this->projectRoot . '/vendor/autoload.php')) {
            $autoloader = require_once $this->projectRoot . '/vendor/autoload.php';
            $this->loadedVariables['autoloader'] = $autoloader;
            $output->writeln('<info>Autoloader Composer chargé</info>');
        }
    }

    private function setupTabCompletion()
    {
        $shell = $this->getApplication();
        if (!$shell instanceof Shell) {
            return;
        }

        $config = $shell->getConfig();
        $matchers = [];

        // Ajoute les matchers selon le framework
        switch ($this->detectedFramework) {
            case 'symfony':
                if (isset($this->loadedVariables['container'])) {
                    $matchers[] = new SymfonyServiceMatcher($this->loadedVariables['container']);
                    $matchers[] = new SymfonyParameterMatcher($this->loadedVariables['container']);
                }
                break;
            case 'laravel':
                if (isset($this->loadedVariables['app'])) {
                    $matchers[] = new LaravelServiceMatcher($this->loadedVariables['app']);
                }
                break;
        }

        $config->addMatchers($matchers);
    }

    private function displayLoadedVariables(OutputInterface $output)
    {
        if (empty($this->loadedVariables)) {
            return;
        }

        $output->writeln('<comment>Variables disponibles:</comment>');
        foreach ($this->loadedVariables as $name => $value) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            $output->writeln("  \${$name} ({$type})");
        }

        // Injecte les variables dans le scope PsySH
        $shell = $this->getApplication();
        if ($shell instanceof Shell) {
            $shell->setScopeVariables($this->loadedVariables);
        }
    }
}