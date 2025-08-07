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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to explore the execution context.
 */
class ContextCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('context')
            ->setAliases(['ctx'])
            ->setDefinition([
                new InputOption('depth', 'd', InputOption::VALUE_REQUIRED, 'Depth of context exploration.', 3),
                new InputOption('watch', 'w', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Watch specific variables.'),
            ])
            ->setDescription('Explore the current execution context, showing variables and their values up to a specified depth.')
            ->setHelp(
                <<<'HELP'
Explore the current execution context and inspect variables.

You can specify a depth to control how deep the exploration goes.
WATCH variables to keep track of their values during execution.

Examples:
  <return>> ctx</return>
  <return>> ctx --depth=5</return>
  <return>> ctx --watch myVar</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $depth = (int) $input->getOption('depth');
        $watchedVars = $input->getOption('watch');

        $context = $this->getShell();

        $output->writeln('<info>Exploring context (depth=' . $depth . '):</info>');

        $variables = $context->getScopeVariables();

        $table = new Table($output);
        $table->setHeaders(['Variable', 'Type', 'Value']);

        foreach ($variables as $name => $value) {
            if (!$this->isWatched($name, $watchedVars)) {
                continue;
            }

            $table->addRow([
                $name,
                gettype($value),
                $this->renderValue($value, $depth),
            ]);
        }

        $table->render();

        return 0;
    }

    protected function isWatched(string $variable, array $watchedVars): bool
    {
        return empty($watchedVars) || in_array($variable, $watchedVars);
    }

    protected function renderValue($value, int $depth): string
    {
        if ($depth <= 0) {
            return '...';
        }

        // This simple example renders arrays and objects with recursion
        if (is_array($value)) {
            $out = '[';
            foreach ($value as $key => $val) {
                $out .= sprintf('%s => %s, ', $key, $this->renderValue($val, $depth - 1));
            }
            return rtrim($out, ', ') . ']';
        }

        if (is_object($value)) {
            $out = get_class($value) . ' {';
            foreach (get_object_vars($value) as $prop => $val) {
                $out .= sprintf('%s: %s, ', $prop, $this->renderValue($val, $depth - 1));
            }
            return rtrim($out, ', ') . '}';
        }

        return print_r($value, true);
    }
}
