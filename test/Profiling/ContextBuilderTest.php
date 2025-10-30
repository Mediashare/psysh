<?php

namespace Psy\Test\Profiling;

use Psy\Profiling\ContextBuilder;
use Psy\Shell;
use Psy\Test\TestCase;

class ContextBuilderTest extends TestCase
{
    public function testBuildContextScript()
    {
        $shell = new Shell();

        // Set up context in the shell
        $shell->setScopeVariables([
            'my_var' => 'hello',
            'my_array' => [1, 2, 3],
            'my_object' => (object)['a' => 1],
        ]);

        define('MY_CONSTANT', 'my_value');

        // Mock user-defined class
        $shell->addCode('class MyClass { public function myMethod() { return 1; } }');

        $script = ContextBuilder::buildContextScript($shell);

        // Assertions
        $this->assertStringContainsString('require_once', $script);
        $this->assertStringContainsString('$my_var = \'hello\';', $script);
        $this->assertStringContainsString('$my_array = [1, 2, 3];', $script);
        $this->assertStringContainsString('unserialize', $script);
        $this->assertStringContainsString('if (!defined(\'MY_CONSTANT\')) define(\'MY_CONSTANT\', \'my_value\');', $script);
        $this->assertStringContainsString('class MyClass', $script);
    }
}
