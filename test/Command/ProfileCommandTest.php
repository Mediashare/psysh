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

use Psy\Command\ProfileCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class ProfileCommandTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not available');
        }

        $this->tester = new CommandTester(new ProfileCommand());
    }

    public function testProfileCommand()
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

    public function testProfileCommandWithExport()
    {
        $code = 'echo "hello";';
        $outputFile = \tempnam(\sys_get_temp_dir(), 'profile_');
        $this->tester->execute(['code' => $code, '--out' => $outputFile]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Profiler output saved to:', $output);
        $this->assertFileExists($outputFile);

        @\unlink($outputFile);
    }

    public function testProfileCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('profile'));
    }
}
