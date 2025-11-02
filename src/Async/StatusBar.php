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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Status bar for displaying real-time metrics.
 */
class StatusBar
{
    private OutputInterface $output;
    private bool $isVisible = false;
    private string $lastContent = '';
    private bool $enabled = true;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Enable or disable the status bar.
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;

        if (!$enabled && $this->isVisible) {
            $this->hide();
        }
    }

    /**
     * Check if status bar is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Update the status bar content.
     *
     * @param string $content
     */
    public function update(string $content): void
    {
        if (!$this->enabled) {
            return;
        }

        // Only update if content has changed
        if ($content === $this->lastContent) {
            return;
        }

        $this->lastContent = $content;

        if ($this->isVisible) {
            $this->clearLine();
        }

        $this->show($content);
    }

    /**
     * Show status bar with content.
     *
     * @param string $content
     */
    private function show(string $content): void
    {
        if (!$this->enabled) {
            return;
        }

        // Save cursor position, move to bottom, display content
        $this->output->write("\033[s", false);  // Save cursor position
        $this->output->write("\033[999;1H", false);  // Move to bottom
        $this->output->write("\033[K", false);  // Clear line
        $this->output->write($this->formatContent($content), false);
        $this->output->write("\033[u", false);  // Restore cursor position

        $this->isVisible = true;
    }

    /**
     * Hide the status bar.
     */
    public function hide(): void
    {
        if (!$this->isVisible || !$this->enabled) {
            return;
        }

        $this->clearLine();
        $this->isVisible = false;
        $this->lastContent = '';
    }

    /**
     * Clear the status bar line.
     */
    private function clearLine(): void
    {
        $this->output->write("\033[s", false);  // Save cursor position
        $this->output->write("\033[999;1H", false);  // Move to bottom
        $this->output->write("\033[K", false);  // Clear line
        $this->output->write("\033[u", false);  // Restore cursor position
    }

    /**
     * Format content with styling.
     *
     * @param string $content
     * @return string
     */
    private function formatContent(string $content): string
    {
        // Add background and styling for the status bar
        if ($this->output->isDecorated()) {
            return "<bg=blue;fg=white> {$content} </>";
        }

        return " {$content} ";
    }

    /**
     * Update with metrics data.
     *
     * @param array $metrics
     */
    public function updateWithMetrics(array $metrics): void
    {
        if (!$this->enabled) {
            return;
        }

        $content = \sprintf(
            'Execution: %s | Memory: %s | Peak: %s',
            $this->formatTime($metrics['execution_time'] ?? 0),
            $this->formatBytes($metrics['memory_usage'] ?? 0),
            $this->formatBytes($metrics['peak_memory_usage'] ?? 0)
        );

        $this->update($content);
    }

    /**
     * Format time for display.
     *
     * @param float $time
     * @return string
     */
    private function formatTime(float $time): string
    {
        if ($time < 0.001) {
            return \sprintf('%.2fμs', $time * 1000000);
        } elseif ($time < 1) {
            return \sprintf('%.2fms', $time * 1000);
        } else {
            return \sprintf('%.2fs', $time);
        }
    }

    /**
     * Format bytes for display.
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
}
