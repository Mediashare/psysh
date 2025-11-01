<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Metrics;

use Psy\Context;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Formats and displays metrics in the shell.
 */
class MetricsDisplay
{
    private MetricsCollector $collector;
    private bool $enabled = true;

    /**
     * @param MetricsCollector $collector
     */
    public function __construct(MetricsCollector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * Enable metrics display.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable metrics display.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if metrics display is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Display metrics to the output.
     *
     * @param OutputInterface $output
     * @param Context         $context
     */
    public function display(OutputInterface $output, Context $context): void
    {
        if (!$this->enabled) {
            return;
        }

        // Don't show metrics if output is quiet
        if ($output->isQuiet()) {
            return;
        }

        $metrics = $this->collectMetrics($context);
        $formattedMetrics = $this->formatMetrics($metrics);

        if (!empty($formattedMetrics)) {
            $output->writeln($formattedMetrics);
        }
    }

    /**
     * Collect all metrics into an array.
     *
     * @param Context $context
     *
     * @return array
     */
    private function collectMetrics(Context $context): array
    {
        $metrics = [];

        // Execution time
        $execTime = $this->collector->getLastExecutionTime();
        if ($execTime > 0) {
            $metrics['time'] = $this->formatExecutionTime($execTime);
        }

        // Memory usage
        $currentMemory = $this->collector->getCurrentMemory();
        $peakMemory = $this->collector->getPeakMemory();
        $metrics['memory'] = $this->formatMemory($currentMemory);
        
        if ($peakMemory > $currentMemory) {
            $metrics['peak'] = $this->formatMemory($peakMemory);
        }

        // Variable count
        $varCount = count($context->getAll());
        if ($varCount > 0) {
            $metrics['vars'] = (string) $varCount;
        }

        // Command count
        $cmdCount = $this->collector->getCommandCount();
        if ($cmdCount > 0) {
            $metrics['cmds'] = (string) $cmdCount;
        }

        return $metrics;
    }

    /**
     * Format execution time.
     *
     * @param float $seconds
     *
     * @return string
     */
    private function formatExecutionTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return sprintf('%.2fμs', $seconds * 1000000);
        } elseif ($seconds < 1) {
            return sprintf('%.2fms', $seconds * 1000);
        } else {
            return sprintf('%.3fs', $seconds);
        }
    }

    /**
     * Format memory size.
     *
     * @param int $bytes
     *
     * @return string
     */
    private function formatMemory(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return sprintf(
            '%.2f%s',
            $bytes / pow(1024, $power),
            $units[$power]
        );
    }

    /**
     * Format metrics into a display string.
     *
     * @param array $metrics
     *
     * @return string
     */
    private function formatMetrics(array $metrics): string
    {
        if (empty($metrics)) {
            return '';
        }

        $parts = [];
        
        // Use styled output
        foreach ($metrics as $key => $value) {
            $label = $this->getMetricLabel($key);
            $parts[] = sprintf('<whisper>%s:</whisper> <info>%s</info>', $label, $value);
        }

        return '<whisper>┌─</whisper> ' . implode(' <whisper>│</whisper> ', $parts) . ' <whisper>─┘</whisper>';
    }

    /**
     * Get a human-readable label for a metric key.
     *
     * @param string $key
     *
     * @return string
     */
    private function getMetricLabel(string $key): string
    {
        $labels = [
            'time' => 'Time',
            'memory' => 'Memory',
            'peak' => 'Peak',
            'vars' => 'Vars',
            'cmds' => 'Cmds',
        ];

        return $labels[$key] ?? ucfirst($key);
    }
}
