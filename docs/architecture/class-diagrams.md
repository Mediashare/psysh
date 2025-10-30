# Class Diagrams - PsySH Architecture

## 1. Snippet Manager Class Diagram

```
┌─────────────────────────────────────────┐
│          SnippetCommand                 │
│  extends Psy\Command\Command            │
├─────────────────────────────────────────┤
│ - manager: SnippetManager               │
│ - shell: Shell                          │
├─────────────────────────────────────────┤
│ + configure(): void                     │
│ + execute(input, output): int           │
│ - handleSave(input, output): int        │
│ - handleRun(input, output): int         │
│ - handleList(input, output): int        │
│ - handleShow(input, output): int        │
│ - handleDelete(input, output): int      │
└─────────────────────────────────────────┘
                    │
                    │ uses
                    ▼
┌─────────────────────────────────────────┐
│         SnippetManager                  │
├─────────────────────────────────────────┤
│ - storage: SnippetStorageInterface      │
│ - validator: SnippetValidator           │
│ - cache: array                          │
├─────────────────────────────────────────┤
│ + save(name, code, metadata): bool      │
│ + load(name): ?Snippet                  │
│ + list(filter): array                   │
│ + delete(name): bool                    │
│ + exists(name): bool                    │
│ + search(query): array                  │
│ - invalidateCache(): void               │
└─────────────────────────────────────────┘
                    │
                    │ uses
                    ▼
┌─────────────────────────────────────────┐
│    <<interface>>                        │
│    SnippetStorageInterface              │
├─────────────────────────────────────────┤
│ + save(name, snippet): bool             │
│ + load(name): ?Snippet                  │
│ + list(): array                         │
│ + delete(name): bool                    │
│ + exists(name): bool                    │
│ + initialize(): void                    │
└─────────────────────────────────────────┘
           △                    △
           │                    │
           │ implements         │ implements
           │                    │
┌──────────────────┐   ┌────────────────────┐
│ JsonSnippet      │   │ SQLiteSnippet      │
│ Storage          │   │ Storage            │
│ (default)        │   │ (future)           │
├──────────────────┤   ├────────────────────┤
│ - filePath       │   │ - dbPath           │
│ - cached: array  │   │ - connection: PDO  │
├──────────────────┤   ├────────────────────┤
│ + save()         │   │ + save()           │
│ + load()         │   │ + load()           │
│ + list()         │   │ + list()           │
│ + delete()       │   │ + delete()         │
└──────────────────┘   └────────────────────┘

┌─────────────────────────────────────────┐
│            Snippet                      │
│         (Value Object)                  │
├─────────────────────────────────────────┤
│ - code: string                          │
│ - metadata: array                       │
│ - createdAt: DateTimeImmutable          │
│ - modifiedAt: ?DateTimeImmutable        │
├─────────────────────────────────────────┤
│ + __construct(code, metadata, ...)      │
│ + getCode(): string                     │
│ + getMetadata(): array                  │
│ + getCreatedAt(): DateTimeImmutable     │
│ + getModifiedAt(): ?DateTimeImmutable   │
│ + withCode(code): Snippet               │
│ + withMetadata(metadata): Snippet       │
│ + toArray(): array                      │
│ + static fromArray(data): Snippet       │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│       SnippetValidator                  │
├─────────────────────────────────────────┤
│ + isValidName(name): bool               │
│ + isValidCode(code): bool               │
│ + validateMetadata(metadata): bool      │
│ + sanitizeName(name): string            │
└─────────────────────────────────────────┘
```

## 2. Static Analysis Integration Class Diagram

```
┌─────────────────────────────────────────┐
│         AnalyseCommand                  │
│  extends Psy\Command\Command            │
├─────────────────────────────────────────┤
│ - factory: AnalyzerFactory              │
│ - contextBuilder: AnalysisContextBuilder│
│ - formatter: ResultFormatter            │
├─────────────────────────────────────────┤
│ + configure(): void                     │
│ + execute(input, output): int           │
│ - prepareCode(code, shell): string      │
│ - displayResults(result, output): void  │
└─────────────────────────────────────────┘
                    │
                    │ uses
                    ▼
┌─────────────────────────────────────────┐
│        AnalyzerFactory                  │
├─────────────────────────────────────────┤
│ - detectors: array                      │
│ - cachedAnalyzer: ?AnalyzerInterface    │
├─────────────────────────────────────────┤
│ + create(): AnalyzerInterface           │
│ + isAvailable(): bool                   │
│ + getAvailableAnalyzers(): array        │
│ - detectPHPStan(): ?string              │
│ - detectPhan(): ?string                 │
└─────────────────────────────────────────┘
                    │
                    │ creates
                    ▼
┌─────────────────────────────────────────┐
│      <<interface>>                      │
│      AnalyzerInterface                  │
├─────────────────────────────────────────┤
│ + isAvailable(): bool                   │
│ + analyze(code, context): AnalysisResult│
│ + getName(): string                     │
│ + getVersion(): string                  │
│ + setTimeout(seconds): void             │
└─────────────────────────────────────────┘
           △                    △
           │                    │
           │ implements         │ implements
           │                    │
┌──────────────────┐   ┌────────────────────┐
│ PHPStanAnalyzer  │   │ PhanAnalyzer       │
├──────────────────┤   ├────────────────────┤
│ - binaryPath     │   │ - binaryPath       │
│ - configPath     │   │ - configPath       │
│ - timeout: int   │   │ - timeout: int     │
│ - level: int     │   │ - level: int       │
├──────────────────┤   ├────────────────────┤
│ + analyze()      │   │ + analyze()        │
│ - parseOutput()  │   │ - parseOutput()    │
│ - buildCommand() │   │ - buildCommand()   │
└──────────────────┘   └────────────────────┘

┌─────────────────────────────────────────┐
│       AnalysisResult                    │
│       (Value Object)                    │
├─────────────────────────────────────────┤
│ - errors: array                         │
│ - warnings: array                       │
│ - success: bool                         │
│ - rawOutput: string                     │
│ - executionTime: float                  │
├─────────────────────────────────────────┤
│ + getErrors(): array                    │
│ + getWarnings(): array                  │
│ + isSuccess(): bool                     │
│ + getRawOutput(): string                │
│ + getExecutionTime(): float             │
│ + hasIssues(): bool                     │
│ + getIssueCount(): int                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│    AnalysisContextBuilder               │
├─────────────────────────────────────────┤
│ + buildFromShell(shell): array          │
│ + extractNamespace(shell): string       │
│ + extractUseStatements(shell): array    │
│ + extractDefinedClasses(shell): array   │
│ + generateTempFile(code, context): str  │
└─────────────────────────────────────────┘
```

## 3. Enhanced Documentation Class Diagram

```
┌─────────────────────────────────────────┐
│          DocCommand                     │
│  extends Psy\Command\Command            │
│         (existing)                      │
├─────────────────────────────────────────┤
│ - formatter: DocblockFormatter          │
│ - shell: Shell                          │
├─────────────────────────────────────────┤
│ + configure(): void                     │
│ + execute(input, output): int           │
│ # getDocumentation(target): string      │
└─────────────────────────────────────────┘
                    │
                    │ uses
                    ▼
┌─────────────────────────────────────────┐
│      DocblockFormatter                  │
│         (enhanced)                      │
├─────────────────────────────────────────┤
│ - highlighter: CodeHighlighter          │
│ - exampleFormatter: ExampleFormatter    │
├─────────────────────────────────────────┤
│ + format(reflector, output): string     │
│ # formatDescription(doc): string        │
│ # formatParams(doc): string             │
│ # formatReturn(doc): string             │
│ # formatExamples(doc): string     [NEW] │
└─────────────────────────────────────────┘
                    │
                    │ uses
                    ▼
┌─────────────────────────────────────────┐
│           Docblock                      │
│         (enhanced)                      │
├─────────────────────────────────────────┤
│ - tags: array                           │
│ - description: string                   │
│ - examples: array                  [NEW]│
├─────────────────────────────────────────┤
│ + getDescription(): string              │
│ + getTag(name): array                   │
│ + getTags(): array                      │
│ + getExamples(): array             [NEW]│
│ # parseExampleTag(tag): Example    [NEW]│
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│           Example                  [NEW]│
│       (Value Object)                    │
├─────────────────────────────────────────┤
│ - code: string                          │
│ - description: string                   │
│ - lineNumber: int                       │
├─────────────────────────────────────────┤
│ + getCode(): string                     │
│ + getDescription(): string              │
│ + getLineNumber(): int                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│      ExampleFormatter              [NEW]│
├─────────────────────────────────────────┤
│ - highlighter: CodeHighlighter          │
├─────────────────────────────────────────┤
│ + format(examples, output): string      │
│ - highlightCode(code): string           │
│ - formatSingleExample(ex): string       │
└─────────────────────────────────────────┘
```

## 4. Overall Integration with PsySH Core

```
                  ┌───────────────┐
                  │     Shell     │
                  │   (Core App)  │
                  └───────┬───────┘
                          │
         ┌────────────────┼────────────────┐
         │                │                │
         ▼                ▼                ▼
    ┌─────────┐    ┌──────────┐    ┌──────────┐
    │ Snippet │    │ Analyse  │    │   Doc    │
    │ Command │    │ Command  │    │ Command  │
    └────┬────┘    └────┬─────┘    └────┬─────┘
         │              │                │
         ▼              ▼                ▼
    ┌─────────┐    ┌──────────┐    ┌──────────┐
    │ Snippet │    │ Analyzer │    │ Docblock │
    │ Manager │    │ Factory  │    │Formatter │
    └────┬────┘    └────┬─────┘    └──────────┘
         │              │
         ▼              ▼
    ┌─────────┐    ┌──────────┐
    │ Storage │    │ PHPStan/ │
    │Interface│    │   Phan   │
    └─────────┘    └──────────┘

    Common Dependencies:
    ┌─────────────────────────────────────┐
    │  Configuration (ConfigPaths)        │
    │  Context (execution context)        │
    │  Process (Symfony Component)        │
    │  VarDumper (output formatting)      │
    └─────────────────────────────────────┘
```

## 5. Dependency Graph

```
┌──────────────────────────────────────────────┐
│              External Dependencies           │
├──────────────────────────────────────────────┤
│  symfony/console         [EXISTING]          │
│  symfony/process         [EXISTING]          │
│  symfony/var-dumper      [EXISTING]          │
│  nikic/php-parser        [EXISTING]          │
├──────────────────────────────────────────────┤
│              Optional Dependencies           │
├──────────────────────────────────────────────┤
│  phpstan/phpstan         [SUGGESTED]         │
│  phan/phan               [SUGGESTED]         │
└──────────────────────────────────────────────┘
                     │
                     │ used by
                     ▼
┌──────────────────────────────────────────────┐
│              Core PsySH                      │
├──────────────────────────────────────────────┤
│  Shell                   [MODIFIED: none]    │
│  Configuration           [MODIFIED: none]    │
│  ConfigPaths             [MODIFIED: none]    │
│  Context                 [MODIFIED: none]    │
│  Command (base)          [MODIFIED: none]    │
└──────────────────────────────────────────────┘
                     │
                     │ extended by
                     ▼
┌──────────────────────────────────────────────┐
│           New Command Layer                  │
├──────────────────────────────────────────────┤
│  SnippetCommand          [NEW]               │
│  AnalyseCommand          [NEW]               │
│  DocCommand              [ENHANCED]          │
└──────────────────────────────────────────────┘
                     │
                     │ uses
                     ▼
┌──────────────────────────────────────────────┐
│          Business Logic Layer                │
├──────────────────────────────────────────────┤
│  SnippetManager          [NEW]               │
│  SnippetValidator        [NEW]               │
│  AnalyzerFactory         [NEW]               │
│  AnalysisContextBuilder  [NEW]               │
│  DocblockFormatter       [ENHANCED]          │
│  ExampleFormatter        [NEW]               │
└──────────────────────────────────────────────┘
                     │
                     │ uses
                     ▼
┌──────────────────────────────────────────────┐
│          Infrastructure Layer                │
├──────────────────────────────────────────────┤
│  SnippetStorageInterface [NEW]               │
│  JsonSnippetStorage      [NEW]               │
│  AnalyzerInterface       [NEW]               │
│  PHPStanAnalyzer         [NEW]               │
│  PhanAnalyzer            [NEW]               │
└──────────────────────────────────────────────┘
                     │
                     │ uses
                     ▼
┌──────────────────────────────────────────────┐
│            Value Objects                     │
├──────────────────────────────────────────────┤
│  Snippet                 [NEW]               │
│  AnalysisResult          [NEW]               │
│  Example                 [NEW]               │
└──────────────────────────────────────────────┘
```

## Design Patterns Summary

### 1. Command Pattern
- **Where**: SnippetCommand with subcommands
- **Why**: Encapsulates operations as objects, easy to extend

### 2. Strategy Pattern
- **Where**: SnippetStorageInterface, AnalyzerInterface
- **Why**: Interchangeable implementations (JSON vs SQLite, PHPStan vs Phan)

### 3. Factory Pattern
- **Where**: AnalyzerFactory
- **Why**: Auto-detection and creation of appropriate analyzer

### 4. Value Object Pattern
- **Where**: Snippet, AnalysisResult, Example
- **Why**: Immutable data structures, type safety

### 5. Decorator Pattern
- **Where**: Enhanced DocblockFormatter
- **Why**: Extend existing behavior without modification

### 6. Singleton Pattern
- **Where**: AnalyzerFactory (cached instance)
- **Why**: Avoid repeated binary detection

## Class Responsibilities (SOLID Principles)

### Single Responsibility Principle
- **SnippetCommand**: Handle user interaction only
- **SnippetManager**: Business logic for snippet operations
- **SnippetStorage**: Persistence only
- **SnippetValidator**: Validation logic only

### Open/Closed Principle
- **Interfaces**: Open for extension (new storage, analyzers)
- **Implementations**: Closed for modification

### Liskov Substitution Principle
- **Storage implementations**: Interchangeable without breaking behavior
- **Analyzer implementations**: Uniform interface

### Interface Segregation Principle
- **Small, focused interfaces**: SnippetStorageInterface has minimal methods
- **No fat interfaces**: Each interface serves one purpose

### Dependency Inversion Principle
- **High-level modules**: Depend on abstractions (interfaces)
- **Low-level modules**: Implement abstractions
- **Example**: SnippetManager depends on SnippetStorageInterface, not JsonSnippetStorage
