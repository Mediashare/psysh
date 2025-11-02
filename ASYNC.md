# Async Metrics and Real-Time Status Bar

PsySH now supports asynchronous metrics tracking and real-time status bar display using the AMPHP library. This allows you to see execution time, memory usage, and other metrics in real-time as your code executes.

## Features

### 1. Async Metrics Manager
Tracks real-time metrics during code execution:
- Execution time (in microseconds, milliseconds, or seconds)
- Memory usage
- Peak memory usage

### 2. Status Bar
Displays real-time information at the bottom of the terminal during code execution:
- Shows execution time as code runs
- Shows current and peak memory usage
- Automatically hides after execution completes

### 3. Async Execution Wrapper
Wraps code execution to integrate metrics tracking seamlessly with the REPL.

## Installation

The async features are automatically available when you install PsySH with composer. The required AMPHP dependencies are included in the `composer.json`.

Optional extensions for improved async performance:
```bash
# Install EV extension (recommended)
pecl install ev

# Or install UV extension
pecl install uv
```

## Configuration

### Enable Async Features in Configuration File

Create or edit your PsySH configuration file (typically `~/.config/psysh/config.php`):

```php
<?php

return [
    // Enable async metrics tracking
    'useAsyncMetrics' => true,
    
    // Enable status bar
    'useStatusBar' => true,
];
```

### Enable at Runtime with the `async` Command

You can also enable/disable async features during a PsySH session:

```php
// Show async status
>>> async --status

// Enable async metrics
>>> async --enable

// Disable async metrics
>>> async --disable

// Enable status bar
>>> async --statusbar=on

// Disable status bar
>>> async --statusbar=off
```

## Usage Examples

### Example 1: Simple Code Execution with Metrics

```php
// Enable async features
>>> async --enable
>>> async --statusbar=on

// Run some code
>>> for ($i = 0; $i < 1000000; $i++) { $sum += $i; }
// You'll see real-time metrics in the status bar at the bottom

// Check final metrics
>>> async --status
```

### Example 2: Long-Running Operations

```php
>>> async --enable
>>> async --statusbar=on

// Simulate a long-running operation
>>> sleep(5);
// Watch the execution time update in real-time

>>> async --status
// See the final execution time and memory usage
```

### Example 3: Memory-Intensive Operations

```php
>>> async --enable
>>> async --statusbar=on

// Create a large array
>>> $bigArray = range(1, 1000000);
// Watch memory usage increase in real-time

>>> async --status
// Check peak memory usage
```

### Example 4: Programmatic Access

You can also access async components programmatically:

```php
// Get the async execution wrapper
>>> $wrapper = $__psysh__->getAsyncExecutionWrapper();

// Get metrics manager
>>> $metrics = $wrapper->getMetricsManager();

// Execute code with metrics tracking
>>> $result = $wrapper->execute(function() {
...     usleep(100000); // 100ms
...     return 42;
... });

// Get metrics
>>> $metrics->getMetrics();
=> [
     "execution_time" => 0.10023,
     "memory_usage" => 8388608,
     "peak_memory_usage" => 8388608,
     "is_running" => false,
   ]

// Get formatted output
>>> $metrics->getFormattedExecutionTime()
=> "100.23ms"

>>> $metrics->getFormattedMemoryUsage()
=> "8.00MB"
```

## API Reference

### Configuration Methods

```php
// Get configuration
$config = $shell->getConfig();

// Enable/disable async metrics
$config->setUseAsyncMetrics(true);
$config->useAsyncMetrics(); // Returns bool

// Enable/disable status bar
$config->setUseStatusBar(true);
$config->useStatusBar(); // Returns bool
```

### Shell Methods

```php
// Get async components
$shell->getAsyncExecutionWrapper(); // Returns AsyncExecutionWrapper|null
$shell->getAsyncMetricsManager(); // Returns AsyncMetricsManager|null
$shell->getStatusBar(); // Returns StatusBar|null
```

### AsyncMetricsManager Methods

```php
$manager = new \Psy\Async\AsyncMetricsManager();

// Start tracking
$manager->start();

// Stop tracking
$manager->stop();

// Check if running
$manager->isRunning(); // Returns bool

// Get metrics
$manager->getMetrics(); // Returns array

// Get formatted values
$manager->getFormattedExecutionTime(); // Returns string (e.g., "123.45ms")
$manager->getFormattedMemoryUsage(); // Returns string (e.g., "8.00MB")
$manager->getFormattedPeakMemoryUsage(); // Returns string (e.g., "10.50MB")

// Add listener for real-time updates
$manager->addListener(function($metrics) {
    // Called every 100ms while tracking
    echo "Time: {$metrics['execution_time']}\n";
});
```

### StatusBar Methods

```php
$statusBar = new \Psy\Async\StatusBar($output);

// Enable/disable
$statusBar->setEnabled(true);
$statusBar->isEnabled(); // Returns bool

// Update content
$statusBar->update('Custom status text');

// Update with metrics
$statusBar->updateWithMetrics($metrics);

// Hide status bar
$statusBar->hide();
```

### AsyncExecutionWrapper Methods

```php
$wrapper = new \Psy\Async\AsyncExecutionWrapper($metricsManager, $statusBar);

// Enable/disable
$wrapper->setEnabled(true);
$wrapper->isEnabled(); // Returns bool

// Execute with metrics
$result = $wrapper->execute(function() {
    // Your code here
    return 'result';
});

// Execute asynchronously (returns DeferredFuture)
$future = $wrapper->executeAsync(function() {
    // Your code here
    return 'result';
});

// Get components
$wrapper->getMetricsManager(); // Returns AsyncMetricsManager
$wrapper->getStatusBar(); // Returns StatusBar|null
$wrapper->setStatusBar($statusBar); // Set status bar
```

## How It Works

The async implementation uses the AMPHP library's event loop system:

1. When async metrics are enabled, an `AsyncMetricsManager` is created
2. The manager uses `Revolt\EventLoop` to update metrics every 100ms
3. A `StatusBar` component receives metric updates and displays them at the bottom of the terminal
4. The `AsyncExecutionWrapper` wraps code execution to automatically start/stop metrics tracking
5. After execution completes, the status bar is hidden and final metrics are available

## Performance Considerations

- The metrics update interval is set to 100ms (0.1 seconds) by default
- This provides smooth real-time updates without significant performance impact
- For very short code executions (< 100ms), you may not see status bar updates
- Installing the `ev` or `uv` PHP extensions can improve async performance

## Troubleshooting

### Status bar not appearing
- Check that your terminal supports ANSI escape codes
- Ensure `useStatusBar` is enabled in configuration
- Try enabling with `async --statusbar=on`

### Metrics not tracking
- Verify async metrics are enabled: `async --status`
- Enable with: `async --enable`
- Check that AMPHP dependencies are installed

### Event loop issues
- Ensure no other code is interfering with the event loop
- Try installing the `ev` or `uv` PHP extension for better compatibility

## Disabling Async Features

If you experience issues or prefer to disable async features:

```php
// During runtime
>>> async --disable
>>> async --statusbar=off

// In configuration file
return [
    'useAsyncMetrics' => false,
    'useStatusBar' => false,
];
```

## Integration with Other Features

The async system integrates seamlessly with:
- `timeit` command - Compare execution times
- `profile` command - Profile with real-time metrics
- All REPL features - Works transparently in the background

## Examples in Practice

### Database Query Monitoring
```php
>>> async --enable --statusbar=on
>>> $pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
>>> $stmt = $pdo->query('SELECT * FROM large_table');
>>> $results = $stmt->fetchAll();
// Watch memory usage grow as results are fetched
```

### API Request Timing
```php
>>> async --enable --statusbar=on
>>> $response = file_get_contents('https://api.example.com/data');
// See how long the request takes in real-time
```

### Algorithm Performance
```php
>>> async --enable --statusbar=on
>>> function fibonacci($n) {
...     if ($n <= 1) return $n;
...     return fibonacci($n-1) + fibonacci($n-2);
... }
>>> fibonacci(30);
// Watch execution time grow with recursive calls
```

## Future Enhancements

Potential future improvements:
- Configurable update interval
- Custom status bar formats
- Additional metrics (CPU usage, network I/O)
- Metric history and graphing
- Export metrics to file

## See Also

- [PROFILING.md](PROFILING.md) - Code profiling features
- [Configuration Options](https://github.com/bobthecow/psysh/wiki/Config-options)
- [AMPHP Documentation](https://amphp.org/)
