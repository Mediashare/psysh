# PsySH ProfileCommand - Patch Notes

## Version 1.2.0 - 2025-08-07

### Major Enhancements

#### Simplified and Robust `profile` Command Execution
- **Refactoring**: The `profile` command has been refactored to use the shell's own execution engine (`Shell::execute`) instead of a custom implementation that relied on temporary files and manual context recreation.
- **Benefits**:
  - **Accuracy**: Profiling now occurs within the exact same execution context as normal PsySH operations, leading to more accurate and reliable performance metrics.
  - **Simplicity**: Removed over 100 lines of complex, brittle code related to temporary file generation and scope variable serialization.
  - **Robustness**: The new implementation is more resilient and less prone to errors, especially with complex types like closures and objects.
- **Fallback Mechanism**: In environments where `Shell::execute` might not be fully available (like certain test scenarios), a fallback to a direct `eval()` with restored scope variables is implemented, ensuring functionality is maintained.

#### New `--debug` option for `profile` command
- **Feature**: A `--debug` flag has been added to the `profile` command to help with troubleshooting the profiler itself.
- **Functionality**: When enabled, it provides verbose output about the profiling process, including the generated script and any errors encountered.

#### Files Modified
- `src/Command/ProfileCommand.php`: Major refactoring of the execution logic to use `Shell::execute`.
- `PROFILING.md`: Updated to reflect the new execution mechanism and added the `--debug` option.
- `GEMINI.md`: Updated the profiling section to describe the new implementation.

## Version 1.1.0 - 2025-07-30

### Major Enhancements

#### Complete Context Preservation for ProfileCommand
- **Feature**: ProfileCommand now captures and preserves the complete execution context from PsySH shell, including classes, functions, constants, and external projects loaded via `psysh autoload`.
- **Implementation**:
  - Added execution history tracking to `Shell.php` with `$executedCodeHistory` property
  - Automatic interception and storage of all code definitions (classes, functions, constants, namespaces)
  - Dynamic context reconstruction without hardcoded logic
  - Support for external PHP projects (Symfony, Laravel, WordPress) via AutoloadCommand integration
  - Process isolation for clean profiling while maintaining complete context

#### Enhanced ProfileCommand Features
- **Human-Readable Formatting**: Adaptive time and memory display
  - Time: μs → ms → s → min (automatically scaled)
  - Memory: B → KB → MB → GB (automatically scaled)
  - Automatic removal of trailing zeros
- **Improved Context Capture**:
  - Composer autoloader detection and restoration
  - Environment variable preservation (APP_ENV, DATABASE_URL, etc.)
  - Framework object detection and handling
  - Shell-defined classes, functions, and constants
  - User-defined constants capture
- **Better Error Handling**: Comprehensive fallbacks and error recovery

#### Files Modified
- `src/Shell.php`:
  - Added `$executedCodeHistory` property
  - Modified `execute()` to track code definitions
  - Added `addToExecutedCodeHistory()`, `getExecutedCodeHistory()`, `getExecutedCodeAsString()`
  - Added intelligent filtering for definitions vs execution code
- `src/Command/ProfileCommand.php`:
  - Complete rewrite of context serialization system
  - Added `formatTime()` and `formatMemory()` for adaptive display
  - Enhanced autoloader and environment variable capture
  - Removed hardcoded class reconstruction logic
  - Improved process isolation with context preservation

#### Example Usage
```php
// Define a class in PsySH shell:
> class BinaryCalculator {
.     public function toBinary($num) {
.         return $this->convert($num);
.     }
.     private function convert($num) {
.         return decbin($num);
.     }
. }

// Profile it directly - context is automatically preserved:
> profile ($calc = new BinaryCalculator())->toBinary(10);
Profiling results (user code only):
+------------------+-------+--------+--------+--------+----------+
| Function         | Calls | Time   | Time % | Memory | Memory % |
+------------------+-------+--------+--------+--------+----------+
| BinaryCalculator | 1     | 15 μs  | 45.2%  | 1.2 KB | 35.8%   |
+------------------+-------+--------+--------+--------+----------+

Total execution: Time: 33 μs, Memory: 3.4 KB
```

### Bug Fixes

#### Fixed Multi-line Input Support for Commands with CodeArgument
- **Issue**: Commands like `timeit` that accept PHP code arguments (CodeArgument) were failing when the code was incomplete on the first line, preventing multi-line input.
- **Root Cause**: 
  - The `ShellInput` parser was including the command name in the extracted code argument
  - For `timeit if (true) {`, the code argument became `"timeit if (true) {"` instead of `"if (true) {"`
  - This caused the `CodeArgumentParser` to throw `"unexpected T_IF"` instead of `"unexpected EOF"`
  - The multi-line detection logic didn't recognize `"unexpected T_IF"` as an incomplete code pattern
- **Fix**: 
  - Added `hasCodeArgument()` method to `ShellInput` class for better detection
  - Modified `handleMultiLineCodeArgument()` in `Shell.php` to properly extract PHP code by removing the command name prefix
  - The code extraction now correctly identifies `"if (true) {"` as incomplete PHP code requiring multi-line input

#### Updated Test Compatibility
- Modified `ProfileCommandTest.php` to support new adaptive formatting
- Updated assertions to work with flexible time/memory units
- All existing tests pass with enhanced functionality

#### Example Usage
```php
// Multi-line commands now work correctly:
> timeit if (true) {
. echo "hello";
. }
Command took 0.000123 seconds to complete.

// ProfileCommand with complete context preservation:
> profile ($calc = new BinaryCalculator())->toBinary(10);
// Works perfectly with shell-defined classes!
```

## Version 1.0.2 - 2025-07-30 (Previous)

## Version 1.0.1 - 2025-07-30

### Bug Fixes

#### Fixed "Undefined array key 'is_user'" error
- **Issue**: `profile` command failed with a PHP warning when processing profiling data.
- **Root Cause**: The `filterProfileData` function was not setting the `is_user` flag, which is required by the `filterFunctions` method to correctly identify and filter user-land code versus PsySH internal code.
- **Fix**: Modified `filterProfileData` to correctly identify PsySH functions and set the `is_user` flag to `false` for them, ensuring the filter works as expected.

## Version 1.0.0 - 2024-07-29

### 
 Bug Fixes

#### Fixed Xdebug 3.x Compatibility Issues
- **Issue**: ProfileCommand was failing with "Could not find a cachegrind output file" error
- **Root Cause**: 
  - Code was only enabling Xdebug profiling for `--full` mode (`filterLevel === 'all'`)
  - For default 'user' filter, it tried to use deprecated `xdebug_start_profiling()` and `xdebug_stop_profiling()` functions
  - These functions were removed in Xdebug 3.x, so no cachegrind files were generated
  - The cachegrind parser was written for an older format that doesn't match Xdebug 3.x output

#### Changes Made

**1. Fixed Xdebug Profiling Configuration**
```php
// BEFORE: Only enabled for 'all' filter level
if ($filterLevel === 'all') {
    $processArgs = array_merge($processArgs, [
        '-d', 'xdebug.mode=profile',
        '-d', 'xdebug.start_with_request=yes',
        '-d', 'xdebug.output_dir='.$tmpDir,
    ]);
}

// AFTER: Always enabled since Xdebug 3.x removed manual control
$processArgs = array_merge($processArgs, [
    '-d', 'xdebug.mode=profile',
    '-d', 'xdebug.start_with_request=yes', 
    '-d', 'xdebug.output_dir='.$tmpDir,
]);
```

**2. Removed Deprecated Xdebug Functions**
```php
// BEFORE: Used non-existent functions in Xdebug 3.x
if ($filterLevel !== 'all') {
    $script .= "if (function_exists('xdebug_start_profiling')) { xdebug_start_profiling(); }\n";
    $script .= $userCode."\n";
    $script .= "if (function_exists('xdebug_stop_profiling')) { xdebug_stop_profiling(false); }\n";
}

// AFTER: Simplified - let Xdebug handle profiling via INI settings
$script = $contextCode;
$script .= $userCode."\n";
```

**3. Rewrote Cachegrind Parser for Xdebug 3.x Format**
```php
// NEW PARSER HANDLES:
// fl=(2) Command line code          <- File reference
// fn=(1) {main}                     <- Function definition  
// 1 1058 32                         <- Line Time Memory
// summary: 4054 424360              <- Total Time Memory
```

**4. Fixed Parser Errors**
- Added proper error handling for undefined array keys
- Implemented correct regex patterns for Xdebug 3.x format
- Fixed summary parsing to extract both time and memory values

### 
 Improvements

**Better Error Handling**
- Parser no longer crashes on unexpected format variations
- Graceful handling of missing function data

**Enhanced Compatibility**  
- Works with Xdebug 3.x (tested with 3.4.4)
- Maintains backward compatibility where possible

### 
 Testing

**Verified Functionality**
```bash
# Command now works correctly:
> profile (new BinaryCalculator())->toBinary(100)

# Output shows proper profiling table:
Profiling results (user code only):
+----------+-------+-----------+--------+-------------+----------+
| Function | Calls | Time (ms) | Time % | Memory (KB) | Memory % |
+----------+-------+-----------+--------+-------------+----------+
| {main}   | 1     | 5415.654  | 83.8%  | 12770.52    | 74.1%    |
...
```

### 
 Technical Details

**Files Modified**
- `src/Command/ProfileCommand.php`
  - `runProfilingProcess()` - Fixed Xdebug configuration
  - `buildScript()` - Removed deprecated function calls  
  - `parseCachegrindFile()` - Complete rewrite for new format
  - Removed debug `var_dump()` statement

**Xdebug Version Tested**
- Xdebug 3.4.4 with PHP 8.4.10

**Filter Levels Supported**
- `user` (default) - Shows only user code
- `php` - Shows user code + PHP internal functions  
- `all` - Shows everything including framework overhead

### 
 Notes

- The fix ensures profiling works for ALL filter levels, not just `--full`
- Performance data is now accurately captured and displayed
- Memory and timing information is properly parsed from the new format
- The command maintains all existing CLI options and behaviors
