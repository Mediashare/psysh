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

use Psy\Command\HotspotsCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class HotspotsCommandTest extends TestCase
{
    private $command;

    protected function setUp(): void
    {
        $this->command = new HotspotsCommand();
        $this->command->setApplication(new Shell());
    }

    public function testHotspotsCommand()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "hello";',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Performance Hotspots Analysis', $output);
        $this->assertStringContainsString('Total Execution Time:', $output);
        $this->assertStringContainsString('Memory Usage:', $output);
        $this->assertStringContainsString('Rank', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Calls', $output);
        $this->assertStringContainsString('Time (ms)', $output);
        $this->assertStringContainsString('% of Total', $output);
        $this->assertStringContainsString('Memory (KB)', $output);
        $this->assertStringContainsString('Performance Insights:', $output);
    }

    public function testHotspotsCommandWithLimit()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'for($i=0;$i<100;$i++) { md5("test".$i); }',
            '--limit' => '5',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Performance Hotspots Analysis', $output);
        $this->assertStringContainsString('#1', $output);
    }

    public function testHotspotsCommandWithOutFile()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $outFile = \tempnam(\sys_get_temp_dir(), 'hotspots');
        $tester->execute([
            'code' => 'echo "hello";',
            '--out' => $outFile,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling data saved to:', $output);
        $this->assertFileExists($outFile);
        \unlink($outFile);
    }

    public function testHotspotsCommandWithoutXdebug()
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
}
