# Configuration System Deep Dive

## Overview

`Configuration.php` is the **central nervous system** of PsySH, managing settings, services, and dependencies. At **58KB (1500+ lines)**, it's one of the largest files and exhibits **God Object** anti-pattern characteristics.

## File Statistics

- **Size**: 58,224 bytes
- **Lines**: ~1,500
- **Class**: `Psy\Configuration`
- **Namespace**: `Psy`
- **Dependencies**: 10+ external classes
- **Complexity**: VERY HIGH

## Responsibilities Analysis

### Current Responsibilities (Too Many!)

1. **Configuration Management** ✅
   - Store/retrieve configuration values
   - Validate configuration options
   - Merge configuration from multiple sources

2. **Service Container** ⚠️
   - Lazy-load services
   - Manage service lifecycle
   - Dependency injection

3. **Factory Pattern** ⚠️
   - Create Readline instances
   - Create Output instances
   - Create CodeCleaner instances
   - Create Presenter instances
   - Create AutoCompleter instances
   - Create Checker instances

4. **Path Management** ⚠️
   - Config directory resolution
   - Data directory resolution
   - Runtime directory resolution
   - History file resolution

5. **Shell Integration** ⚠️
   - Shell instance management
   - Command registration
   - Matcher registration

**Violation**: Single Responsibility Principle (SRP)

## Configuration Options

### Available Options (31 total)

```php
private const AVAILABLE_OPTIONS = [
    'codeCleaner',           // CodeCleaner instance or false
    'colorMode',             // auto|forced|disabled
    'configDir',             // Configuration directory path
    'dataDir',               // Data storage path
    'defaultIncludes',       // Auto-include files
    'eraseDuplicates',       // Remove duplicate history
    'errorLoggingLevel',     // Error reporting level
    'forceArrayIndexes',     // Always show array indexes
    'formatterStyles',       // Output formatter styles (deprecated)
    'historyFile',           // History file path
    'historySize',           // Max history entries
    'interactiveMode',       // auto|forced|disabled
    'manualDbFile',          // PHP manual database path
    'pager',                 // Output pager command
    'prompt',                // Custom prompt (deprecated)
    'rawOutput',             // Disable output formatting
    'requireSemicolons',     // Require semicolons in code
    'runtimeDir',            // Runtime directory
    'startupMessage',        // Custom startup message
    'strictTypes',           // Enforce strict types
    'theme',                 // Color theme
    'updateCheck',           // Check for updates
    'useBracketedPaste',     // Enable bracketed paste mode
    'usePcntl',              // Use process control
    'useReadline',           // Use readline library
    'useTabCompletion',      // Enable tab completion
    'useUnicode',            // Unicode support
    'verbosity',             // Output verbosity level
    'warnOnMultipleConfigs', // Warn on config conflicts
    'yolo',                  // Disable validation (dangerous!)
];
```

## Service Management

### Services Managed

```php
// Core Services
private ?Readline\Readline $readline = null;
private ?ShellOutput $output = null;
private ?Shell $shell = null;
private ?CodeCleaner $cleaner = null;
private ?Presenter $presenter = null;
private ?AutoCompleter $autoCompleter = null;
private ?Checker $checker = null;

// Database
private ?\PDO $manualDb = null;

// Output pager
private $pager = null; // string|OutputPager|false|null
```

### Service Creation Methods

Each service has getter methods with lazy initialization:

```php
public function getReadline(): Readline\Readline
public function getOutput(): ShellOutput
public function getCodeCleaner(): CodeCleaner
public function getPresenter(): Presenter
public function getAutoCompleter(): AutoCompleter
public function getChecker(): Checker
public function getManualDb(): ?\PDO
public function getPager(): OutputPager
```

## Configuration Loading

### Multi-Source Configuration

```php
public function __construct(array $config = [])
{
    $this->configPaths = new ConfigPaths();

    // 1. Explicit configFile option
    if (isset($config['configFile'])) {
        $this->configFile = $config['configFile'];
    }

    // 2. Environment variable
    elseif (isset($_SERVER['PSYSH_CONFIG'])) {
        $this->configFile = $_SERVER['PSYSH_CONFIG'];
    }

    // 3. Load from file if exists
    if ($this->configFile && is_file($this->configFile)) {
        $this->loadConfigFile($this->configFile);
    }

    // 4. Apply provided config array
    $this->setOptions($config);
}
```

### Configuration File Locations

Managed by `ConfigPaths` class:

```php
// User config
~/.config/psysh/config.php
~/.psysh/config.php

// System config
/etc/psysh/config.php

// Project config
./psysh.config.php
./.psysh/config.php
```

## Path Resolution System

### Directory Hierarchy

```php
// Config directory
getConfigDir(): string
├── ~/.config/psysh/
├── ~/.psysh/
└── /etc/psysh/

// Data directory
getDataDir(): string
├── ~/.local/share/psysh/
└── ~/.psysh/

// Runtime directory
getRuntimeDir(): string
├── ~/.cache/psysh/
└── ~/.psysh/
```

### History File Resolution

```php
public function getHistoryFile(): string
{
    if ($this->historyFile === false) {
        return ''; // Disabled
    }

    if ($this->historyFile !== null) {
        return $this->historyFile; // Explicit
    }

    // Auto-detect
    return $this->getDataDir() . '/history';
}
```

## Service Factory Pattern

### Example: Readline Creation

```php
public function getReadline(): Readline\Readline
{
    if (!isset($this->readline)) {
        $this->readline = $this->createReadline();
    }

    return $this->readline;
}

private function createReadline(): Readline\Readline
{
    if ($this->useReadline === false) {
        return new Readline\Transient(...);
    }

    if (Readline\GNUReadline::isSupported()) {
        return new Readline\GNUReadline(...);
    }

    if (Readline\Libedit::isSupported()) {
        return new Readline\Libedit(...);
    }

    return new Readline\Transient(...);
}
```

### Service Dependencies

```
Configuration
  ├── createReadline()
  │   ├── GNUReadline (if available)
  │   ├── Libedit (if available)
  │   └── Transient (fallback)
  ├── createOutput()
  │   └── ShellOutput + Theme
  ├── createCodeCleaner()
  │   ├── Parser (nikic/php-parser)
  │   ├── Printer
  │   └── NodeTraverser + Passes
  ├── createPresenter()
  │   └── Symfony VarDumper
  ├── createAutoCompleter()
  │   └── Matchers
  └── createChecker()
      ├── GitHubChecker
      ├── IntervalChecker
      └── NoopChecker
```

## Command and Matcher Registration

### Dynamic Command Addition

```php
public function addCommands(array $commands): self
{
    $this->newCommands = array_merge($this->newCommands, $commands);
    return $this;
}

public function getExtraCommands(): array
{
    return $this->newCommands;
}
```

### Matcher Registration

```php
public function addMatchers(array $matchers): self
{
    $this->newMatchers = array_merge($this->newMatchers, $matchers);
    return $this;
}

public function getExtraMatchers(): array
{
    return $this->newMatchers;
}
```

## Configuration Validation

### Option Validation

```php
private function setOptions(array $options): void
{
    foreach ($options as $key => $value) {
        if (!in_array($key, self::AVAILABLE_OPTIONS)) {
            throw new \InvalidArgumentException(
                "Unknown configuration option: $key"
            );
        }

        $method = 'set' . ucfirst($key);
        if (method_exists($this, $method)) {
            $this->$method($value);
        }
    }
}
```

### Type Validation

Each setter performs type validation:

```php
public function setColorMode(string $mode): self
{
    if (!in_array($mode, [
        self::COLOR_MODE_AUTO,
        self::COLOR_MODE_FORCED,
        self::COLOR_MODE_DISABLED
    ])) {
        throw new \InvalidArgumentException("Invalid color mode: $mode");
    }

    $this->colorMode = $mode;
    return $this;
}
```

## Environment Detection

### Piped Input/Output Detection

```php
public function hasPipedInput(): bool
{
    if ($this->pipedInput === null) {
        $this->pipedInput = !posix_isatty(STDIN);
    }
    return $this->pipedInput;
}

public function hasPipedOutput(): bool
{
    if ($this->pipedOutput === null) {
        $this->pipedOutput = !posix_isatty(STDOUT);
    }
    return $this->pipedOutput;
}
```

### Extension Detection

```php
private function hasReadline(): bool
{
    return extension_loaded('readline');
}

private function hasPcntl(): bool
{
    return extension_loaded('pcntl') &&
           extension_loaded('posix');
}
```

## Architectural Issues

### 🔴 CRITICAL: God Object Anti-Pattern

**Problems**:
1. **Too many responsibilities** (6+ distinct areas)
2. **Hard to test** (requires extensive mocking)
3. **Tight coupling** (many classes depend on Configuration)
4. **Difficult to extend** (no clear extension points)
5. **Large file size** (58KB, 1500+ lines)

### 🟡 Code Smells

1. **Long Parameter Lists**
   - Service creation methods have many parameters
   - Difficult to maintain

2. **Feature Envy**
   - Configuration knows too much about service internals
   - Violates encapsulation

3. **Shotgun Surgery**
   - Adding new service requires changes in multiple places
   - High maintenance cost

## Refactoring Recommendations

### Recommended Architecture

```
ConfigurationManager (Facade)
  ├── ConfigLoader
  │   ├── FileConfigLoader
  │   ├── EnvironmentConfigLoader
  │   └── ArrayConfigLoader
  ├── ServiceContainer (DI Container)
  │   ├── ReadlineProvider
  │   ├── OutputProvider
  │   ├── CodeCleanerProvider
  │   ├── PresenterProvider
  │   ├── AutoCompleterProvider
  │   └── CheckerProvider
  ├── PathResolver
  │   ├── ConfigPathResolver
  │   ├── DataPathResolver
  │   └── RuntimePathResolver
  ├── OptionRegistry
  │   ├── OptionValidator
  │   └── OptionStorage
  └── EnvironmentDetector
      ├── ExtensionDetector
      └── TerminalDetector
```

### Step-by-Step Refactoring Plan

**Phase 1: Extract Path Management**
```php
// New class
class PathResolver
{
    public function getConfigDir(): string
    public function getDataDir(): string
    public function getRuntimeDir(): string
    public function getHistoryFile(): string
}

// Usage in Configuration
private PathResolver $pathResolver;
```

**Phase 2: Extract Service Container**
```php
// New class
class ServiceContainer
{
    private array $providers = [];
    private array $instances = [];

    public function get(string $service): object
    public function has(string $service): bool
    public function register(string $service, callable $provider): void
}
```

**Phase 3: Extract Option Management**
```php
// New class
class OptionRegistry
{
    private array $options = [];
    private OptionValidator $validator;

    public function set(string $key, $value): void
    public function get(string $key, $default = null)
    public function has(string $key): bool
}
```

**Phase 4: Slim Down Configuration**
```php
// Refactored Configuration (facade)
class Configuration
{
    private ServiceContainer $services;
    private OptionRegistry $options;
    private PathResolver $paths;

    public function __construct(array $config = [])
    {
        $this->services = new ServiceContainer();
        $this->options = new OptionRegistry();
        $this->paths = new PathResolver();

        $this->registerServices();
        $this->loadConfiguration($config);
    }
}
```

## Testing Challenges

### Current Testing Issues

1. **Hard to mock** - Too many dependencies
2. **Integration test heavy** - Unit tests difficult
3. **State management** - Complex setup/teardown
4. **Environment dependent** - Different behavior on different systems

### Improved Testing Strategy

```php
// After refactoring
class PathResolverTest extends TestCase
{
    // Easy to test in isolation
    public function testGetConfigDir() { }
}

class ServiceContainerTest extends TestCase
{
    // Easy to test without full Configuration
    public function testServiceRegistration() { }
}

class OptionRegistryTest extends TestCase
{
    // Simple value object testing
    public function testSetOption() { }
}
```

## Performance Considerations

### Lazy Loading ✅

All services use lazy initialization:
```php
if (!isset($this->service)) {
    $this->service = $this->createService();
}
```

**Benefits**:
- Fast startup time
- Only pay for what you use
- Memory efficient

### Configuration Caching ⚠️

**Current State**: No caching
**Issue**: Configuration file parsed on every run
**Recommendation**: Add optional config caching

```php
// Proposed
class CachedConfigLoader
{
    public function load(string $file): array
    {
        $cacheFile = $this->getCacheFile($file);

        if ($this->isCacheValid($cacheFile, $file)) {
            return unserialize(file_get_contents($cacheFile));
        }

        $config = include $file;
        file_put_contents($cacheFile, serialize($config));
        return $config;
    }
}
```

## Security Considerations

### Configuration File Execution ⚠️

**Risk**: Configuration files are PHP code (via `include`)
**Mitigation**: Files loaded from trusted locations only

```php
// config.php can execute arbitrary code
return [
    'defaultIncludes' => [
        __DIR__ . '/bootstrap.php'
    ],
    // Malicious code could be here
];
```

**Recommendation**: Consider JSON/YAML config format

### YOLO Mode 🔴

```php
'yolo' => true // Disables ALL validation
```

**Risk**: HIGH - Disables CodeCleaner safety checks
**Use Case**: Advanced users only
**Recommendation**: Warn loudly when enabled

## Deprecation Management

### Deprecated Options

```php
/** @deprecated */
private array $formatterStyles = [];

/** @deprecated */
private ?string $prompt = null;
```

**Strategy**: Maintain but warn
**Timeline**: Remove in next major version

## Conclusion

The Configuration class is **functional but problematic**:

**Strengths** ✅:
- Comprehensive option coverage
- Lazy service loading
- Multi-source configuration
- Good validation

**Critical Issues** 🔴:
- God Object anti-pattern
- Too many responsibilities
- Hard to test
- Large file size
- Tight coupling

**Configuration Grade**: **C+ (75/100)**
- **Functionality**: A (90/100)
- **Architecture**: D (60/100)
- **Maintainability**: C (70/100)
- **Testability**: D+ (65/100)
- **Code Quality**: B (80/100)

**Refactoring Priority**: 🔴 **CRITICAL - High Priority**

The Configuration class should be the **first target** for architectural refactoring, split into at least 5 smaller, focused classes.
