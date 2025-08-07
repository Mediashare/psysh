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
    private $shell;

    protected function setUp(): void
    {
        $this->shell = new Shell();
        $this->command = new ProfileCommand();
        $this->command->setApplication($this->shell);
    }

    protected function tearDown(): void
    {
        // Clean up any temporary files created during tests
        $tempFiles = glob(sys_get_temp_dir() . '/profile_*.json');
        foreach ($tempFiles as $file) {
            @unlink($file);
        }
    }

    private function requiresXdebugOrXhprof()
    {
        if (!\extension_loaded('xdebug') && !\extension_loaded('xhprof')) {
            $this->markTestSkipped('Either Xdebug or XHProf extension is required.');
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
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Calls', $output);
        $this->assertStringContainsString('Time', $output);
        $this->assertStringContainsString('Memory', $output);
    }

    public function testProfileCommandWithContext()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->setScopeVariables(['a' => 1, 'b' => 2]);

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

    public function testProfileCommandWithClosure()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->setScopeVariables([
            'myClosure' => function () {
                return 'hello from closure';
            },
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo $myClosure();',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringNotContainsString('could not be serialized', $output);
    }

    public function testOutOption()
    {
        $this->requiresXdebugOrXhprof();

        $tempFile = sys_get_temp_dir() . '/profile_test.json';
        
        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--out' => $tempFile,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profile data saved to:', $output);
        $this->assertStringContainsString($tempFile, $output);
        $this->assertFileExists($tempFile);
        
        $data = json_decode(file_get_contents($tempFile), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        
        @unlink($tempFile);
    }

    public function testFullOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--full' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (all functions)', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testFilterUserOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'user',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (user code only)', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testFilterPhpOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'php',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (php)', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testFilterAllOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'all',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (all functions)', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testThresholdOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'usleep(1000);', // Sleep for 1000 microseconds
            '--threshold' => 500, // 500 microseconds threshold
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        // Should show functions that exceed the threshold
    }

    public function testThresholdTooHighOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "fast";',
            '--threshold' => 999999999, // Very high threshold
        ]);

        $output = $tester->getDisplay();
        // Should show minimal data or fallback message
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testShowParamsOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
            '--show-params' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Parameters', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testFullNamespacesOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--full-namespaces' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        // Full namespaces should be shown without truncation
    }

    public function testTraceAllOption()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is required for --trace-all option.');
        }
        
        if (!function_exists('xdebug_start_trace')) {
            $this->markTestSkipped('Xdebug trace functions are not available.');
        }

        // Check if tracing is enabled in Xdebug configuration
        if (!ini_get('xdebug.mode') || !str_contains(ini_get('xdebug.mode'), 'trace')) {
            $this->markTestSkipped('Xdebug tracing mode is not enabled.');
        }

        try {
            $tester = new CommandTester($this->command);
            $tester->execute([
                'code' => 'strlen("test");',
                '--trace-all' => true,
            ]);

            $output = $tester->getDisplay();
            $this->assertStringContainsString('Total execution', $output);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not enabled')) {
                $this->markTestSkipped('Xdebug functionality is not enabled: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    public function testDebugOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--debug' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Debug:', $output);
        $this->assertStringContainsString('filterLevel=', $output);
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testCombinedOptions()
    {
        $this->requiresXdebugOrXhprof();

        $tempFile = sys_get_temp_dir() . '/profile_combined_test.json';
        
        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("combined test");',
            '--out' => $tempFile,
            '--full' => true,
            '--threshold' => 1000,
            '--show-params' => true,
            '--full-namespaces' => true,
            '--debug' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Debug:', $output);
        $this->assertStringContainsString('Profiling results (all functions)', $output);
        $this->assertStringContainsString('Parameters', $output);
        $this->assertStringContainsString('Profile data saved to:', $output);
        $this->assertStringContainsString('Total execution', $output);
        
        $this->assertFileExists($tempFile);
        @unlink($tempFile);
    }

    public function testInvalidFilterOption()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--filter' => 'invalid',
        ]);

        $output = $tester->getDisplay();
        // Should still execute with fallback to default
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testNegativeThreshold()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--threshold' => -1000,
        ]);

        $output = $tester->getDisplay();
        // Should handle negative threshold gracefully
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testComplexCodeWithAllOptions()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->setScopeVariables([
            'data' => ['test1', 'test2', 'test3'],
            'callback' => function($item) { return strtoupper($item); }
        ]);

        $tempFile = sys_get_temp_dir() . '/profile_complex_test.json';
        
        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'array_map($callback, $data);',
            '--out' => $tempFile,
            '--full' => true,
            '--threshold' => 0,
            '--show-params' => true,
            '--full-namespaces' => true,
            '--debug' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Debug:', $output);
        $this->assertStringContainsString('Parameters', $output);
        $this->assertStringContainsString('Profile data saved to:', $output);
        $this->assertStringContainsString('Total execution', $output);
        
        $this->assertFileExists($tempFile);
        
        $data = json_decode(file_get_contents($tempFile), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        
        @unlink($tempFile);
    }

    public function testProfileCommandWithoutExtensions()
    {
        // Temporarily disable extensions check by mocking
        if (\extension_loaded('xdebug') || \extension_loaded('xhprof')) {
            $this->markTestSkipped('Extensions are loaded, cannot test error condition.');
        }

        $tester = new CommandTester($this->command);
        
        $this->expectException(\Psy\Exception\RuntimeException::class);
        $this->expectExceptionMessage('XHProf or XDebug extension is not loaded');
        
        $tester->execute([
            'code' => 'echo "test";',
        ]);
    }

    public function testInvalidOutPath()
    {
        $this->requiresXdebugOrXhprof();

        $invalidPath = '/invalid/nonexistent/directory/profile.json';
        
        try {
            $tester = new CommandTester($this->command);
            $tester->execute([
                'code' => 'echo "test";',
                '--out' => $invalidPath,
            ]);

            $output = $tester->getDisplay();
            // Should handle invalid path gracefully and show error message
            $this->assertStringContainsString('failed to save profile data', $output);
        } catch (\Exception $e) {
            // The file_put_contents error is expected, but we need to catch it
            // to check that the command handles it gracefully
            $this->assertStringContainsString('failed to open stream', $e->getMessage());
        }
    }

    public function testTimeFormatting()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'usleep(1000);', // Sleep for 1000 microseconds
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        // Should show proper time formatting (μs, ms, s, etc.)
    }

    public function testMemoryFormatting()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => '$largeArray = array_fill(0, 1000, "test");',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Memory:', $output);
        // Should show proper memory formatting (B, KB, MB, etc.)
    }

    public function testErrorInProfiledCode()
    {
        $this->markTestSkipped("@TODO fix this test");
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        
        // This should throw an exception but still provide profiling data
        try {
            $tester->execute([
                'code' => 'throw new \Exception("Test error");',
                '--debug' => true,
            ]);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected exception from the profiled code
            $this->assertStringContainsString('Test error', $e->getMessage());
        }

        $output = $tester->getDisplay();
        // Should show debug info even when an error occurs
        $this->assertStringContainsString('Debug:', $output);
    }

    public function testErrorInProfiledVariableCode()
    {
        $this->markTestSkipped("@TODO fix this test");
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        
        // This should throw an exception but still provide profiling data
        try {
            $tester->execute([
                'code' => 'echo "test " . $a',
                '--debug' => true,
            ]);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected exception from the profiled code
            dd($e);
            $this->assertStringContainsString('Test error', $e->getMessage());
        }

        $output = $tester->getDisplay();
        // Should show debug info even when an error occurs
        $this->assertStringContainsString('Debug:', $output);
    }

    public function testLongRunningCode()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'for ($i = 0; $i < 1000; $i++) { strlen("test"); }',
            '--threshold' => 1000, // 1ms threshold
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Function', $output);
        $this->assertStringContainsString('Calls', $output);
    }

    public function testZeroThreshold()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'echo "test";',
            '--threshold' => 0,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        // With zero threshold, should show all functions
    }

    public function testVeryHighThresholdWithComplexCode()
    {
        $this->requiresXdebugOrXhprof();

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'array_sum(range(1, 100));',
            '--threshold' => 9999999, // Very high threshold
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        // Should handle gracefully even with very high threshold
    }
}