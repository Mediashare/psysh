<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Async\AsyncExecutionWrapper;
use Psy\Async\AsyncMetricsManager;
use Psy\Async\StatusBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to manage async metrics and status bar.
 */
class AsyncCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('async')
            ->setAliases(['metrics'])
            ->setDefinition([
                new InputOption('enable', null, InputOption::VALUE_NONE, 'Enable async metrics tracking'),
                new InputOption('disable', null, InputOption::VALUE_NONE, 'Disable async metrics tracking'),
                new InputOption('status', null, InputOption::VALUE_NONE, 'Show async status'),
                new InputOption('statusbar', null, InputOption::VALUE_REQUIRED, 'Enable/disable status bar (on|off)'),
            ])
            ->setDescription('Manage async metrics and status bar.')
            ->setHelp(
                <<<'HELP'
Manage async metrics tracking and status bar display.

Examples:

<return># Show async status</return>
<return>>>> async --status</return>

<return># Enable async metrics</return>
<return>>>> async --enable</return>

<return># Disable async metrics</return>
<return>>>> async --disable</return>

<return># Enable status bar</return>
<return>>>> async --statusbar=on</return>

<return># Disable status bar</return>
<return>>>> async --statusbar=off</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shell = $this->getShell();
        $config = $shell->getConfig();
        $asyncWrapper = $shell->getAsyncExecutionWrapper();

        // Show status
        if ($input->getOption('status')) {
            $this->showStatus($output, $config, $asyncWrapper, $shell);
            return 0;
        }

        // Enable async metrics
        if ($input->getOption('enable')) {
            if ($asyncWrapper === null) {
                // Initialize async components
                $metricsManager = new AsyncMetricsManager();
                $statusBar = null;
                
                if ($config->useStatusBar()) {
                    $statusBar = new StatusBar($output);
                }
                
                $asyncWrapper = new AsyncExecutionWrapper($metricsManager, $statusBar);
                
                // Set the async wrapper on the shell (need to add setter method)
                $output->writeln('<info>Async metrics enabled.</info>');
                $output->writeln('<comment>Note: Full integration requires shell restart.</comment>');
            } else {
                $asyncWrapper->setEnabled(true);
                $output->writeln('<info>Async metrics enabled.</info>');
            }
            
            $config->setUseAsyncMetrics(true);
            return 0;
        }

        // Disable async metrics
        if ($input->getOption('disable')) {
            if ($asyncWrapper !== null) {
                $asyncWrapper->setEnabled(false);
            }
            
            $config->setUseAsyncMetrics(false);
            $output->writeln('<info>Async metrics disabled.</info>');
            return 0;
        }

        // Manage status bar
        if ($statusBarOption = $input->getOption('statusbar')) {
            $enable = \in_array(\strtolower($statusBarOption), ['on', '1', 'true', 'yes']);
            
            $config->setUseStatusBar($enable);
            
            if ($asyncWrapper !== null) {
                $statusBar = $asyncWrapper->getStatusBar();
                if ($statusBar !== null) {
                    $statusBar->setEnabled($enable);
                } elseif ($enable) {
                    $statusBar = new StatusBar($output);
                    $asyncWrapper->setStatusBar($statusBar);
                }
            }
            
            $output->writeln(\sprintf(
                '<info>Status bar %s.</info>',
                $enable ? 'enabled' : 'disabled'
            ));
            return 0;
        }

        // If no options provided, show help
        $this->showStatus($output, $config, $asyncWrapper, $shell);

        return 0;
    }

    /**
     * Show async status.
     *
     * @param OutputInterface $output
     * @param \Psy\Configuration $config
     * @param AsyncExecutionWrapper|null $asyncWrapper
     * @param \Psy\Shell $shell
     */
    private function showStatus(
        OutputInterface $output,
        $config,
        ?AsyncExecutionWrapper $asyncWrapper,
        $shell
    ): void {
        $output->writeln('<info>Async Status:</info>');
        $output->writeln('');
        
        $asyncEnabled = $config->useAsyncMetrics();
        $statusBarEnabled = $config->useStatusBar();
        $wrapperActive = $asyncWrapper !== null && $asyncWrapper->isEnabled();
        
        $output->writeln(\sprintf(
            '  Async Metrics (Config): <comment>%s</comment>',
            $asyncEnabled ? 'Enabled' : 'Disabled'
        ));
        
        $output->writeln(\sprintf(
            '  Status Bar (Config):    <comment>%s</comment>',
            $statusBarEnabled ? 'Enabled' : 'Disabled'
        ));
        
        $output->writeln(\sprintf(
            '  Execution Wrapper:      <comment>%s</comment>',
            $wrapperActive ? 'Active' : 'Inactive'
        ));
        
        $output->writeln('');
        
        if ($asyncWrapper !== null) {
            $metricsManager = $asyncWrapper->getMetricsManager();
            if ($metricsManager !== null) {
                $metrics = $metricsManager->getMetrics();
                
                $output->writeln('<info>Current Metrics:</info>');
                $output->writeln(\sprintf(
                    '  Execution Time: <comment>%s</comment>',
                    $metricsManager->getFormattedExecutionTime()
                ));
                $output->writeln(\sprintf(
                    '  Memory Usage:   <comment>%s</comment>',
                    $metricsManager->getFormattedMemoryUsage()
                ));
                $output->writeln(\sprintf(
                    '  Peak Memory:    <comment>%s</comment>',
                    $metricsManager->getFormattedPeakMemoryUsage()
                ));
            }
        }
    }
}
