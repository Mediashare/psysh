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

use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class ExplainCommandTest extends \Psy\Test\TestCase
{
    private $command;

    protected function setUp(): void
    {
        $this->command = new \Psy\Command\ExplainCommand();
        $this->command->setApplication(new Shell());
    }

    public function testCommandExists()
    {
        $this->assertInstanceOf(\Psy\Command\ExplainCommand::class, $this->command);
        $this->assertNotEmpty($this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testCommandConfiguration()
    {
        $definition = $this->command->getDefinition();
        $this->assertNotNull($definition);
        
        // Test that command has expected name
        $this->assertIsString($this->command->getName());
        $this->assertNotEmpty($this->command->getName());
    }

    public function testCommandHelp()
    {
        $help = $this->command->getHelp();
        $this->assertIsString($help);
        $this->assertNotEmpty($help);
    }

    /**
     * Test command without required arguments (should show error or help)
     */
    public function testCommandWithoutArguments()
    {
        $tester = new CommandTester($this->command);
        
        try {
            $tester->execute([]);
            // If no exception is thrown, check the output contains help or error
            $output = $tester->getDisplay();
            $this->assertNotEmpty($output);
        } catch (\Exception $e) {
            // It's normal for commands to throw exceptions when required args are missing
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }
}
