# Metrics Display Feature

## Overview

The Metrics Display feature provides real-time feedback about command execution in PsySH, showing essential development information below each command prompt.

## Features

### Displayed Metrics

The metrics display shows:

- **Time**: Execution time for each command
  - Automatically formatted in μs (microseconds), ms (milliseconds), or s (seconds)
- **Memory**: Current memory usage
  - Formatted in B, KB, MB, or GB as appropriate
- **Vars**: Number of variables currently in scope
- **Cmds**: Total number of commands executed in the session
- **Peak**: Peak memory usage (only shown when different from current)

### Example Output

```
>>> $greeting = "Hello, PsySH!";
= "Hello, PsySH!"

┌─ Time: 3.76ms │ Memory: 10.00MB │ Vars: 3 │ Cmds: 1 ─┘

>>> $numbers = range(1, 100);
= [1, 2, 3, ..., 100]

┌─ Time: 2.31ms │ Memory: 12.00MB │ Vars: 4 │ Cmds: 2 ─┘
```

## Configuration

### Enable/Disable by Default

In your PsySH configuration file (`~/.config/psysh/config.php`):

```php
<?php

return [
    // Disable metrics display by default
    'showMetrics' => false,
    
    // ... other configuration options
];
```

### Interactive Toggle

Use the `metrics` command during a PsySH session:

```
>>> metrics --on     # Enable metrics display
>>> metrics --off    # Disable metrics display
>>> metrics          # Toggle current state
```

### Command-Line Help

Get help about the metrics command:

```
>>> help metrics
```

## Use Cases

The metrics display is particularly useful for:

1. **Performance Monitoring**: Track execution time of different approaches
2. **Memory Debugging**: Identify memory-intensive operations
3. **Variable Management**: Keep track of scope complexity
4. **Learning**: Understand the cost of different PHP operations
5. **Development**: Quick feedback during interactive coding

## Technical Details

### Classes

- **MetricsCollector** (`Psy\Metrics\MetricsCollector`): Collects metrics data
- **MetricsDisplay** (`Psy\Metrics\MetricsDisplay`): Formats and displays metrics
- **MetricsCommand** (`Psy\Command\MetricsCommand`): Interactive command

### Integration Points

The metrics system integrates into the Shell's execution loop:

- `Shell::onExecute()`: Starts metrics collection before code execution
- `Shell::afterLoop()`: Ends collection and displays metrics after each loop

### Quiet Mode

Metrics display respects PsySH's quiet mode (`-q` flag) and will not display when output is set to quiet.

## Examples

### Basic Usage

```php
>>> $a = 1;
= 1
┌─ Time: 3.76ms │ Memory: 10.00MB │ Vars: 3 │ Cmds: 1 ─┘

>>> $b = 2;
= 2
┌─ Time: 2.30ms │ Memory: 10.00MB │ Vars: 4 │ Cmds: 2 ─┘
```

### Disabling Temporarily

```php
>>> metrics --off
Metrics display disabled.

>>> $x = 100;
= 100

>>> metrics --on
Metrics display enabled.

>>> $y = 200;
= 200
┌─ Time: 2.15ms │ Memory: 12.00MB │ Vars: 5 │ Cmds: 3 ─┘
```

### Performance Comparison

```php
>>> $start = microtime(true);
>>> $arr1 = array_map(fn($x) => $x * 2, range(1, 1000));
>>> $end = microtime(true);
= [2, 4, 6, ..., 2000]
┌─ Time: 5.23ms │ Memory: 14.50MB │ Vars: 7 │ Cmds: 3 ─┘

>>> $arr2 = [];
>>> for ($i = 1; $i <= 1000; $i++) { $arr2[] = $i * 2; }
┌─ Time: 12.47ms │ Memory: 15.00MB │ Vars: 9 │ Cmds: 4 ─┘
```

## Testing

The feature includes comprehensive unit tests:

```bash
vendor/bin/phpunit test/Metrics/
```

Tests cover:
- Metrics collection (timing, memory, commands)
- Display formatting and styling
- Enable/disable functionality
- Quiet mode behavior
- Integration with Shell
