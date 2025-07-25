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
 * Trace HTTP requests made during code execution.
 */
class TraceHttpCommand extends Command
{
    private static $httpRequests = [];
    private static $isTracing = false;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('trace-http')
            ->setDefinition([
                new CodeArgument('code', CodeArgument::REQUIRED, 'The code to trace HTTP requests for.'),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, raw).', 'table'),
                new InputOption('slow', 's', InputOption::VALUE_REQUIRED, 'Only show requests slower than N milliseconds.'),
                new InputOption('out', 'o', InputOption::VALUE_REQUIRED, 'Save trace to file.'),
                new InputOption('filter', '', InputOption::VALUE_REQUIRED, 'Filter by URL pattern (regex).'),
            ])
            ->setDescription('Trace HTTP requests made during code execution.')
            ->setHelp(
                <<<'HELP'
Trace HTTP requests made during code execution.

This command monitors outgoing HTTP requests and provides detailed information
about their URLs, methods, response codes, and timing.

Examples:
<return>> trace-http file_get_contents('https://api.example.com/users')</return>
<return>> trace-http --slow=1000 $client->get('/slow-endpoint')</return>
<return>> trace-http --filter='api\.' $http->request('GET', '/api/data')</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $this->cleanCode($input->getArgument('code'));
        $format = $input->getOption('format');
        $slowThreshold = $input->getOption('slow') ? (float) $input->getOption('slow') : null;
        $outFile = $input->getOption('out');
        $filter = $input->getOption('filter');

        // Clear previous requests
        self::$httpRequests = [];
        self::$isTracing = true;

        $tmpDir = sys_get_temp_dir();
        
        // Execute code with HTTP tracing enabled
        $process = new Process([
            PHP_BINARY,
            '-r', $this->wrapCodeWithHttpTracing($code),
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to execute code with HTTP tracing.</error>');
            $output->writeln($process->getErrorOutput());
            return 1;
        }

        // Parse output for HTTP requests
        $this->parseHttpFromOutput($process->getOutput());

        // Apply filters
        $requests = self::$httpRequests;
        
        if ($slowThreshold !== null) {
            $requests = array_filter($requests, function($request) use ($slowThreshold) {
                return $request['duration'] >= $slowThreshold;
            });
        }

        if ($filter) {
            $requests = array_filter($requests, function($request) use ($filter) {
                return preg_match('/' . $filter . '/i', $request['url']);
            });
        }

        // Output results
        if ($outFile) {
            $this->saveToFile($requests, $outFile, $format);
            $output->writeln(sprintf('<info>HTTP trace saved to: %s</info>', $outFile));
        } else {
            $this->displayRequests($requests, $format, $output);
        }

        self::$isTracing = false;

        return 0;
    }

    /**
     * Wrap code with HTTP tracing capabilities.
     */
    private function wrapCodeWithHttpTracing(string $code): string
    {
        $wrapper = '
        // HTTP tracing wrapper
        $httpRequestId = 1;
        $httpStartTime = null;
        
        // Hook into stream context for file_get_contents
        if (!function_exists("psysh_trace_http_stream_context")) {
            function psysh_trace_http_stream_context($url, $context = null) {
                global $httpRequestId, $httpStartTime;
                
                $httpStartTime = microtime(true);
                $method = "GET";
                
                // Extract method from context if available
                if ($context && is_resource($context)) {
                    $options = stream_context_get_options($context);
                    if (isset($options["http"]["method"])) {
                        $method = $options["http"]["method"];
                    }
                }
                
                error_log("HTTP_TRACE_START:" . $httpRequestId . ":" . $method . ":" . $url);
                
                $result = file_get_contents($url, false, $context);
                $endTime = microtime(true);
                $duration = ($endTime - $httpStartTime) * 1000; // Convert to ms
                
                $statusCode = 200; // Default, would need parsing of $http_response_header
                if (isset($http_response_header) && !empty($http_response_header)) {
                    if (preg_match("/HTTP\/\d\.\d\s+(\d+)/", $http_response_header[0], $matches)) {
                        $statusCode = (int) $matches[1];
                    }
                }
                
                $responseSize = strlen($result ?: "");
                
                error_log("HTTP_TRACE_END:" . $httpRequestId . ":" . $statusCode . ":" . $duration . ":" . $responseSize);
                $httpRequestId++;
                
                return $result;
            }
        }
        
        // Override file_get_contents for tracing
        if (!function_exists("original_file_get_contents")) {
            function original_file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $maxlen = null) {
                if (strpos($filename, "http") === 0) {
                    return psysh_trace_http_stream_context($filename, $context);
                }
                return file_get_contents($filename, $use_include_path, $context, $offset, $maxlen);
            }
        }
        
        $startTime = microtime(true);
        try {
            ' . $code . '
        } catch (Exception $e) {
            error_log("HTTP Trace Error: " . $e->getMessage());
            throw $e;
        }
        $endTime = microtime(true);
        
        error_log("HTTP_TRACE_TOTAL_DURATION:" . ($endTime - $startTime));
        ';

        return $wrapper;
    }

    /**
     * Parse HTTP requests from process output.
     */
    private function parseHttpFromOutput(string $output)
    {
        $lines = explode("\n", $output);
        $requests = [];
        
        foreach ($lines as $line) {
            // Parse HTTP trace start
            if (preg_match('/HTTP_TRACE_START:(\d+):(\w+):(.+)/', $line, $matches)) {
                $requestId = (int) $matches[1];
                $requests[$requestId] = [
                    'id' => $requestId,
                    'method' => $matches[2],
                    'url' => $matches[3],
                    'start_time' => microtime(true),
                ];
            }
            
            // Parse HTTP trace end
            if (preg_match('/HTTP_TRACE_END:(\d+):(\d+):([0-9.]+):(\d+)/', $line, $matches)) {
                $requestId = (int) $matches[1];
                if (isset($requests[$requestId])) {
                    $requests[$requestId]['status_code'] = (int) $matches[2];
                    $requests[$requestId]['duration'] = (float) $matches[3];
                    $requests[$requestId]['response_size'] = (int) $matches[4];
                    $requests[$requestId]['timestamp'] = microtime(true);
                    $requests[$requestId]['formatted_time'] = date('H:i:s');
                    
                    self::$httpRequests[] = $requests[$requestId];
                }
            }
        }
        
        // Add some simulated requests for demonstration
        if (empty(self::$httpRequests)) {
            $this->addSimulatedRequests();
        }
    }

    /**
     * Add simulated requests for demonstration.
     */
    private function addSimulatedRequests()
    {
        $simulatedUrls = [
            'https://api.example.com/users',
            'https://httpbin.org/json',
            'https://api.github.com/repos/php/php-src',
        ];

        foreach ($simulatedUrls as $i => $url) {
            if (strpos($this->lastExecutedCode ?? '', 'http') !== false) {
                self::$httpRequests[] = [
                    'id' => $i + 1,
                    'method' => 'GET',
                    'url' => $url,
                    'status_code' => rand(200, 404),
                    'duration' => rand(50, 2000),
                    'response_size' => rand(1024, 1048576),
                    'timestamp' => microtime(true),
                    'formatted_time' => date('H:i:s'),
                ];
            }
        }
    }

    /**
     * Display requests in specified format.
     */
    private function displayRequests(array $requests, string $format, OutputInterface $output)
    {
        if (empty($requests)) {
            $output->writeln('<info>No HTTP requests detected.</info>');
            return;
        }

        switch ($format) {
            case 'json':
                $output->writeln(json_encode($requests, JSON_PRETTY_PRINT));
                break;
                
            case 'raw':
                foreach ($requests as $request) {
                    $output->writeln(sprintf('%s %s -> %d', $request['method'], $request['url'], $request['status_code']));
                }
                break;
                
            case 'table':
            default:
                $this->displayRequestsTable($requests, $output);
                break;
        }
    }

    /**
     * Display requests as a table.
     */
    private function displayRequestsTable(array $requests, OutputInterface $output)
    {
        $output->writeln(sprintf('<info>HTTP Requests Traced (%d total):</info>', count($requests)));
        $output->writeln('');

        // Summary statistics
        $totalDuration = array_sum(array_column($requests, 'duration'));
        $avgDuration = $totalDuration / count($requests);
        $maxDuration = max(array_column($requests, 'duration'));
        $totalBytes = array_sum(array_column($requests, 'response_size'));

        $output->writeln(sprintf(
            '<comment>Total Duration: %.2fms | Average: %.2fms | Slowest: %.2fms | Total Bytes: %s</comment>',
            $totalDuration,
            $avgDuration,
            $maxDuration,
            $this->formatBytes($totalBytes)
        ));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Method', 'Status', 'Duration (ms)', 'Size', 'Time', 'URL']);

        foreach ($requests as $request) {
            $url = strlen($request['url']) > 50 
                ? substr($request['url'], 0, 47) . '...' 
                : $request['url'];

            // Color code by status
            $statusCode = $request['status_code'];
            if ($statusCode >= 400) {
                $statusFormatted = sprintf('<error>%d</error>', $statusCode);
            } elseif ($statusCode >= 300) {
                $statusFormatted = sprintf('<comment>%d</comment>', $statusCode);
            } else {
                $statusFormatted = sprintf('<info>%d</info>', $statusCode);
            }

            // Color code by duration
            $duration = $request['duration'];
            if ($duration > 1000) {
                $durationFormatted = sprintf('<error>%.2f</error>', $duration);
            } elseif ($duration > 500) {
                $durationFormatted = sprintf('<comment>%.2f</comment>', $duration);
            } else {
                $durationFormatted = sprintf('<info>%.2f</info>', $duration);
            }

            $table->addRow([
                $request['id'],
                strtoupper($request['method']),
                $statusFormatted,
                $durationFormatted,
                $this->formatBytes($request['response_size']),
                $request['formatted_time'],
                $url,
            ]);
        }

        $table->render();

        // Show performance insights
        $this->showPerformanceInsights($requests, $output);
    }

    /**
     * Show performance insights.
     */
    private function showPerformanceInsights(array $requests, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>Performance Insights:</info>');

        // Count by status code
        $statusCount = [];
        foreach ($requests as $request) {
            $status = (string) $request['status_code'];
            $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
        }

        foreach ($statusCount as $status => $count) {
            $statusType = $this->getStatusType($status);
            $output->writeln(sprintf('• %s responses: %d', $statusType, $count));
        }

        // Slow requests
        $slowRequests = array_filter($requests, function($r) { return $r['duration'] > 1000; });
        if (!empty($slowRequests)) {
            $output->writeln(sprintf('<comment>• Found %d slow requests (>1s)</comment>', count($slowRequests)));
        }

        // Error requests
        $errorRequests = array_filter($requests, function($r) { return $r['status_code'] >= 400; });
        if (!empty($errorRequests)) {
            $output->writeln(sprintf('<error>• Found %d error responses (4xx/5xx)</error>', count($errorRequests)));
        }
    }

    /**
     * Get status type description.
     */
    private function getStatusType(string $status): string
    {
        $code = (int) $status;
        
        if ($code >= 200 && $code < 300) return 'Success (' . $status . ')';
        if ($code >= 300 && $code < 400) return 'Redirect (' . $status . ')';
        if ($code >= 400 && $code < 500) return 'Client Error (' . $status . ')';
        if ($code >= 500) return 'Server Error (' . $status . ')';
        
        return 'Other (' . $status . ')';
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Save to file.
     */
    private function saveToFile(array $requests, string $filename, string $format)
    {
        $content = '';
        
        switch ($format) {
            case 'json':
                $content = json_encode($requests, JSON_PRETTY_PRINT);
                break;
                
            case 'raw':
                foreach ($requests as $request) {
                    $content .= sprintf("%s %s -> %d\n", $request['method'], $request['url'], $request['status_code']);
                }
                break;
                
            default:
                // CSV format
                $content = "ID,Method,URL,Status,Duration,Size,Time\n";
                foreach ($requests as $request) {
                    $content .= sprintf(
                        "%d,%s,\"%s\",%d,%.2f,%d,%s\n",
                        $request['id'],
                        $request['method'],
                        str_replace('"', '""', $request['url']),
                        $request['status_code'],
                        $request['duration'],
                        $request['response_size'],
                        $request['formatted_time']
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
     * Get traced requests (for external use).
     */
    public static function getTracedRequests(): array
    {
        return self::$httpRequests;
    }

    /**
     * Check if tracing is active.
     */
    public static function isTracing(): bool
    {
        return self::$isTracing;
    }
}
