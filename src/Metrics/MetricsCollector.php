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

/**
 * Collects metrics during PsySH execution.
 */
class MetricsCollector
{
    private float $commandStartTime = 0.0;
    private float $lastExecutionTime = 0.0;
    private int $commandCount = 0;
    private int $peakMemory = 0;
    private ?string $lastError = null;
    private int $lastErrorLevel = 0;

    /**
     * Mark the start of command execution.
     */
    public function startCommand(): void
    {
        $this->commandStartTime = microtime(true);
    }

    /**
     * Mark the end of command execution and calculate execution time.
     */
    public function endCommand(): void
    {
        if ($this->commandStartTime > 0) {
            $this->lastExecutionTime = microtime(true) - $this->commandStartTime;
            $this->commandStartTime = 0.0;
            $this->commandCount++;
        }

        $this->peakMemory = max($this->peakMemory, memory_get_peak_usage(true));
    }

    /**
     * Record an error.
     *
     * @param int    $level   Error level
     * @param string $message Error message
     */
    public function recordError(int $level, string $message): void
    {
        $this->lastError = $message;
        $this->lastErrorLevel = $level;
    }

    /**
     * Clear the last error.
     */
    public function clearError(): void
    {
        $this->lastError = null;
        $this->lastErrorLevel = 0;
    }

    /**
     * Get the last execution time in seconds.
     */
    public function getLastExecutionTime(): float
    {
        return $this->lastExecutionTime;
    }

    /**
     * Get the total number of commands executed.
     */
    public function getCommandCount(): int
    {
        return $this->commandCount;
    }

    /**
     * Get current memory usage in bytes.
     */
    public function getCurrentMemory(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get peak memory usage in bytes.
     */
    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }

    /**
     * Get the last error message.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get the last error level.
     */
    public function getLastErrorLevel(): int
    {
        return $this->lastErrorLevel;
    }

    /**
     * Check if there's an active error.
     */
    public function hasError(): bool
    {
        return $this->lastError !== null;
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->commandStartTime = 0.0;
        $this->lastExecutionTime = 0.0;
        $this->commandCount = 0;
        $this->peakMemory = 0;
        $this->lastError = null;
        $this->lastErrorLevel = 0;
    }
}
