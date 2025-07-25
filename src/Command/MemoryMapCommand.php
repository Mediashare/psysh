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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Visualize memory usage patterns with ASCII representation.
 */
class MemoryMapCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('memory-map')
            ->setDefinition([
                new InputOption('width', 'w', InputOption::VALUE_REQUIRED, 'Width of the memory visualization chart.', '60'),
                new InputOption('out', '', InputOption::VALUE_REQUIRED, 'Path to the output file for the profiling data.'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to analyze memory usage for.'),
            ])
            ->setDescription('Visualize memory usage patterns with ASCII representation.')
            ->setHelp(
                <<<'HELP'
Visualize memory usage patterns with ASCII representation.

This command profiles code execution and creates an ASCII visualization
of memory allocation patterns across function calls.

e.g.
<return>> memory-map array_fill(0, 1000, 'test')</return>
<return>> memory-map --width=80 str_repeat('x', 10000)</return>
<return>> memory-map --out=memory.grind complex_function()</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xdebug')) {
            $output->writeln('<error>Xdebug extension is not loaded. The memory-map command is unavailable.</error>');

            return 1;
        }

        $code = $this->cleanCode($input->getArgument('code'));
        $outFile = $input->getOption('out');
        $width = (int) $input->getOption('width');
        $tmpDir = \sys_get_temp_dir();

        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir='.$tmpDir,
            '-r', $code,
        ]);

        $process->run();

        if (!($process->isSuccessful())) {
            $output->writeln('<error>Failed to execute profiling process.</error>');
            $output->writeln($process->getErrorOutput());

            return 1;
        }

        $profileFile = $this->findLatestProfileFile($tmpDir);
        if ($profileFile === null) {
            $output->writeln('<error>Could not find a cachegrind output file.</error>');

            return 1;
        }

        if ($outFile) {
            \rename($profileFile, $outFile);
            $output->writeln(\sprintf('<info>Profiling data saved to: %s</info>', $outFile));
        } else {
            $this->displayMemoryMap($output, $profileFile, $width);
            \unlink($profileFile);
        }

        return 0;
    }

    private function findLatestProfileFile(string $dir): ?string
    {
        $files = \glob($dir.'/cachegrind.out.*');
        if (empty($files)) {
            return null;
        }

        \usort($files, function ($a, $b) {
            return \filemtime($b) <=> \filemtime($a);
        });

        return $files[0];
    }

    private function displayMemoryMap(OutputInterface $output, string $profileFile, int $width)
    {
        $data = $this->parseCachegrindFile($profileFile);

        if (empty($data['functions'])) {
            $output->writeln('<warning>No profiling data found in the output file.</warning>');

            return;
        }

        $totalMem = $data['summary']['memory'] / 1024; // Convert to KB
        
        $output->writeln('<info>Memory Usage Visualization</info>');
        $output->writeln(\sprintf('<comment>Total Memory Usage: %.2f KB</comment>', $totalMem));
        $output->writeln('');

        // Sort by memory usage (descending)
        \usort($data['functions'], function ($a, $b) {
            return $b['memory'] <=> $a['memory'];
        });

        // Take top functions that account for significant memory usage
        $significantFunctions = [];
        $threshold = $totalMem * 0.05; // Show functions using at least 5% of total memory
        
        foreach ($data['functions'] as $func) {
            $funcMemKB = $func['memory'] / 1024;
            if ($funcMemKB >= $threshold || \count($significantFunctions) < 5) {
                $significantFunctions[] = $func;
            }
            if (\count($significantFunctions) >= 10) {
                break;
            }
        }

        // Create ASCII visualization
        $this->renderMemoryChart($output, $significantFunctions, $totalMem, $width);
        
        // Display detailed table
        $output->writeln('');
        $this->renderMemoryTable($output, $significantFunctions);
    }

    private function renderMemoryChart(OutputInterface $output, array $functions, float $totalMemKB, int $width)
    {
        $output->writeln('<info>Memory Usage Chart:</info>');
        $output->writeln('');

        $maxMem = 0;
        foreach ($functions as $func) {
            $memKB = $func['memory'] / 1024;
            if ($memKB > $maxMem) {
                $maxMem = $memKB;
            }
        }

        if ($maxMem == 0) {
            $output->writeln('<comment>No significant memory usage detected.</comment>');
            return;
        }

        // Create the chart
        foreach ($functions as $func) {
            $memKB = $func['memory'] / 1024;
            $percentage = ($memKB / $totalMemKB) * 100;
            $barLength = (int) (($memKB / $maxMem) * ($width - 20));
            
            $funcName = $this->truncateFunctionName($func['name'], 25);
            $bar = \str_repeat('█', $barLength);
            $spaces = \str_repeat(' ', max(0, 25 - \strlen($funcName)));
            
            // Use different characters for different memory ranges
            if ($percentage > 25) {
                $bar = "<fg=red>" . $bar . "</>";
            } elseif ($percentage > 10) {
                $bar = "<fg=yellow>" . $bar . "</>";
            } else {
                $bar = "<fg=green>" . $bar . "</>";
            }
            
            $output->writeln(\sprintf(
                '%s%s |%s %.2f KB (%.1f%%)',
                $funcName,
                $spaces,
                $bar,
                $memKB,
                $percentage
            ));
        }

        // Add legend
        $output->writeln('');
        $output->writeln('<comment>Legend: Each █ represents memory usage proportion</comment>');
        $output->writeln('<comment>Colors: <fg=red>Red (>25%)</>, <fg=yellow>Yellow (10-25%)</>, <fg=green>Green (<10%)</></comment>');
    }

    private function renderMemoryTable(OutputInterface $output, array $functions)
    {
        $output->writeln('<info>Memory Usage Details:</info>');
        
        $table = new Table($output);
        $table->setHeaders(['Function', 'Memory (KB)', 'Memory (bytes)', 'Calls', 'Avg per Call (bytes)']);

        foreach ($functions as $func) {
            $memKB = $func['memory'] / 1024;
            $avgPerCall = $func['calls'] > 0 ? $func['memory'] / $func['calls'] : 0;
            
            $table->addRow([
                $this->truncateFunctionName($func['name'], 40),
                \sprintf('%.3f', $memKB),
                \number_format($func['memory']),
                \number_format($func['calls']),
                \number_format($avgPerCall, 1),
            ]);
        }

        $table->render();
    }

    private function truncateFunctionName(string $name, int $maxLength): string
    {
        if (\strlen($name) > $maxLength) {
            return \substr($name, 0, $maxLength - 3) . '...';
        }
        
        return $name;
    }

    private function parseCachegrindFile(string $filePath): array
    {
        $file = \fopen($filePath, 'r');
        if (!($file)) {
            return ['summary' => [], 'functions' => []];
        }

        $summary = [];
        $functions = [];
        $currentFunc = null;
        $timeUnit = 100;

        while (($line = \fgets($file)) !== false) {
            $line = \trim($line);
            if (empty($line) || \strpos($line, '#') === 0) {
                continue;
            }

            if (\preg_match('/^summary:\s+(\d+)\s+(\d+)/', $line, $matches)) {
                $summary = ['time' => (int) $matches[1], 'memory' => (int) $matches[2]];
            } elseif (\preg_match('/^events:\s*Time\s*\((\d+)ns\)/', $line, $matches)) {
                $timeUnit = (int) $matches[1];
            } elseif (\preg_match('/^fn=(.+)/', $line, $matches)) {
                $currentFunc = $matches[1];
                if (!isset($functions[$currentFunc])) {
                    $functions[$currentFunc] = ['name' => $currentFunc, 'calls' => 0, 'time' => 0, 'memory' => 0];
                }
            } elseif ($currentFunc && \preg_match('/^calls=(\d+)/', $line, $matches)) {
                $functions[$currentFunc]['calls'] += $matches[1];
            } elseif ($currentFunc && \preg_match('/^\d+\s+(\d+)\s+(\d+)$/', $line, $matches)) {
                $functions[$currentFunc]['time'] += (int) $matches[1];
                $functions[$currentFunc]['memory'] += (int) $matches[2];
            }
        }

        \fclose($file);

        $summary['time_unit'] = $timeUnit;

        return ['summary' => $summary, 'functions' => \array_values($functions)];
    }

    private function cleanCode(string $code): string
    {
        // A bit of a hack, but it works for now.
        if (\strpos($code, ';') === false) {
            $code = 'return '.$code;
        }

        return $code;
    }
}