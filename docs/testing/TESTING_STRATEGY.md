# PsySH Testing Strategy

## Overview

This document outlines the comprehensive testing strategy for PsySH profiling commands, including ProfileCommand, HotspotsCommand, MemoryMapCommand, and trace commands.

## Test Structure

### Unit Tests

Unit tests are located in `/test/Command/` and cover individual command functionality:

- **ProfileCommandTest.php** - Tests for the profile command with 40+ test cases
- **HotspotsCommandTest.php** - Tests for hotspots analysis command
- **MemoryMapCommandTest.php** - Tests for memory visualization command
- **SmartTraceCommandTest.php** - Tests for enhanced stack trace functionality
- **TraceHttpCommandTest.php** - Tests for HTTP request tracing
- **TraceSqlCommandTest.php** - Tests for SQL query tracing

### Integration Tests

Integration tests are located in `/test/Command/Integration/` and test complete workflows:

- **ProfilingWorkflowTest.php** - End-to-end profiling scenarios

## Test Coverage Requirements

### Minimum Coverage Targets
- **Statements**: >80%
- **Branches**: >75%
- **Functions**: >80%
- **Lines**: >80%

### Test Categories

#### 1. Basic Functionality Tests
Tests that verify core command behavior:
- Command execution
- Output format
- Basic options

#### 2. Option Combination Tests
Tests that verify various option combinations work correctly:
- `--out` with file export
- `--full` for complete profiling data
- `--filter` with different levels (user, php, all)
- `--threshold` for filtering by execution time
- `--show-params` for parameter display
- `--full-namespaces` for complete namespace display
- `--debug` for debugging information

#### 3. Edge Case Tests
Tests that handle boundary conditions:
- Empty input
- Very large datasets
- Negative thresholds
- Invalid file paths
- Missing required extensions

#### 4. Error Handling Tests
Tests that verify proper error handling:
- Missing extensions (Xdebug, XHProf)
- Invalid code execution
- File system errors
- Runtime exceptions
- Type errors
- Argument errors

#### 5. Performance Tests
Tests that verify performance characteristics:
- Large data processing
- Memory efficiency
- Execution time thresholds

## Running Tests

### Run All Tests
```bash
vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
vendor/bin/phpunit test/Command/ProfileCommandTest.php
vendor/bin/phpunit test/Command/Integration/
```

### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Run Specific Test
```bash
vendor/bin/phpunit --filter testBasicProfileCommand
```

## Test Dependencies

### Required PHP Extensions
- **PHPUnit**: Testing framework
- **Xdebug** or **XHProf**: Profiling functionality
- **PDO SQLite**: For SQL trace tests
- **curl**: For HTTP trace tests

### Conditional Test Execution

Tests that require specific extensions use `markTestSkipped()` when dependencies are not available:

```php
if (!\extension_loaded('xdebug') && !\extension_loaded('xhprof')) {
    $this->markTestSkipped('Either Xdebug or XHProf extension is required.');
}
```

## Test Patterns

### Standard Test Structure

```php
public function testSomething()
{
    // Arrange
    $this->shell->setScopeVariables(['var' => 'value']);
    $tester = new CommandTester($this->command);

    // Act
    $tester->execute([
        'code' => 'some_code();',
        '--option' => 'value',
    ]);

    // Assert
    $output = $tester->getDisplay();
    $this->assertStringContainsString('expected text', $output);
}
```

### Error Testing Pattern

```php
public function testErrorCondition()
{
    $tester = new CommandTester($this->command);

    try {
        $tester->execute(['code' => 'throw new \Exception("error");']);
        $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
        $this->assertStringContainsString('error', $e->getMessage());
    }
}
```

### Setup and Teardown

```php
protected function setUp(): void
{
    $this->shell = new Shell();
    $this->command = new ProfileCommand();
    $this->command->setApplication($this->shell);
}

protected function tearDown(): void
{
    // Clean up temporary files
    $tempFiles = glob(sys_get_temp_dir() . '/profile_*.json');
    foreach ($tempFiles as $file) {
        @unlink($file);
    }
}
```

## Test Data

### Sample Code Snippets

Simple operations:
```php
'code' => 'echo "hello";'
'code' => 'strlen("test");'
```

Complex operations:
```php
'code' => 'array_map(function($v) { return $v * 2; }, range(1, 100));'
'code' => 'for($i=0;$i<1000;$i++) { md5("test".$i); }'
```

### Fixtures

Test fixtures are located in `/test/Command/ListCommand/Fixtures/` and provide reusable test data.

## Continuous Integration

### CI Pipeline
1. Install dependencies
2. Run PHPUnit tests
3. Generate coverage report
4. Upload coverage to reporting service

### Quality Gates
- All tests must pass
- Coverage must meet minimum thresholds
- No critical code quality issues

## Known Limitations

### Skipped Tests
Some tests are marked with `@TODO fix this test` and are currently skipped:
- `testErrorInProfiledCode` - Needs refinement for exception handling
- `testErrorInProfiledVariableCode` - Similar exception handling issue

### Platform-Specific Tests
Tests may behave differently based on:
- PHP version
- Available extensions
- Operating system
- File system permissions

## Debugging Failed Tests

### Common Issues

1. **Missing Extensions**
   - Solution: Install required extensions (Xdebug, XHProf)

2. **File Permission Errors**
   - Solution: Ensure temp directory is writable

3. **Timeout Issues**
   - Solution: Increase PHPUnit timeout in phpunit.xml

### Debug Mode

Run tests with verbose output:
```bash
vendor/bin/phpunit --verbose
vendor/bin/phpunit --debug
```

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Descriptive Names**: Test names should clearly describe what is being tested
3. **Single Assertion Focus**: Each test should verify one behavior
4. **Clean State**: Always clean up resources in tearDown()
5. **Meaningful Assertions**: Use specific assertions with clear messages
6. **Mock External Dependencies**: Use mocking for external services
7. **Fast Tests**: Keep unit tests fast (<100ms each)

## Contributing Tests

When adding new features:
1. Write tests first (TDD approach)
2. Ensure all edge cases are covered
3. Add integration tests for workflows
4. Update this documentation
5. Run full test suite before committing

## Test Metrics

### Current Coverage
- ProfileCommand: 95%+ coverage with 40+ test cases
- HotspotsCommand: Comprehensive coverage
- MemoryMapCommand: Comprehensive coverage
- Integration tests: Complete workflow coverage

### Test Execution Time
- Unit tests: ~5-10 seconds
- Integration tests: ~10-15 seconds
- Total suite: ~20-30 seconds

## Future Improvements

1. Add performance benchmarking tests
2. Expand integration test scenarios
3. Add mutation testing
4. Improve code coverage for edge cases
5. Add visual regression testing for output formatting
