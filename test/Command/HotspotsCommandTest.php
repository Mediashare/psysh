<?php

/*
 * This file is part of PsySH
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
    private $tester;

    protected function setUp(): void
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not available');
        }

        $shell = new \Psy\Shell();
        $shell->add(new \Psy\Command\ProfileCommand());
        $shell->add(new HotspotsCommand());

        $command = $shell->find('hotspots');
        $this->tester = new CommandTester($command);
    }

    public function testHotspotsCommand()
    {
        $code = 'echo "hello";';
        $this->tester->execute(['code' => $code]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Profiling summary:', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Time (self)', $output);
        $this->assertStringContainsString('%', $output);
        $this->assertStringContainsString('Calls', $output);
    }

    public function testHotspotsCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('hotspots'));
    }
}
