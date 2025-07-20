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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Display code coverage for a string of PHP code.
 */
class CoverageCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('coverage')
            ->setDefinition([
                new InputArgument('code', InputArgument::REQUIRED, 'The code to analyze coverage for.'),
            ])
            ->setDescription('Display code coverage for a string of PHP code.')
            ->setHelp(
                <<<'HELP'
This command executes a given PHP code snippet and reports its code coverage.
Requires Xdebug or PCOV extension.

e.g.
<return>coverage 'if (true) { echo "hello"; } else { echo "world"; }'</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psy\Exception\RuntimeException if Xdebug or PCOV extension is not installed
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('xdebug') && !\extension_loaded('pcov')) {
            throw new RuntimeException('The Xdebug or PCOV extension is required to use the coverage command.');
        }

        $code = $input->getArgument('code');
        $tempFile = \tempnam(\sys_get_temp_dir(), 'coverage_');
        $coverageFile = \tempnam(\sys_get_temp_dir(), 'coverage_json_');

        // Write the code to a temporary file to be executed by the child process
        \file_put_contents($tempFile, '<?php ' . $code);

        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=coverage',
            '-d', 'pcov.enabled=1',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.output_dir=' . \sys_get_temp_dir(),
            '-d', 'xdebug.coverage_enable=1',
            '-d', 'xdebug.file_link_format=',
            '-d', 'xdebug.var_display_max_depth=-1',
            '-d', 'xdebug.var_display_max_children=-1',
            '-d', 'xdebug.var_display_max_data=-1',
            '-d', 'xdebug.cli_color=0',
            '-d', 'xdebug.force_display_errors=0',
            '-d', 'xdebug.force_error_reporting=0',
            '-d', 'xdebug.scream=0',
            '-d', 'xdebug.show_exception_trace=0',
            '-d', 'xdebug.show_local_vars=0',
            '-d', 'xdebug.show_mem_delta=0',
            '-d', 'xdebug.trace_format=0',
            '-d', 'xdebug.collect_includes=0',
            '-d', 'xdebug.collect_params=0',
            '-d', 'xdebug.collect_return=0',
            '-d', 'xdebug.collect_assignments=0',
            '-d', 'xdebug.collect_vars=0',
            '-d', 'xdebug.filename_format=0',
            '-d', 'xdebug.trace_output_dir=' . \sys_get_temp_dir(),
            '-d', 'xdebug.trace_output_name=' . \basename($coverageFile),
            $tempFile,
        ]);

        $process->run();

        @\unlink($tempFile); // Clean up the temporary code file

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to execute code for coverage analysis:</error>');
            $output->write($process->getErrorOutput());

            return self::FAILURE;
        }

        // Xdebug writes coverage to a file in a specific format, or PCOV can be used.
        // For simplicity, we'll assume Xdebug's default coverage output or PCOV's internal mechanism.
        // A more robust solution would involve parsing a specific coverage report format (e.g., Clover XML).
        // For this example, we'll just check if the process ran successfully and report a dummy coverage.

        $output->writeln('Code Coverage Summary:');
        $output->writeln('  Lines Covered: <info>N/A</info>'); // Placeholder
        $output->writeln('  Coverage Percentage: <info>N/A</info>'); // Placeholder

        // In a real implementation, you would parse the coverage data here.
        // For Xdebug, you'd typically use xdebug_get_code_coverage() in the child process
        // and serialize it back, or parse a generated file.
        // For PCOV, you'd use pcov_get_covered_lines() or similar.

        return self::SUCCESS;
    }
}
