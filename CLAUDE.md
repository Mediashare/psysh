# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PsySH is a runtime developer console, interactive debugger and REPL for PHP. It's a library that provides an interactive shell for modern PHP development, allowing users to debug, explore, and experiment with PHP code in real-time.

**Key Technologies:**
- PHP 8.0+ (with 7.4 support)
- Symfony Console for command infrastructure
- nikic/php-parser for PHP AST parsing
- Symfony VarDumper for output formatting
- Composer for dependency management

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

## Architecture Overview

### Core Components

**Shell.php** (`src/Shell.php`):
- Main application class extending Symfony Console Application
- Manages the REPL loop, input/output, code execution, and context
- Handles command registration and execution
- Central orchestrator for all shell functionality

**Command System** (`src/Command/`):
- All commands extend `Psy\Command\Command` (which extends Symfony's BaseCommand)
- Commands must be associated with a Shell instance, not generic Console Application
- Key commands: ListCommand, ShowCommand, DocCommand, HelpCommand, ProfileCommand, etc.

**Code Processing Pipeline**:
- **CodeCleaner** (`src/CodeCleaner/`): AST-based code analysis and transformation
- **ParserFactory**: Creates PHP-Parser instances for code parsing
- **Context**: Manages variable scope and execution context
- **Reflection**: Enhanced reflection capabilities for runtime inspection

**Input/Output System**:
- **Readline** (`src/Readline/`): Multiple readline implementations (GNU, Libedit, Hoa, Userland)
- **Formatter** (`src/Formatter/`): Output formatting for code, docblocks, signatures, traces
- **TabCompletion**: Intelligent autocompletion system with various matchers

### Key Patterns

**Command Development**:
```php
// All PsySH commands must extend Psy\Command\Command
class MyCommand extends Command
{
    // Use getShell() instead of getApplication() for type safety
    protected function execute($input, $output) {
        $shell = $this->getShell(); // Returns Shell instance
        // Implementation
    }
}
```

**Code Cleaning/Processing**:
- Code goes through multiple "passes" in CodeCleaner before execution
- Each pass extends `CodeCleanerPass` and transforms the AST
- Passes handle PHP version compatibility, syntax validation, and runtime safety

**Context Management**:
- Variables are managed in the `Context` class
- Shell maintains execution context across REPL sessions
- Includes/requires are tracked separately from variable context

### Testing Structure

- Tests mirror the `src/` structure in `test/`
- `CodeCleanerTestCase` base class for testing code cleaner passes
- `TestCase` and `ParserTestCase` for general testing utilities
- Command tests should extend appropriate base classes and test command behavior

### Development Workflow Notes

**Branch Management:**
- Main development branch: `main` 
- Feature branch for Xdebug integration: `xdebug`
- Current modified files: `src/Command/ProfileCommand.php`, `src/Shell.php`

**Dependencies and Build Tools:**
- Uses `composer-bin-plugin` for isolated dev dependencies in `vendor-bin/`
- Box PHAR compiler for building distributable executables
- PHPStan baseline and ignore files located in `vendor-bin/phpstan/`

**Process Isolation Pattern:**
- Code execution uses `Symfony\Component\Process\Process` for isolation
- Critical for new features like Xdebug profiling to avoid affecting main shell
- See XDEBUG.md for detailed specs on isolated process profiling implementation