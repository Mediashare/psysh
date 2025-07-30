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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Helper\Table;

/**
 * Compare the performance of two code snippets.
 */
class CompareCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('compare')
            ->setDefinition([
                new InputArgument('codeA', InputArgument::REQUIRED, 'The first code snippet.'),
                new InputArgument('codeB', InputArgument::REQUIRED, 'The second code snippet.'),
            ])
            ->setDescription('Compare the performance of two code snippets.')
            ->setHelp(
                <<<'HELP'
This command compares the execution performance of two PHP code snippets.
It runs each snippet through the `profile` command and displays a side-by-side comparison
of their performance metrics.

e.g.
<return>compare 'md5("hello")' 'sha1("hello")'</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $codeA = $input->getArgument('codeA');
        $codeB = $input->getArgument('codeB');

        $output->writeln('<info>Profiling Code A...</info>');
        $resultA = $this->runProfileCommand($codeA);
        $output->writeln('<info>Profiling Code B...</info>');
        $resultB = $this->runProfileCommand($codeB);

        if ($resultA === null || $resultB === null) {
            $output->writeln('\n<info>Performance Comparison:</info>');
            $output->writeln('<error>Failed to profile one or both code snippets. Comparison aborted.</error>');

            return self::FAILURE;
        }

        $this->displayComparison($output, $resultA, $resultB);

        return self::SUCCESS;
    }

    /**
     * Run the profile command and return its parsed output.
     *
     * @param string $code
     * @return array|null
     */
    private function runProfileCommand(string $code): ?array
    {
        $profileInput = new ArrayInput([
            'command' => 'profile',
            'code'    => $code,
        ]);

        $bufferedOutput = new BufferedOutput();
        $profileCommand = $this->getApplication()->find('profile');
        $exitCode = $profileCommand->run($profileInput, $bufferedOutput);

        if ($exitCode !== self::SUCCESS) {
            return null;
        }

        $output = $bufferedOutput->fetch();

        // Parse the output to extract summary data
        $summary = [];
        if (\preg_match('/Execution time: (\d+\.\d+)(ms|s)/', $output, $matches)) {
            $time = (float) $matches[1];
            if ($matches[2] === 's') {
                $time *= 1000; // Convert to ms
            }
            $summary['time'] = $time;
        }
        if (\preg_match('/Peak memory:\s+(\d+\.\d+)(MB|KB|B)/', $output, $matches)) {
            $memory = (float) $matches[1];
            $unit = $matches[2];
            switch ($unit) {
                case 'KB': $memory *= 1024; break;
                case 'MB': $memory *= 1024 * 1024; break;
            }
            $summary['memory'] = $memory;
        }

        return $summary;
    }

    /**
     * Display the comparison table.
     *
     * @param OutputInterface $output
     * @param array $resultA
     * @param array $resultB
     */
    private function displayComparison(OutputInterface $output, array $resultA, array $resultB)
    {
        $output->writeln('\n<info>Performance Comparison:</info>');

        $table = new Table($output);
        $table->setHeaders(['Metric', 'Code A', 'Code B', 'Difference']);

        // Time comparison
        $timeDiff = isset($resultA['time'], $resultB['time']) ? $resultB['time'] - $resultA['time'] : 'N/A';
        $table->addRow([
            'Execution Time',
            isset($resultA['time']) ? \sprintf('%.2fms', $resultA['time']) : 'N/A',
            isset($resultB['time']) ? \sprintf('%.2fms', $resultB['time']) : 'N/A',
            \sprintf('%+.2fms', $timeDiff)
        ]);

        // Memory comparison
        $memoryDiff = isset($resultA['memory'], $resultB['memory']) ? $resultB['memory'] - $resultA['memory'] : null;
        $memoryDiffFormatted = ($memoryDiff !== null) ? $this->formatBytes($memoryDiff) : 'N/A';
        $table->addRow([
            'Peak Memory',
            isset($resultA['memory']) ? $this->formatBytes($resultA['memory']) : 'N/A',
            isset($resultB['memory']) ? $this->formatBytes($resultB['memory']) : 'N/A',
            $memoryDiffFormatted
        ]);

        $table->render();
    }

    /**
     * Format bytes into a human-readable string.
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = \max($bytes, 0);
        $pow = \floor(($bytes ? \log($bytes) : 0) / \log(1024));
        $pow = \min($pow, \count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return \round($bytes, $precision) . ' ' . $units[$pow];
    }
}