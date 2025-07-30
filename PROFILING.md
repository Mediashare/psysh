# Profiling with PsySH

PsySH includes a `profile` command that allows you to profile your code using `xhprof`.

## Usage

To use the `profile` command, you must have the `xhprof` extension installed and enabled.

```bash
> profile [options] [code]
```

### Options

* `--out`: Path to the output file for the profiling data.
* `--full`: Show full profiling data including PsySH overhead.
* `--filter`: Filter level: user (default), php, all
* `--threshold`: Minimum time threshold in microseconds

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
