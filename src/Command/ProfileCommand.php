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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Profile a string of PHP code and display the execution summary.
 */
class ProfileCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('profile')
            ->setDefinition([
                new InputArgument('code', InputArgument::REQUIRED, 'The code to profile.'),
                new InputOption('out', null, InputOption::VALUE_REQUIRED, 'Export the cachegrind output to a file.'),
            ])
            ->setDescription('Profile a string of PHP code and display the execution summary.')
            ->setHelp(
                <<<'HELP'
Profile a string of PHP code and display the execution summary.

This command uses Xdebug to profile the given code and provides a summary
of the top functions by execution time.

e.g.
<return>profile for ($i = 0; $i < 1000; $i++) { md5('hello'); }</return>

To save the raw cachegrind output for analysis in an external tool like KCacheGrind, use the `--out` option:

e.g.
<return>profile --out=profile.grind 'str_repeat("a", 10000)'</return>
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
            throw new RuntimeException('The Xdebug extension is required to use the profile command.');
        }

        $code = $input->getArgument('code');
        $outputFile = $input->getOption('out');
        $tempDir = \sys_get_temp_dir();

        // Find the profiler file before we run, in case there are leftovers.
        $beforeFiles = \glob($tempDir.'/cachegrind.out.*');

        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', "xdebug.output_dir={$tempDir}",
            '-r', $code,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to profile code:</error>');
            $output->write($process->getErrorOutput());

            return self::FAILURE;
        }

        $afterFiles = \glob($tempDir.'/cachegrind.out.*');
        $newFiles = \array_diff($afterFiles, $beforeFiles);

        if (empty($newFiles)) {
            $output->writeln('<warning>Profiler output file not found.</warning>');

            return self::FAILURE;
        }

        // Get the latest created file
        \usort($newFiles, function ($a, $b) {
            return \filemtime($b) <=> \filemtime($a);
        });
        $profilerFile = $newFiles[0];

        if ($outputFile) {
            \rename($profilerFile, $outputFile);
            $output->writeln(\sprintf('Profiler output saved to: %s', $outputFile));
        } else {
            $this->displaySummary($output, $profilerFile);
            \unlink($profilerFile);
        }

        return self::SUCCESS;
    }

    /**
     * Parse the cachegrind file and display a summary table.
     *
     * @param OutputInterface $output
     * @param string          $profilerFile
     */
    private function displaySummary(OutputInterface $output, string $profilerFile)
    {
        $file = @\fopen($profilerFile, 'r');
        if ($file === false) {
            throw new RuntimeException('Unable to read profiler output file.');
        }

        $functions = [];
        $currentFunction = null;
        $totalCost = 0;

        // First pass to get total cost from summary line
        while (($line = \fgets($file)) !== false) {
            if (\strpos(\trim($line), 'summary:') === 0) {
                $totalCost = (int) \substr(\trim($line), 8);
                break;
            }
        }

        if ($totalCost === 0) {
            $output->writeln('<warning>Could not determine total execution cost from profiler output.</warning>');
            \fclose($file);

            return;
        }

        // Rewind and parse function costs
        \rewind($file);
        while (($line = \fgets($file)) !== false) {
            $line = \trim($line);
            if (\strpos($line, 'fn=') === 0) {
                $currentFunction = \substr($line, 3);
                if (!isset($functions[$currentFunction])) {
                    $functions[$currentFunction] = ['cost' => 0, 'calls' => 0];
                }
            } elseif ($currentFunction && @\ctype_digit($line[0])) {
                $parts = \explode(' ', $line);
                // The second number on a line following `fn=` is the self-cost.
                if (isset($parts[1])) {
                    $functions[$currentFunction]['cost'] += (int) $parts[1];
                }
                // A line with cost info represents one call, so we can count them.
                $functions[$currentFunction]['calls']++;
            }
        }
        \fclose($file);

        // Filter out zero-cost entries and sort by cost
        $functions = \array_filter($functions, function ($data) {
            return $data['cost'] > 0;
        });
        \uasort($functions, function ($a, $b) {
            return $b['cost'] <=> $a['cost'];
        });

        $output->writeln('Profiling summary:');
        $table = new Table($output);
        $table->setHeaders(['Function', 'Time (self)', '%', 'Calls']);

        $rowCount = 0;
        foreach ($functions as $name => $data) {
            if (++$rowCount > 10) {
                break; // Limit to top 10
            }
            $percentage = ($data['cost'] / $totalCost) * 100;
            $table->addRow([
                $name,
                \number_format($data['cost']),
                \number_format($percentage, 2),
                $data['calls'],
            ]);
        }

        $table->render();
    }
}
