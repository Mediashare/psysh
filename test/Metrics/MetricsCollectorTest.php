<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Metrics;

use Psy\Metrics\MetricsCollector;
use Psy\Test\TestCase;

class MetricsCollectorTest extends TestCase
{
    public function testStartAndEndCommand()
    {
        $collector = new MetricsCollector();
        
        $this->assertEquals(0, $collector->getCommandCount());
        $this->assertEquals(0.0, $collector->getLastExecutionTime());
        
        $collector->startCommand();
        usleep(10000); // Sleep for 10ms
        $collector->endCommand();
        
        $this->assertEquals(1, $collector->getCommandCount());
        $this->assertGreaterThan(0.0, $collector->getLastExecutionTime());
        $this->assertGreaterThanOrEqual(0.01, $collector->getLastExecutionTime());
    }

    public function testMultipleCommands()
    {
        $collector = new MetricsCollector();
        
        $collector->startCommand();
        $collector->endCommand();
        
        $collector->startCommand();
        $collector->endCommand();
        
        $collector->startCommand();
        $collector->endCommand();
        
        $this->assertEquals(3, $collector->getCommandCount());
    }

    public function testMemoryTracking()
    {
        $collector = new MetricsCollector();
        
        $currentMemory = $collector->getCurrentMemory();
        $this->assertGreaterThan(0, $currentMemory);
        
        // Force memory allocation
        $data = str_repeat('x', 1024 * 1024); // 1MB
        
        $collector->startCommand();
        $collector->endCommand();
        
        $peakMemory = $collector->getPeakMemory();
        $this->assertGreaterThanOrEqual($currentMemory, $peakMemory);
        
        unset($data);
    }

    public function testErrorTracking()
    {
        $collector = new MetricsCollector();
        
        $this->assertFalse($collector->hasError());
        $this->assertNull($collector->getLastError());
        
        $collector->recordError(E_WARNING, 'Test warning');
        
        $this->assertTrue($collector->hasError());
        $this->assertEquals('Test warning', $collector->getLastError());
        $this->assertEquals(E_WARNING, $collector->getLastErrorLevel());
        
        $collector->clearError();
        
        $this->assertFalse($collector->hasError());
        $this->assertNull($collector->getLastError());
    }

    public function testReset()
    {
        $collector = new MetricsCollector();
        
        $collector->startCommand();
        $collector->endCommand();
        $collector->recordError(E_NOTICE, 'Test error');
        
        $this->assertEquals(1, $collector->getCommandCount());
        $this->assertTrue($collector->hasError());
        
        $collector->reset();
        
        $this->assertEquals(0, $collector->getCommandCount());
        $this->assertEquals(0.0, $collector->getLastExecutionTime());
        $this->assertFalse($collector->hasError());
        $this->assertNull($collector->getLastError());
    }

    public function testEndCommandWithoutStart()
    {
        $collector = new MetricsCollector();
        
        $collector->endCommand();
        
        // Should not crash and execution time should still be 0
        $this->assertEquals(0.0, $collector->getLastExecutionTime());
        // Command count should NOT increment since startCommand wasn't called
        $this->assertEquals(0, $collector->getCommandCount());
    }
}
