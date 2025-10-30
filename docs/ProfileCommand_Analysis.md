# Code Quality Analysis Report: ProfileCommand

## Executive Summary

**Overall Quality Score: 4.5/10**

- **Files Analyzed:** 1 (ProfileCommand.php)
- **Lines of Code:** 1,093 lines
- **Methods:** 26
- **Critical Issues Found:** 12
- **Code Smells:** 18
- **Technical Debt Estimate:** 40-60 hours

The ProfileCommand class suffers from severe **Single Responsibility Principle** violations, with one class handling profiling orchestration, context reconstruction, data parsing, filtering, formatting, and display. The class needs significant refactoring to improve maintainability and testability.

---

## Complexity Metrics

### Method Length Distribution

| Method | Lines | Complexity | Status |
|--------|-------|------------|--------|
| `execute()` | 111 lines | **HIGH** | ⚠️ CRITICAL |
| `executeWithXdebugTracing()` | 73 lines | **HIGH** | ⚠️ CRITICAL |
| `displayResults()` | 104 lines | **VERY HIGH** | 🔴 URGENT |
| `filterProfileData()` | 54 lines | **MEDIUM** | ⚠️ WARNING |
| `isPsyshSystemCall()` | 67 lines | **HIGH** | ⚠️ CRITICAL |
| `parseXdebugTrace()` | 60 lines | **HIGH** | ⚠️ CRITICAL |
| `extractFunctionParams()` | 55 lines | **MEDIUM** | ⚠️ WARNING |
| `reconstructClassFromReflection()` | 50 lines | **MEDIUM** | ⚠️ WARNING |

**Cyclomatic Complexity:**
- **execute():** ~15 (High - threshold is 10)
- **displayResults():** ~12 (High)
- **filterFunctions():** ~8 (Medium-High)
- **isPsyshSystemCall():** ~10 (High)
- **parseXdebugTrace():** ~9 (Medium-High)

### Nesting Depth Issues

Maximum nesting depth reaches **5 levels** in several methods:
- `parseXdebugTrace()`: Lines 534-580 (5 levels)
- `displayResults()`: Lines 605-708 (4 levels)
- `filterProfileData()`: Lines 807-861 (4 levels)

---

## Critical Issues

### 1. **SEVERE: Single Responsibility Principle Violation** 🔴

**Location:** Entire class (lines 12-1094)

**Problem:** The class handles 7+ distinct responsibilities:

1. Command configuration
2. XHProf profiling execution
3. Xdebug tracing execution
4. Context reconstruction (variables, constants, classes)
5. Trace data parsing
6. Data filtering and transformation
7. Result formatting and display

**Impact:**
- Impossible to test individual concerns in isolation
- High coupling between unrelated functionality
- Difficult to maintain and extend
- Violation of Open/Closed Principle

**Recommendation:** Extract into separate classes:

```php
// BEFORE: God Object Anti-Pattern
class ProfileCommand extends Command
{
    // 26 methods handling everything
}

// AFTER: Separated Concerns
class ProfileCommand extends Command
{
    private ProfilerFactory $profilerFactory;
    private ContextReconstructor $contextReconstructor;
    private TraceParser $traceParser;
    private ResultFormatter $resultFormatter;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profiler = $this->profilerFactory->create($input);
        $context = $this->contextReconstructor->reconstruct();
        $data = $profiler->profile($code, $context);
        $filtered = $this->traceParser->parse($data, $filterLevel);
        $this->resultFormatter->display($filtered, $output);

        return 0;
    }
}

// New Classes:
// - ProfilerFactory (creates XHProfProfiler or XdebugProfiler)
// - XHProfProfiler implements ProfilerInterface
// - XdebugProfiler implements ProfilerInterface
// - ContextReconstructor (captures shell state)
// - TraceParser (parses and filters trace data)
// - ResultFormatter (formats and displays results)
```

---

### 2. **SEVERE: Method Too Long - execute()** 🔴

**Location:** Lines 70-181 (111 lines)

**Cyclomatic Complexity:** ~15

**Problem:**
- Handles input parsing, validation, execution branching, error handling, and output
- Multiple levels of nesting
- Hard to test individual paths

**Refactoring:**

```php
// BEFORE: Monolithic execute method (111 lines)
protected function execute(InputInterface $input, OutputInterface $output): int
{
    // 20 lines of input parsing
    // 15 lines of validation
    // 40 lines of profiling logic
    // 20 lines of error handling
    // 16 lines of output handling
}

// AFTER: Extracted into focused methods
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $config = $this->parseConfiguration($input);
    $this->validateExtensions();

    $profileData = $this->executeProfiler($config, $output);
    $this->displayAndSaveResults($profileData, $config, $output);

    return 0;
}

private function parseConfiguration(InputInterface $input): ProfileConfiguration
{
    return new ProfileConfiguration(
        code: $this->normalizeInlineCode($input->getArgument('code')),
        filterLevel: $input->getOption('full') ? 'all' : $input->getOption('filter'),
        threshold: (int) $input->getOption('threshold'),
        showParams: $input->getOption('show-params'),
        fullNamespaces: $input->getOption('full-namespaces'),
        traceAll: $input->getOption('trace-all'),
        debug: $input->getOption('debug'),
        outFile: $input->getOption('out')
    );
}

private function executeProfiler(ProfileConfiguration $config, OutputInterface $output): array
{
    $profiler = $this->profilerFactory->createProfiler($config);
    return $profiler->execute($this->getShell(), $output);
}
```

**Benefits:**
- Each method has a single, clear purpose
- Easier to test each step independently
- Better error handling isolation
- Improved readability

---

### 3. **SEVERE: Method Too Long - displayResults()** 🔴

**Location:** Lines 605-708 (104 lines)

**Problem:**
- Handles filtering, sorting, table creation, and summary calculation
- Complex nested conditionals for edge cases
- Multiple responsibilities mixed together

**Refactoring:**

```php
// BEFORE: 104-line method doing everything
private function displayResults(array $data, OutputInterface $output, ...): void
{
    // Filter data (30 lines)
    // Sort data (5 lines)
    // Build table (40 lines)
    // Display summary (20 lines)
}

// AFTER: Extracted responsibilities
private function displayResults(array $data, OutputInterface $output, ProfileConfiguration $config): void
{
    $filtered = $this->prepareFilteredData($data, $config);
    $this->displayResultsTable($filtered, $output, $config);
    $this->displayExecutionSummary($filtered, $output);
}

private function prepareFilteredData(array $data, ProfileConfiguration $config): array
{
    $filtered = $this->filterFunctions($data, $config->filterLevel, $config->threshold);

    if (empty($filtered)) {
        return $this->getFallbackData($data, $config);
    }

    uasort($filtered, fn($a, $b) => $b['time'] <=> $a['time']);
    return array_slice($filtered, 0, 20);
}

private function displayResultsTable(array $filtered, OutputInterface $output, ProfileConfiguration $config): void
{
    $table = new Table($output);
    $table->setHeaders($this->buildTableHeaders($config));

    foreach ($filtered as $name => $func) {
        $table->addRow($this->buildTableRow($name, $func, $config));
    }

    $output->writeln(sprintf("\n<info>Profiling results (%s):</info>",
        $this->getFilterLevelDescription($config->filterLevel)
    ));

    $table->render();
}
```

---

### 4. **CRITICAL: Tight Coupling to Shell** 🔴

**Location:** Lines 91, 137, 140-143, 152-154, 423, 494, 504, 518

**Problem:**
- Direct dependencies on `$this->getShell()` scattered throughout
- Methods cannot be tested without a full Shell instance
- Violates Dependency Inversion Principle

**Refactoring:**

```php
// BEFORE: Direct Shell coupling
private function executeWithXdebugTracing(...): array
{
    $shell = $this->getShell();
    $vars = $shell->getScopeVariables();
    // ...
}

// AFTER: Dependency Injection
class ProfileCommand extends Command
{
    public function __construct(
        private readonly ShellContextProvider $contextProvider,
        private readonly ProfilerFactory $profilerFactory
    ) {
        parent::__construct();
    }
}

interface ShellContextProvider
{
    public function getScopeVariables(): array;
    public function getExecutedCodeAsString(): string;
    public function execute(string $code): mixed;
    public function getScopeVariable(string $name): mixed;
    public function setScopeVariables(array $vars): void;
}

// Mock for testing
class MockShellContextProvider implements ShellContextProvider
{
    public function getScopeVariables(): array
    {
        return ['test' => 'value'];
    }
    // ...
}
```

---

### 5. **CRITICAL: Complex Conditional Logic** 🔴

**Location:** `isPsyshSystemCall()` lines 1011-1077 (67 lines)

**Problem:**
- 67-line method with deeply nested conditions
- 5 different filtering strategies mixed together
- Hard to understand and maintain

**Refactoring:**

```php
// BEFORE: Complex nested conditions
private function isPsyshSystemCall(?string $parent, string $child): bool
{
    $systemFunctions = [...];

    if (str_starts_with($child, 'Symfony\\Polyfill\\')) {
        return true;
    }

    if (in_array($child, $systemFunctions)) {
        return true;
    }

    if ($parent && str_starts_with($parent, 'Psy\\Command\\ProfileCommand::')) {
        return true;
    }

    // ... 40 more lines of conditions
}

// AFTER: Strategy Pattern + Chain of Responsibility
class SystemCallFilter
{
    private array $filters;

    public function __construct()
    {
        $this->filters = [
            new PolyfillFilter(),
            new SystemFunctionFilter(),
            new ProfileCommandFilter(),
            new InternalMethodFilter(),
            new ConsoleNamespaceFilter(),
        ];
    }

    public function isSystemCall(?string $parent, string $child): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->matches($parent, $child)) {
                return true;
            }
        }
        return false;
    }
}

interface CallFilter
{
    public function matches(?string $parent, string $child): bool;
}

class PolyfillFilter implements CallFilter
{
    public function matches(?string $parent, string $child): bool
    {
        return str_starts_with($child, 'Symfony\\Polyfill\\');
    }
}

class SystemFunctionFilter implements CallFilter
{
    private const SYSTEM_FUNCTIONS = [
        'Psy\\Shell::handleInput',
        'Psy\\Shell::execute',
        // ...
    ];

    public function matches(?string $parent, string $child): bool
    {
        return in_array($child, self::SYSTEM_FUNCTIONS, true);
    }
}
```

---

### 6. **HIGH: Magic String Constants** ⚠️

**Location:** Throughout the class

**Problem:**
- Filter levels: 'user', 'php', 'all' (lines 40, 84, 168, etc.)
- Status strings scattered throughout
- No type safety

**Refactoring:**

```php
// BEFORE: Magic strings
$filterLevel = $input->getOption('full') ? 'all' : $input->getOption('filter');

switch ($filterLevel) {
    case 'user':
        return $func['is_user'];
    case 'php':
        return $func['is_user'] || $this->isInternalFunction($name);
    case 'all':
        return true;
}

// AFTER: Enum (PHP 8.1+)
enum FilterLevel: string
{
    case USER = 'user';
    case PHP = 'php';
    case ALL = 'all';

    public function shouldInclude(array $func, string $name): bool
    {
        return match($this) {
            self::USER => $func['is_user'],
            self::PHP => $func['is_user'] || $this->isInternalFunction($name),
            self::ALL => true,
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::USER => 'user code only',
            self::PHP => 'user code + PHP internals',
            self::ALL => 'all functions',
        };
    }
}

// Usage
$filterLevel = FilterLevel::from(
    $input->getOption('full') ? 'all' : $input->getOption('filter')
);
```

---

### 7. **HIGH: Duplicate Code - Formatting Methods** ⚠️

**Location:** `formatTime()` (lines 927-957) and `formatMemory()` (lines 965-999)

**Problem:**
- Both methods have identical structure
- Same logic pattern repeated

**Refactoring:**

```php
// BEFORE: Duplicate logic
private function formatTime(int $microseconds): string
{
    // 30 lines of unit conversion logic
}

private function formatMemory(int $bytes): string
{
    // 34 lines of nearly identical unit conversion logic
}

// AFTER: Extracted shared logic
class UnitFormatter
{
    private const TIME_UNITS = [
        ['threshold' => 60000000, 'divisor' => 60000000, 'unit' => 'min', 'decimals' => 2],
        ['threshold' => 1000000, 'divisor' => 1000000, 'unit' => 's', 'decimals' => 2],
        ['threshold' => 1000, 'divisor' => 1000, 'unit' => 'ms', 'decimals' => 1],
        ['threshold' => 0, 'divisor' => 1, 'unit' => 'μs', 'decimals' => 0],
    ];

    private const MEMORY_UNITS = [
        ['threshold' => 1073741824, 'divisor' => 1073741824, 'unit' => 'GB', 'decimals' => 2],
        ['threshold' => 1048576, 'divisor' => 1048576, 'unit' => 'MB', 'decimals' => 2],
        ['threshold' => 1024, 'divisor' => 1024, 'unit' => 'KB', 'decimals' => 1],
        ['threshold' => 0, 'divisor' => 1, 'unit' => 'B', 'decimals' => 0],
    ];

    public static function formatTime(int $microseconds): string
    {
        return self::format($microseconds, self::TIME_UNITS, 'μs');
    }

    public static function formatMemory(int $bytes): string
    {
        if ($bytes < 0) {
            return '-' . self::formatMemory(-$bytes);
        }
        return self::format($bytes, self::MEMORY_UNITS, 'B');
    }

    private static function format(int $value, array $units, string $baseUnit): string
    {
        if ($value === 0) {
            return "0 $baseUnit";
        }

        foreach ($units as $config) {
            if ($value >= $config['threshold']) {
                $formatted = number_format(
                    $value / $config['divisor'],
                    $config['decimals']
                );

                return rtrim(rtrim($formatted, '0'), '.') . ' ' . $config['unit'];
            }
        }

        return "$value $baseUnit";
    }
}
```

---

### 8. **HIGH: Error Handling Inconsistency** ⚠️

**Location:** Various methods

**Problem:**
- Some methods use try-catch, others don't
- Inconsistent error messages
- Silent failures with comments

**Examples:**

```php
// Line 228: Silent failure
$serialized = @serialize($value);
if ($serialized !== false) {
    // ...
} else {
    $context[] = sprintf("// Object \$%s of class %s could not be serialized.", ...);
}

// Line 374: Try-catch with silent ignore
try {
    if ($param->isDefaultValueAvailable()) {
        // ...
    }
} catch (\Exception $e) {
    // Ignorer les erreurs de valeur par défaut
}

// Line 393: Returns null on error
} catch (\Exception $e) {
    return null;
}
```

**Refactoring:**

```php
// Create custom exception hierarchy
class ProfileCommandException extends RuntimeException {}
class SerializationException extends ProfileCommandException {}
class ContextReconstructionException extends ProfileCommandException {}

// AFTER: Consistent error handling
private function captureShellVariables($shell, array &$context): void
{
    $vars = $shell->getScopeVariables();

    foreach ($vars as $name => $value) {
        try {
            $serialized = $this->serializeVariable($value);
            $context[] = sprintf('$%s = %s;', $name, $serialized);
        } catch (SerializationException $e) {
            // Log for debugging but don't break execution
            $context[] = sprintf("// Variable \$%s: %s", $name, $e->getMessage());
        }
    }
}

private function serializeVariable(mixed $value): string
{
    if ($value instanceof \Closure) {
        throw new SerializationException('Closures cannot be serialized');
    }

    if (is_object($value)) {
        $serialized = @serialize($value);
        if ($serialized === false) {
            throw new SerializationException(
                sprintf('Object of class %s could not be serialized', get_class($value))
            );
        }
        return sprintf('unserialize(%s)', var_export($serialized, true));
    }

    if (!$this->isSerializable($value)) {
        throw new SerializationException('Value is not serializable');
    }

    return var_export($value, true);
}
```

---

## Code Smells Identified

### 1. **Long Parameter List** (Lines 605, 672)

```php
// 🚫 6 parameters - too many
private function displayResults(
    array $data,
    OutputInterface $output,
    string $filterLevel,
    int $threshold,
    bool $showParams = false,
    bool $fullNamespaces = false
): void

// ✅ Replace with configuration object
private function displayResults(
    array $data,
    OutputInterface $output,
    ProfileConfiguration $config
): void
```

### 2. **Feature Envy** (Lines 214-241)

The `captureShellVariables()` method is more interested in the Shell's data than its own:

```php
// 🚫 Method reaches deep into Shell
private function captureShellVariables($shell, array &$context): void
{
    $vars = $shell->getScopeVariables();
    // Uses $shell data extensively
}

// ✅ Extract to ShellContextCapture class
class ShellContextCapture
{
    public function __construct(private Shell $shell) {}

    public function captureVariables(): array
    {
        // Now this class owns the responsibility
    }
}
```

### 3. **Primitive Obsession** (Throughout)

Over-reliance on arrays for structured data:

```php
// 🚫 Using arrays for structured data
$filtered[$child] = [
    'calls' => $metrics['ct'] ?? 0,
    'time' => ($metrics['wt'] ?? 0),
    'memory' => $metrics['mu'] ?? 0,
    'peak_memory' => $metrics['pmu'] ?? 0,
    'cpu_time' => ($metrics['cpu'] ?? 0),
];

// ✅ Use value objects
class FunctionMetrics
{
    public function __construct(
        public readonly int $calls,
        public readonly int $time,
        public readonly int $memory,
        public readonly int $peakMemory,
        public readonly int $cpuTime
    ) {}

    public static function fromXHProfData(array $data): self
    {
        return new self(
            calls: $data['ct'] ?? 0,
            time: $data['wt'] ?? 0,
            memory: $data['mu'] ?? 0,
            peakMemory: $data['pmu'] ?? 0,
            cpuTime: $data['cpu'] ?? 0
        );
    }
}
```

### 4. **Dead Code / Commented Code** (Lines 159, 185-187)

```php
// Line 159: Debug code that should use proper logging
// Debug: afficher les données brutes si demandé

// Lines 185-187: Empty lines serving as separators
// Should use proper method grouping instead
```

### 5. **Inappropriate Intimacy** (Lines 140-143)

```php
// Direct manipulation of Shell's internal state
$vars = $shell->getScopeVariables();
unset($vars[$profileDataVar]);
$shell->setScopeVariables($vars);
```

### 6. **God Object**

The entire class - handles too many responsibilities.

### 7. **Shotgun Surgery**

Adding a new profiler would require changes in:
- `execute()` method (line 111)
- Extension validation (line 72)
- Profiling logic (lines 111-146)
- Data format parsing (multiple methods)

### 8. **Refused Bequest**

Not applicable - proper inheritance from Command base class.

### 9. **Speculative Generality**

Some methods like `isSerializableClosure()` have complex logic for edge cases that may never occur.

### 10. **Temporary Field**

Variables like `$profileDataVar` (line 108) only used during execution.

---

## SOLID Principles Analysis

### ✅ **Liskov Substitution Principle: PASS**

The class properly extends Symfony's Command class without violating contracts.

### ❌ **Single Responsibility: FAIL**

**Violations:**
- Profiling orchestration
- Context reconstruction
- Data parsing
- Result formatting
- File I/O

### ❌ **Open/Closed: FAIL**

**Violations:**
- Cannot add new profiler types without modifying `execute()`
- Cannot add new filter strategies without modifying `filterFunctions()`
- Cannot add new output formats without modifying `displayResults()`

### ❌ **Interface Segregation: FAIL**

No interfaces defined - everything is tightly coupled to concrete implementations.

**Should have:**
```php
interface ProfilerInterface {
    public function profile(string $code, array $context): array;
}

interface TraceParserInterface {
    public function parse(string $traceFile): array;
}

interface ResultFormatterInterface {
    public function format(array $data, ProfileConfiguration $config): string;
}
```

### ❌ **Dependency Inversion: FAIL**

Depends on concrete `Shell` class, not abstractions.

---

## Design Patterns

### Currently Used:

**None explicitly** - the class is procedural in nature.

### Recommended Patterns:

#### 1. **Strategy Pattern** - for profilers

```php
interface ProfilerStrategy
{
    public function canHandle(): bool;
    public function profile(string $code, array $context): array;
}

class XHProfStrategy implements ProfilerStrategy
{
    public function canHandle(): bool
    {
        return extension_loaded('xhprof');
    }
}

class XdebugStrategy implements ProfilerStrategy
{
    public function canHandle(): bool
    {
        return extension_loaded('xdebug');
    }
}
```

#### 2. **Factory Pattern** - for profiler creation

```php
class ProfilerFactory
{
    public function create(ProfileConfiguration $config): ProfilerStrategy
    {
        if ($config->traceAll || !extension_loaded('xhprof')) {
            return new XdebugStrategy();
        }
        return new XHProfStrategy();
    }
}
```

#### 3. **Builder Pattern** - for complex object construction

```php
class ProfileConfigurationBuilder
{
    private string $code;
    private string $filterLevel = 'user';
    private int $threshold = 0;

    public function withCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function build(): ProfileConfiguration
    {
        return new ProfileConfiguration($this->code, $this->filterLevel, ...);
    }
}
```

#### 4. **Chain of Responsibility** - for filtering

```php
abstract class FilterHandler
{
    protected ?FilterHandler $next = null;

    public function setNext(FilterHandler $handler): FilterHandler
    {
        $this->next = $handler;
        return $handler;
    }

    abstract public function filter(string $parent, string $child): bool;
}
```

#### 5. **Template Method** - for context capture

```php
abstract class ContextCapture
{
    final public function capture(): array
    {
        $context = [];
        $this->prepareContext($context);
        $this->captureVariables($context);
        $this->captureConstants($context);
        $this->captureClasses($context);
        return $context;
    }

    abstract protected function captureVariables(array &$context): void;
    abstract protected function captureConstants(array &$context): void;
    abstract protected function captureClasses(array &$context): void;
}
```

---

## Refactoring Roadmap

### Phase 1: Extract Value Objects (2-4 hours)

1. Create `ProfileConfiguration` class
2. Create `FunctionMetrics` class
3. Create `FilterLevel` enum
4. Replace array parameters with objects

### Phase 2: Extract Service Classes (8-12 hours)

1. `ProfilerFactory` + `ProfilerInterface`
2. `XHProfProfiler` implementation
3. `XdebugProfiler` implementation
4. `TraceParser` for Xdebug trace parsing
5. `ContextReconstructor` for shell context

### Phase 3: Extract Formatters (4-6 hours)

1. `ResultFormatter` interface
2. `TableResultFormatter` implementation
3. `UnitFormatter` utility class
4. `FunctionNameFormatter` utility

### Phase 4: Extract Filters (6-8 hours)

1. `CallFilter` interface
2. Multiple filter implementations
3. `FilterChain` or `SystemCallFilter` coordinator

### Phase 5: Improve Command Class (4-6 hours)

1. Slim down `execute()` to orchestration only
2. Inject dependencies via constructor
3. Add proper error handling
4. Write unit tests for each component

### Phase 6: Add Tests (12-16 hours)

1. Unit tests for all service classes
2. Integration tests for ProfileCommand
3. Test fixtures for different scenarios

**Total Estimated Effort:** 40-60 hours

---

## Recommendations

### Immediate Actions (Next Sprint)

1. **Extract ProfileConfiguration class** - removes 6-parameter methods
2. **Create ProfilerInterface** - enables testing and extensibility
3. **Extract UnitFormatter** - removes code duplication
4. **Add FilterLevel enum** - eliminates magic strings

### Medium-term (Next Quarter)

1. Extract all service classes
2. Add comprehensive unit tests (currently 0% coverage)
3. Implement design patterns
4. Add proper logging instead of debug output

### Long-term (Next 6 months)

1. Consider extracting profiling to separate package
2. Add support for additional profilers (Blackfire, etc.)
3. Build plugin system for custom formatters
4. Performance optimization for large traces

---

## Positive Findings ✅

Despite the issues, there are some good practices:

1. **Clear constant definitions** (lines 14-31)
2. **Good help text** (lines 49-66)
3. **Adaptive formatting** (`formatTime`, `formatMemory`)
4. **Comprehensive filtering options**
5. **Proper namespace usage**
6. **Type hints on most methods**
7. **Readonly arrays for configuration** (IGNORED_FUNCTIONS, PSYSH_NAMESPACES)

---

## Conclusion

The ProfileCommand class is a **textbook example of technical debt accumulation**. While it functions correctly, it's a maintenance nightmare. The class violates multiple SOLID principles and exhibits numerous code smells.

**Priority:** 🔴 **HIGH** - Refactor before adding new features

**Risk:** Adding new functionality without refactoring will exponentially increase complexity and technical debt.

**Recommendation:** Allocate 2-3 sprints for systematic refactoring following the roadmap above.

---

## Appendix: Method Responsibility Matrix

| Method | Current Responsibilities | Should Be In |
|--------|-------------------------|--------------|
| `execute()` | Orchestration, validation, error handling | `ProfileCommand` (keep) |
| `executeWithXdebugTracing()` | Xdebug profiling | `XdebugProfiler` |
| `parseXdebugTrace()` | Trace parsing | `XdebugTraceParser` |
| `buildContextScript()` | Context building | `ContextReconstructor` |
| `captureShellVariables()` | Variable capture | `ShellVariableCapture` |
| `captureShellConstants()` | Constant capture | `ShellConstantCapture` |
| `captureShellDefinedClasses()` | Class capture | `ShellClassCapture` |
| `displayResults()` | Formatting and display | `TableResultFormatter` |
| `filterProfileData()` | Data filtering | `ProfileDataFilter` |
| `filterFunctions()` | Function filtering | `FunctionFilter` |
| `isPsyshSystemCall()` | System call detection | `SystemCallFilter` |
| `formatTime()` | Time formatting | `UnitFormatter` |
| `formatMemory()` | Memory formatting | `UnitFormatter` |
| `formatFunctionName()` | Name formatting | `FunctionNameFormatter` |
| `extractFunctionParams()` | Parameter extraction | `ParameterExtractor` |

---

**Report Generated:** 2025-10-28
**Analyzer:** Code Quality Analyzer
**Tool Version:** 1.0.0
