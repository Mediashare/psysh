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
                new InputOption('out', '', InputOption::VALUE_REQUIRED, 'Path to the output file for the profiling data.'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to profile.'),
            ])
            ->setDescription('Profile a string of PHP code and display the execution summary.')
            ->setHelp(
                <<<'HELP'
Profile a string of PHP code and display the execution summary.

e.g.
<return>> profile sleep(1)</return>
<return>> profile --out=profile.grind file_get_contents('https://example.com')</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xdebug')) {
            $output->writeln('<error>Xdebug extension is not loaded. The profile command is unavailable.</error>');

            return 1;
        }

        $code = $this->cleanCode($input->getArgument('code'));
        $outFile = $input->getOption('out');
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
            $this->displaySummary($output, $profileFile);
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

    private function displaySummary(OutputInterface $output, string $profileFile)
    {
        $data = $this->parseCachegrindFile($profileFile);

        if (empty($data['functions'])) {
            $output->writeln('<warning>No profiling data found in the output file.</warning>');

            return;
        }

        $timeUnit = $data['summary']['time_unit'] ?? 100;
        $totalTime = ($data['summary']['time'] * $timeUnit) / 1000000;
        $totalMem = $data['summary']['memory'] / 1024;

        $output->writeln(\sprintf(
            '<info>Total Time: %.2f ms, Memory: %.2f KB</info>',
            $totalTime,
            $totalMem
        ));

        $table = new Table($output);
        $table->setHeaders(['Function', 'Calls', 'Time (ms)', 'Memory (KB)']);

        \usort($data['functions'], function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });
        $functions = \array_slice($data['functions'], 0, 15);

        foreach ($functions as $func) {
            $table->addRow([
                $func['name'],
                $func['calls'],
                \sprintf('%.2f', ($func['time'] * $timeUnit) / 1000000),
                \sprintf('%.2f', $func['memory'] / 1024),
            ]);
        }

        $table->render();
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
            $code = 'return '.$code.';';
        }

        return $code;
    }
}