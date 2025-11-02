<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Async;

use Amp\DeferredFuture;
use Revolt\EventLoop;

/**
 * Manages real-time metrics for code execution.
 */
class AsyncMetricsManager
{
    private ?string $callbackId = null;
    private float $startTime = 0.0;
    private float $currentTime = 0.0;
    private int $memoryUsage = 0;
    private int $peakMemoryUsage = 0;
    private bool $isRunning = false;
    private array $listeners = [];
    private ?DeferredFuture $completionFuture = null;

    /**
     * Start tracking metrics.
     */
    public function start(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        $this->startTime = \microtime(true);
        $this->currentTime = 0.0;
        $this->memoryUsage = \memory_get_usage(true);
        $this->peakMemoryUsage = \memory_get_peak_usage(true);
        $this->completionFuture = new DeferredFuture();

        // Update metrics every 100ms
        $this->callbackId = EventLoop::repeat(0.1, function () {
            $this->updateMetrics();
        });
    }

    /**
     * Stop tracking metrics.
     */
    public function stop(): void
    {
        if (!$this->isRunning) {
            return;
        }

        $this->isRunning = false;

        if ($this->callbackId !== null) {
            EventLoop::cancel($this->callbackId);
            $this->callbackId = null;
        }

        $this->updateMetrics();

        if ($this->completionFuture !== null) {
            $this->completionFuture->complete($this->getMetrics());
            $this->completionFuture = null;
        }
    }

    /**
     * Update current metrics.
     */
    private function updateMetrics(): void
    {
        $this->currentTime = \microtime(true) - $this->startTime;
        $this->memoryUsage = \memory_get_usage(true);
        $this->peakMemoryUsage = \memory_get_peak_usage(true);

        $this->notifyListeners();
    }

    /**
     * Get current metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return [
            'execution_time' => $this->currentTime,
            'memory_usage' => $this->memoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'is_running' => $this->isRunning,
        ];
    }

    /**
     * Get formatted execution time.
     *
     * @return string
     */
    public function getFormattedExecutionTime(): string
    {
        $time = $this->currentTime;

        if ($time < 0.001) {
            return \sprintf('%.2fμs', $time * 1000000);
        } elseif ($time < 1) {
            return \sprintf('%.2fms', $time * 1000);
        } else {
            return \sprintf('%.2fs', $time);
        }
    }

    /**
     * Get formatted memory usage.
     *
     * @return string
     */
    public function getFormattedMemoryUsage(): string
    {
        return $this->formatBytes($this->memoryUsage);
    }

    /**
     * Get formatted peak memory usage.
     *
     * @return string
     */
    public function getFormattedPeakMemoryUsage(): string
    {
        return $this->formatBytes($this->peakMemoryUsage);
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = \max($bytes, 0);
        $pow = \floor(($bytes ? \log($bytes) : 0) / \log(1024));
        $pow = \min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return \sprintf('%.2f%s', $bytes, $units[$pow]);
    }

    /**
     * Check if metrics are currently being tracked.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Add a listener to be notified of metric updates.
     *
     * @param callable $listener
     */
    public function addListener(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Notify all listeners of metric updates.
     */
    private function notifyListeners(): void
    {
        $metrics = $this->getMetrics();

        foreach ($this->listeners as $listener) {
            \call_user_func($listener, $metrics);
        }
    }

    /**
     * Get completion future for awaiting metrics completion.
     *
     * @return DeferredFuture|null
     */
    public function getCompletionFuture(): ?DeferredFuture
    {
        return $this->completionFuture;
    }
}
