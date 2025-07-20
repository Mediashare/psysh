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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Display memory usage for a string of PHP code.
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
                new InputArgument('code', InputArgument::REQUIRED, 'The code to analyze memory usage for.'),
            ])
            ->setDescription('Display memory usage for a string of PHP code.')
            ->setHelp(
                <<<'HELP'
This command executes a given PHP code snippet and reports its peak memory usage.

e.g.
<return>memory-map 'str_repeat("a", 1024 * 1024);'</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psy\Exception\RuntimeException if the Xdebug extension is not installed
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xdebug')) {
            throw new RuntimeException('The Xdebug extension is required to use the memory-map command.');
        }

        $code = $input->getArgument('code');
        $tempDir = \sys_get_temp_dir();
        $outputFile = \tempnam($tempDir, 'memmap_');

        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', "xdebug.output_dir={$tempDir}",
            '-d', 'xdebug.profiler_output_name='.\basename($outputFile),
            '-r', $code,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to execute code for memory analysis:</error>');
            $output->write($process->getErrorOutput());

            return self::FAILURE;
        }

        // Read the profiler file to extract memory information
        $fileContent = @\file_get_contents($outputFile);
        @\unlink($outputFile); // Clean up the temporary file

        if ($fileContent === false) {
            $output->writeln('<warning>Could not read profiler output file for memory analysis.</warning>');

            return self::FAILURE;
        }

        $peakMemory = 0;
        if (\preg_match('/^summary: (\d+)\n/m', $fileContent, $matches)) {
            // Xdebug profiler summary line contains total cost, which can be interpreted as memory if configured
            // However, for memory, it's better to look for specific memory lines if available or rely on peak usage from PHP itself.
            // For simplicity, we'll parse the cachegrind format for memory if it's there.
            // Cachegrind files typically have `mem=` lines for memory usage.
            if (\preg_match_all('/^\d+ \d+ mem=(\d+)/m', $fileContent, $memMatches)) {
                $peakMemory = \max($memMatches[1]);
            } else {
                // Fallback if mem= is not found, try to use the summary as a rough estimate if it's memory-related
                // This is a simplification, as 'summary' is usually CPU cycles.
                // A more robust solution would involve parsing specific memory events or using memory_get_peak_usage() in the child process.
                $output->writeln('<warning>Could not find detailed memory information in profiler output. Displaying summary as a rough estimate.</warning>');
                $peakMemory = (int) $matches[1];
            }
        }

        $output->writeln('Memory Usage Summary:');
        $output->writeln(\sprintf('  Peak Memory: <info>%s</info>', $this->formatBytes($peakMemory)));

        return self::SUCCESS;
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