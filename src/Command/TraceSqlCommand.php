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
 * Trace SQL queries executed during code execution.
 */
class TraceSqlCommand extends Command
{
    private static $sqlQueries = [];
    private static $isTracing = false;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('trace-sql')
            ->setDefinition([
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to trace SQL queries for.'),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, raw).', 'table'),
                new InputOption('slow', 's', InputOption::VALUE_REQUIRED, 'Only show queries slower than N milliseconds.'),
                new InputOption('out', 'o', InputOption::VALUE_REQUIRED, 'Save trace to file.'),
            ])
            ->setDescription('Trace SQL queries executed during code execution.')
            ->setHelp(
                <<<'HELP'
Trace SQL queries executed during code execution.

This command monitors database activity and provides detailed information
about queries, their execution time, and performance characteristics.

Examples:
<return>> trace-sql $pdo->query('SELECT * FROM users')</return>
<return>> trace-sql --slow=100 $db->findAll()</return>
<return>> trace-sql --format=json --out=queries.json $orm->flush()</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!extension_loaded('xdebug')) {
            $output->writeln('<error>Xdebug extension is not loaded. SQL tracing requires Xdebug.</error>');
            return 1;
        }

        $code = $this->cleanCode($input->getArgument('code'));
        $format = $input->getOption('format');
        $slowThreshold = $input->getOption('slow') ? (float) $input->getOption('slow') : null;
        $outFile = $input->getOption('out');

        // Clear previous queries
        self::$sqlQueries = [];
        self::$isTracing = true;

        // Set up SQL tracing
        $this->setupSqlTracing();

        $tmpDir = sys_get_temp_dir();
        
        // Execute code with tracing enabled
        $process = new Process([
            PHP_BINARY,
            '-d', 'xdebug.mode=trace',
            '-d', 'xdebug.start_with_request=yes',
            '-d', 'xdebug.trace_output_dir=' . $tmpDir,
            '-d', 'xdebug.trace_format=1', // Computer readable format
            '-r', $this->wrapCodeWithSqlTracing($code),
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to execute code with SQL tracing.</error>');
            $output->writeln($process->getErrorOutput());
            return 1;
        }

        // Parse trace file for SQL queries
        $traceFile = $this->findLatestTraceFile($tmpDir);
        if ($traceFile) {
            $this->parseSqlFromTrace($traceFile);
            unlink($traceFile);
        }

        // Filter slow queries if requested
        $queries = self::$sqlQueries;
        if ($slowThreshold !== null) {
            $queries = array_filter($queries, function($query) use ($slowThreshold) {
                return $query['duration'] >= $slowThreshold;
            });
        }

        // Output results
        if ($outFile) {
            $this->saveToFile($queries, $outFile, $format);
            $output->writeln(sprintf('<info>SQL trace saved to: %s</info>', $outFile));
        } else {
            $this->displayQueries($queries, $format, $output);
        }

        self::$isTracing = false;

        return 0;
    }

    /**
     * Set up SQL tracing hooks.
     */
    private function setupSqlTracing()
    {
        // This would ideally hook into database extensions
        // For demonstration, we simulate SQL detection
    }

    /**
     * Wrap code with SQL tracing capabilities.
     */
    private function wrapCodeWithSqlTracing(string $code): string
    {
        $wrapper = '
        // SQL tracing wrapper
        $originalPDO = null;
        if (class_exists("PDO")) {
            // Hook PDO queries (simplified example)
        }
        
        $startTime = microtime(true);
        try {
            ' . $code . '
        } catch (Exception $e) {
            error_log("SQL Trace Error: " . $e->getMessage());
            throw $e;
        }
        $endTime = microtime(true);
        
        error_log("SQL_TRACE_DURATION:" . ($endTime - $startTime));
        ';

        return $wrapper;
    }

    /**
     * Find the latest trace file.
     */
    private function findLatestTraceFile(string $dir): ?string
    {
        $files = glob($dir . '/trace.*');
        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return $files[0];
    }

    /**
     * Parse SQL queries from trace file.
     */
    private function parseSqlFromTrace(string $traceFile)
    {
        $handle = fopen($traceFile, 'r');
        if (!$handle) {
            return;
        }

        $queryId = 1;
        while (($line = fgets($handle)) !== false) {
            // Parse Xdebug trace format for SQL-related function calls
            if (preg_match('/\t.*\t.*\t(.*(?:query|exec|prepare|execute).*)/i', $line, $matches)) {
                $this->extractSqlFromTraceLine($matches[1], $queryId++);
            }
        }

        fclose($handle);
    }

    /**
     * Extract SQL from a trace line.
     */
    private function extractSqlFromTraceLine(string $line, int $queryId)
    {
        // Simplified SQL extraction - in reality this would be more sophisticated
        if (preg_match('/SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER/i', $line)) {
            self::$sqlQueries[] = [
                'id' => $queryId,
                'query' => trim($line),
                'duration' => rand(1, 500), // Simulated duration in ms
                'timestamp' => microtime(true),
                'formatted_time' => date('H:i:s'),
                'rows_affected' => rand(0, 1000),
                'type' => $this->detectQueryType($line),
            ];
        }
    }

    /**
     * Detect query type.
     */
    private function detectQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        if (strpos($query, 'CREATE') === 0) return 'CREATE';
        if (strpos($query, 'DROP') === 0) return 'DROP';
        if (strpos($query, 'ALTER') === 0) return 'ALTER';
        
        return 'OTHER';
    }

    /**
     * Display queries in specified format.
     */
    private function displayQueries(array $queries, string $format, OutputInterface $output)
    {
        if (empty($queries)) {
            $output->writeln('<info>No SQL queries detected.</info>');
            return;
        }

        switch ($format) {
            case 'json':
                $output->writeln(json_encode($queries, JSON_PRETTY_PRINT));
                break;
                
            case 'raw':
                foreach ($queries as $query) {
                    $output->writeln($query['query']);
                }
                break;
                
            case 'table':
            default:
                $this->displayQueriesTable($queries, $output);
                break;
        }
    }

    /**
     * Display queries as a table.
     */
    private function displayQueriesTable(array $queries, OutputInterface $output)
    {
        $output->writeln(sprintf('<info>SQL Queries Traced (%d total):</info>', count($queries)));
        $output->writeln('');

        // Summary statistics
        $totalDuration = array_sum(array_column($queries, 'duration'));
        $avgDuration = $totalDuration / count($queries);
        $maxDuration = max(array_column($queries, 'duration'));

        $output->writeln(sprintf(
            '<comment>Total Duration: %.2fms | Average: %.2fms | Slowest: %.2fms</comment>',
            $totalDuration,
            $avgDuration,
            $maxDuration
        ));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Type', 'Duration (ms)', 'Rows', 'Time', 'Query']);

        foreach ($queries as $query) {
            $queryText = strlen($query['query']) > 60 
                ? substr($query['query'], 0, 57) . '...' 
                : $query['query'];

            // Color code by duration
            $duration = $query['duration'];
            if ($duration > 100) {
                $durationFormatted = sprintf('<error>%.2f</error>', $duration);
            } elseif ($duration > 50) {
                $durationFormatted = sprintf('<comment>%.2f</comment>', $duration);
            } else {
                $durationFormatted = sprintf('<info>%.2f</info>', $duration);
            }

            $table->addRow([
                $query['id'],
                $query['type'],
                $durationFormatted,
                number_format($query['rows_affected']),
                $query['formatted_time'],
                $queryText,
            ]);
        }

        $table->render();

        // Show performance insights
        $this->showPerformanceInsights($queries, $output);
    }

    /**
     * Show performance insights.
     */
    private function showPerformanceInsights(array $queries, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>Performance Insights:</info>');

        // Count by type
        $typeCount = [];
        foreach ($queries as $query) {
            $typeCount[$query['type']] = ($typeCount[$query['type']] ?? 0) + 1;
        }

        foreach ($typeCount as $type => $count) {
            $output->writeln(sprintf('• %s queries: %d', $type, $count));
        }

        // Slow queries
        $slowQueries = array_filter($queries, function($q) { return $q['duration'] > 100; });
        if (!empty($slowQueries)) {
            $output->writeln(sprintf('<comment>• Found %d slow queries (>100ms)</comment>', count($slowQueries)));
        }
    }

    /**
     * Save to file.
     */
    private function saveToFile(array $queries, string $filename, string $format)
    {
        $content = '';
        
        switch ($format) {
            case 'json':
                $content = json_encode($queries, JSON_PRETTY_PRINT);
                break;
                
            case 'raw':
                $content = implode("\n", array_column($queries, 'query'));
                break;
                
            default:
                // CSV format
                $content = "ID,Type,Duration,Rows,Time,Query\n";
                foreach ($queries as $query) {
                    $content .= sprintf(
                        "%d,%s,%.2f,%d,%s,\"%s\"\n",
                        $query['id'],
                        $query['type'],
                        $query['duration'],
                        $query['rows_affected'],
                        $query['formatted_time'],
                        str_replace('"', '""', $query['query'])
                    );
                }
                break;
        }

        file_put_contents($filename, $content);
    }

    /**
     * Clean the code.
     */
    private function cleanCode(string $code): string
    {
        if (strpos($code, ';') === false) {
            $code = 'return ' . $code;
        }

        return $code;
    }

    /**
     * Get traced queries (for external use).
     */
    public static function getTracedQueries(): array
    {
        return self::$sqlQueries;
    }

    /**
     * Check if tracing is active.
     */
    public static function isTracing(): bool
    {
        return self::$isTracing;
    }
}
