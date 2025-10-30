# ProfileCommand Refactoring Status

## ✅ Completed Phases

### 1. Engines Abstraction (Done)
**Files created:**
- `src/Profiling/ProfilerEngine.php` - Interface for profiling engines
- `src/Profiling/XhprofEngine.php` - XHProf in-process profiling
- `src/Profiling/XdebugInProcessEngine.php` - Xdebug in-process tracing (when XDEBUG_MODE=trace)
- `src/Profiling/XdebugSubprocessEngine.php` - Xdebug subprocess mode (fallback)

**Key features:**
- Auto-selects best available engine based on extensions and environment
- Consistent interface: `profile(code, shell, debug): array`
- Static `isAvailable()` for engine capability checking
- All engines return normalized profile data

### 2. ContextBuilder (Done)
**File:** `src/Profiling/ContextBuilder.php`

**Responsibilities:**
- Reconstructs shell execution context in subprocess
- Reinjects user-defined code from Shell::getExecutedCodeAsString()
- Serializes scope variables safely (scalars/arrays via var_export, objects via serialize/unserialize)
- Handles environment variables and user-defined constants
- Skips unserializable objects/resources with informative comments

### 3. Xdebug v3 Parser (Done)
**Integrated in both XdebugInProcessEngine and XdebugSubprocessEngine**

**Capabilities:**
- Parses Xdebug computerized trace format v3 (format=1)
- Handles enter ("->") and exit ("<-") operation codes
- Calculates inclusive time and memory delta per function
- Computes percentages for display
- Robust: skips TRACE START/END markers and empty lines

### 4. ProfileCommand Refactor (Done)
**File:** `src/Command/ProfileCommand.php`

**New structure:**
- `execute()`: Simplified orchestration - parse options, select engine, display, save
- `selectEngine()`: Deterministic engine selection with debug output
- Old engine-specific code removed (captured in ProfilerEngine implementations)
- All filtering/rendering logic remains intact
- Options fully supported:
  - `--full`: Alias for `--filter=all`
  - `--filter` (user|php|all): Apply filtering after engine execution
  - `--threshold`: Minimum time in μs; negative values clamped to 0
  - `--show-params`: Add parameters column to output table
  - `--full-namespaces`: Disable namespace truncation
  - `--trace-all`: Force XdebugSubprocessEngine with full tracing
  - `--out`: Save normalized JSON to file
  - `--debug`: Detailed engine and execution info

### 5. Normalization Pipeline (Done)
**In ProfileCommand.execute():**
- Engines return normalized data immediately (no post-processing needed)
- `displayResults()` applies level-based filtering and threshold
- `saveProfileData()` exports normalized JSON as-is

**Normalized schema (per function):**
```php
'function_name' => [
    'calls'         => int,
    'time'          => int,        // μs inclusive
    'exclusive_time' => int,       // μs
    'memory'        => int,        // bytes
    'peak_memory'   => int,        // bytes
    'cpu_time'      => int,        // μs
    'is_user'       => bool,       // true if user code
    'params'        => string|null // optional
]
```

## ⏳ Remaining Tasks

### 1. Add Tests (Next)
**Status:** Not started

**Unit tests needed:**
- `test/Profiling/XdebugParserTest.php` - Fixtures for v3 trace parsing
- `test/Profiling/XhprofNormalizationTest.php` - XHProf data handling
- `test/Profiling/ContextBuilderTest.php` - Context reconstruction

**Integration tests (in `test/Command/ProfileCommandTest.php`):**
- Each option individually and combined
- Error recovery (code that throws)
- REPL scope (variables, constants, classes, closures)
- --out with valid/invalid paths
- Engine selection logic

**Fixtures:**
- Sample Xdebug v3 trace files with various call patterns
- Expected normalized output for validation

### 2. Run Full Test Suite & Fix Regressions
**Status:** Not started

**Steps:**
1. Run `vendor/bin/phpunit test/Command/ProfileCommandTest.php`
2. Run `vendor/bin/phpunit test/Profiling/`
3. Fix any failures or regressions
4. Verify message text matches test expectations (e.g., "failed to save profile data")

## Quick Test Commands (Manual)

From psysh REPL:
```php
> profile 1+1
> profile --filter=php strlen("test")
> profile --full --threshold=0 usleep(1000)
> profile --show-params array_map(fn($x)=>$x*2, [1,2,3])
> profile --debug $var + 1
> profile --out=/tmp/test.json strlen("test")
> $code = 'fn() => 42'; profile $code()
```

## Architecture Diagram

```
ProfileCommand::execute()
    ↓
    ├─ Parse options
    ├─ selectEngine()
    │   ├─ XhprofEngine::isAvailable() ? XhprofEngine
    │   ├─ XdebugInProcessEngine::isAvailable() ? XdebugInProcessEngine
    │   ├─ XdebugSubprocessEngine::isAvailable() ? XdebugSubprocessEngine
    │   └─ throw RuntimeException
    │
    ├─ engine→profile(code, shell, debug)
    │   ├─ In-process engines: wrap code, enable profiler, execute, parse
    │   ├─ Subprocess: build context, write script, execute php subprocess, parse trace
    │   └─ return normalized array
    │
    ├─ displayResults(data, filterLevel, threshold, ...)
    │   ├─ filterFunctions(data, filterLevel, threshold)
    │   ├─ render table (top 20 functions)
    │   └─ display summary
    │
    └─ if --out: saveProfileData(data, outFile)
```

## Key Improvements

1. **Robustness**: Engines handle errors gracefully; subprocess always emits trace path
2. **Scope Fidelity**: REPL context properly reconstructed in subprocess via `getExecutedCodeAsString()`
3. **Clarity**: Engine selection explicit; options behavior documented
4. **Separation of Concerns**: ProfileCommand orchestrates; engines handle profiling details
5. **Testability**: Each engine and normalization step can be tested independently

## Known Limitations

1. **Exclusive Time**: Computed as call duration only (not subtracting child calls); fine for filtering but not for flame graphs
2. **Parameters**: Captured from trace format when available; may be incomplete for complex types
3. **Trace File Size**: No streaming parser; large traces (~100MB+) load entirely into memory

## Next Steps (After Tests Pass)

1. ✅ Consider optional flame graph export (future enhancement)
2. ✅ Add CacheGrind format support for profiling tools integration
3. ✅ Implement exclusive time calculation via child subtraction
4. ✅ Profile documentation in README

---

**Last Updated:** 2025-10-29
**Refactoring Completion:** 80% (engines, builders, parsing done; testing remains)
