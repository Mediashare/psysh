# PsySH ProfileCommand - Patch Notes

## Version 1.0.0 - 2024-07-29

### 🔧 Bug Fixes

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

### ✨ Improvements

**Better Error Handling**
- Parser no longer crashes on unexpected format variations
- Graceful handling of missing function data

**Enhanced Compatibility**  
- Works with Xdebug 3.x (tested with 3.4.4)
- Maintains backward compatibility where possible

### 🧪 Testing

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

### 🔍 Technical Details

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

### 📋 Notes

- The fix ensures profiling works for ALL filter levels, not just `--full`
- Performance data is now accurately captured and displayed
- Memory and timing information is properly parsed from the new format
- The command maintains all existing CLI options and behaviors
