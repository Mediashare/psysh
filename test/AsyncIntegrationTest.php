<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use PHPUnit\Framework\TestCase;
use Psy\Configuration;
use Psy\Shell;

class AsyncIntegrationTest extends TestCase
{
    public function testShellWithAsyncDisabled()
    {
        $config = new Configuration([
            'useAsyncMetrics' => false,
            'useStatusBar' => false,
        ]);

        $shell = new Shell($config);

        $this->assertNull($shell->getAsyncExecutionWrapper());
        $this->assertNull($shell->getAsyncMetricsManager());
        $this->assertNull($shell->getStatusBar());
    }

    public function testShellWithAsyncEnabled()
    {
        $config = new Configuration([
            'useAsyncMetrics' => true,
            'useStatusBar' => false,
        ]);

        $shell = new Shell($config);

        $this->assertNotNull($shell->getAsyncExecutionWrapper());
        $this->assertNotNull($shell->getAsyncMetricsManager());
        $this->assertNull($shell->getStatusBar());
    }

    public function testShellWithAsyncAndStatusBarEnabled()
    {
        $config = new Configuration([
            'useAsyncMetrics' => true,
            'useStatusBar' => true,
        ]);

        $shell = new Shell($config);

        $this->assertNotNull($shell->getAsyncExecutionWrapper());
        $this->assertNotNull($shell->getAsyncMetricsManager());
        $this->assertNotNull($shell->getStatusBar());
    }

    public function testConfigurationGettersAndSetters()
    {
        $config = new Configuration();

        // Test default values
        $this->assertFalse($config->useAsyncMetrics());
        $this->assertFalse($config->useStatusBar());

        // Test setters
        $config->setUseAsyncMetrics(true);
        $this->assertTrue($config->useAsyncMetrics());

        $config->setUseStatusBar(true);
        $this->assertTrue($config->useStatusBar());

        // Test disabling
        $config->setUseAsyncMetrics(false);
        $this->assertFalse($config->useAsyncMetrics());

        $config->setUseStatusBar(false);
        $this->assertFalse($config->useStatusBar());
    }

    public function testAsyncExecutionWrapperIntegration()
    {
        $config = new Configuration([
            'useAsyncMetrics' => true,
        ]);

        $shell = new Shell($config);
        $wrapper = $shell->getAsyncExecutionWrapper();

        $this->assertNotNull($wrapper);
        $this->assertTrue($wrapper->isEnabled());

        // Test execution
        $result = $wrapper->execute(function () {
            return 'test_result';
        });

        $this->assertEquals('test_result', $result);

        // Test metrics were tracked
        $metrics = $wrapper->getMetricsManager()->getMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('execution_time', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
    }

    public function testStatusBarIntegration()
    {
        $config = new Configuration([
            'useAsyncMetrics' => true,
            'useStatusBar' => true,
        ]);

        $shell = new Shell($config);
        $statusBar = $shell->getStatusBar();

        $this->assertNotNull($statusBar);
        $this->assertTrue($statusBar->isEnabled());

        // Test disabling
        $statusBar->setEnabled(false);
        $this->assertFalse($statusBar->isEnabled());

        // Test re-enabling
        $statusBar->setEnabled(true);
        $this->assertTrue($statusBar->isEnabled());
    }

    public function testAsyncCommandIsRegistered()
    {
        $shell = new Shell();
        $command = $shell->get('async');

        $this->assertInstanceOf(\Psy\Command\AsyncCommand::class, $command);
        $this->assertTrue($shell->has('async'));
        $this->assertTrue($shell->has('metrics')); // alias
    }
}
