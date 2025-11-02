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
use Psy\Async\AsyncExecutionWrapper;
use Psy\Async\StatusBar;
use Symfony\Component\Console\Output\BufferedOutput;

class AsyncExecutionWrapperTest extends TestCase
{
    public function testExecuteWithoutAsyncWrapper()
    {
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);
        $wrapper->setEnabled(false);

        $result = $wrapper->execute(function () {
            return 'test result';
        });

        $this->assertEquals('test result', $result);
        $this->assertFalse($manager->isRunning());
    }

    public function testExecuteWithAsyncWrapper()
    {
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);

        $result = $wrapper->execute(function () {
            \usleep(50000); // 50ms
            return 'async test';
        });

        $this->assertEquals('async test', $result);
        $this->assertFalse($manager->isRunning());

        $metrics = $manager->getMetrics();
        $this->assertGreaterThan(0, $metrics['execution_time']);
    }

    public function testExecuteWithException()
    {
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $wrapper->execute(function () {
                throw new \RuntimeException('Test exception');
            });
        } finally {
            $this->assertFalse($manager->isRunning());
        }
    }

    public function testExecuteWithStatusBar()
    {
        $output = new BufferedOutput();
        $manager = new AsyncMetricsManager();
        $statusBar = new StatusBar($output);
        $wrapper = new AsyncExecutionWrapper($manager, $statusBar);

        $result = $wrapper->execute(function () {
            \usleep(50000); // 50ms
            return 'test with status bar';
        });

        $this->assertEquals('test with status bar', $result);
        $this->assertFalse($manager->isRunning());
    }

    public function testIsEnabled()
    {
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);

        $this->assertTrue($wrapper->isEnabled());

        $wrapper->setEnabled(false);
        $this->assertFalse($wrapper->isEnabled());
    }

    public function testGetMetricsManager()
    {
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);

        $this->assertSame($manager, $wrapper->getMetricsManager());
    }

    public function testGetStatusBar()
    {
        $output = new BufferedOutput();
        $manager = new AsyncMetricsManager();
        $statusBar = new StatusBar($output);
        $wrapper = new AsyncExecutionWrapper($manager, $statusBar);

        $this->assertSame($statusBar, $wrapper->getStatusBar());
    }

    public function testSetStatusBar()
    {
        $output = new BufferedOutput();
        $manager = new AsyncMetricsManager();
        $wrapper = new AsyncExecutionWrapper($manager);

        $this->assertNull($wrapper->getStatusBar());

        $statusBar = new StatusBar($output);
        $wrapper->setStatusBar($statusBar);

        $this->assertSame($statusBar, $wrapper->getStatusBar());
    }
}
