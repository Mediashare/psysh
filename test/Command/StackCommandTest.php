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

use Psy\Command\StackCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Psy\Configuration;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @group Xdebug
 */
class StackCommandTest extends TestCase
{
    public function testStackCommandIsRegistered()
    {
        $shell = new Shell();
        $this->assertTrue($shell->has('stack'));
    }

    public function testStackCommand()
    {
        $output = $this->callStackTestFunction('test', 123);

        $this->assertStringContainsString('Call Stack:', $output);
        $this->assertStringContainsString('Psy\Test\Command\StackCommandTest->callStackTestFunction()', $output);
        $this->assertStringNotContainsString('(\'test\', 123)', $output);
    }

    public function testStackCommandAnnotate()
    {
        $output = $this->callStackTestFunctionAnnotated('annotated', 456);

        $this->assertStringContainsString('Call Stack:', $output);
        $this->assertStringContainsString('callStackTestFunctionAnnotated(', $output);
        $this->assertStringContainsString('\'annotated\', 456', $output);
    }

    private function callStackTestFunction($arg1, $arg2)
    {
        $config = $this->getMockBuilder(Configuration::class)->getMock();
        $formatter = $this->getMockBuilder(OutputFormatter::class)->getMock();
        $presenter = $this->getMockBuilder(Presenter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['present'])
            ->getMock();
        $presenter->method('present')->willReturnCallback(function ($val) { 
            if (is_object($val)) {
                return get_class($val) . ' Object';
            }
            return var_export($val, true); 
        });
        $config->method('getPresenter')->willReturn($presenter);

        $shell = new Shell($config);
        $shell->add(new StackCommand());
        $command = $shell->find('stack');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $command->run($input, $output);

        return $output->fetch();
    }

    private function callStackTestFunctionAnnotated($arg1, $arg2)
    {
        $config = $this->getMockBuilder(Configuration::class)->getMock();
        $formatter = $this->getMockBuilder(OutputFormatter::class)->getMock();
        $presenter = $this->getMockBuilder(Presenter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['present'])
            ->getMock();
        $presenter->method('present')->willReturnCallback(function ($val) { 
            if (is_object($val)) {
                return get_class($val) . ' Object';
            }
            return var_export($val, true); 
        });
        $config->method('getPresenter')->willReturn($presenter);

        $shell = new Shell($config);
        $shell->add(new StackCommand());
        $command = $shell->find('stack');

        $input = new ArrayInput(['--annotate' => true]);
        $output = new BufferedOutput();

        $command->run($input, $output);

        return $output->fetch();
    }
}
