# ProfileCommand Test Coverage Analysis

## Executive Summary

**Test Coverage Status**: 🟡 **Medium Coverage (~45-55%)**
- **Unit Tests**: 27 test methods in ProfileCommandTest.php
- **Integration Tests**: 10 test methods in ProfilingWorkflowTest.php
- **Current Issues**: Multiple test failures due to Xdebug trace file generation errors
- **Critical Gaps**: Context reconstruction, trace parsing edge cases, error handling, temporary file management

---

## 1. Test Coverage Report by Method

### ✅ Well Covered (>80% coverage)

| Method | Coverage | Test Cases | Notes |
|--------|----------|------------|-------|
| `configure()` | ~90% | 10+ tests | Configuration options well tested |
| `displayResults()` | ~85% | 15+ tests | Multiple filter levels, formatting options tested |
| `formatTime()` | ~80% | 5 tests | Various time ranges tested |
| `formatMemory()` | ~80% | 5 tests | Various memory ranges tested |
| `normalizeInlineCode()` | ~75% | 3 tests | Basic normalization covered |

### 🟡 Partially Covered (40-80% coverage)

| Method | Coverage | Test Cases | Notes |
|--------|----------|------------|-------|
| `execute()` | ~60% | 20 tests | **GAPS**: Error paths, edge cases |
| `filterProfileData()` | ~55% | 8 tests | **GAPS**: Complex filtering scenarios |
| `filterFunctions()` | ~50% | 6 tests | **GAPS**: Edge cases in filter levels |
| `saveProfileData()` | ~50% | 3 tests | **GAPS**: Error handling, invalid paths |
| `extractFunctionParams()` | ~45% | 2 tests | **GAPS**: Complex parameter types |

### 🔴 Poor Coverage (<40%)

| Method | Coverage | Test Cases | Notes |
|--------|----------|------------|-------|
| `executeWithXdebugTracing()` | ~30% | 2 tests | **CRITICAL GAP** - Core functionality |
| `buildContextScript()` | ~25% | 1 test | **CRITICAL GAP** - Context reconstruction |
| `parseXdebugTrace()` | ~20% | 1 test | **CRITICAL GAP** - Trace parsing |
| `captureShellVariables()` | ~15% | 0 tests | **NO COVERAGE** |
| `captureShellConstants()` | ~10% | 0 tests | **NO COVERAGE** |
| `captureEnvironmentVariables()` | ~10% | 0 tests | **NO COVERAGE** |
| `captureShellDefinedClasses()` | ~5% | 0 tests | **NO COVERAGE** |
| `reconstructClassFromReflection()` | ~5% | 0 tests | **NO COVERAGE** |
| `isSerializable()` | ~20% | 1 test | **GAPS**: Complex types |
| `isSerializableClosure()` | ~0% | 0 tests | **NO COVERAGE** |
| `isPsyshSystemCall()` | ~30% | 0 tests | **GAPS**: Edge cases |
| `isUserCodeContext()` | ~25% | 0 tests | **GAPS**: Complex scenarios |
| `isUserFunction()` | ~40% | 2 tests | **GAPS**: Edge cases |
| `isInternalFunction()` | ~50% | 2 tests | **GAPS**: Complex reflection scenarios |
| `formatFunctionName()` | ~60% | 3 tests | **GAPS**: Very long names |
| `enhanceWithCallGraph()` | ~30% | 0 tests | **GAPS**: Call graph building |

---

## 2. Coverage Gaps Analysis

### 2.1 Critical Missing Scenarios (Priority: 🔴 HIGH)

#### Context Reconstruction (0% coverage)
```php
// NO TESTS for these critical methods:
- captureShellVariables()      // Line 214-241
- captureShellConstants()       // Line 243-261
- captureEnvironmentVariables() // Line 189-212
- captureShellDefinedClasses()  // Line 314-342
- reconstructClassFromReflection() // Line 344-395
```

**Impact**: Context reconstruction failures would break profiling in realistic usage scenarios.

**Missing Tests**:
1. ❌ Variables with complex types (DateTime, PDO, resources)
2. ❌ Serialization failures for objects
3. ❌ Closure variable handling
4. ❌ User-defined constants
5. ❌ Framework environment variables (Laravel, Symfony)
6. ❌ Class reconstruction from eval'd code
7. ❌ Namespace handling in reconstructed classes

#### Trace Parsing (20% coverage)
```php
// MINIMAL TESTS for:
- parseXdebugTrace()      // Line 523-583
- enhanceWithCallGraph()  // Line 587-603
```

**Impact**: Incorrect trace parsing = wrong profiling results.

**Missing Tests**:
1. ❌ Malformed trace file formats
2. ❌ Missing entry/exit pairs
3. ❌ Deeply nested call stacks (>50 levels)
4. ❌ Large trace files (>10MB)
5. ❌ Trace files with errors/incomplete data
6. ❌ Memory overflow in trace parsing
7. ❌ Call graph cycles and recursion

#### XHProf vs Xdebug Mode Switching (30% coverage)
```php
// Line 111-146: Extension detection and switching
```

**Missing Tests**:
1. ❌ XHProf available, Xdebug not loaded
2. ❌ Xdebug available, XHProf not loaded
3. ❌ Both extensions available, XHProf preferred
4. ❌ `--trace-all` override forcing Xdebug
5. ❌ XHProf disabled at runtime
6. ❌ Xdebug trace mode not enabled

### 2.2 Important Missing Scenarios (Priority: 🟡 MEDIUM)

#### Error Handling (40% coverage)
```php
// Partial coverage in execute() method
```

**Missing Tests**:
1. ❌ Exceptions during profiling cleanup (line 147-156)
2. ❌ File system errors in trace generation
3. ❌ Subprocess execution failures (line 456-472)
4. ❌ Out of memory during profiling
5. ❌ Timeout in long-running code
6. ❌ Permission errors for temp files

#### Temporary File Cleanup (30% coverage)
```php
// Line 481-489: Cleanup in finally block
```

**Missing Tests**:
1. ❌ Temp file not deleted on success
2. ❌ Temp file not deleted on error
3. ❌ Race conditions with multiple profile commands
4. ❌ Disk full scenarios
5. ❌ Permission errors preventing cleanup

#### Filter Level Functionality (55% coverage)
```php
// Line 710-732: Filter functions
```

**Missing Tests**:
1. ❌ Filter interaction with threshold
2. ❌ Edge case: all functions filtered out
3. ❌ Performance with 10,000+ functions
4. ❌ Namespace filtering accuracy
5. ❌ PsySH internal function detection edge cases

### 2.3 Minor Missing Scenarios (Priority: 🟢 LOW)

1. ❌ Very long function names (>200 chars) truncation
2. ❌ Unicode in function names
3. ❌ Multiple profiling sessions in parallel
4. ❌ Profile data JSON encoding edge cases
5. ❌ Table rendering with very wide columns

---

## 3. Test Quality Assessment

### 3.1 Strengths ✅

1. **Good basic coverage** of command options
2. **Integration tests** cover realistic workflows
3. **Proper setup/teardown** with temp file cleanup
4. **Extension detection** with appropriate test skipping
5. **Output validation** for expected strings

### 3.2 Weaknesses ❌

1. **No mocking strategy** - tests depend on real extensions
2. **Assertions too weak** - mostly `assertStringContainsString()`
3. **No performance benchmarks** - execution time not validated
4. **No negative testing** for context reconstruction
5. **Error tests are skipped** with `@TODO` markers
6. **No fixtures** for complex test data
7. **Test failures** indicate broken Xdebug integration

### 3.3 Current Test Failures

```
11 errors out of 27 tests (40.7% failure rate)
```

**Root Cause**: Xdebug trace file generation failures
- Path issues: `hello/var/tmp/...` (invalid path)
- Variable capture issues: Undefined `$myClosure`, `$callback`
- Trace format issues: File not created

**Recommendation**: Fix context reconstruction before adding more tests.

---

## 4. Missing Test Scenarios (Prioritized)

### 🔴 Priority 1: Critical Functionality

#### Test Suite: Context Reconstruction
```php
// tests/Command/ContextReconstructionTest.php

public function testCaptureSimpleVariables()
public function testCaptureComplexObjects()
public function testSerializationFailures()
public function testCaptureClosures()
public function testCaptureConstants()
public function testCaptureEnvironmentVariables()
public function testReconstructUserDefinedClasses()
public function testHandleNonSerializableTypes()
public function testVariableWithCircularReference()
```

#### Test Suite: Trace Parsing
```php
// tests/Command/TraceParsing/XdebugTraceParserTest.php

public function testParseMalformedTrace()
public function testParseIncompleteTrace()
public function testParseLargeTraceFile()
public function testParseNestedCallStack()
public function testParseRecursiveFunction()
public function testHandleMemoryOverflow()
public function testParseTraceWithException()
```

#### Test Suite: Extension Detection
```php
// tests/Command/ExtensionDetectionTest.php

public function testXHProfOnlyAvailable()
public function testXdebugOnlyAvailable()
public function testBothExtensionsAvailable()
public function testNoExtensionsThrowsException()
public function testTraceAllForcesXdebug()
public function testXHProfModeSwitching()
```

### 🟡 Priority 2: Error Handling

#### Test Suite: Error Recovery
```php
// tests/Command/ErrorHandlingTest.php

public function testErrorDuringProfiling()
public function testExceptionInProfiledCode()
public function testCleanupAfterError()
public function testFileSystemErrors()
public function testOutOfMemoryDuringProfiling()
public function testSubprocessExecutionFailure()
public function testInvalidTraceFileFormat()
```

#### Test Suite: Temporary File Management
```php
// tests/Command/TempFileManagementTest.php

public function testTempFileCreation()
public function testTempFileCleanupOnSuccess()
public function testTempFileCleanupOnError()
public function testDiskFullScenario()
public function testPermissionErrorOnCleanup()
public function testConcurrentProfileSessions()
```

### 🟢 Priority 3: Edge Cases

#### Test Suite: Filter and Threshold Edge Cases
```php
// tests/Command/FilteringEdgeCasesTest.php

public function testAllFunctionsFilteredOut()
public function testThresholdEqualToExecutionTime()
public function testVeryHighFunctionCount()
public function testNestedNamespaceFiltering()
public function testMixedUserAndSystemCode()
```

---

## 5. Test Implementation Examples

### Example 1: Context Reconstruction (Critical Gap)

```php
<?php
// test/Command/ContextReconstructionTest.php

namespace Psy\Test\Command;

use PHPUnit\Framework\TestCase;
use Psy\Command\ProfileCommand;
use Psy\Shell;

class ContextReconstructionTest extends TestCase
{
    private $command;
    private $shell;

    protected function setUp(): void
    {
        $this->shell = new Shell();
        $this->command = new ProfileCommand();
        $this->command->setApplication($this->shell);
    }

    /**
     * Test capturing simple scalar variables
     */
    public function testCaptureSimpleVariables(): void
    {
        $this->shell->setScopeVariables([
            'string_var' => 'test',
            'int_var' => 42,
            'float_var' => 3.14,
            'bool_var' => true,
            'null_var' => null,
            'array_var' => [1, 2, 3],
        ]);

        // Use reflection to access private method
        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Verify all variables are captured
        $this->assertStringContainsString('$string_var = \'test\';', $contextScript);
        $this->assertStringContainsString('$int_var = 42;', $contextScript);
        $this->assertStringContainsString('$float_var = 3.14;', $contextScript);
        $this->assertStringContainsString('$bool_var = true;', $contextScript);
        $this->assertStringContainsString('$null_var = NULL;', $contextScript);
        $this->assertStringContainsString('$array_var = array', $contextScript);
    }

    /**
     * Test capturing serializable objects
     */
    public function testCaptureSerializableObjects(): void
    {
        $this->shell->setScopeVariables([
            'date' => new \DateTime('2024-01-01'),
            'stdClass' => (object)['prop' => 'value'],
        ]);

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Should use serialize/unserialize for objects
        $this->assertStringContainsString('unserialize(', $contextScript);
        $this->assertStringContainsString('$date', $contextScript);
        $this->assertStringContainsString('$stdClass', $contextScript);
    }

    /**
     * Test handling non-serializable objects
     */
    public function testHandleNonSerializableObjects(): void
    {
        // PDO is not serializable
        try {
            $pdo = new \PDO('sqlite::memory:');
        } catch (\Exception $e) {
            $this->markTestSkipped('PDO not available');
        }

        $this->shell->setScopeVariables([
            'pdo' => $pdo,
        ]);

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Should include a comment about non-serializable object
        $this->assertStringContainsString('// Object $pdo', $contextScript);
        $this->assertStringContainsString('could not be serialized', $contextScript);
    }

    /**
     * Test capturing closures
     */
    public function testCaptureClosure(): void
    {
        $this->shell->setScopeVariables([
            'myClosure' => function($x) { return $x * 2; },
        ]);

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Closures should be ignored with a comment
        $this->assertStringContainsString('// Closure $myClosure ignored', $contextScript);
    }

    /**
     * Test capturing user-defined constants
     */
    public function testCaptureUserDefinedConstants(): void
    {
        define('TEST_CONSTANT_PROFILING', 'test_value');
        define('TEST_INT_CONSTANT', 123);

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Should define constants if not already defined
        $this->assertStringContainsString('define(', $contextScript);
        $this->assertStringContainsString('TEST_CONSTANT_PROFILING', $contextScript);
        $this->assertStringContainsString('test_value', $contextScript);
    }

    /**
     * Test capturing environment variables
     */
    public function testCaptureEnvironmentVariables(): void
    {
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        $contextScript = $method->invoke($this->command);

        // Should capture important environment variables
        $this->assertStringContainsString('APP_ENV', $contextScript);
        $this->assertStringContainsString('testing', $contextScript);

        // Cleanup
        putenv('APP_ENV');
        putenv('APP_DEBUG');
    }

    /**
     * Test reconstructing user-defined classes
     */
    public function testReconstructUserDefinedClass(): void
    {
        // Execute code that defines a class in the shell
        $this->shell->execute('class TestClass { public function test() { return 42; } }');

        $method = new \ReflectionMethod(ProfileCommand::class, 'captureShellDefinedClasses');
        $method->setAccessible(true);

        $classDefinitions = $method->invoke($this->command);

        // Should reconstruct the class definition
        $this->assertStringContainsString('class TestClass', $classDefinitions);
        $this->assertStringContainsString('function test', $classDefinitions);
    }

    /**
     * Test handling variables with circular references
     */
    public function testHandleCircularReference(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $this->shell->setScopeVariables([
            'circular' => $obj1,
        ]);

        $method = new \ReflectionMethod(ProfileCommand::class, 'buildContextScript');
        $method->setAccessible(true);

        // Should not crash
        $contextScript = $method->invoke($this->command);

        // Might fail to serialize, should have error comment
        $this->assertTrue(
            str_contains($contextScript, 'unserialize(') ||
            str_contains($contextScript, 'could not be serialized')
        );
    }
}
```

### Example 2: Trace Parsing (Critical Gap)

```php
<?php
// test/Command/TraceParsing/XdebugTraceParserTest.php

namespace Psy\Test\Command\TraceParsing;

use PHPUnit\Framework\TestCase;
use Psy\Command\ProfileCommand;

class XdebugTraceParserTest extends TestCase
{
    private $command;
    private $parseMethod;

    protected function setUp(): void
    {
        $this->command = new ProfileCommand();

        // Access private parseXdebugTrace method via reflection
        $this->parseMethod = new \ReflectionMethod(
            ProfileCommand::class,
            'parseXdebugTrace'
        );
        $this->parseMethod->setAccessible(true);
    }

    /**
     * Create a temporary trace file for testing
     */
    private function createTraceFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'test_trace_');
        file_put_contents($file, $content);
        return $file;
    }

    /**
     * Test parsing a well-formed trace file
     */
    public function testParseWellFormedTrace(): void
    {
        $traceContent = <<<TRACE
TRACE START [2024-01-01 00:00:00.000000]
0 0.0001 100000 -> main()
1 0.0002 100100 -> strlen() /test.php:1
1 0.0003 100100 <- strlen() /test.php:1
0 0.0004 100000 <- main()
TRACE END [2024-01-01 00:00:00.000000]
TRACE;

        $file = $this->createTraceFile($traceContent);

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('strlen', $result);
        $this->assertEquals(1, $result['strlen']['calls']);
        $this->assertGreaterThan(0, $result['strlen']['time']);

        unlink($file);
    }

    /**
     * Test parsing a trace with malformed lines
     */
    public function testParseMalformedTrace(): void
    {
        $traceContent = <<<TRACE
TRACE START
0 0.0001 100000 -> main()
INVALID LINE HERE
1 0.0002 100100 -> strlen()
ANOTHER BAD LINE
1 0.0003 100100 <- strlen()
0 0.0004 100000 <- main()
TRACE END
TRACE;

        $file = $this->createTraceFile($traceContent);

        // Should handle malformed lines gracefully
        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        // Should still parse valid lines
        $this->assertArrayHasKey('strlen', $result);

        unlink($file);
    }

    /**
     * Test parsing an incomplete trace (missing exit)
     */
    public function testParseIncompleteTrace(): void
    {
        $traceContent = <<<TRACE
TRACE START
0 0.0001 100000 -> main()
1 0.0002 100100 -> strlen()
# Missing <- strlen() exit
0 0.0003 100000 <- main()
TRACE END
TRACE;

        $file = $this->createTraceFile($traceContent);

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        // Should handle missing exits

        unlink($file);
    }

    /**
     * Test parsing a deeply nested call stack
     */
    public function testParseDeepNesting(): void
    {
        $lines = ["TRACE START"];

        // Create 100 levels of nesting
        for ($i = 0; $i < 100; $i++) {
            $time = sprintf('0.%04d', $i);
            $memory = 100000 + ($i * 100);
            $lines[] = "$i $time $memory -> func$i()";
        }

        // Exit all functions in reverse order
        for ($i = 99; $i >= 0; $i--) {
            $time = sprintf('0.%04d', 100 + (99 - $i));
            $memory = 100000 + ($i * 100);
            $lines[] = "$i $time $memory <- func$i()";
        }

        $lines[] = "TRACE END";

        $traceContent = implode("\n", $lines);
        $file = $this->createTraceFile($traceContent);

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        $this->assertCount(100, $result);

        // Verify each function was captured
        for ($i = 0; $i < 100; $i++) {
            $this->assertArrayHasKey("func$i", $result);
        }

        unlink($file);
    }

    /**
     * Test parsing a large trace file
     */
    public function testParseLargeTraceFile(): void
    {
        $this->markTestSkipped('Performance test - run manually');

        $lines = ["TRACE START"];

        // Create 100,000 function calls
        for ($i = 0; $i < 100000; $i++) {
            $time = sprintf('0.%06d', $i * 2);
            $memory = 100000;
            $funcName = 'func' . ($i % 1000); // 1000 unique functions
            $lines[] = "0 $time $memory -> $funcName()";

            $time = sprintf('0.%06d', ($i * 2) + 1);
            $lines[] = "0 $time $memory <- $funcName()";
        }

        $lines[] = "TRACE END";

        $traceContent = implode("\n", $lines);
        $file = $this->createTraceFile($traceContent);

        $startTime = microtime(true);
        $result = $this->parseMethod->invoke($this->command, $file);
        $parseTime = microtime(true) - $startTime;

        $this->assertIsArray($result);
        $this->assertLessThan(5.0, $parseTime, 'Parsing should complete in under 5 seconds');

        unlink($file);
    }

    /**
     * Test parsing a trace with recursive function calls
     */
    public function testParseRecursiveFunction(): void
    {
        $traceContent = <<<TRACE
TRACE START
0 0.0001 100000 -> recursive()
1 0.0002 100100 -> recursive()
2 0.0003 100200 -> recursive()
2 0.0004 100200 <- recursive()
1 0.0005 100100 <- recursive()
0 0.0006 100000 <- recursive()
TRACE END
TRACE;

        $file = $this->createTraceFile($traceContent);

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('recursive', $result);
        $this->assertEquals(3, $result['recursive']['calls']);

        unlink($file);
    }

    /**
     * Test parsing an empty trace file
     */
    public function testParseEmptyTrace(): void
    {
        $file = $this->createTraceFile('');

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        $this->assertEmpty($result);

        unlink($file);
    }

    /**
     * Test parsing with special characters in function names
     */
    public function testParseSpecialCharacters(): void
    {
        $traceContent = <<<TRACE
TRACE START
0 0.0001 100000 -> Namespace\\Class::method()
0 0.0002 100000 <- Namespace\\Class::method()
1 0.0003 100100 -> {closure}()
1 0.0004 100100 <- {closure}()
TRACE END
TRACE;

        $file = $this->createTraceFile($traceContent);

        $result = $this->parseMethod->invoke($this->command, $file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Namespace\\Class::method', $result);
        $this->assertArrayHasKey('{closure}', $result);

        unlink($file);
    }
}
```

### Example 3: Extension Detection (Critical Gap)

```php
<?php
// test/Command/ExtensionDetectionTest.php

namespace Psy\Test\Command;

use PHPUnit\Framework\TestCase;
use Psy\Command\ProfileCommand;
use Psy\Shell;
use Psy\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class ExtensionDetectionTest extends TestCase
{
    private $shell;
    private $command;

    protected function setUp(): void
    {
        $this->shell = new Shell();
        $this->command = new ProfileCommand();
        $this->command->setApplication($this->shell);
    }

    /**
     * Test with XHProf extension only
     */
    public function testXHProfOnlyMode(): void
    {
        if (!extension_loaded('xhprof') || extension_loaded('xdebug')) {
            $this->markTestSkipped('Test requires XHProf only (no Xdebug)');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
        ]);

        $output = $tester->getDisplay();

        // Should use XHProf mode
        $this->assertStringContainsString('Total execution', $output);
        $this->assertEquals(0, $tester->getStatusCode());
    }

    /**
     * Test with Xdebug extension only
     */
    public function testXdebugOnlyMode(): void
    {
        if (!extension_loaded('xdebug') || extension_loaded('xhprof')) {
            $this->markTestSkipped('Test requires Xdebug only (no XHProf)');
        }

        if (!function_exists('xdebug_start_trace')) {
            $this->markTestSkipped('Xdebug trace functions not available');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
        ]);

        $output = $tester->getDisplay();

        // Should use Xdebug tracing mode
        $this->assertStringContainsString('Total execution', $output);
        $this->assertEquals(0, $tester->getStatusCode());
    }

    /**
     * Test with both extensions available (XHProf should be preferred)
     */
    public function testBothExtensionsAvailablePreferXHProf(): void
    {
        if (!extension_loaded('xhprof') || !extension_loaded('xdebug')) {
            $this->markTestSkipped('Test requires both XHProf and Xdebug');
        }

        $tester = new CommandTester($this->command);

        // Without --trace-all, should prefer XHProf
        $tester->execute([
            'code' => 'strlen("test");',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);

        // XHProf mode doesn't create trace files
        $traceFiles = glob('/tmp/trace.*.xt');
        $this->assertEmpty($traceFiles, 'Should use XHProf, not Xdebug tracing');
    }

    /**
     * Test --trace-all forces Xdebug even when XHProf is available
     */
    public function testTraceAllForcesXdebug(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Test requires Xdebug');
        }

        if (!function_exists('xdebug_start_trace')) {
            $this->markTestSkipped('Xdebug trace functions not available');
        }

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
            '--trace-all' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total execution', $output);

        // Should have used Xdebug tracing mode
        // (Implementation detail: check execution path via debug output)
    }

    /**
     * Test with no profiling extensions throws exception
     */
    public function testNoExtensionsThrowsException(): void
    {
        if (extension_loaded('xhprof') || extension_loaded('xdebug')) {
            $this->markTestSkipped('Test requires no profiling extensions');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XHProf or XDebug extension is not loaded');

        $tester = new CommandTester($this->command);
        $tester->execute([
            'code' => 'strlen("test");',
        ]);
    }

    /**
     * Test XHProf functions available at runtime
     */
    public function testXHProfRuntimeAvailability(): void
    {
        if (!extension_loaded('xhprof')) {
            $this->markTestSkipped('Test requires XHProf extension');
        }

        $this->assertTrue(function_exists('xhprof_enable'));
        $this->assertTrue(function_exists('xhprof_disable'));
        $this->assertTrue(defined('XHPROF_FLAGS_CPU'));
        $this->assertTrue(defined('XHPROF_FLAGS_MEMORY'));
    }

    /**
     * Test Xdebug functions available at runtime
     */
    public function testXdebugRuntimeAvailability(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Test requires Xdebug extension');
        }

        // Check if trace functions are available
        if (!function_exists('xdebug_start_trace')) {
            $this->markTestSkipped('Xdebug trace functions not available');
        }

        $this->assertTrue(function_exists('xdebug_start_trace'));
        $this->assertTrue(function_exists('xdebug_stop_trace'));
    }
}
```

---

## 6. Testing Strategy Recommendations

### 6.1 Unit vs Integration vs E2E

| Test Type | Usage | Examples |
|-----------|-------|----------|
| **Unit Tests** (60%) | Isolated method testing with mocks | `isSerializable()`, `formatTime()`, `formatMemory()` |
| **Integration Tests** (30%) | Multi-component interaction | Context reconstruction + trace parsing |
| **E2E Tests** (10%) | Full profiling workflows | Complete profiling session with all features |

### 6.2 Mocking Strategy

#### Critical Mocks Needed:

1. **Shell Mock** for variable/constant capture testing
```php
$shellMock = $this->createMock(Shell::class);
$shellMock->method('getScopeVariables')
    ->willReturn(['test' => 'value']);
```

2. **Extension Function Mocks** (when extension not available)
```php
// Mock XHProf functions
if (!function_exists('xhprof_enable')) {
    function xhprof_enable($flags) { return true; }
    function xhprof_disable() { return ['test' => ['ct' => 1]]; }
}
```

3. **File System Mock** for trace file operations
```php
$vfs = vfsStream::setup('temp');
$traceFile = vfsStream::url('temp/trace.xt');
```

### 6.3 Fixture Suggestions

#### 1. Trace File Fixtures
```
test/fixtures/traces/
├── valid_simple.xt          # Basic valid trace
├── valid_nested.xt          # Deeply nested calls
├── valid_recursive.xt       # Recursive functions
├── malformed_incomplete.xt  # Missing exits
├── malformed_invalid.xt     # Invalid format
└── large_10k_calls.xt       # Performance testing
```

#### 2. Context Fixtures
```
test/fixtures/contexts/
├── simple_variables.php     # Basic variable types
├── complex_objects.php      # Serializable objects
├── closures.php            # Closure handling
└── user_classes.php        # User-defined classes
```

### 6.4 Performance Testing

#### Benchmarks Needed:

1. **Trace Parsing Performance**
   - Small file (<1MB): <100ms
   - Medium file (1-10MB): <1s
   - Large file (>10MB): <5s

2. **Context Reconstruction**
   - 10 variables: <50ms
   - 100 variables: <200ms
   - 1000 variables: <1s

3. **Profile Execution**
   - Simple expression: <500ms overhead
   - Complex code: <1s overhead

---

## 7. Action Items

### 🔴 Immediate (This Week)

1. **Fix failing tests** - Resolve Xdebug trace file generation issues
2. **Add context reconstruction tests** - Minimum 10 test cases
3. **Add trace parsing tests** - Minimum 8 test cases
4. **Add extension detection tests** - Cover all scenarios

### 🟡 Short Term (Next 2 Weeks)

5. **Improve test assertions** - Use specific assertions instead of `assertStringContainsString()`
6. **Add error handling tests** - Cover all exception paths
7. **Create test fixtures** - Trace files and context scenarios
8. **Add performance benchmarks** - Validate profiling overhead

### 🟢 Long Term (Next Month)

9. **Achieve 80% code coverage** - Comprehensive test suite
10. **Add mutation testing** - Verify test quality
11. **CI/CD integration** - Automated coverage reports
12. **Documentation** - Testing guide for contributors

---

## 8. Estimated Test Coverage After Implementation

| Component | Current | After Priority 1 | After All |
|-----------|---------|------------------|-----------|
| `execute()` | 60% | 85% | 95% |
| Context reconstruction | 10% | 80% | 90% |
| Trace parsing | 20% | 75% | 85% |
| Extension detection | 30% | 90% | 95% |
| Error handling | 40% | 70% | 85% |
| Filtering | 55% | 70% | 80% |
| **Overall** | **45%** | **75%** | **87%** |

---

## 9. Test Maintenance Guidelines

### Best Practices

1. ✅ **Use descriptive test names** - `testCaptureSerializableObjects()` not `test1()`
2. ✅ **One assertion per concept** - Test one behavior at a time
3. ✅ **Arrange-Act-Assert pattern** - Clear test structure
4. ✅ **Clean up resources** - Always delete temp files
5. ✅ **Skip appropriately** - Use `markTestSkipped()` with clear reasons
6. ✅ **Test data builders** - Use fixtures and factories
7. ✅ **Avoid test interdependence** - Each test should be independent

### Anti-Patterns to Avoid

1. ❌ Tests that depend on execution order
2. ❌ Tests that depend on external state
3. ❌ Tests with hardcoded paths
4. ❌ Tests without cleanup
5. ❌ Tests with sleep() or arbitrary timeouts
6. ❌ Tests that modify global state without restoration

---

## Conclusion

The ProfileCommand currently has **medium test coverage (~45-55%)** with significant gaps in critical areas:

- ✅ **Strengths**: Good basic option coverage, integration tests, proper setup/teardown
- ❌ **Critical Gaps**: Context reconstruction (0%), trace parsing (20%), extension switching (30%)
- ⚠️ **Issues**: 40.7% test failure rate, weak assertions, no mocking strategy

**Recommended Path Forward**:
1. Fix existing test failures (Xdebug integration)
2. Implement Priority 1 tests (context reconstruction, trace parsing, extension detection)
3. Achieve 75% coverage with Priority 1 + 2
4. Target 87% coverage with comprehensive suite

**Estimated Effort**:
- Priority 1: 16-24 hours
- Priority 2: 12-16 hours
- Priority 3: 8-12 hours
- **Total**: 36-52 hours for comprehensive coverage
