<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Formatter\TraceFormatter;

/**
 * Display a filtered backtrace.
 */
class SmartTraceCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('trace')
            ->setAliases(['tr'])
            ->setDefinition([
                new InputOption('smart', 's', InputOption::VALUE_NONE, 'Only show userland code (hide vendor frames).'),
            ])
            ->setDescription('Display a filtered backtrace.')
            ->setHelp(
                <<<'HELP'
This command displays a backtrace of the current execution flow.

Use the `--smart` option to filter out frames from vendor directories, focusing
on your application's code.

e.g.
<return>trace --smart</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Remove the trace command itself from the backtrace
        \array_shift($trace);

        $smart = $input->getOption('smart');

        if ($smart) {
            $filteredTrace = [];
            foreach ($trace as $frame) {
                if (isset($frame['file'])) {
                    $isVendor = \preg_match('/vendor/', $frame['file']);
                    if ($isVendor === 0) { // Keep if no match (0)
                        $filteredTrace[] = $frame;
                    }
                } else {
                    $filteredTrace[] = $frame; // Keep frames without a file (e.g., internal functions)
                }
            }
            $trace = $filteredTrace;
        }

        if (empty($trace)) {
            $output->writeln('<info>No trace available.</info>');

            return self::SUCCESS;
        }

        $output->writeln('Stack trace:');
        $i = 0;
        foreach ($trace as $frame) {
            $line = '#'.($i++).' ';
            if (isset($frame['file'])) {
                $line .= $frame['file'].':'.$frame['line'].' ';
            }
            if (isset($frame['class'])) {
                $line .= $frame['class'];
                if (isset($frame['type'])) {
                    $line .= $frame['type'];
                }
            }
            if (isset($frame['function'])) {
                $line .= $frame['function'].'()';
            }
            $output->writeln($line);
        }

        return self::SUCCESS;
    }
}
