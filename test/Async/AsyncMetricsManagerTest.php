<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Async;

use PHPUnit\Framework\TestCase;
use Psy\Async\AsyncMetricsManager;

class AsyncMetricsManagerTest extends TestCase
{
    public function testStartAndStopTracking()
    {
        $manager = new AsyncMetricsManager();

        $this->assertFalse($manager->isRunning());

        $manager->start();
        $this->assertTrue($manager->isRunning());

        // Give it a moment to track some metrics
        \usleep(100000); // 100ms

        $manager->stop();
        $this->assertFalse($manager->isRunning());

        $metrics = $manager->getMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('execution_time', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('peak_memory_usage', $metrics);
        $this->assertArrayHasKey('is_running', $metrics);
        $this->assertFalse($metrics['is_running']);
        $this->assertGreaterThan(0, $metrics['execution_time']);
    }

    public function testGetFormattedExecutionTime()
    {
        $manager = new AsyncMetricsManager();

        $manager->start();
        \usleep(100000); // 100ms
        $manager->stop();

        $formatted = $manager->getFormattedExecutionTime();
        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d+\.\d+(μs|ms|s)/', $formatted);
    }

    public function testGetFormattedMemoryUsage()
    {
        $manager = new AsyncMetricsManager();

        $manager->start();
        $manager->stop();

        $formatted = $manager->getFormattedMemoryUsage();
        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d+\.\d+(B|KB|MB|GB)/', $formatted);
    }

    public function testGetFormattedPeakMemoryUsage()
    {
        $manager = new AsyncMetricsManager();

        $manager->start();
        $manager->stop();

        $formatted = $manager->getFormattedPeakMemoryUsage();
        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d+\.\d+(B|KB|MB|GB)/', $formatted);
    }

    public function testMetricsListener()
    {
        $manager = new AsyncMetricsManager();
        $callbackExecuted = false;
        $receivedMetrics = null;

        $manager->addListener(function ($metrics) use (&$callbackExecuted, &$receivedMetrics) {
            $callbackExecuted = true;
            $receivedMetrics = $metrics;
        });

        $manager->start();
        \usleep(150000); // 150ms to ensure listener is called
        $manager->stop();

        $this->assertTrue($callbackExecuted);
        $this->assertIsArray($receivedMetrics);
        $this->assertArrayHasKey('execution_time', $receivedMetrics);
    }
}
