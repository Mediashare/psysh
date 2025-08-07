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

use Psy\Command\CoverageCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class CoverageCommandTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        if (!\extension_loaded('xdebug') && !\extension_loaded('pcov')) {
            $this->markTestSkipped('Xdebug or PCOV extension is not available');
        }

        $shell = new \Psy\Shell();
        $shell->add(new CoverageCommand());

        $command = $shell->find('coverage');
        $this->tester = new CommandTester($command);
    }

    public function testCoverageCommand()
    {
        $code = 'if (true) { echo "hello"; } else { echo "world"; }';
        $this->tester->execute(['code' => $code]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Code Coverage Summary:', $output);
    }

    public function testCoverageCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('coverage'));
    }
}
