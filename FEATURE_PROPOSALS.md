# Proposed Features for Psysh

This document outlines potential new features and improvements for the Psysh interactive shell.

---

### 1. Simple Code Profiler Integration (Xdebug / PCOV)

*   **Description**: Introduce a simple code profiler to identify performance bottlenecks (CPU and memory) directly from the shell.
*   **Suggested Command**: `profile <...code...>`
*   **Specifications**:
    *   The command should check for the availability of a profiling extension (like Xdebug or PCOV).
    *   It would enable profiling, execute the user's code, and then stop the profiler.
    *   The output should be a simple, readable summary, such as the top 5 slowest functions, total execution time, and peak memory usage.
    *   **Bonus**: An option to export the full profiler output to a standard format file (e.g., `profile --out=profile.grind <...code...>`).
*   **Implementation Notes**:
    *   Create a new `ProfileCommand.php` in `src/Command/`.
    *   Use functions like `xdebug_start_trace()` / `xdebug_stop_trace()` or their equivalents.
    *   The command would need to parse the profiler's output file.

---

### 2. On-the-fly Code Analysis with PHPStan/Phan

*   **Description**: Allow static analysis of a code snippet or a function/class directly from within Psysh.
*   **Suggested Command**: `analyse <...code...>` or `analyse <FunctionName::class>`
*   **Specifications**:
    *   The command would take a string of code or a symbol as an argument.
    *   It would write this code to a temporary file, including the necessary context (e.g., `<?php`, `namespace`, `use` statements).
    *   It would then execute `phpstan` or `phan` on that temporary file.
    *   The analysis results would be captured and displayed in a formatted way inside the shell.
*   **Implementation Notes**:
    *   Create an `AnalyseCommand.php`.
    *   The command would need to locate the static analysis tool in `vendor/bin`.
    *   Use `Symfony\Component\Process\Process` to run the analysis tool as a subprocess.

---

### 3. Code Snippet Manager

*   **Description**: A feature to let users save, manage, and re-execute frequently used code snippets.
*   **Suggested Commands**:
    *   `snippet save <name> <...code...>`: Saves a snippet.
    *   `snippet run <name>`: Executes a saved snippet.
    *   `snippet list`: Lists all saved snippets.
    *   `snippet show <name>`: Displays a snippet's code.
    *   `snippet delete <name>`: Deletes a snippet.
*   **Specifications**:
    *   Snippets could be stored in a user-level configuration file (e.g., `~/.config/psysh/snippets.json`).
    *   `snippet run` would inject the snippet's code into the current shell scope for execution.
    *   Tab-completion should be implemented for snippet names.
*   **Implementation Notes**:
    *   This could be implemented as a main `SnippetCommand` that handles the subcommands (`save`, `run`, etc.).
    *   A `SnippetManager` class could handle the logic for storing and retrieving snippets from the JSON file.

---

### 4. Enhanced `doc` Command with Code Examples

*   **Description**: Improve the existing `doc` command to display concrete usage examples from a function's or method's docblock.
*   **Suggested Command**: An enhancement to the existing `doc` command.
*   **Specifications**:
    *   The docblock parser should be updated to find and extract content from `@example` tags.
    *   If an `@example` tag is found, its code content should be formatted with syntax highlighting and displayed as part of the `doc` command's output.
*   **Implementation Notes**:
    *   The primary changes would be in `src/Formatter/DocblockFormatter.php`.
    *   The docblock parsing logic in `src/Util/Docblock.php` would need to be extended to support the `@example` tag.
