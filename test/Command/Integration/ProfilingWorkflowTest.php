<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\Integration;

use Psy\Command\ProfileCommand;
use Psy\Command\HotspotsCommand;
use Psy\Command\MemoryMapCommand;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for complete profiling workflows
 *
 * Tests the interaction between different profiling commands
 * and ensures they work correctly together in real-world scenarios.
 */
class ProfilingWorkflowTest extends TestCase
{
    private $shell;

    protected function setUp(): void
    {
        $this->shell = new Shell();
    }

    protected function tearDown(): void
    {
        // Clean up any temporary files
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

    public function testCompleteProfilingWorkflow()
    {
        $this->requiresXdebugOrXhprof();

        // Step 1: Profile code
        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'for($i=0;$i<100;$i++) { md5("test".$i); }',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
        $this->assertStringContainsString('Memory:', $output);
    }

    public function testHotspotsWorkflow()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is required.');
        }

        // Analyze hotspots in complex code
        $hotspotsCommand = new HotspotsCommand();
        $hotspotsCommand->setApplication($this->shell);

        $tester = new CommandTester($hotspotsCommand);
        $tester->execute([
            'code' => 'array_map(function($n) { return $n * $n; }, range(1, 100));',
            '--limit' => '10',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Performance Hotspots Analysis', $output);
        $this->assertStringContainsString('Total Execution Time:', $output);
    }

    public function testMemoryMapWorkflow()
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is required.');
        }

        // Visualize memory usage
        $memoryMapCommand = new MemoryMapCommand();
        $memoryMapCommand->setApplication($this->shell);

        $tester = new CommandTester($memoryMapCommand);
        $tester->execute([
            'code' => '$data = []; for($i=0;$i<1000;$i++) { $data[] = str_repeat("x", 100); }',
            '--width' => '50',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Memory Usage Visualization', $output);
        $this->assertStringContainsString('Total Memory Usage:', $output);
    }

    public function testProfilingWithFileExport()
    {
        $this->requiresXdebugOrXhprof();

        $tempFile = sys_get_temp_dir() . '/profile_integration_test.json';

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'strlen("test");',
            '--out' => $tempFile,
        ]);

        $this->assertFileExists($tempFile);

        $data = json_decode(file_get_contents($tempFile), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        @unlink($tempFile);
    }

    public function testProfilingComplexAlgorithm()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->setScopeVariables([
            'quicksort' => function($arr) use (&$quicksort) {
                if (count($arr) <= 1) return $arr;
                $pivot = $arr[0];
                $left = $right = [];
                for ($i = 1; $i < count($arr); $i++) {
                    if ($arr[$i] < $pivot) $left[] = $arr[$i];
                    else $right[] = $arr[$i];
                }
                return array_merge($quicksort($left), [$pivot], $quicksort($right));
            }
        ]);

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => '$quicksort([5, 2, 8, 1, 9, 3, 7, 4, 6]);',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testProfilingWithDifferentFilters()
    {
        $this->requiresXdebugOrXhprof();

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        // Test user filter
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'user',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (user code only)', $output);

        // Test php filter
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'php',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (php)', $output);

        // Test all filter
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'strlen("test");',
            '--filter' => 'all',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Profiling results (all functions)', $output);
    }

    public function testProfilingIterativeOptimization()
    {
        $this->requiresXdebugOrXhprof();

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        // First iteration - inefficient code
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => '$result = ""; for($i=0;$i<100;$i++) { $result .= "x"; }',
        ]);

        $output1 = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output1);

        // Second iteration - optimized code
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => '$result = str_repeat("x", 100);',
        ]);

        $output2 = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output2);

        // Both should complete successfully
        $this->assertNotEmpty($output1);
        $this->assertNotEmpty($output2);
    }

    public function testProfilingWithContext()
    {
        $this->requiresXdebugOrXhprof();

        $this->shell->setScopeVariables([
            'config' => ['debug' => true, 'cache' => false],
            'service' => new class {
                public function process($data) {
                    return array_map('strtoupper', $data);
                }
            }
        ]);

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => '$service->process(["test", "data"]);',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
    }

    public function testProfilingErrorRecovery()
    {
        $this->requiresXdebugOrXhprof();

        $profileCommand = new ProfileCommand();
        $profileCommand->setApplication($this->shell);

        // First, execute code that will error
        try {
            $tester = new CommandTester($profileCommand);
            $tester->execute([
                'code' => 'throw new \Exception("Test error");',
            ]);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Test error', $e->getMessage());
        }

        // Then, execute valid code - profiler should still work
        $tester = new CommandTester($profileCommand);
        $tester->execute([
            'code' => 'echo "recovered";',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);
    }
}
