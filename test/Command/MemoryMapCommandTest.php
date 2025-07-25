<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\MemoryMapCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class MemoryMapCommandTest extends TestCase
{
    private $command;

    protected function setUp(): void
    {
        $this->command = new MemoryMapCommand();
        $this->command->setApplication(new Shell());
    }

    public function testMemoryMapCommand()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'str_repeat("x", 1000);',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Memory Usage Visualization', $output);
        $this->assertStringContainsString('Total Memory Usage:', $output);
        $this->assertStringContainsString('Memory Usage Chart:', $output);
        $this->assertStringContainsString('Memory Usage Details:', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Memory (KB)', $output);
        $this->assertStringContainsString('Memory (bytes)', $output);
        $this->assertStringContainsString('Calls', $output);
        $this->assertStringContainsString('Avg per Call', $output);
        $this->assertStringContainsString('Legend:', $output);
    }

    public function testMemoryMapCommandWithWidth()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'array_fill(0, 100, "test");',
            '--width' => '40',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Memory Usage Visualization', $output);
        $this->assertStringContainsString('Memory Usage Chart:', $output);
    }

    public function testMemoryMapCommandWithOutFile()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $outFile = \tempnam(\sys_get_temp_dir(), 'memorymap');
        $tester->execute([
            'code' => 'echo "hello";',
            '--out' => $outFile,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling data saved to:', $output);
        $this->assertFileExists($outFile);
        \unlink($outFile);
    }

    public function testMemoryMapCommandWithoutXdebug()
    {
        if (\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is loaded, cannot test without it.');
        }

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'code' => 'echo "hello";',
        ]);

        $this->assertEquals(1, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Xdebug extension is not loaded', $output);
    }

    public function testMemoryMapCommandWithComplexCode()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'for($i=0;$i<10;$i++) { $data[] = str_repeat("x", 100); }',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Memory Usage Visualization', $output);
        // Should show some visual bars
        $this->assertStringContainsString('█', $output);
    }
}
