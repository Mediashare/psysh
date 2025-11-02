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
 * Wraps code execution with async metrics tracking.
 */
class AsyncExecutionWrapper
{
    private AsyncMetricsManager $metricsManager;
    private ?StatusBar $statusBar = null;
    private bool $enabled = true;

    public function __construct(AsyncMetricsManager $metricsManager, ?StatusBar $statusBar = null)
    {
        $this->metricsManager = $metricsManager;
        $this->statusBar = $statusBar;
    }

    /**
     * Enable or disable async execution wrapper.
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if async execution wrapper is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Execute code with metrics tracking.
     *
     * @param callable $callable The code to execute
     * @return mixed The result of the callable
     */
    public function execute(callable $callable)
    {
        if (!$this->enabled) {
            return $callable();
        }

        // Setup metrics listener for status bar
        if ($this->statusBar !== null) {
            $this->metricsManager->addListener(function (array $metrics) {
                $this->statusBar->updateWithMetrics($metrics);
            });
        }

        // Start metrics tracking
        $this->metricsManager->start();

        $result = null;
        $exception = null;

        try {
            // Execute the code
            $result = $callable();
        } catch (\Throwable $e) {
            $exception = $e;
        } finally {
            // Stop metrics tracking
            $this->metricsManager->stop();

            // Hide status bar
            if ($this->statusBar !== null) {
                $this->statusBar->hide();
            }
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Execute code asynchronously with metrics tracking.
     *
     * @param callable $callable The code to execute
     * @return DeferredFuture
     */
    public function executeAsync(callable $callable): DeferredFuture
    {
        $deferred = new DeferredFuture();

        if (!$this->enabled) {
            EventLoop::defer(function () use ($callable, $deferred) {
                try {
                    $result = $callable();
                    $deferred->complete($result);
                } catch (\Throwable $e) {
                    $deferred->error($e);
                }
            });

            return $deferred;
        }

        // Setup metrics listener for status bar
        if ($this->statusBar !== null) {
            $this->metricsManager->addListener(function (array $metrics) {
                $this->statusBar->updateWithMetrics($metrics);
            });
        }

        EventLoop::defer(function () use ($callable, $deferred) {
            $this->metricsManager->start();

            try {
                $result = $callable();
                $deferred->complete($result);
            } catch (\Throwable $e) {
                $deferred->error($e);
            } finally {
                $this->metricsManager->stop();

                if ($this->statusBar !== null) {
                    $this->statusBar->hide();
                }
            }
        });

        return $deferred;
    }

    /**
     * Get the metrics manager.
     *
     * @return AsyncMetricsManager
     */
    public function getMetricsManager(): AsyncMetricsManager
    {
        return $this->metricsManager;
    }

    /**
     * Get the status bar.
     *
     * @return StatusBar|null
     */
    public function getStatusBar(): ?StatusBar
    {
        return $this->statusBar;
    }

    /**
     * Set the status bar.
     *
     * @param StatusBar|null $statusBar
     */
    public function setStatusBar(?StatusBar $statusBar): void
    {
        $this->statusBar = $statusBar;
    }
}
