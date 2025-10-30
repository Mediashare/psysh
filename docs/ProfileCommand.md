# ProfileCommand Documentation

## Overview

The `profile` command is a powerful performance analysis tool for PsySH that helps you identify performance bottlenecks in your PHP code. It measures execution time, memory usage, and function call counts to help you optimize your applications.

**Key Features:**
- Profiles any PHP expression or code block
- Supports both XHProf and Xdebug profiling engines
- Intelligent filtering to focus on relevant code
- Adaptive time/memory formatting for easy reading
- Export capabilities for external analysis
- Detailed function call analysis with parameters

## Installation

### Requirements

The profile command requires **either** XHProf or Xdebug extension to be installed.

#### Option 1: XHProf (Recommended)

XHProf is faster and provides more accurate profiling data with lower overhead.

```bash
# Install via PECL
pecl install xhprof

# Enable in php.ini
extension=xhprof.so
```

#### Option 2: Xdebug

Xdebug is more widely available and can capture ALL function calls including internal functions.

```bash
# Install via PECL
pecl install xdebug

# Enable in php.ini
zend_extension=xdebug.so
xdebug.mode=develop,debug,trace
```

**Note:** XHProf is preferred for production-like profiling due to its lower overhead. Use Xdebug's `--trace-all` mode when you need to capture every single function call, including native PHP functions like `strlen()`.

## Basic Usage

### Simple Profiling

Profile any PHP expression directly in the PsySH shell:

```php
>>> profile $calculator->compute(1000)
```

### Profile with Variable Output

```php
>>> profile $result = $service->processData($input)
```

### Profile Complex Operations

```php
>>> profile foreach ($items as $item) { $processor->handle($item); }
```

## Options Reference

### `--full`

**Type:** Flag (no value)
**Description:** Shows complete profiling data including PsySH framework overhead.

By default, the profile command filters out PsySH's internal functions to focus on your code. Use `--full` to see everything, including the shell's execution machinery.

```php
>>> profile --full $myFunction()
```

**When to use:** Debugging PsySH integration issues or understanding total execution context.

---

### `--filter=LEVEL`

**Type:** String option
**Values:** `user` (default), `php`, `all`
**Description:** Controls which functions are displayed in the profiling results.

**Filter Levels:**
- `user`: Shows only user-defined functions and methods (default)
- `php`: Shows user code + PHP internal functions (strlen, array_filter, etc.)
- `all`: Shows everything including PsySH framework code

```php
>>> profile --filter=user $myCode()      # Only your code
>>> profile --filter=php $myCode()       # Your code + PHP internals
>>> profile --filter=all $myCode()       # Everything
```

**Note:** The `--full` flag is a shortcut for `--filter=all`.

---

### `--threshold=N`

**Type:** Integer
**Unit:** Microseconds (μs)
**Default:** 0 (show all functions)
**Description:** Filters out functions that executed faster than the specified threshold.

```php
>>> profile --threshold=1000 $operation()     # Hide functions < 1ms
>>> profile --threshold=10000 $operation()    # Hide functions < 10ms
>>> profile --threshold=100000 $operation()   # Hide functions < 100ms
```

**Common Thresholds:**
- `1000` μs (1ms): Good for filtering out noise in most applications
- `10000` μs (10ms): Focus on major performance bottlenecks
- `100000` μs (100ms): Identify critical slow operations only

---

### `--show-params`

**Type:** Flag (no value)
**Description:** Displays function parameters in the profiling results table.

Adds a "Parameters" column showing the signature of each profiled function, including default values where available.

```php
>>> profile --show-params $processor->execute($data)
```

**Output Example:**
```
| Function           | Calls | Time    | Parameters              |
|--------------------|-------|---------|-------------------------|
| processData()      | 1     | 150ms   | $data, $options=[]      |
| validateInput()    | 1     | 5ms     | $input, $strict=false   |
```

---

### `--full-namespaces`

**Type:** Flag (no value)
**Description:** Shows complete namespace paths without truncation.

By default, long namespaces are shortened for readability. This option displays the full qualified class/function names.

```php
>>> profile --full-namespaces $service->method()
```

**Without flag:**
```
Command::execute()
ExecutionClosure::handle()
```

**With flag:**
```
Psy\Command\ProfileCommand::execute()
Psy\ExecutionClosure\Closure::handle()
```

---

### `--trace-all`

**Type:** Flag (no value)
**Requires:** Xdebug extension
**Description:** Forces the use of Xdebug tracing to capture ALL function calls.

This includes native PHP functions that XHProf typically skips (like `strlen()`, `is_array()`, etc.). Results in much more detailed data but significantly higher overhead.

```php
>>> profile --trace-all $complexOperation()
```

**Note:** Automatically uses Xdebug even if XHProf is available. Use when you need complete visibility into every function call.

---

### `--out=PATH`

**Type:** String (file path)
**Description:** Exports the complete profiling data to a JSON file.

Saves the normalized profiling data for later analysis, sharing, or processing with external tools.

```php
>>> profile --out=/tmp/profile.json $operation()
>>> profile --out=./profiles/$(date +%Y%m%d).json $batch->process()
```

**Output Format:** JSON with the following structure:
```json
{
  "functionName": {
    "calls": 10,
    "time": 150000,
    "memory": 1048576,
    "peak_memory": 2097152,
    "cpu_time": 145000,
    "time_percent": 45.5,
    "memory_percent": 32.1,
    "is_user": true
  }
}
```

---

### `--debug`

**Type:** Flag (no value)
**Description:** Shows detailed debugging information about the profiling process.

Useful for troubleshooting profiling issues, understanding context reconstruction, or debugging unexpected results.

```php
>>> profile --debug $problematicCode()
```

**Debug Output Includes:**
- Raw profiling data keys
- Filter level settings
- Context reconstruction details
- Generated profiling scripts (for Xdebug)
- Execution commands

## Filter Levels Explained

Understanding the filter levels helps you focus on the right information:

### `user` (Default)

**Shows:** Only your application code and third-party libraries you've loaded.

**Filters Out:**
- PsySH framework functions
- Symfony Console components
- PhpParser internals
- PHP internal functions

**Best For:**
- General application profiling
- Finding bottlenecks in business logic
- Optimizing your code

**Example:**
```php
>>> profile --filter=user $service->calculate()

| Function                    | Calls | Time    | Memory  |
|-----------------------------|-------|---------|---------|
| MyService::calculate()      | 1     | 45ms    | 256KB   |
| DataProcessor::transform()  | 50    | 35ms    | 128KB   |
| Cache::get()                | 10    | 5ms     | 64KB    |
```

---

### `php`

**Shows:** User code + PHP internal functions (strlen, array_map, json_decode, etc.)

**Filters Out:**
- PsySH framework code
- Console rendering functions

**Best For:**
- Identifying expensive PHP operations
- Understanding string/array operation costs
- Optimizing data structure usage

**Example:**
```php
>>> profile --filter=php $parser->parse($json)

| Function                    | Calls | Time    | Memory  |
|-----------------------------|-------|---------|---------|
| JsonParser::parse()         | 1     | 45ms    | 256KB   |
| json_decode()               | 1     | 30ms    | 200KB   |
| preg_match()                | 150   | 10ms    | 32KB    |
| strlen()                    | 500   | 2ms     | 0B      |
```

---

### `all` (or `--full`)

**Shows:** Everything - your code, PHP internals, and PsySH framework.

**Filters Out:** Nothing.

**Best For:**
- Debugging PsySH integration issues
- Understanding total execution context
- Measuring true overhead
- Framework development

**Example:**
```php
>>> profile --filter=all $myCode()

| Function                           | Calls | Time    | Memory  |
|------------------------------------|-------|---------|---------|
| Psy\Shell::execute()               | 1     | 100ms   | 512KB   |
| MyService::calculate()             | 1     | 45ms    | 256KB   |
| Psy\ExecutionClosure::execute()    | 1     | 35ms    | 200KB   |
| Symfony\Console\Output::writeln()  | 5     | 15ms    | 64KB    |
```

## Examples

### Example 1: Profile a Method Call

**Task:** Analyze the performance of a calculation method.

```php
>>> $calculator = new Calculator()
>>> profile $calculator->fibonacci(30)

Profiling results (user code only):

| Function                    | Calls | Time    | Time % | Memory  | Memory % |
|-----------------------------|-------|---------|--------|---------|----------|
| Calculator::fibonacci()     | 1     | 234ms   | 98.5%  | 128KB   | 95.2%    |
| Calculator::memoize()       | 30    | 3.5ms   | 1.5%   | 6.4KB   | 4.8%     |

Total execution: Time: 238ms, Memory: 134KB
```

---

### Example 2: Find Slow Functions

**Task:** Identify functions taking more than 10ms.

```php
>>> profile --threshold=10000 $service->batchProcess($items)

Profiling results (user code only):

| Function                      | Calls | Time    | Time % | Memory  | Memory % |
|-------------------------------|-------|---------|--------|---------|----------|
| BatchProcessor::process()     | 1     | 450ms   | 75.0%  | 2.5MB   | 80.0%    |
| DatabaseQuery::execute()      | 50    | 125ms   | 20.8%  | 512KB   | 16.4%    |
| Cache::warmup()               | 1     | 25ms    | 4.2%   | 128KB   | 3.6%     |

Total execution: Time: 600ms, Memory: 3.1MB
```

Functions under 10ms are hidden, letting you focus on the real bottlenecks.

---

### Example 3: Profile with Custom Threshold and Export

**Task:** Profile a complex operation, filter noise, and save results.

```php
>>> profile --threshold=5000 --out=/tmp/api-profile.json $api->handleRequest($request)

Profiling results (user code only):

| Function                      | Calls | Time    | Time % | Memory  | Memory % |
|-------------------------------|-------|---------|--------|---------|----------|
| ApiController::handle()       | 1     | 850ms   | 68.5%  | 4.2MB   | 70.0%    |
| RequestValidator::validate()  | 1     | 125ms   | 10.1%  | 512KB   | 8.5%     |
| Database::query()             | 15    | 200ms   | 16.1%  | 1.2MB   | 20.0%    |
| ResponseBuilder::build()      | 1     | 65ms    | 5.2%   | 96KB    | 1.5%     |

Total execution: Time: 1.24s, Memory: 6MB

Profile data saved to: /tmp/api-profile.json
```

The exported JSON can be analyzed with external tools or compared across runs.

---

### Example 4: Profile with Parameters Shown

**Task:** Understand function signatures while profiling.

```php
>>> profile --show-params --threshold=1000 $validator->validate($data)

Profiling results (user code only):

| Function              | Calls | Time    | Parameters                    |
|-----------------------|-------|---------|-------------------------------|
| Validator::validate() | 1     | 45ms    | $data, $rules=[], $strict=true|
| Rule::apply()         | 10    | 35ms    | $value, $context=null         |
| Sanitizer::clean()    | 10    | 8ms     | $input, $flags=0              |

Total execution: Time: 88ms, Memory: 256KB
```

---

### Example 5: Debug Profiling Issues

**Task:** Troubleshoot unexpected profiling behavior.

```php
>>> profile --debug --filter=php $problematic->method()

Debug: raw --full=false
Debug: filterLevel=php, showAll=false, showParams=false, fullNamespaces=false, traceAll=false
Raw profile data keys:
  main()==>MyClass::method()
  MyClass::method()==>strlen()
  MyClass::method()==>array_map()
Filter level: php, Original: 35 functions, Filtered: 8 functions

Profiling results (user code + PHP internals):
[results table]
```

The debug output helps understand what's being filtered and why.

---

### Example 6: Deep Trace with Xdebug

**Task:** Capture every single function call, including PHP internals.

```php
>>> profile --trace-all --filter=php $stringProcessor->process($text)

Profiling results (user code + PHP internals):

| Function                      | Calls | Time    | Time % | Memory  | Memory % |
|-------------------------------|-------|---------|--------|---------|----------|
| StringProcessor::process()    | 1     | 125ms   | 62.5%  | 512KB   | 60.0%    |
| preg_replace()                | 50    | 45ms    | 22.5%  | 256KB   | 30.0%    |
| strlen()                      | 500   | 15ms    | 7.5%   | 0B      | 0.0%     |
| str_replace()                 | 100   | 10ms    | 5.0%   | 64KB    | 7.5%     |
| is_string()                   | 600   | 5ms     | 2.5%   | 0B      | 0.0%     |

Total execution: Time: 200ms, Memory: 832KB
```

## Architecture

### Engine Abstraction

The ProfileCommand uses an **adaptive engine selection** strategy:

```
┌─────────────────────────────────────┐
│      ProfileCommand                 │
│  (Symfony Console Command)          │
└───────────────┬─────────────────────┘
                │
                ├──> Check Extensions
                │    ├─> XHProf available?
                │    └─> Xdebug available?
                │
                ├──> Select Engine
                │    ├─> XHProf (default, low overhead)
                │    └─> Xdebug (--trace-all or fallback)
                │
                ├──> Execute Code
                │    ├─> XHProf: In-process profiling
                │    └─> Xdebug: Subprocess with trace file
                │
                ├──> Normalize Data
                │    ├─> XHProf: parent==>child format
                │    └─> Xdebug: Aggregated trace format
                │
                └──> Display Results
                     ├─> Apply filters (user/php/all)
                     ├─> Apply threshold
                     └─> Format table
```

### Key Components

1. **Context Reconstruction**: Captures shell variables, constants, and classes to profile code with full context
2. **Data Normalization**: Converts different profiler formats into a unified structure
3. **Intelligent Filtering**: Smart heuristics to show relevant code while hiding framework noise
4. **Adaptive Formatting**: Time and memory displayed in human-readable units (μs/ms/s, B/KB/MB)

### Data Flow

```
User Code Input
      │
      ├─> Wrap with Profiler
      │   ├─> XHProf: xhprof_enable() / xhprof_disable()
      │   └─> Xdebug: xdebug_start_trace() / xdebug_stop_trace()
      │
      ├─> Execute in Context
      │   ├─> Capture variables
      │   ├─> Reconstruct classes
      │   └─> Preserve constants
      │
      ├─> Collect Raw Data
      │   ├─> Function calls
      │   ├─> Execution time
      │   └─> Memory usage
      │
      ├─> Normalize & Filter
      │   ├─> Apply filter level
      │   ├─> Apply threshold
      │   └─> Calculate percentages
      │
      └─> Display Results
          ├─> Table format
          ├─> Summary statistics
          └─> Optional export
```

## Troubleshooting

### Error: "XHProf or XDebug extension is not loaded"

**Cause:** Neither profiling extension is installed.

**Solution:**
```bash
# Install XHProf
pecl install xhprof
echo "extension=xhprof.so" >> /path/to/php.ini

# OR install Xdebug
pecl install xdebug
echo "zend_extension=xdebug.so" >> /path/to/php.ini
echo "xdebug.mode=develop,trace" >> /path/to/php.ini

# Verify installation
php -m | grep -E "(xhprof|xdebug)"
```

---

### Issue: "No functions exceeded the threshold"

**Cause:** Your threshold is too high for the profiled operation.

**Solution:**
```php
# Lower the threshold or remove it
>>> profile --threshold=0 $operation()
>>> profile $operation()  # No threshold
```

---

### Issue: "Xdebug trace file was not generated"

**Cause:** Xdebug is not configured for tracing or lacks write permissions.

**Solution:**
```bash
# Check Xdebug configuration
php -i | grep xdebug.mode
# Should include "trace"

# Add to php.ini
xdebug.mode=develop,trace
xdebug.trace_output_dir=/tmp

# Ensure directory is writable
chmod 777 /tmp
```

---

### Issue: Context reconstruction errors

**Cause:** Some objects or closures can't be serialized for the subprocess.

**Solution:**
```php
# Use --debug to see what's failing
>>> profile --debug $operation()

# Simplify the context or use XHProf instead of Xdebug
>>> profile $operation()  # XHProf doesn't need subprocess
```

---

### Issue: Too much PsySH noise in results

**Cause:** Using `--full` or `--filter=all` shows framework code.

**Solution:**
```php
# Use default user filter
>>> profile $operation()
>>> profile --filter=user $operation()

# Or filter with threshold
>>> profile --threshold=10000 $operation()
```

---

### Issue: Missing PHP internal function calls

**Cause:** XHProf doesn't trace native PHP functions.

**Solution:**
```php
# Use Xdebug with --trace-all
>>> profile --trace-all --filter=php $operation()
```

This captures all native function calls but adds significant overhead.

## Performance Tips

### When to Use Each Engine

#### Use XHProf When:
- ✅ Profiling production or production-like code
- ✅ You need minimal overhead
- ✅ Focusing on user code and major PHP functions
- ✅ Running long-running processes
- ✅ Measuring real-world performance

**Overhead:** ~3-5% performance impact

```php
>>> profile $normalOperation()  # Automatically uses XHProf
```

#### Use Xdebug When:
- ✅ You need to see EVERY function call
- ✅ Debugging complex call chains
- ✅ Tracking down elusive performance issues
- ✅ Need detailed parameter information
- ✅ Analyzing internal PHP function usage

**Overhead:** ~50-100% performance impact

```php
>>> profile --trace-all $debugOperation()  # Forces Xdebug
```

### Optimizing Profiling Sessions

1. **Start Broad, Then Narrow**
   ```php
   # First: Get overview
   >>> profile $operation()

   # Then: Focus on slow parts
   >>> profile --threshold=10000 $operation()

   # Finally: Deep dive
   >>> profile --show-params --filter=php $specificMethod()
   ```

2. **Use Thresholds Effectively**
   ```php
   # For quick analysis (hide noise < 1ms)
   >>> profile --threshold=1000 $code()

   # For finding bottlenecks (show only > 10ms)
   >>> profile --threshold=10000 $code()

   # For critical issues only (> 100ms)
   >>> profile --threshold=100000 $code()
   ```

3. **Export for Comparison**
   ```php
   # Before optimization
   >>> profile --out=/tmp/before.json $operation()

   # After optimization
   >>> profile --out=/tmp/after.json $operation()

   # Compare externally
   ```

4. **Batch Profiling**
   ```php
   # Profile multiple operations
   >>> profile --out=op1.json $operation1()
   >>> profile --out=op2.json $operation2()
   >>> profile --out=op3.json $operation3()
   ```

### Best Practices

- **Profile realistic workloads**: Use representative data sizes
- **Run multiple times**: First run may include cold cache overhead
- **Check memory too**: Memory leaks are easier to spot than you think
- **Use version control**: Track profile exports alongside code changes
- **Set baselines**: Know your acceptable performance targets
- **Profile early**: Don't wait until production to find issues

## Limitations

### Current Limitations

1. **Subprocess Overhead (Xdebug Only)**
   - Xdebug tracing runs code in a subprocess
   - Context reconstruction may not capture all variables
   - Objects with complex serialization may fail
   - Closures defined in eval'd code cannot be reconstructed

   **Workaround:** Use XHProf for in-process profiling when possible.

2. **No Visualization**
   - Results are table-based only
   - No flame graphs or call graphs
   - No visual timeline

   **Workaround:** Export JSON and use external tools (e.g., speedscope.app, qcachegrind).

3. **Memory Measurements Are Approximate**
   - Shows memory deltas, not absolute peak usage
   - Memory may be freed during execution
   - PHP's garbage collector can affect measurements

   **Workaround:** Use multiple runs and focus on relative differences.

4. **No Real-time Profiling**
   - Must complete execution to see results
   - Cannot pause/resume profiling
   - Cannot profile indefinitely running processes

   **Workaround:** Profile specific code sections rather than full applications.

5. **Limited Async/Concurrent Support**
   - Does not track parallel execution
   - No support for ReactPHP, Swoole, or other async frameworks
   - Single-threaded profiling only

   **Workaround:** Profile synchronous paths individually.

### Known Issues

- **Large Trace Files**: Xdebug can generate huge trace files (GBs) for complex operations
- **Parameter Display**: `--show-params` shows signatures, not actual runtime values
- **Closure Names**: Anonymous closures may have auto-generated names
- **Magic Methods**: `__call()` and `__callStatic()` may not show target method names

### Future Enhancements

Planned improvements:
- Real-time streaming profiling results
- Flame graph generation
- Comparative profiling (before/after)
- Integration with external profiling tools
- Database query profiling hooks
- HTTP request profiling
- Custom metric collection

## See Also

- [PsySH Documentation](https://psysh.org/)
- [XHProf Documentation](https://www.php.net/manual/en/book.xhprof.php)
- [Xdebug Profiling Guide](https://xdebug.org/docs/profiler)
- [PHP Performance Best Practices](https://www.php.net/manual/en/internals2.opcodes.php)

---

**Version:** 1.0
**Last Updated:** October 2024
**Status:** Stable
