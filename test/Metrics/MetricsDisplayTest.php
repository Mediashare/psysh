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

use Psy\Context;
use Psy\Metrics\MetricsCollector;
use Psy\Metrics\MetricsDisplay;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class MetricsDisplayTest extends TestCase
{
    public function testEnableDisable()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        
        $this->assertTrue($display->isEnabled());
        
        $display->disable();
        $this->assertFalse($display->isEnabled());
        
        $display->enable();
        $this->assertTrue($display->isEnabled());
    }

    public function testDisplayWhenDisabled()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        $display->disable();
        $display->display($output, $context);
        
        $this->assertEquals('', $output->fetch());
    }

    public function testDisplayWithMetrics()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        // Simulate command execution
        $collector->startCommand();
        usleep(1000); // 1ms
        $collector->endCommand();
        
        $display->display($output, $context);
        
        $result = $output->fetch();
        
        // Should contain metrics display
        $this->assertNotEquals('', $result);
        $this->assertStringContainsString('Time:', $result);
        $this->assertStringContainsString('Memory:', $result);
    }

    public function testDisplayWithVariables()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        // Add some variables to context
        $context->setAll(['x' => 1, 'y' => 2, 'z' => 3, '_' => null]);
        
        $collector->startCommand();
        $collector->endCommand();
        
        $display->display($output, $context);
        
        $result = $output->fetch();
        
        // Should show variable count
        $this->assertStringContainsString('Vars:', $result);
        $this->assertStringContainsString('4', $result);
    }

    public function testDisplayWithCommandCount()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        // Execute multiple commands
        for ($i = 0; $i < 5; $i++) {
            $collector->startCommand();
            $collector->endCommand();
        }
        
        $display->display($output, $context);
        
        $result = $output->fetch();
        
        // Should show command count
        $this->assertStringContainsString('Cmds:', $result);
        $this->assertStringContainsString('5', $result);
    }

    public function testDisplayQuietOutput()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $output->setVerbosity(BufferedOutput::VERBOSITY_QUIET);
        $context = new Context();
        
        $collector->startCommand();
        $collector->endCommand();
        
        $display->display($output, $context);
        
        // Should not display anything when output is quiet
        $this->assertEquals('', $output->fetch());
    }

    public function testDisplayFormatting()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        $collector->startCommand();
        usleep(5000); // 5ms
        $collector->endCommand();
        
        $display->display($output, $context);
        
        $result = $output->fetch();
        
        // Should have box drawing characters
        $this->assertStringContainsString('┌─', $result);
        $this->assertStringContainsString('─┘', $result);
        $this->assertStringContainsString('│', $result);
    }

    public function testDisplayWithCwd()
    {
        $collector = new MetricsCollector();
        $display = new MetricsDisplay($collector);
        $output = new BufferedOutput();
        $context = new Context();
        
        $collector->startCommand();
        $collector->endCommand();
        
        $display->display($output, $context);
        
        $result = $output->fetch();
        
        // Should show CWD
        $this->assertStringContainsString('CWD:', $result);
    }
}

