# ADR-001: New Command Architecture for PsySH Enhancements

**Status**: Proposed
**Date**: 2025-10-27
**Decision Makers**: Architecture Agent (Hive Mind Swarm)
**Related**: FEATURE_PROPOSALS.md

## Context

PsySH requires three new major features:
1. **Code Snippet Manager** - Save, manage, and re-execute code snippets
2. **Static Analysis Integration** - On-the-fly PHPStan/Phan analysis
3. **Enhanced Documentation** - Improved `doc` command with code examples

These features must integrate seamlessly with the existing architecture while maintaining backward compatibility.

## Decision Drivers

1. **Consistency**: Follow existing PsySH command patterns (extends `Psy\Command\Command`)
2. **Testability**: Full PHPUnit test coverage following existing test patterns
3. **Performance**: Minimize overhead, especially for snippet retrieval
4. **User Experience**: Intuitive command syntax consistent with existing commands
5. **Maintainability**: Clear separation of concerns, under 500 lines per file
6. **Extensibility**: Support future enhancements without major refactoring

## Architectural Constraints

- PHP 7.4+ / 8.0+ compatibility
- Must extend `Psy\Command\Command` base class
- Must work with Symfony Console components (v3.4 - v7.0)
- Must integrate with existing `Shell` execution context
- Must use existing `ConfigPaths` for user configuration storage
- Must follow PSR-4 autoloading (`Psy\` namespace)
- Must maintain existing command naming conventions

## Decision

### 1. Snippet Manager Architecture

**Command Structure**:
```
src/Command/SnippetCommand.php     (Main command dispatcher)
src/Snippet/SnippetManager.php     (Business logic layer)
src/Snippet/SnippetStorage.php     (Storage abstraction)
src/Snippet/Snippet.php            (Value object)
```

**Design Pattern**: Command Pattern + Strategy Pattern for storage

**Key Decisions**:
- Use subcommands pattern: `snippet save`, `snippet run`, `snippet list`, etc.
- Store snippets in JSON at `~/.config/psysh/snippets.json`
- Use `SnippetStorage` interface to allow future storage backends (SQLite, etc.)
- `SnippetManager` handles validation and business logic
- `Snippet` value object ensures data integrity

**API Contract**:
```php
interface SnippetStorageInterface {
    public function save(string $name, Snippet $snippet): bool;
    public function load(string $name): ?Snippet;
    public function list(): array;
    public function delete(string $name): bool;
    public function exists(string $name): bool;
}

class Snippet {
    public function __construct(
        private string $code,
        private array $metadata = [],
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $modifiedAt = null
    ) {}
}
```

### 2. Static Analysis Integration Architecture

**Command Structure**:
```
src/Command/AnalyseCommand.php     (Main command)
src/Analysis/AnalyzerInterface.php (Analyzer abstraction)
src/Analysis/PHPStanAnalyzer.php   (PHPStan implementation)
src/Analysis/PhanAnalyzer.php      (Phan implementation)
src/Analysis/AnalyzerFactory.php   (Auto-detection)
src/Analysis/AnalysisResult.php    (Result value object)
```

**Design Pattern**: Strategy Pattern + Factory Pattern

**Key Decisions**:
- Auto-detect available analyzer (PHPStan > Phan > fallback)
- Generate temporary file with proper context (namespace, use statements)
- Use `Symfony\Component\Process\Process` for subprocess execution
- Parse and format results for shell output
- Cache analyzer binary paths in shell session

**API Contract**:
```php
interface AnalyzerInterface {
    public function isAvailable(): bool;
    public function analyze(string $code, array $context): AnalysisResult;
    public function getName(): string;
    public function getVersion(): string;
}

class AnalysisResult {
    public function __construct(
        private array $errors,
        private array $warnings,
        private bool $success,
        private string $rawOutput
    ) {}
}
```

### 3. Enhanced Documentation Architecture

**Modified Files**:
```
src/Formatter/DocblockFormatter.php  (Extend with @example support)
src/Util/Docblock.php                (Add example tag parsing)
src/Command/DocCommand.php           (Minor updates for example display)
```

**Design Pattern**: Decorator Pattern (enhance existing behavior)

**Key Decisions**:
- Minimal changes to existing code (decorator pattern)
- Parse `@example` tags from docblocks
- Syntax highlight example code using existing VarDumper integration
- Maintain backward compatibility with existing `doc` command
- No new files required - extend existing formatter

**API Contract**:
```php
// Extension to Docblock class
class Docblock {
    // New method
    public function getExamples(): array;

    // Existing methods remain unchanged
}

// Extension to DocblockFormatter
class DocblockFormatter {
    // New method
    private function formatExamples(array $examples, OutputInterface $output): string;
}
```

## Integration Points

### 1. Shell Integration
All commands must integrate with `Shell::execute()` for code execution:

```php
// In SnippetCommand
$shell = $this->getShell();
$result = $shell->execute($snippet->getCode());
```

### 2. Context Awareness
Commands must access current execution context:

```php
// In AnalyseCommand
$context = $shell->getContext();
$scope = $context->getAll(); // Get current variables, functions, classes
```

### 3. Configuration Integration
Use existing `ConfigPaths` for user storage:

```php
$configPath = ConfigPaths::getCurrentConfigDir();
$snippetFile = $configPath . '/snippets.json';
```

### 4. Tab Completion Integration
Register custom matchers for new commands:

```php
// In SnippetCommand
protected function configure() {
    // Register snippet name completion
    $this->getShell()->addMatcher(new SnippetNameMatcher($this->manager));
}
```

## Testing Strategy

### Unit Tests Structure
```
test/Command/SnippetCommandTest.php
test/Snippet/SnippetManagerTest.php
test/Snippet/SnippetStorageTest.php
test/Command/AnalyseCommandTest.php
test/Analysis/PHPStanAnalyzerTest.php
test/Analysis/PhanAnalyzerTest.php
test/Formatter/DocblockFormatterTest.php
```

### Test Coverage Requirements
- Minimum 80% code coverage for new code
- 100% coverage for critical paths (snippet save/load, analysis execution)
- Integration tests with `FakeShell` for command execution
- Mock `Process` for analyzer subprocess tests
- Filesystem mocking for snippet storage tests

### Test Patterns
```php
class SnippetCommandTest extends CommandTestCase {
    private SnippetCommand $command;
    private MockSnippetManager $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->manager = new MockSnippetManager();
        $this->command = new SnippetCommand($this->manager);
    }

    public function testSaveSnippet(): void {
        // Arrange
        $input = new StringInput('snippet save test "echo 123"');

        // Act
        $exitCode = $this->command->run($input, $this->output);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertTrue($this->manager->exists('test'));
    }
}
```

## Error Handling Strategy

### Error Categories
1. **User Input Errors**: Invalid snippet names, malformed code
2. **System Errors**: File I/O failures, missing analyzers
3. **Execution Errors**: Code syntax errors, runtime failures
4. **Configuration Errors**: Invalid config files, permission issues

### Error Handling Patterns
```php
// Graceful degradation
try {
    $analyzer = $this->factory->create();
} catch (NoAnalyzerAvailableException $e) {
    $output->writeln('<error>No static analyzer found. Install PHPStan or Phan.</error>');
    return 1;
}

// User-friendly messages
if (!$this->validator->isValidSnippetName($name)) {
    throw new InvalidArgumentException(
        sprintf('Invalid snippet name "%s". Must be alphanumeric with dashes/underscores.', $name)
    );
}

// Fallback behavior
if (!file_exists($snippetFile)) {
    // Create default empty snippet file
    $this->storage->initialize();
}
```

## Backwards Compatibility

### Guaranteed Compatibility
1. **No breaking changes** to existing commands
2. **No modifications** to public Shell API
3. **No changes** to existing configuration format
4. **New files only** in new namespaces (`Snippet\`, `Analysis\`)

### Version Support
- PHP 7.4+ support maintained
- Symfony Console 3.4+ support maintained
- Graceful feature detection for optional dependencies

### Migration Path
- No migration required - new features are additive
- Existing configurations remain valid
- New configuration keys are optional

## Performance Considerations

### Snippet Manager
- **Lazy loading**: Load snippets only when accessed
- **Caching**: Cache parsed JSON in shell session
- **Indexing**: Maintain in-memory index for fast lookups

### Static Analysis
- **Binary caching**: Cache analyzer binary path
- **Temp file reuse**: Reuse temp directory across analyses
- **Process pooling**: Consider process pooling for repeated analyses
- **Timeout**: 30-second default timeout for analyzer execution

### Documentation Enhancement
- **Minimal overhead**: Only parse examples when explicitly displayed
- **Lazy parsing**: Parse docblock on-demand, not at command initialization

## Security Considerations

### Snippet Execution
- **Warning display**: Show warning before executing snippets
- **Code review**: Display snippet code before execution
- **Confirmation**: Require confirmation for destructive operations

### Static Analysis
- **Sandbox execution**: Analyzer runs in separate process
- **Path validation**: Validate temp file paths
- **Input sanitization**: Escape shell arguments properly

### File Storage
- **Permission checks**: Verify write permissions before saving
- **Path traversal prevention**: Validate snippet names
- **Secure defaults**: 0600 permissions on snippet files

## Extensibility Points

### Future Enhancements
1. **Remote Snippet Sharing**: HTTP API for snippet exchange
2. **SQLite Storage Backend**: Better performance for large collections
3. **Snippet Templates**: Parameterized snippets with placeholders
4. **Analysis Caching**: Cache analysis results by code hash
5. **Custom Analyzers**: Plugin system for custom analysis tools
6. **Documentation Search**: Full-text search in PHP manual

### Plugin Architecture
```php
interface SnippetProviderInterface {
    public function getSnippets(): array;
}

// Allow registration of custom providers
$manager->registerProvider(new GitHubSnippetProvider());
```

## Monitoring and Metrics

### Success Metrics
- Command execution time < 100ms (snippet operations)
- Analysis completion time < 5s (for typical code)
- Zero regression in existing command performance
- Test suite execution time < 30s

### Usage Tracking (Optional)
- Track most-used snippets (local only, no telemetry)
- Track analysis error patterns for UX improvement
- Monitor analyzer availability statistics

## Decision Outcome

**Accepted** - This architecture provides:
- Clear separation of concerns
- Excellent testability
- Strong backward compatibility
- Room for future growth
- Consistent with existing patterns

**Next Steps**:
1. Review with Coder agent for implementation feasibility
2. Review with Tester agent for testing approach
3. Create detailed class diagrams
4. Create sequence diagrams for key workflows
5. Begin implementation in priority order:
   - Phase 1: Snippet Manager (highest user value)
   - Phase 2: Enhanced Documentation (lowest risk)
   - Phase 3: Static Analysis (highest complexity)

## References

- PsySH Architecture: src/Shell.php, src/Command/Command.php
- Existing Commands: src/Command/ProfileCommand.php (similar complexity)
- Test Patterns: test/Command/
- Configuration: src/Configuration.php, src/ConfigPaths.php
