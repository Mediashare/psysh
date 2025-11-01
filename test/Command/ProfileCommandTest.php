<?php

namespace Psy\Test\Command;

use Psy\Command\ProfileCommand;
use Psy\Profiling\XhprofEngine;
use Psy\Profiling\XdebugInProcessEngine;
use Psy\Profiling\XdebugSubprocessEngine;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class ProfileCommandTest extends \Psy\Test\TestCase
{
    private $command;
    private $shell;

    protected function setUp(): void
    {
        $this->shell = new Shell();
        $this->command = new ProfileCommand();
        $this->command->setApplication($this->shell);
    }

    private function requiresXdebugOrXhprof()
    {
        if (
            !XhprofEngine::isAvailable() &&
            !XdebugInProcessEngine::isAvailable() &&
            !XdebugSubprocessEngine::isAvailable()
        ) {
            $this->markTestSkipped('No suitable profiling engine is available.');
        }
    }

    public function testBasicProfileCommand()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "hello";',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Memory:', $output);
    }

    public function testOutOption()
    {
        $this->requiresXdebugOrXhprof();

        $tempFile = tempnam(sys_get_temp_dir(), 'psysh_test_');
        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--out' => $tempFile,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profile data saved to:', $output);
        $this->assertFileExists($tempFile);
        $this->assertJson(file_get_contents($tempFile));
        unlink($tempFile);
    }

    public function testInvalidOutPath()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--out' => '/invalid/path/to/file',
        ]);

        $this->assertStringContainsString('failed to save profile data', $tester->getDisplay());
    }

    public function testErrorInProfiledCode()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'throw new \Exception("test error");',
        ]);

        // The command should not crash, but might show a warning or error from the execution loop.
        // Depending on the engine, the error might be caught and displayed.
        // For now, just assert that the command completes.
        $this->assertStringContainsString('Total execution', $tester->getDisplay());
    }

    public function testReplScopeHandling()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->execute('class MyProfileTestClass { public static function run() { return 123; } }');
        $this->shell->execute('define("MY_PROFILE_TEST_CONST", 456);');
        $this->shell->setScopeVariables(['my_profile_var' => 789]);

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'MyProfileTestClass::run() + MY_PROFILE_TEST_CONST + $my_profile_var',
            '--engine' => 'xdebug-subprocess', // Force subprocess engine to test context serialization
            '--debug' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringNotContainsString('Undefined', $output);
    }
}
