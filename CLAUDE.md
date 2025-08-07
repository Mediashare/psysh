# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PsySH is a runtime developer console, interactive debugger and REPL for PHP. It's a library that provides an interactive shell for modern PHP development, allowing users to debug, explore, and experiment with PHP code in real-time.

**Key Technologies:**
- PHP 8.0+ (with 7.4 support) 
- Symfony Console for command infrastructure
- nikic/php-parser for PHP AST parsing and code transformation
- Symfony VarDumper for output formatting
- Symfony Process for isolated code execution
- opis/closure for closure serialization (profiling)
- Composer for dependency management with bamarni/composer-bin-plugin for isolated dev dependencies

## Development Commands

### Building and Testing
```bash
# Install dependencies (sets up vendor-bin structure)
composer install

# Run all tests
make test
# or alternatively
vendor/bin/phpunit

# Run individual test file
vendor/bin/phpunit test/Command/ProfileCommandTest.php

# Run specific test method
vendor/bin/phpunit --filter testSpecificMethod test/SomeTest.php

# Run static analysis (PHPStan level 1)
make phpstan
# or alternatively  
vendor/bin/phpstan --memory-limit=1G analyse

# Build PHAR executable
make build

# Clean all build artifacts and vendor-bin directories
make clean
```

### Code Quality
- PHPStan is configured at level 1 (phpstan.neon.dist) with baseline and ignore files
- Uses bamarni/composer-bin-plugin for isolated dev dependencies in vendor-bin/
- Tests are located in the `test/` directory and mirror `src/` structure
- Code follows PSR-4 autoloading with `Psy\` namespace
- PHP 7.4+ compatibility required (see composer.json constraints)
- No code style enforcement tool - follow existing patterns in codebase

## Architecture Overview

### Core Components

**Shell.php** (`src/Shell.php`):
- Main application class extending Symfony Console Application
- Manages the REPL loop, input/output, code execution, and context
- Handles command registration and execution
- Tracks executed code history via `$executedCodeHistory` for context preservation
- Provides `setCodeExecutionWrapper()` for profiling and other code execution hooks
- Central orchestrator for all shell functionality

**Command System** (`src/Command/`):
- All commands extend `Psy\Command\Command` (which extends Symfony's BaseCommand)
- Commands must be associated with a Shell instance, not generic Console Application
- Use `getShell()` instead of `getApplication()` for type safety and Shell-specific methods
- **ProfileCommand v1.2.0**: Refactored to use `Shell::execute` for maximum accuracy, supports XHProf and Xdebug with fallback mechanisms
- Key commands: ListCommand, ShowCommand, DocCommand, HelpCommand, ProfileCommand, etc.

**Code Processing Pipeline**:
- **CodeCleaner** (`src/CodeCleaner/`): AST-based code analysis and transformation with multiple passes
  - Each pass extends `CodeCleanerPass` and transforms the AST for PHP version compatibility, syntax validation, and runtime safety
  - Key passes: `NamespacePass`, `UseStatementPass`, `ImplicitReturnPass`, `LeavePsyshAlonePass`
- **ParserFactory**: Creates PHP-Parser instances for code parsing
- **Context**: Manages variable scope and execution context across REPL sessions
- **ExecutionClosure**: Handles actual code execution within the shell context

**Input/Output System**:
- **Readline** (`src/Readline/`): Multiple readline implementations (GNU, Libedit, Hoa, Userland)
  - Hoa implementation provides fallback when system readline is unavailable
- **ShellInput**: Specialized input handling with CodeArgument support for multi-line commands
- **Formatter** (`src/Formatter/`): Output formatting for code, docblocks, signatures, traces
- **TabCompletion**: Intelligent autocompletion system with context-aware matchers

### Key Patterns

**Command Development**:
```php
// All PsySH commands must extend Psy\Command\Command
class MyCommand extends Command
{
    // Use getShell() instead of getApplication() for type safety
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $shell = $this->getShell(); // Returns Shell instance, not generic Application
        // Access shell-specific functionality like context, scope variables, etc.
        $context = $shell->getScopeVariables();
        return 0; // Always return exit code
    }
}
```

**Code Cleaning/Processing**:
- Code goes through multiple "passes" in CodeCleaner before execution
- Each pass extends `CodeCleanerPass` and transforms the AST
- Passes handle PHP version compatibility, syntax validation, and runtime safety
- Critical passes like `LeavePsyshAlonePass` prevent modification of PsySH internals

**Context Management & Execution**:
- Variables are managed in the `Context` class with `getScopeVariables()` and `setScopeVariables()`
- Shell maintains execution context across REPL sessions
- Code execution happens via `ExecutionClosure` within the shell's context
- **ProfileCommand Execution**: Uses `Shell::execute` for native context preservation (v1.2.0)
  - Same execution context as normal PsySH operations
  - Automatic fallback to `eval()` with scope restoration if needed
  - No manual serialization required - maximum robustness
  - Handles complex closures and objects seamlessly
- Includes/requires are tracked separately from variable context

### Testing Structure

- Tests mirror the `src/` structure in `test/`
- `CodeCleanerTestCase` base class for testing code cleaner passes
- `TestCase` and `ParserTestCase` for general testing utilities  
- Command tests should extend appropriate base classes and test command behavior
- Use `CommandTester` from Symfony Console for testing commands programmatically
- **ProfileCommand Testing**: Updated tests support new adaptive formatting, fallback mechanisms, and context preservation features
- **Debug Testing**: Tests include scenarios for fallback execution and error handling
- Test fixtures in `test/fixtures/` provide sample configurations and test data

### ProfileCommand Development Notes (v1.2.0)

**Execution Architecture**:
- **Primary**: `Shell::execute` - Same context as normal PsySH operations
- **Fallback**: `eval()` with scope restoration - For edge cases or test environments  
- **Debug Mode**: `--debug` flag provides verbose output for troubleshooting
- **Robustness**: Handles complex closures, objects, and edge cases gracefully

**Key Implementation Details**:
- Removed 100+ lines of complex temporary file and serialization logic
- No manual context reconstruction needed
- Better error handling and recovery mechanisms
- Maintains backward compatibility with existing profiling workflows

**Known Limitations**:
- Interactive closures (defined in REPL) cannot be serialized - define in files for profiling
- Xdebug 3.x compatibility fully supported, older versions may have limited features
- Complex object graphs may require fallback execution method

### Development Workflow Notes

**Branch Management:**
- Main development branch: `main` 
- **Recent focus**: ProfileCommand v1.2.2 - addressing option parsing limitations in interactive mode
- Modified files: `src/Command/ProfileCommand.php`, `src/Shell.php`, test files

**Dependencies and Build Tools:**
- Uses `composer-bin-plugin` for isolated dev dependencies in `vendor-bin/`
- **Optional dependency**: `opis/closure` for advanced closure handling in ProfileCommand (fallback scenarios)
- Box PHAR compiler for building distributable executables
- PHPStan baseline and ignore files located in `vendor-bin/phpstan/`

**Profiling Execution Pattern (v1.2.2):**
- **Primary method**: Uses `Shell::execute` for native context execution
- **Fallback method**: `eval()` with scope variable restoration for edge cases
- **Legacy support**: `Symfony\Component\Process\Process` with temporary files (deprecated)
- **Debug capability**: `--debug` flag provides verbose troubleshooting output
- Supports both XHProf and Xdebug profiling backends
- **Known Issue**: Interactive shell option parsing limitation affects ProfileCommand options
- **Documentation**: See PROFILING.md for usage, PATCHNOTES.md for version history, GEMINI.md for implementation rules

**Current Known Issues (v0.12.15):**
- ProfileCommand works correctly without options, but option flags are not properly detected in interactive PsySH mode
- All options work correctly in programmatic tests using `CommandTester`
- Interactive usage like `> profile --full sleep(1)` may not parse options correctly
- Workaround: Use `CommandTester` for testing with options

## Recent Updates

- Files to track: 
  - `@PATCHNOTES.md`
  - `@src/Command/ProfileCommand.php`
  - `@src/Shell.php`
  - `@test/Command/ProfileCommandTest.php`

## Test Writing and Code Modification Guidelines

- Règles de développement : 
  * À chaque modification du code, il doit y avoir création et/ou édition d'un test unitaire afin de vérifier le bon fonctionnement de la modification
  * Écrire des tests robustes, utiliser l'agent phpunit-test-generator si nécessaire