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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Display the current call stack with optional argument annotation.
 */
class StackCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('stack')
            ->setDefinition([
                new InputOption('annotate', 'a', InputOption::VALUE_NONE, 'Show argument values.'),
            ])
            ->setDescription('Display the current call stack with optional argument annotation.')
            ->setHelp(
                <<<'HELP'
This command displays the current call stack.

Use the `--annotate` option to display argument values for each function call.

e.g.
<return>stack --annotate</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $trace = \debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Remove the stack command itself from the backtrace
        \array_shift($trace);

        $annotate = $input->getOption('annotate');

        if (empty($trace)) {
            $output->writeln('<info>No stack trace available.</info>');

            return self::SUCCESS;
        }

        $output->writeln('Call Stack:');
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
                $line .= $frame['function'].'(';

                if ($annotate && isset($frame['args'])) {
                    $args = [];
                    foreach ($frame['args'] as $arg) {
                        $args[] = $this->getApplication()->getConfig()->getPresenter()->present($arg);
                    }
                    $line .= \implode(', ', $args);
                }

                $line .= ')';
            }

            $output->writeln($line);
        }

        return self::SUCCESS;
    }
}
