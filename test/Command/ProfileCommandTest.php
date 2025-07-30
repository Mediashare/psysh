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

use Psy\Command\ProfileCommand;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class ProfileCommandTest extends \Psy\Test\TestCase
{
    private $command;

    protected function setUp(): void
    {
        $this->command = new ProfileCommand();
        $this->command->setApplication(new Shell());
    }

    public function testProfileCommand()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "hello";',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Memory:', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Calls', $output);
        $this->assertStringContainsString('Time', $output);
        $this->assertStringContainsString('Memory', $output);
    }

    public function testProfileCommandWithContext()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded.');
        }

        $shell = new Shell();
        $shell->setScopeVariables(['a' => 1, 'b' => 2]);
        $this->command->setApplication($shell);

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => '$a + $b',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Memory:', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Calls', $output);
        $this->assertStringContainsString('Time', $output);
        $this->assertStringContainsString('Memory', $output);
    }
}