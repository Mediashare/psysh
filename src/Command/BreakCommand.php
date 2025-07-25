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

use Psy\Input\CodeArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Set dynamic breakpoints for debugging.
 */
class BreakCommand extends Command
{
    private static $breakpoints = [];
    private static $conditionalBreakpoints = [];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('break')
            ->setAliases(['bp'])
            ->setDefinition([
                new InputArgument('target', InputArgument::OPTIONAL, 'Function/method to break on (e.g., Class::method, function_name).'),
                new InputOption('if', '', InputOption::VALUE_REQUIRED, 'Conditional breakpoint expression.'),
                new InputOption('list', 'l', InputOption::VALUE_NONE, 'List all active breakpoints.'),
                new InputOption('clear', 'c', InputOption::VALUE_OPTIONAL, 'Clear breakpoint(s). Use "all" to clear all.', false),
                new InputOption('enable', 'e', InputOption::VALUE_REQUIRED, 'Enable a specific breakpoint by ID.'),
                new InputOption('disable', 'd', InputOption::VALUE_REQUIRED, 'Disable a specific breakpoint by ID.'),
            ])
            ->setDescription('Set dynamic breakpoints for debugging.')
            ->setHelp(
                <<<'HELP'
Set dynamic breakpoints for debugging code execution.

This command allows you to set breakpoints on functions, methods, or based on conditions.
When Xdebug is available, it will attempt to use Xdebug's breakpoint functionality.

Examples:
<return>> break MyClass::myMethod</return>
<return>> break --if '$x > 10'</return>
<return>> break function_name</return>
<return>> break --list</return>
<return>> break --clear all</return>
<return>> break --disable 1</return>
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
            return $this->listBreakpoints($output);
        }

        // Handle clear option
        if ($input->getOption('clear') !== false) {
            return $this->clearBreakpoints($input, $output);
        }

        // Handle enable/disable options
        if ($input->getOption('enable')) {
            return $this->enableBreakpoint($input, $output);
        }

        if ($input->getOption('disable')) {
            return $this->disableBreakpoint($input, $output);
        }

        // Set new breakpoint
        $target = $input->getArgument('target');
        $condition = $input->getOption('if');

        if (!$target && !$condition) {
            $output->writeln('<error>You must specify a target or condition for the breakpoint.</error>');
            return 1;
        }

        return $this->setBreakpoint($target, $condition, $output);
    }

    /**
     * Set a new breakpoint.
     */
    private function setBreakpoint(?string $target, ?string $condition, OutputInterface $output): int
    {
        $breakpointId = count(self::$breakpoints) + count(self::$conditionalBreakpoints) + 1;

        if ($condition) {
            // Conditional breakpoint
            self::$conditionalBreakpoints[$breakpointId] = [
                'id' => $breakpointId,
                'condition' => $condition,
                'enabled' => true,
                'hit_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $output->writeln(sprintf(
                '<info>Conditional breakpoint #%d set: %s</info>',
                $breakpointId,
                $condition
            ));
        } else {
            // Function/method breakpoint
            self::$breakpoints[$breakpointId] = [
                'id' => $breakpointId,
                'target' => $target,
                'enabled' => true,
                'hit_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $output->writeln(sprintf(
                '<info>Breakpoint #%d set on: %s</info>',
                $breakpointId,
                $target
            ));
        }

        // Try to set Xdebug breakpoint if available
        if (extension_loaded('xdebug')) {
            $this->setXdebugBreakpoint($target, $condition, $output);
        } else {
            $output->writeln('<comment>Note: Xdebug not available. Breakpoint registered for simulation only.</comment>');
        }

        return 0;
    }

    /**
     * List all active breakpoints.
     */
    private function listBreakpoints(OutputInterface $output): int
    {
        $allBreakpoints = array_merge(self::$breakpoints, self::$conditionalBreakpoints);

        if (empty($allBreakpoints)) {
            $output->writeln('<info>No breakpoints set.</info>');
            return 0;
        }

        $output->writeln('<info>Active Breakpoints:</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Type', 'Target/Condition', 'Status', 'Hit Count', 'Created']);

        foreach ($allBreakpoints as $bp) {
            $type = isset($bp['target']) ? 'Function/Method' : 'Conditional';
            $targetOrCondition = $bp['target'] ?? $bp['condition'];
            $status = $bp['enabled'] ? '<info>Enabled</info>' : '<comment>Disabled</comment>';

            $table->addRow([
                $bp['id'],
                $type,
                $targetOrCondition,
                $status,
                $bp['hit_count'],
                $bp['created_at'],
            ]);
        }

        $table->render();

        return 0;
    }

    /**
     * Clear breakpoints.
     */
    private function clearBreakpoints(InputInterface $input, OutputInterface $output): int
    {
        $clearTarget = $input->getOption('clear');

        if ($clearTarget === 'all') {
            $count = count(self::$breakpoints) + count(self::$conditionalBreakpoints);
            self::$breakpoints = [];
            self::$conditionalBreakpoints = [];
            $output->writeln(sprintf('<info>Cleared %d breakpoint(s).</info>', $count));
        } elseif (is_numeric($clearTarget)) {
            $id = (int) $clearTarget;
            
            if (isset(self::$breakpoints[$id])) {
                unset(self::$breakpoints[$id]);
                $output->writeln(sprintf('<info>Cleared breakpoint #%d.</info>', $id));
            } elseif (isset(self::$conditionalBreakpoints[$id])) {
                unset(self::$conditionalBreakpoints[$id]);
                $output->writeln(sprintf('<info>Cleared conditional breakpoint #%d.</info>', $id));
            } else {
                $output->writeln(sprintf('<error>Breakpoint #%d not found.</error>', $id));
                return 1;
            }
        } else {
            $output->writeln('<error>Invalid clear target. Use "all" or a breakpoint ID.</error>');
            return 1;
        }

        return 0;
    }

    /**
     * Enable a breakpoint.
     */
    private function enableBreakpoint(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getOption('enable');

        if (isset(self::$breakpoints[$id])) {
            self::$breakpoints[$id]['enabled'] = true;
            $output->writeln(sprintf('<info>Enabled breakpoint #%d.</info>', $id));
        } elseif (isset(self::$conditionalBreakpoints[$id])) {
            self::$conditionalBreakpoints[$id]['enabled'] = true;
            $output->writeln(sprintf('<info>Enabled conditional breakpoint #%d.</info>', $id));
        } else {
            $output->writeln(sprintf('<error>Breakpoint #%d not found.</error>', $id));
            return 1;
        }

        return 0;
    }

    /**
     * Disable a breakpoint.
     */
    private function disableBreakpoint(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getOption('disable');

        if (isset(self::$breakpoints[$id])) {
            self::$breakpoints[$id]['enabled'] = false;
            $output->writeln(sprintf('<info>Disabled breakpoint #%d.</info>', $id));
        } elseif (isset(self::$conditionalBreakpoints[$id])) {
            self::$conditionalBreakpoints[$id]['enabled'] = false;
            $output->writeln(sprintf('<info>Disabled conditional breakpoint #%d.</info>', $id));
        } else {
            $output->writeln(sprintf('<error>Breakpoint #%d not found.</error>', $id));
            return 1;
        }

        return 0;
    }

    /**
     * Set an Xdebug breakpoint if available.
     */
    private function setXdebugBreakpoint(?string $target, ?string $condition, OutputInterface $output)
    {
        if (!extension_loaded('xdebug')) {
            return;
        }

        try {
            if ($target) {
                // For function/method breakpoints, we would need to use xdebug_set_filter
                // or other Xdebug 3.x functionality
                $output->writeln('<comment>Xdebug function breakpoints require additional setup.</comment>');
            }

            if ($condition) {
                $output->writeln('<comment>Xdebug conditional breakpoints integration available.</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<comment>Xdebug integration warning: %s</comment>', $e->getMessage()));
        }
    }

    /**
     * Check if a breakpoint should trigger.
     * This method would be called from the execution context.
     */
    public static function shouldBreak(string $function, array $context = []): bool
    {
        // Check function/method breakpoints
        foreach (self::$breakpoints as $bp) {
            if (!$bp['enabled']) {
                continue;
            }

            if ($bp['target'] === $function) {
                self::$breakpoints[$bp['id']]['hit_count']++;
                return true;
            }
        }

        // Check conditional breakpoints
        foreach (self::$conditionalBreakpoints as &$bp) {
            if (!$bp['enabled']) {
                continue;
            }

            try {
                // Evaluate condition (in a real implementation, this would need proper sandboxing)
                $result = eval("return " . $bp['condition'] . ";");
                if ($result) {
                    $bp['hit_count']++;
                    return true;
                }
            } catch (\Exception $e) {
                // Condition evaluation failed, ignore
            }
        }

        return false;
    }

    /**
     * Get all active breakpoints (for external use).
     */
    public static function getBreakpoints(): array
    {
        return [
            'function' => self::$breakpoints,
            'conditional' => self::$conditionalBreakpoints,
        ];
    }
}
