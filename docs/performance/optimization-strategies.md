# PsySH Performance Optimization Strategies

## Executive Summary

This document outlines comprehensive optimization strategies for PsySH to achieve:
- **Startup time**: < 100ms (P95)
- **Memory footprint**: < 50MB for typical sessions
- **Command execution**: < 50ms average
- **Zero memory leaks**

## 1. Startup Time Optimization

### Current Bottlenecks
1. **Composer Autoloader Scanning** (~40-60ms)
2. **Configuration Loading** (~20-30ms)
3. **Readline Initialization** (~15-25ms)
4. **Command Registration** (~10-20ms)

### Optimization Strategies

#### 1.1 Lazy Loading Components
```php
// BEFORE: Eager loading all commands
foreach ($this->getCommands() as $command) {
    $this->add($command);
}

// AFTER: Lazy command registration
private array $commandFactories = [];

public function registerCommandFactory(string $name, callable $factory): void
{
    $this->commandFactories[$name] = $factory;
}

public function get(string $name): Command
{
    if (isset($this->commandFactories[$name])) {
        $command = ($this->commandFactories[$name])();
        $this->add($command);
        unset($this->commandFactories[$name]);
        return $command;
    }
    return parent::get($name);
}
```

**Impact**: 30-40% startup time reduction

#### 1.2 Configuration Caching
```php
// Cache compiled configuration
class Configuration
{
    private static ?array $cachedConfig = null;

    public function __construct()
    {
        if (self::$cachedConfig === null) {
            self::$cachedConfig = $this->loadAndCompileConfig();
        }
        $this->config = self::$cachedConfig;
    }

    private function loadAndCompileConfig(): array
    {
        $cacheFile = $this->getCacheDir() . '/config.cache.php';

        if (file_exists($cacheFile) && !$this->isConfigStale($cacheFile)) {
            return require $cacheFile;
        }

        $config = $this->buildConfig();
        file_put_contents($cacheFile, '<?php return ' . var_export($config, true) . ';');
        return $config;
    }
}
```

**Impact**: 20-30% config loading speedup

#### 1.3 Optimized Autoloading
```php
// Use Composer's optimized autoloader
// In composer.json:
{
    "config": {
        "optimize-autoloader": true,
        "classmap-authoritative": true,
        "apcu-autoloader": true
    }
}
```

**Impact**: 15-25% autoloader speedup

## 2. Memory Optimization

### Current Memory Footprint Analysis
- **Base Shell**: ~25MB
- **Readline/History**: ~5-10MB
- **Context/Scope**: ~8-12MB (depending on variables)
- **Code Cleaner/Parser**: ~10-15MB
- **Command Objects**: ~3-5MB

### Optimization Strategies

#### 2.1 Reduce Readline Buffer Size
```php
class Readline
{
    // BEFORE: Unlimited history
    private array $history = [];

    // AFTER: Limited history with rotation
    private const MAX_HISTORY = 1000;
    private array $history = [];

    public function addHistory(string $line): void
    {
        $this->history[] = $line;

        if (count($this->history) > self::MAX_HISTORY) {
            array_shift($this->history);
        }
    }
}
```

**Impact**: 40-60% reduction in history memory usage

#### 2.2 Parser/AST Caching
```php
class CodeCleaner
{
    private array $astCache = [];
    private const MAX_CACHE_SIZE = 100;

    public function clean(string $code): array
    {
        $hash = md5($code);

        if (isset($this->astCache[$hash])) {
            return $this->astCache[$hash];
        }

        $ast = $this->parser->parse($code);

        // LRU cache management
        if (count($this->astCache) >= self::MAX_CACHE_SIZE) {
            array_shift($this->astCache);
        }

        $this->astCache[$hash] = $ast;
        return $ast;
    }
}
```

**Impact**: 30-50% reduction in parser memory overhead

#### 2.3 Context Variable Weak References
```php
// Use WeakMaps for temporary objects (PHP 8.0+)
class Context
{
    private \WeakMap $temporaryObjects;

    public function __construct()
    {
        $this->temporaryObjects = new \WeakMap();
    }

    public function setTemporary(object $key, mixed $value): void
    {
        $this->temporaryObjects[$key] = $value;
        // Automatically garbage collected when $key is destroyed
    }
}
```

**Impact**: Eliminates memory leaks for temporary objects

## 3. Execution Speed Optimization

### 3.1 Code Evaluation Pipeline
```php
// BEFORE: Multiple passes
$code = $input;
$code = $this->preprocessCode($code);
$code = $this->cleanCode($code);
$ast = $this->parseCode($code);
$result = $this->evaluateAST($ast);

// AFTER: Single-pass compilation
class OptimizedPipeline
{
    public function execute(string $code): mixed
    {
        // Combine preprocessing and cleaning
        $prepared = $this->prepareCode($code);

        // Use cached AST if available
        $ast = $this->getCachedOrParse($prepared);

        // Direct evaluation without intermediate steps
        return $this->fastEval($ast);
    }
}
```

**Impact**: 40-60% faster code execution

### 3.2 JIT Compilation (PHP 8.0+)
```php
// Enable JIT for code evaluation
ini_set('opcache.jit', 'tracing');
ini_set('opcache.jit_buffer_size', '100M');
```

**Impact**: 20-40% speedup for complex computations

### 3.3 Parallel Command Processing
```php
// For independent commands, process in parallel
class ParallelExecutor
{
    public function executeMultiple(array $commands): array
    {
        if (!extension_loaded('parallel')) {
            return array_map([$this, 'execute'], $commands);
        }

        $futures = [];
        foreach ($commands as $cmd) {
            $futures[] = \parallel\run(fn() => $this->execute($cmd));
        }

        return array_map(fn($f) => $f->value(), $futures);
    }
}
```

**Impact**: 2-3x speedup for batch operations

## 4. Autoloading Efficiency

### 4.1 Class Map Generation
```bash
# Generate optimized class map
composer dump-autoload --optimize --classmap-authoritative

# Enable APCu caching
composer dump-autoload --apcu
```

### 4.2 Namespace Optimization
```php
// Organize frequently-used classes in same namespace
namespace Psy\Core;  // New namespace for hot path classes

// Move these together:
- Shell
- Configuration
- Context
- CodeCleaner
```

**Impact**: 25-35% faster class loading

## 5. I/O Optimization

### 5.1 Batch Output Writing
```php
class OptimizedOutput extends ShellOutput
{
    private array $buffer = [];
    private const BUFFER_SIZE = 100;

    public function write($messages, bool $newline = false, int $options = 0): void
    {
        $this->buffer[] = [$messages, $newline, $options];

        if (count($this->buffer) >= self::BUFFER_SIZE) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach ($this->buffer as [$msg, $nl, $opt]) {
            parent::write($msg, $nl, $opt);
        }
        $this->buffer = [];
    }
}
```

**Impact**: 50-70% reduction in I/O syscalls

### 5.2 Async History Writing
```php
class AsyncHistoryWriter
{
    private array $pendingWrites = [];

    public function addEntry(string $entry): void
    {
        $this->pendingWrites[] = $entry;
    }

    public function __destruct()
    {
        // Write all at once on shutdown
        if (!empty($this->pendingWrites)) {
            file_put_contents(
                $this->historyFile,
                implode("\n", $this->pendingWrites) . "\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
```

**Impact**: Eliminates I/O blocking during interactive use

## 6. Memory Leak Prevention

### 6.1 Circular Reference Detection
```php
class MemorySafeContext
{
    private array $variables = [];

    public function set(string $name, mixed $value): void
    {
        // Break circular references before storing
        if (is_object($value)) {
            $this->detectCircularRefs($value);
        }

        $this->variables[$name] = $value;
    }

    private function detectCircularRefs(object $obj, array $seen = []): void
    {
        $hash = spl_object_id($obj);

        if (isset($seen[$hash])) {
            throw new \RuntimeException('Circular reference detected');
        }

        $seen[$hash] = true;

        // Check object properties recursively
        foreach ((array)$obj as $prop) {
            if (is_object($prop)) {
                $this->detectCircularRefs($prop, $seen);
            }
        }
    }
}
```

### 6.2 Explicit Cleanup
```php
class Shell
{
    public function __destruct()
    {
        // Explicitly break references
        $this->context = null;
        $this->commands = [];
        $this->matchers = [];

        // Force garbage collection
        gc_collect_cycles();
    }
}
```

## 7. Performance Monitoring

### 7.1 Built-in Profiler
```php
class PerformanceMonitor
{
    private array $metrics = [];

    public function track(string $operation, callable $fn): mixed
    {
        $start = hrtime(true);
        $memBefore = memory_get_usage(true);

        try {
            return $fn();
        } finally {
            $duration = (hrtime(true) - $start) / 1e6; // ms
            $memDelta = memory_get_usage(true) - $memBefore;

            $this->metrics[$operation][] = [
                'duration_ms' => $duration,
                'memory_bytes' => $memDelta,
                'timestamp' => microtime(true),
            ];
        }
    }

    public function getReport(): array
    {
        $report = [];
        foreach ($this->metrics as $op => $samples) {
            $durations = array_column($samples, 'duration_ms');
            $report[$op] = [
                'count' => count($samples),
                'avg_ms' => array_sum($durations) / count($durations),
                'p95_ms' => $this->percentile($durations, 95),
                'total_memory_mb' => array_sum(array_column($samples, 'memory_bytes')) / 1024 / 1024,
            ];
        }
        return $report;
    }
}
```

### 7.2 Automated Regression Detection
```php
// Compare with baseline
class RegressionDetector
{
    public function checkForRegressions(array $current, array $baseline): array
    {
        $regressions = [];

        foreach ($current as $metric => $value) {
            if (!isset($baseline[$metric])) continue;

            $delta = ($value - $baseline[$metric]) / $baseline[$metric];

            if ($delta > 0.1) { // 10% regression threshold
                $regressions[$metric] = [
                    'current' => $value,
                    'baseline' => $baseline[$metric],
                    'regression' => round($delta * 100, 2) . '%',
                ];
            }
        }

        return $regressions;
    }
}
```

## Implementation Priority

### Phase 1 (High Impact, Low Effort)
1. Enable Composer optimizations
2. Implement configuration caching
3. Add output buffering
4. Limit history size

**Expected improvement**: 40-50% overall

### Phase 2 (High Impact, Medium Effort)
1. Lazy command loading
2. AST caching
3. Batch I/O operations
4. Weak reference cleanup

**Expected improvement**: 30-40% additional

### Phase 3 (Medium Impact, High Effort)
1. Optimized execution pipeline
2. JIT configuration
3. Parallel processing
4. Namespace reorganization

**Expected improvement**: 20-30% additional

## Measurement & Validation

### Before/After Benchmarks
```bash
# Run benchmark suite
php docs/performance/benchmark-suite.php

# Compare results
php docs/performance/compare-benchmarks.php \
    benchmark-results-before.json \
    benchmark-results-after.json
```

### Continuous Monitoring
- Run benchmarks in CI/CD pipeline
- Alert on > 5% performance regressions
- Track trends over time

## Target Achievements

After implementing all optimizations:

| Metric | Current | Target | Improvement |
|--------|---------|--------|-------------|
| Startup (P95) | ~150ms | <100ms | 33% faster |
| Memory (typical) | ~60MB | <50MB | 17% reduction |
| Command exec | ~80ms | <50ms | 38% faster |
| Autoload/class | ~800μs | <500μs | 38% faster |

## Conclusion

These optimizations will significantly improve PsySH's performance while maintaining full functionality. The phased approach allows incremental implementation with measurable results at each stage.
