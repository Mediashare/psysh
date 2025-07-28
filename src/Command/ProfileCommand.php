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
use Psy\Shell;
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

        // Extract the current context
        $context = $this->getApplication()->getScopeVariables();
        $contextCode = '';
        $excludedVars = [];
        
        foreach ($context as $name => $value) {
            if ($name !== 'this') { // Exclude $this to avoid serialization issues
                // Ensure variable name starts with $
                $varName = \str_starts_with($name, '$') ? $name : '$' . $name;
                
                // Check if the value can be serialized (exclude closures, resources, etc.)
                if ($this->canSerialize($value)) {
                    $contextCode .= \sprintf('%s = \unserialize(%s);', $varName, \var_export(\serialize($value), true));
                } else {
                    // For closures and other non-serializable objects, we can't transfer them
                    $excludedVars[] = \sprintf('%s (%s)', $varName, \gettype($value));
                    // Add a comment or placeholder to indicate the variable was excluded
                    $contextCode .= \sprintf('// Variable %s (type: %s) excluded - not serializable\n', $varName, \gettype($value));
                }
            }
        }
        
        // Warn user about excluded variables
        if (!empty($excludedVars)) {
            $output->writeln('<comment>Note: The following variables are not available in the profiled context:</comment>');
            foreach ($excludedVars as $var) {
                $output->writeln('<comment>  - ' . $var . '</comment>');
            }
            $output->writeln('');
        }

        // Create a script that sets up context and executes the code
        $psyshScript = '';
        
        // Add context setup
        foreach ($context as $name => $value) {
            if ($name !== 'this' && $this->canSerialize($value)) {
                $varName = \str_starts_with($name, '$') ? $name : '$' . $name;
                $psyshScript .= sprintf('%s = unserialize(%s);', $varName, var_export(serialize($value), true)) . "\n";
            }
        }
        
        // Add the code to execute
        $psyshScript .= $code . "\n";
        $psyshScript .= "exit\n"; // Exit PsySH after execution
        
        // Debug: Show the generated script
        if (\getenv('PSYSH_DEBUG')) {
            $output->writeln('<comment>Generated PsySH script:</comment>');
            $output->writeln($psyshScript);
        }

        // First, run the code in a PsySH sub-shell to capture its output (without profiling)
        $psyshBinary = realpath(__DIR__ . '/../../bin/psysh');
        $outputProcess = new Process([
            PHP_BINARY,
            $psyshBinary,
        ]);
        $outputProcess->setInput($psyshScript);
        $outputProcess->run();
        
        // Debug the output process
        if (\getenv('PSYSH_DEBUG')) {
            $output->writeln('<comment>Output process exit code: ' . $outputProcess->getExitCode() . '</comment>');
            $output->writeln('<comment>Output process raw output: "' . addslashes($outputProcess->getOutput()) . '"</comment>');
            $output->writeln('<comment>Output process error: "' . addslashes($outputProcess->getErrorOutput()) . '"</comment>');
        }
        
        // Show the output from the code
        $processOutput = trim($outputProcess->getOutput());
        if (!empty($processOutput)) {
            $output->writeln('<info>Code output:</info>');
            $output->writeln($processOutput);
            $output->writeln('');
        }
        
        // Now run the profiling process with PsySH
        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir='.$tmpDir,
            $psyshBinary,
        ]);
        $process->setInput($psyshScript);

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

        $timeUnit = $data['summary']['time_unit'] ?? 1; // Default to 1 ns

        // Calculate totals from function data for better accuracy
        $totalTime = 0;
        $totalMem = 0;
        foreach ($data['functions'] as $func) {
            $totalTime += $func['time'];
            $totalMem += $func['memory'];
        }

        $totalTimeMs = ($totalTime * $timeUnit) / 1000000;
        $totalMemKb = $totalMem / 1024;

        $output->writeln('<info>Profiling results:</info>');
        $output->writeln(\sprintf(
            '<comment>Note: Total time includes PsySH overhead. Focus on relative percentages for performance analysis.</comment>'
        ));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Function', 'Calls', 'Time (ms)', 'Time %', 'Memory (KB)', 'Memory %']);

        \usort($data['functions'], function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        $functions = \array_slice($data['functions'], 0, 20); // Show top 20

        foreach ($functions as $func) {
            $timeMs = ($func['time'] * $timeUnit) / 1000000;
            $memKb = $func['memory'] / 1024;
            $timePct = $totalTimeMs > 0 ? ($timeMs / $totalTimeMs) * 100 : 0;
            $memPct = $totalMemKb > 0 ? ($memKb / $totalMemKb) * 100 : 0;

            $table->addRow([
                $func['name'],
                $func['calls'],
                \sprintf('%.2f', $timeMs),
                \sprintf('%.1f%%', $timePct),
                \sprintf('%.2f', $memKb),
                \sprintf('%.1f%%', $memPct),
            ]);
        }

        $table->render();
        
        $output->writeln('');
        $output->writeln(\sprintf(
            '<info>Total (top 20): Time: %.2f ms, Memory: %.2f KB</info>',
            $totalTimeMs,
            $totalMemKb
        ));
    }

    private function parseCachegrindFile(string $filePath): array
    {
        $file = \fopen($filePath, 'r');
        if (!$file) {
            return ['summary' => [], 'functions' => []];
        }

        $summary = [];
        $functions = [];
        $currentFunc = null;
        $timeUnit = 1; // Default to 1 ns

        while (($line = \fgets($file)) !== false) {
            $line = \trim($line);

            if (empty($line) || $line[0] === '#') {
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
                $functions[$currentFunc]['calls']++; // Each fn= is one call context.
            } elseif ($currentFunc && \preg_match('/^\d+\s+(\d+)\s+(\d+)$/', $line, $matches)) {
                // This is a self-cost line: line_number, time, memory
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

    /**
     * Check if a value can be safely serialized.
     */
    private function canSerialize($value): bool
    {
        // Check for common non-serializable types
        if (\is_resource($value)) {
            return false;
        }

        if ($value instanceof \Closure) {
            return false;
        }

        // Try to serialize and catch any exceptions
        try {
            \serialize($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
