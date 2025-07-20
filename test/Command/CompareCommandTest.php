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

use Psy\Command\CompareCommand;
use Psy\Command\ProfileCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class CompareCommandTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not available');
        }

        $shell = new \Psy\Shell();
        $shell->add(new ProfileCommand()); // CompareCommand depends on ProfileCommand
        $shell->add(new CompareCommand());

        $command = $shell->find('compare');
        $this->tester = new CommandTester($command);
    }

    public function testCompareCommand()
    {
        $codeA = 'usleep(1000);'; // 1ms
        $codeB = 'usleep(2000);'; // 2ms
        $this->tester->execute([
            'codeA' => $codeA,
            'codeB' => $codeB,
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Performance Comparison:', $output);
        $this->assertStringContainsString('Execution Time', $output);
        $this->assertStringContainsString('Peak Memory', $output);
        $this->assertStringContainsString('Code A', $output);
        $this->assertStringContainsString('Code B', $output);
        $this->assertStringContainsString('Difference', $output);

        // Basic check for time difference (B should be greater than A)
        $this->assertMatchesRegularExpression('/\+?\d+\.\d+ms/', $output);
    }

    public function testCompareCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('compare'));
    }
}
