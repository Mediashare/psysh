<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Integration;

use Psy\Test\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Integration test for ProfileCommand that tests actual PsySH execution using Symfony Process.
 *
 * This test class spawns real PsySH processes to test ProfileCommand functionality
 * in actual interactive mode, including option parsing and real profiling output.
 */
class ProfileCommandIntegrationTest extends TestCase
{
    private string $psyshBinary;
    private string $tempDir;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->psyshBinary = realpath(__DIR__ . '/../../bin/psysh');
        $this->tempDir = sys_get_temp_dir() . '/psysh_integration_test';
        
        if (!file_exists($this->psyshBinary)) {
            $this->markTestSkipped('psysh binary not found at ' . $this->psyshBinary);
        }
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        
        $this->tempFiles = [];
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
        
        parent::tearDown();
    }

    private function requiresXdebugOrXhprof(): void
    {
        if (!\extension_loaded('xdebug') && !\extension_loaded('xhprof')) {
            $this->markTestSkipped('Either Xdebug or XHProf extension is required.');
        }
    }

    private function createTestFile(string $content): string
    {
        $tempFile = tempnam($this->tempDir, 'test_code_');
        file_put_contents($tempFile, $content);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }

    private function runPsyshCommand(string $command, int $timeout = 30): array
    {
        // Create a command script that executes the profile command and exits
        $script = $this->createTestFile("<?php
echo \"Starting PsySH\\n\";
");
        
        // Build the command with proper escaping
        $fullCommand = sprintf(
            'echo %s | %s --no-history --no-readline 2>&1',
            escapeshellarg($command . "\nexit"),
            escapeshellarg($this->psyshBinary)
        );
        
        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout($timeout);
        
        try {
            $process->run();
            return [
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exitCode' => $process->getExitCode(),
                'timedOut' => false
            ];
        } catch (ProcessTimedOutException $e) {
            return [
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput() . "\nProcess timed out after {$timeout}s",
                'exitCode' => -1,
                'timedOut' => true
            ];
        }
    }

    private function assertProfileOutput(string $output, string $message = ''): void
    {
        // Check for basic profiling output structure
        $this->assertStringContainsString('Total execution', $output, "Expected profiling summary. $message");
        $this->assertStringContainsString('Memory:', $output, "Expected memory information. $message");
        
        // Should contain function call information
        $this->assertTrue(
            str_contains($output, 'Function') || str_contains($output, 'Calls'),
            "Expected function call information. $message\nOutput: $output"
        );
    }

    public function testBasicProfileCommand(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile echo "hello world";');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Basic profile command');
    }

    public function testProfileCommandWithFullOption(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile --full echo "full test";');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with --full option');
        $this->assertStringContainsString('all functions', $result['output'], 'Should indicate full profiling mode');
    }

    public function testProfileCommandWithShowParamsOption(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile --show-params strlen("test");');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with --show-params option');
        $this->assertStringContainsString('Parameters', $result['output'], 'Should show parameter information');
    }

    public function testProfileCommandWithTraceAllOption(): void
    {
        if (!\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is required for --trace-all option.');
        }
        
        // Check if tracing is available
        if (!function_exists('xdebug_start_trace')) {
            $this->markTestSkipped('Xdebug trace functions are not available.');
        }
        
        $result = $this->runPsyshCommand('profile --trace-all strlen("trace test");');
        
        // trace-all may not work in all environments, but shouldn't crash
        $this->assertNotEquals(-1, $result['exitCode'], 'Command should not timeout');
        
        if ($result['exitCode'] === 0) {
            $this->assertProfileOutput($result['output'], 'Profile with --trace-all option');
        } else {
            // If trace-all fails, it should give a meaningful error
            $this->assertStringContainsString('trace', $result['error'], 'Should mention tracing in error message');
        }
    }

    public function testProfileCommandWithFullNamespacesOption(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile --full-namespaces array_map("strtoupper", ["test"]);');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with --full-namespaces option');
    }

    public function testProfileCommandWithCombinedOptions(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile --full --show-params array_sum([1, 2, 3]);');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with combined options');
        $this->assertStringContainsString('all functions', $result['output'], 'Should indicate full profiling mode');
        $this->assertStringContainsString('Parameters', $result['output'], 'Should show parameter information');
    }

    public function testProfileCommandWithOutputFile(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $outputFile = $this->tempDir . '/profile_output.json';
        $this->tempFiles[] = $outputFile;
        
        $result = $this->runPsyshCommand("profile --out=\"$outputFile\" str_repeat(\"test\", 100);");
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with --out option');
        $this->assertStringContainsString('Profile data saved to:', $result['output'], 'Should mention output file');
        
        // Check if output file was created
        $this->assertFileExists($outputFile, 'Profile output file should be created');
        
        $profileData = json_decode(file_get_contents($outputFile), true);
        $this->assertIsArray($profileData, 'Profile output should be valid JSON');
        $this->assertNotEmpty($profileData, 'Profile data should not be empty');
    }

    public function testProfileCommandWithThreshold(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test with very low threshold
        $result = $this->runPsyshCommand('profile --threshold=0 usleep(1000);');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with low threshold');
        
        // Test with very high threshold
        $result = $this->runPsyshCommand('profile --threshold=999999 echo "fast";');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with high threshold');
    }

    public function testProfileCommandWithInvalidCode(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test syntax error
        $result = $this->runPsyshCommand('profile echo "unclosed string;', 15);
        
        // Should handle syntax errors gracefully
        $this->assertNotEquals(-1, $result['exitCode'], 'Command should not timeout');
        $this->assertTrue(
            str_contains($result['error'], 'Parse error') || 
            str_contains($result['error'], 'syntax') || 
            str_contains($result['output'], 'Parse error') ||
            str_contains($result['output'], 'syntax'),
            'Should report syntax error'
        );
    }

    public function testProfileCommandWithRuntimeError(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test runtime error
        $result = $this->runPsyshCommand('profile $undefinedVariable->method();', 15);
        
        // Should handle runtime errors gracefully
        $this->assertNotEquals(-1, $result['exitCode'], 'Command should not timeout');
        
        // May still show some profiling data before the error
        if ($result['exitCode'] === 0) {
            $this->assertProfileOutput($result['output'], 'Profile with runtime error');
        }
    }

    public function testProfileCommandTimeout(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test with code that takes a long time
        $result = $this->runPsyshCommand('profile sleep(5);', 3); // 3 second timeout
        
        if ($result['timedOut']) {
            $this->assertTrue(true, 'Command properly timed out as expected');
        } else {
            // If it didn't timeout, it should have completed successfully
            $this->assertEquals(0, $result['exitCode'], "Command should either timeout or complete successfully");
            if ($result['exitCode'] === 0) {
                $this->assertProfileOutput($result['output'], 'Long running profile command');
            }
        }
    }

    public function testProfileCommandWithComplexCode(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $complexCode = '
            $data = range(1, 100);
            $result = array_map(function($x) { 
                return str_pad((string)$x, 5, "0", STR_PAD_LEFT); 
            }, $data);
            count($result);
        ';
        
        $result = $this->runPsyshCommand("profile $complexCode");
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with complex code');
    }

    public function testProfileCommandWithDebugOption(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $result = $this->runPsyshCommand('profile --debug echo "debug test";');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with --debug option');
        $this->assertStringContainsString('Debug:', $result['output'], 'Should show debug information');
    }

    public function testProfileCommandWithFilterOptions(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test user filter
        $result = $this->runPsyshCommand('profile --filter=user strlen("user filter");');
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with user filter');
        
        // Test php filter
        $result = $this->runPsyshCommand('profile --filter=php strlen("php filter");');
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with php filter');
        
        // Test all filter
        $result = $this->runPsyshCommand('profile --filter=all strlen("all filter");');
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with all filter');
    }

    public function testProfileCommandInteractiveSession(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test that profile command works in an interactive session with variables
        $commands = [
            '$x = "test value";',
            'profile strlen($x);',
            'exit'
        ];
        
        $result = $this->runPsyshCommand(implode("\n", $commands));
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile in interactive session');
    }

    public function testProfileCommandWithAllCombinedOptions(): void
    {
        $this->requiresXdebugOrXhprof();
        
        $outputFile = $this->tempDir . '/profile_all_options.json';
        $this->tempFiles[] = $outputFile;
        
        $allOptionsCommand = sprintf(
            'profile --full --show-params --full-namespaces --debug --threshold=0 --out="%s" array_merge(["a"], ["b"]);',
            $outputFile
        );
        
        $result = $this->runPsyshCommand($allOptionsCommand);
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with all combined options');
        
        // Check for all expected option indicators
        $this->assertStringContainsString('Debug:', $result['output'], 'Should show debug information');
        $this->assertStringContainsString('all functions', $result['output'], 'Should indicate full profiling mode');
        $this->assertStringContainsString('Parameters', $result['output'], 'Should show parameter information');
        $this->assertStringContainsString('Profile data saved to:', $result['output'], 'Should mention output file');
        
        // Verify output file
        $this->assertFileExists($outputFile, 'Profile output file should be created');
        $profileData = json_decode(file_get_contents($outputFile), true);
        $this->assertIsArray($profileData, 'Profile output should be valid JSON');
    }

    public function testProfileCommandWithoutExtensions(): void
    {
        // This test can only run if extensions are not loaded
        if (\extension_loaded('xdebug') || \extension_loaded('xhprof')) {
            $this->markTestSkipped('Extensions are loaded, cannot test error condition.');
        }
        
        $result = $this->runPsyshCommand('profile echo "no extensions";');
        
        // Should fail with appropriate error message
        $this->assertNotEquals(0, $result['exitCode'], 'Should fail without required extensions');
        $this->assertTrue(
            str_contains($result['error'], 'XHProf') || 
            str_contains($result['error'], 'XDebug') ||
            str_contains($result['output'], 'XHProf') || 
            str_contains($result['output'], 'XDebug'),
            'Should mention required extensions in error message'
        );
    }

    public function testProfileCommandMemoryIntensive(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Test memory-intensive operation
        $result = $this->runPsyshCommand('profile $large = array_fill(0, 10000, "memory test");');
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Memory-intensive profile');
        
        // Should show memory information
        $this->assertStringContainsString('Memory:', $result['output'], 'Should show memory usage');
    }

    public function testProfileCommandWithIncludeFile(): void
    {
        $this->requiresXdebugOrXhprof();
        
        // Create a PHP file to include
        $includeFile = $this->createTestFile('<?php
function test_include_function($param) {
    return "included: " . $param;
}
');
        
        $result = $this->runPsyshCommand("include '$includeFile'; profile test_include_function('hello');");
        
        $this->assertEquals(0, $result['exitCode'], "PsySH should exit successfully. Error: {$result['error']}");
        $this->assertProfileOutput($result['output'], 'Profile with included file');
    }
}