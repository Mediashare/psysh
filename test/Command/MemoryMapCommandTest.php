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

use Psy\Command\MemoryMapCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group Xdebug
 */
class MemoryMapCommandTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not available');
        }

        $shell = new \Psy\Shell();
        $shell->add(new MemoryMapCommand());

        $command = $shell->find('memory-map');
        $this->tester = new CommandTester($command);
    }

    public function testMemoryMapCommand()
    {
        $code = 'str_repeat("a", 1024 * 1024);'; // Allocate 1MB
        $this->tester->execute(['code' => $code]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Memory Usage Summary:', $output);
        $this->assertStringContainsString('Peak Memory:', $output);
        // We can't assert exact memory usage due to PHP overhead, but we can check for MB or KB
        $this->assertMatchesRegularExpression('/Peak Memory: \d+(\.\d+)? (MB|KB|B)/', $output);
    }

    public function testMemoryMapCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('memory-map'));
    }
}
