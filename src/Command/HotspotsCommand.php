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
 * Profile code and display hotspots (functions with highest execution cost).
 * 
 * This is an enhanced version of the profile command with automatic sorting by execution cost.
 */
class HotspotsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('hotspots')
            ->setDefinition([
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of hotspots to display.', '10'),
                new InputOption('out', '', InputOption::VALUE_REQUIRED, 'Path to the output file for the profiling data.'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to profile.'),
            ])
            ->setDescription('Profile code and display hotspots (functions with highest execution cost).')
            ->setHelp(
                <<<'HELP'
Profile code and display hotspots (functions with highest execution cost).

This command runs profiling and focuses on displaying the most expensive 
functions sorted by execution time with performance insights.

e.g.
<return>> hotspots sleep(1)</return>
<return>> hotspots --limit=20 file_get_contents('https://example.com')</return>
<return>> hotspots --out=hotspots.grind expensive_function()</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xdebug')) {
            $output->writeln('<error>Xdebug extension is not loaded. The hotspots command is unavailable.</error>');

            return 1;
        }

        $code = $this->cleanCode($input->getArgument('code'));
        $outFile = $input->getOption('out');
        $limit = (int) $input->getOption('limit');
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
            $this->displayHotspots($output, $profileFile, $limit);
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

    private function displayHotspots(OutputInterface $output, string $profileFile, int $limit)
    {
        $data = $this->parseCachegrindFile($profileFile);

        if (empty($data['functions'])) {
            $output->writeln('<warning>No profiling data found in the output file.</warning>');

            return;
        }

        $timeUnit = $data['summary']['time_unit'] ?? 100;
        $totalTime = ($data['summary']['time'] * $timeUnit) / 1000000;
        $totalMem = $data['summary']['memory'] / 1024;

        $output->writeln('<info>Performance Hotspots Analysis</info>');
        $output->writeln(\sprintf(
            '<comment>Total Execution Time: %.2f ms | Memory Usage: %.2f KB</comment>',
            $totalTime,
            $totalMem
        ));
        $output->writeln('');

        // Sort by execution time (descending)
        \usort($data['functions'], function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        $functions = \array_slice($data['functions'], 0, $limit);
        
        $table = new Table($output);
        $table->setHeaders(['Rank', 'Function', 'Calls', 'Time (ms)', '% of Total', 'Memory (KB)']);

        $rank = 1;
        foreach ($functions as $func) {
            $funcTime = ($func['time'] * $timeUnit) / 1000000;
            $percentage = $totalTime > 0 ? ($funcTime / $totalTime) * 100 : 0;
            
            // Add visual indicators for high impact functions
            $indicator = $percentage > 20 ? ' ***' : ($percentage > 10 ? ' **' : ($percentage > 5 ? ' *' : ''));
            
            $table->addRow([
                '#'.$rank++.$indicator,
                $this->formatFunctionName($func['name']),
                \number_format($func['calls']),
                \sprintf('%.3f', $funcTime),
                \sprintf('%.1f%%', $percentage),
                \sprintf('%.2f', $func['memory'] / 1024),
            ]);
        }

        $table->render();
        
        // Add performance insights
        if (!empty($functions)) {
            $topFunction = $functions[0];
            $topTime = ($topFunction['time'] * $timeUnit) / 1000000;
            $topPercentage = $totalTime > 0 ? ($topTime / $totalTime) * 100 : 0;
            
            $output->writeln('');
            $output->writeln('<info>Performance Insights:</info>');
            if ($topPercentage > 50) {
                $output->writeln(\sprintf('<comment>The function "%s" accounts for %.1f%% of total execution time. Consider optimizing this function first.</comment>', $topFunction['name'], $topPercentage));
            } elseif ($topPercentage > 25) {
                $output->writeln(\sprintf('<comment>The function "%s" is your biggest performance bottleneck at %.1f%% of total time.</comment>', $topFunction['name'], $topPercentage));
            } else {
                $output->writeln('<comment>Performance is relatively well-distributed across functions.</comment>');
            }
        }
    }

    private function formatFunctionName(string $name): string
    {
        // Truncate very long function names for better display
        if (\strlen($name) > 60) {
            return \substr($name, 0, 57) . '...';
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
