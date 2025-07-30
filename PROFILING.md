# Profiling with PsySH

PsySH includes a `profile` command that allows you to profile your code using `xhprof` or `Xdebug`.

## Usage

To use the `profile` command, you must have either the `xhprof` or `Xdebug` extension installed and enabled.

```bash
> profile [options] [code]
```

### Options

* `--out`: Path to the output file for the profiling data.
* `--full`: Show full profiling data including PsySH overhead.
* `--filter`: Filter level: user (default), php, all
* `--threshold`: Minimum time threshold in microseconds
* `--show-params`: Show function parameters in profiling results.
* `--full-namespaces`: Show complete namespaces without truncation.
* `--trace-all`: Use Xdebug tracing to capture ALL function calls (including strlen, etc.)

### Examples

```bash
> profile $calc->toBinary(1000)
> profile --threshold=100 $service->process($data)
> profile --full --out=profile.grind complex_operation()
```

### Profiling multiple statements

To profile a block of code with multiple statements, you can wrap it in a closure (an anonymous function) and execute it immediately:

```php
> profile (function() {
    $calc = new BinaryCalculator();
    $calc->toBinary((int) 999999999999999999999912321312312332123);
    sleep(5);
})()
```

### Context Awareness

The `profile` command is fully context-aware. It captures the state of your PsySH session, including:

*   **Defined Classes and Functions**: Any user-defined classes and functions are available within the profiled code.
*   **Scope Variables**: All variables, including objects and closures, are correctly passed to the profiling context.
*   **Autoloaders**: The state of Composer and any other registered autoloaders is preserved.
*   **Environment**: Constants and environment variables are replicated in the profiling process.

This ensures that code that relies on the interactive session's state can be profiled accurately without modification.
