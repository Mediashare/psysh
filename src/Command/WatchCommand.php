<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Watch variables and display their changes.
 */
class WatchCommand extends Command
{
    private static $watchedVariables = [];
    private static $variableHistory = [];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('watch')
            ->setDefinition([
                new InputArgument('variable', InputArgument::OPTIONAL, 'Variable name to watch (without $ prefix).'),
                new InputOption('list', 'l', InputOption::VALUE_NONE, 'List all watched variables.'),
                new InputOption('clear', 'c', InputOption::VALUE_OPTIONAL, 'Clear watched variable(s). Use "all" to clear all.', false),
                new InputOption('history', 'h', InputOption::VALUE_REQUIRED, 'Show history for a specific variable.'),
                new InputOption('diff', 'd', InputOption::VALUE_NONE, 'Show changes since last check.'),
            ])
            ->setDescription('Watch variables and track their changes over time.')
            ->setHelp(
                <<<'HELP'
Watch variables and track their changes over time.

This command allows you to monitor specific variables and see how they change
during code execution.

Examples:
<return>> watch myVar</return>
<return>> watch --list</return>
<return>> watch --history myVar</return>
<return>> watch --clear myVar</return>
<return>> watch --clear all</return>
<return>> watch --diff</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Handle list option
        if ($input->getOption('list')) {
            return $this->listWatchedVariables($output);
        }

        // Handle clear option
        if ($input->getOption('clear') !== false) {
            return $this->clearWatchedVariables($input, $output);
        }

        // Handle history option
        if ($input->getOption('history')) {
            return $this->showVariableHistory($input, $output);
        }

        // Handle diff option
        if ($input->getOption('diff')) {
            return $this->showVariableDiff($output);
        }

        // Add variable to watch list
        $variable = $input->getArgument('variable');
        if (!$variable) {
            $output->writeln('<error>You must specify a variable name to watch.</error>');
            return 1;
        }

        return $this->addWatchedVariable($variable, $output);
    }

    /**
     * Add a variable to the watch list.
     */
    private function addWatchedVariable(string $variable, OutputInterface $output): int
    {
        // Get current context from shell
        $shell = $this->getApplication();
        if (!method_exists($shell, 'getScopeVariable')) {
            $output->writeln('<error>Cannot access shell context.</error>');
            return 1;
        }

        try {
            $currentValue = $shell->getScopeVariable($variable);
            
            // Record current value
            self::$watchedVariables[$variable] = [
                'name' => $variable,
                'current_value' => $currentValue,
                'last_checked' => microtime(true),
                'change_count' => 0,
                'added_at' => date('Y-m-d H:i:s'),
            ];

            // Initialize history
            if (!isset(self::$variableHistory[$variable])) {
                self::$variableHistory[$variable] = [];
            }

            self::$variableHistory[$variable][] = [
                'value' => $currentValue,
                'timestamp' => microtime(true),
                'formatted_time' => date('Y-m-d H:i:s'),
            ];

            $output->writeln(sprintf(
                '<info>Now watching variable "%s" (current value: %s)</info>',
                $variable,
                $this->formatValue($currentValue)
            ));

        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<comment>Variable "%s" not found in current scope, but added to watch list.</comment>',
                $variable
            ));
            
            self::$watchedVariables[$variable] = [
                'name' => $variable,
                'current_value' => null,
                'last_checked' => microtime(true),
                'change_count' => 0,
                'added_at' => date('Y-m-d H:i:s'),
            ];
        }

        return 0;
    }

    /**
     * List all watched variables.
     */
    private function listWatchedVariables(OutputInterface $output): int
    {
        if (empty(self::$watchedVariables)) {
            $output->writeln('<info>No variables are being watched.</info>');
            return 0;
        }

        $output->writeln('<info>Watched Variables:</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Variable', 'Current Value', 'Changes', 'Last Checked', 'Added At']);

        foreach (self::$watchedVariables as $watch) {
            $table->addRow([
                '$' . $watch['name'],
                $this->formatValue($watch['current_value']),
                $watch['change_count'],
                date('H:i:s', (int) $watch['last_checked']),
                $watch['added_at'],
            ]);
        }

        $table->render();

        return 0;
    }

    /**
     * Clear watched variables.
     */
    private function clearWatchedVariables(InputInterface $input, OutputInterface $output): int
    {
        $clearTarget = $input->getOption('clear');

        if ($clearTarget === 'all') {
            $count = count(self::$watchedVariables);
            self::$watchedVariables = [];
            self::$variableHistory = [];
            $output->writeln(sprintf('<info>Cleared %d watched variable(s).</info>', $count));
        } elseif (isset(self::$watchedVariables[$clearTarget])) {
            unset(self::$watchedVariables[$clearTarget]);
            unset(self::$variableHistory[$clearTarget]);
            $output->writeln(sprintf('<info>Stopped watching variable "%s".</info>', $clearTarget));
        } else {
            $output->writeln(sprintf('<error>Variable "%s" is not being watched.</error>', $clearTarget));
            return 1;
        }

        return 0;
    }

    /**
     * Show variable history.
     */
    private function showVariableHistory(InputInterface $input, OutputInterface $output): int
    {
        $variable = $input->getOption('history');

        if (!isset(self::$variableHistory[$variable])) {
            $output->writeln(sprintf('<error>No history found for variable "%s".</error>', $variable));
            return 1;
        }

        $history = self::$variableHistory[$variable];
        
        $output->writeln(sprintf('<info>History for variable "%s":</info>', $variable));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Timestamp', 'Value']);

        foreach ($history as $entry) {
            $table->addRow([
                $entry['formatted_time'],
                $this->formatValue($entry['value']),
            ]);
        }

        $table->render();

        return 0;
    }

    /**
     * Show variable differences since last check.
     */
    private function showVariableDiff(OutputInterface $output): int
    {
        if (empty(self::$watchedVariables)) {
            $output->writeln('<info>No variables are being watched.</info>');
            return 0;
        }

        $shell = $this->getApplication();
        $changes = [];

        foreach (self::$watchedVariables as $varName => &$watch) {
            try {
                $currentValue = $shell->getScopeVariable($varName);
                
                if ($watch['current_value'] !== $currentValue) {
                    $changes[] = [
                        'name' => $varName,
                        'old_value' => $watch['current_value'],
                        'new_value' => $currentValue,
                    ];

                    // Update watch data
                    $watch['current_value'] = $currentValue;
                    $watch['last_checked'] = microtime(true);
                    $watch['change_count']++;

                    // Add to history
                    self::$variableHistory[$varName][] = [
                        'value' => $currentValue,
                        'timestamp' => microtime(true),
                        'formatted_time' => date('Y-m-d H:i:s'),
                    ];
                }
            } catch (\Exception $e) {
                // Variable not in scope anymore
                if ($watch['current_value'] !== null) {
                    $changes[] = [
                        'name' => $varName,
                        'old_value' => $watch['current_value'],
                        'new_value' => '<undefined>',
                    ];

                    $watch['current_value'] = null;
                    $watch['last_checked'] = microtime(true);
                    $watch['change_count']++;
                }
            }
        }

        if (empty($changes)) {
            $output->writeln('<info>No changes detected in watched variables.</info>');
            return 0;
        }

        $output->writeln('<info>Variable Changes Detected:</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Variable', 'Old Value', 'New Value']);

        foreach ($changes as $change) {
            $table->addRow([
                '$' . $change['name'],
                $this->formatValue($change['old_value']),
                $this->formatValue($change['new_value']),
            ]);
        }

        $table->render();

        return 0;
    }

    /**
     * Format a value for display.
     */
    private function formatValue($value): string
    {
        if ($value === null) {
            return '<null>';
        }

        if ($value === '<undefined>') {
            return '<undefined>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return '"' . (strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value) . '"';
        }

        if (is_array($value)) {
            return sprintf('Array(%d)', count($value));
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return (string) $value;
    }

    /**
     * Update watched variables (called externally).
     */
    public static function updateWatchedVariables($shell): array
    {
        $changes = [];

        foreach (self::$watchedVariables as $varName => &$watch) {
            try {
                $currentValue = $shell->getScopeVariable($varName);
                
                if ($watch['current_value'] !== $currentValue) {
                    $changes[$varName] = [
                        'old' => $watch['current_value'],
                        'new' => $currentValue,
                    ];

                    $watch['current_value'] = $currentValue;
                    $watch['last_checked'] = microtime(true);
                    $watch['change_count']++;

                    // Add to history
                    self::$variableHistory[$varName][] = [
                        'value' => $currentValue,
                        'timestamp' => microtime(true),
                        'formatted_time' => date('Y-m-d H:i:s'),
                    ];
                }
            } catch (\Exception $e) {
                // Variable not in scope
            }
        }

        return $changes;
    }

    /**
     * Get all watched variables (for external use).
     */
    public static function getWatchedVariables(): array
    {
        return self::$watchedVariables;
    }
}
