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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show or hide metrics display in the REPL.
 */
class MetricsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('metrics')
            ->setDefinition([
                new InputOption('on', null, InputOption::VALUE_NONE, 'Enable metrics display'),
                new InputOption('off', null, InputOption::VALUE_NONE, 'Disable metrics display'),
            ])
            ->setDescription('Show or hide metrics display.')
            ->setHelp(
                <<<'HELP'
Show or hide metrics display below the prompt.

The metrics display shows execution time, memory usage, variable count,
and command count for each executed command.

e.g.
<return>>>> metrics --on</return>
<return>>>> metrics --off</return>
<return>>>> metrics</return>         # Toggle current state
HELP
            );
    }

    /**
     * {@inheritdoc}
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shell = $this->getShell();
        $display = $shell->getMetricsDisplay();

        if (!$display) {
            $output->writeln('<error>Metrics display is not available.</error>');

            return 1;
        }

        $on = $input->getOption('on');
        $off = $input->getOption('off');

        if ($on && $off) {
            $output->writeln('<error>Cannot specify both --on and --off options.</error>');

            return 1;
        }

        if ($on) {
            $display->enable();
            $output->writeln('<info>Metrics display enabled.</info>');
        } elseif ($off) {
            $display->disable();
            $output->writeln('<info>Metrics display disabled.</info>');
        } else {
            // Toggle
            if ($display->isEnabled()) {
                $display->disable();
                $output->writeln('<info>Metrics display disabled.</info>');
            } else {
                $display->enable();
                $output->writeln('<info>Metrics display enabled.</info>');
            }
        }

        return 0;
    }
}
