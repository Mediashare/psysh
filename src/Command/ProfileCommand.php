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
                new InputOption('full', '', InputOption::VALUE_NONE, 'Show full profiling data including PsySH overhead.'),
                new InputOption('filter', '', InputOption::VALUE_REQUIRED, 'Filter level: user (default), php, all', 'user'),
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to profile.'),
            ])
            ->setDescription('Profile a string of PHP code and display the execution summary.')
            ->setHelp(
                <<<'HELP'
Profile a string of PHP code and display the execution summary.

e.g.
<return>> profile sleep(1)</return>
<return>> profile --out=profile.grind file_get_contents('https://example.com')</return>
<return>> profile --full $a = [1,2,3]; array_map('strtoupper', $a)</return>
<return>> profile --filter=php json_decode('{"test": true}')</return>

Filter levels:
- user (default): Shows only user code, excludes framework overhead
- php: Shows user code + PHP internal functions
- all: Shows everything including PsySH initialization
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
        $filterLevel = $input->getOption('full') ? 'all' : $input->getOption('filter');

        // Prepare context
        $shell = $this->getShell();
        $context = $shell->getScopeVariables();
        $contextCode = $this->buildContextCode($context, $output);

        // Prepare the script to be executed
        $script = $this->buildScript($contextCode, $code, $filterLevel);

        // First, run the code in a sub-shell to capture its output (without profiling)
        $this->runOutputProcess($script, $output);

        // Now, run the profiling process
        $process = $this->runProfilingProcess($script, $tmpDir, $filterLevel);

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to execute profiling process.</error>');
            $output->writeln($process->getErrorOutput());

            return 1;
        }

        $profileFile = $this->findLatestProfileFile($tmpDir);
        if ($profileFile === null) {
            $output->writeln('<error>Could not find a cachegrind output file.</error>');

            return 1;
        }

        // Handle the output
        if ($outFile) {
            $this->exportProfile($profileFile, $outFile, $filterLevel);
            $output->writeln(\sprintf('<info>Profiling data saved to: %s</info>', $outFile));
        } else {
            $this->displaySummary($output, $profileFile, $filterLevel);
        }

        \unlink($profileFile);

        return 0;
    }

    private function buildContextCode(array $context, OutputInterface $output): string
    {
        $contextCode = '';
        $excludedVars = [];

        foreach ($context as $name => $value) {
            if ($name === '_') { // Don't re-inject the last result
                continue;
            }

            if ($name !== 'this' && $this->canSerialize($value)) {
                $varName = (\strpos($name, '$') === 0) ? $name : '$'.$name;
                $contextCode .= \sprintf('%s = \unserialize(%s);', $varName, \var_export(\serialize($value), true))."\n";
            } else {
                $excludedVars[] = \sprintf('%s (%s)', $name, \gettype($value));
            }
        }

        if (!empty($excludedVars)) {
            $output->writeln('<comment>Note: The following variables are not available in the profiled context:</comment>');
            foreach ($excludedVars as $var) {
                $output->writeln('<comment>  - '.$var.'</comment>');
            }
            $output->writeln('');
        }

        return $contextCode;
    }

    private function buildScript(string $contextCode, string $userCode, string $filterLevel): string
    {
        $script = $contextCode;
        $script .= $userCode."\n";

        return $script;
    }

    private function runOutputProcess(string $script, OutputInterface $output)
    {
        $psyshBinary = \realpath(__DIR__.'/../../bin/psysh');
        $process = new Process([PHP_BINARY, $psyshBinary]);
        $process->setInput($script."\nexit\n");
        $process->run();

        $processOutput = \trim($process->getOutput());
        if (!empty($processOutput)) {
            $output->writeln('<info>Code output:</info>');
            $output->writeln($processOutput);
            $output->writeln('');
        }
    }

    private function runProfilingProcess(string $script, string $tmpDir, string $filterLevel): Process
    {
        $psyshBinary = \realpath(__DIR__.'/../../bin/psysh');
        $processArgs = [PHP_BINARY];

        // Always enable profiling with start_with_request=yes since Xdebug 3.x removed manual control functions
        $processArgs = \array_merge($processArgs, [
            '-d', 'xdebug.mode=profile',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir='.$tmpDir,
        ]);

        $processArgs[] = $psyshBinary;

        $process = new Process($processArgs);
        $process->setInput($script."\nexit\n");
        $process->run();

        return $process;
    }

    private function findLatestProfileFile(string $dir): ?string
    {
        $files = \glob($dir.'/cachegrind.out.*');
        if (empty($files)) {
            return null;
        }

        \usort($files, fn ($a, $b) => \filemtime($b) <=> \filemtime($a));

        return $files[0];
    }

    private function exportProfile(string $sourceFile, string $targetFile, string $filterLevel)
    {
        if ($filterLevel === 'all') {
            \copy($sourceFile, $targetFile);

            return;
        }

        $data = $this->parseCachegrindFile($sourceFile);
        $filteredFunctions = $this->applyFilter($data['functions'], $filterLevel);
        $this->writeFilteredCachegrindFile($targetFile, $data, $filteredFunctions, $filterLevel);
    }

    private function displaySummary(OutputInterface $output, string $profileFile, string $filterLevel)
    {
        $data = $this->parseCachegrindFile($profileFile);

        if (empty($data['functions'])) {
            $output->writeln('<warning>No profiling data found. The code may have executed too quickly.</warning>');

            return;
        }

        $filteredFunctions = $this->applyFilter($data['functions'], $filterLevel);

        if (empty($filteredFunctions)) {
            $output->writeln('<warning>No functions matching the current filter were found in the profile.</warning>');

            return;
        }

        // Calculate totals from the *filtered* data for accurate percentages
        $totalTime = \array_sum(\array_column($filteredFunctions, 'time'));
        $totalMem = \array_sum(\array_column($filteredFunctions, 'mem'));

        $filterDescription = $this->getFilterDescription($filterLevel);
        $output->writeln("<info>Profiling results ({$filterDescription}):</info>");
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Function', 'Calls', 'Time (ms)', 'Time %', 'Memory (KB)', 'Memory %']);

        \usort($filteredFunctions, fn ($a, $b) => $b['time'] <=> $a['time']);

        $functions = \array_slice($filteredFunctions, 0, 20);

        foreach ($functions as $func) {
            $timePct = $totalTime > 0 ? ($func['time'] / $totalTime) * 100 : 0;
            $memPct = $totalMem > 0 ? ($func['mem'] / $totalMem) * 100 : 0;

            $table->addRow([
                $this->formatFunctionName($func['name']),
                $func['calls'],
                \sprintf('%.3f', $func['time'] / 1000), // Time is in microseconds
                \sprintf('%.1f%%', $timePct),
                \sprintf('%.2f', $func['mem'] / 1024),
                \sprintf('%.1f%%', $memPct),
            ]);
        }

        $table->render();
        $output->writeln('');
        $output->writeln(\sprintf(
            '<info>Total execution (%s): Time: %.3f ms, Memory: %.2f KB</info>',
            $filterDescription,
            $totalTime / 1000,
            $totalMem / 1024
        ));
    }

    private function parseCachegrindFile(string $filePath): array
    {
        $content = \file_get_contents($filePath);
        if ($content === false) {
            return ['summary' => [], 'functions' => []];
        }

        $lines = \explode("\n", $content);
        $functions = [];
        $summary = ['time' => 0, 'mem' => 0];
        $currentFunc = null;
        $currentFile = null;
        $functionNames = [];
        $fileNames = [];

        foreach ($lines as $line) {
            $line = \trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse summary line
            if (\strpos($line, 'summary:') === 0) {
                $parts = \explode(' ', $line);
                if (\count($parts) >= 3) {
                    $summary['time'] = (int) $parts[1];
                    $summary['mem'] = (int) $parts[2];
                }
                continue;
            }

            // Parse file line: fl=(1) filename
            if (\preg_match('/^fl=\((\d+)\)\s+(.+)$/', $line, $matches)) {
                $fileNames[(int) $matches[1]] = $matches[2];
                continue;
            }

            // Parse function line: fn=(1) functionname
            if (\preg_match('/^fn=\((\d+)\)\s+(.+)$/', $line, $matches)) {
                $funcId = (int) $matches[1];
                $funcName = $matches[2];
                $functionNames[$funcId] = $funcName;
                
                if (!isset($functions[$funcName])) {
                    $functions[$funcName] = [
                        'id' => $funcId,
                        'name' => $funcName,
                        'time' => 0,
                        'mem' => 0,
                        'calls' => 1, // Default to 1 call
                    ];
                }
                $currentFunc = $funcName;
                continue;
            }

            // Parse cost line: line_number time memory
            if ($currentFunc && \preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $line, $matches)) {
                $functions[$currentFunc]['time'] += (int) $matches[2];
                $functions[$currentFunc]['mem'] += (int) $matches[3];
                continue;
            }

            // Parse call line: calls=count target_line
            if (\preg_match('/^calls=(\d+)\s+(\d+)$/', $line, $matches)) {
                if ($currentFunc) {
                    $functions[$currentFunc]['calls'] = (int) $matches[1];
                }
                continue;
            }
        }

        return ['summary' => $summary, 'functions' => \array_values($functions)];
    }

    private function cleanCode(string $code): string
    {
        if (\strpos($code, ';') === false && \strpos($code, '}') === false) {
            $code = 'return '.$code.';';
        }

        return $code;
    }

    private function applyFilter(array $functions, string $filterLevel): array
    {
        if ($filterLevel === 'all') {
            return $functions;
        }

        $filtered = [];
        foreach ($functions as $func) {
            $name = $func['name'];

            if ($this->isPsySHFrameworkFunction($name) || $this->isComposerFunction($name) || $this->isSymfonyFrameworkFunction($name)) {
                continue;
            }

            if ($filterLevel === 'user' && $this->isPhpInternalFunction($name)) {
                continue;
            }

            $filtered[] = $func;
        }

        return $filtered;
    }

    private function getFilterDescription(string $filterLevel): string
    {
        return [
            'all' => 'all functions including framework overhead',
            'php' => 'user code + PHP internal functions',
            'user' => 'user code only',
        ][$filterLevel] ?? 'unknown';
    }

    private function isPsySHFrameworkFunction(string $name): bool
    {
        return \preg_match('/^Psy\\\\/', $name) || \strpos($name, 'psysh') !== false;
    }

    private function isComposerFunction(string $name): bool
    {
        return \strpos($name, 'Composer\\') === 0 || \strpos($name, 'ClassLoader') !== false;
    }

    private function isSymfonyFrameworkFunction(string $name): bool
    {
        return \strpos($name, 'Symfony\\Component\\Console\\') === 0;
    }

    private function isPhpInternalFunction(string $name): bool
    {
        return \strpos($name, 'php::') === 0 || !\preg_match('/(->|::)/', $name) && \function_exists(\explode(' ', $name)[0]);
    }

    private function formatFunctionName(string $name): string
    {
        if (\preg_match('/^{closure:(.+):(\d+)-(\d+)}$/', $name, $matches)) {
            return \sprintf('{closure:%s:%d-%d}', \basename($matches[1]), $matches[2], $matches[3]);
        }

        if (\preg_match('/^(.+)::\{closure\}$/', $name, $matches)) {
            return $matches[1].'::{closure}';
        }

        // Shorten very long function names
        if (\strlen($name) > 70) {
            return \substr($name, 0, 67).'...';
        }

        return $name;
    }

    private function writeFilteredCachegrindFile(string $filePath, array $data, array $filteredFunctions, string $filterLevel)
    {
        // This function would need a more complex implementation to write a valid,
        // filtered cachegrind file by reconstructing the call graph.
        // For now, we just copy the original if filtering is requested for export.
        \copy($data['source_file'], $filePath);
    }

    private function canSerialize($value): bool
    {
        if ($value instanceof \Closure || \is_resource($value)) {
            return false;
        }
        try {
            \serialize($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
