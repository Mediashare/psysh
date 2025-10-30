# Sequence Diagrams - PsySH Architecture

## 1. Snippet Save Flow

```
User          SnippetCommand    SnippetManager    SnippetValidator    SnippetStorage    FileSystem
 │                  │                  │                  │                  │              │
 │  snippet save    │                  │                  │                  │              │
 │  mytest "code"   │                  │                  │                  │              │
 ├─────────────────>│                  │                  │                  │              │
 │                  │                  │                  │                  │              │
 │                  │ validate name    │                  │                  │              │
 │                  ├─────────────────────────────────────>│                  │              │
 │                  │                  │                  │                  │              │
 │                  │<─────────────────────────────────────┤                  │              │
 │                  │      valid=true  │                  │                  │              │
 │                  │                  │                  │                  │              │
 │                  │ save(name, code) │                  │                  │              │
 │                  ├─────────────────>│                  │                  │              │
 │                  │                  │                  │                  │              │
 │                  │                  │ create Snippet   │                  │              │
 │                  │                  ├────────┐         │                  │              │
 │                  │                  │        │         │                  │              │
 │                  │                  │<───────┘         │                  │              │
 │                  │                  │                  │                  │              │
 │                  │                  │ save(name, snippet)                 │              │
 │                  │                  ├────────────────────────────────────>│              │
 │                  │                  │                  │                  │              │
 │                  │                  │                  │                  │ write JSON   │
 │                  │                  │                  │                  ├─────────────>│
 │                  │                  │                  │                  │              │
 │                  │                  │                  │                  │<─────────────┤
 │                  │                  │                  │                  │  success     │
 │                  │                  │                  │                  │              │
 │                  │                  │<────────────────────────────────────┤              │
 │                  │                  │           true   │                  │              │
 │                  │                  │                  │                  │              │
 │                  │                  │ invalidateCache()│                  │              │
 │                  │                  ├────────┐         │                  │              │
 │                  │                  │        │         │                  │              │
 │                  │                  │<───────┘         │                  │              │
 │                  │                  │                  │                  │              │
 │                  │<─────────────────┤                  │                  │              │
 │                  │       true       │                  │                  │              │
 │                  │                  │                  │                  │              │
 │<─────────────────┤                  │                  │                  │              │
 │  "Snippet saved" │                  │                  │                  │              │
 │                  │                  │                  │                  │              │
```

## 2. Snippet Run Flow

```
User       SnippetCommand    SnippetManager    SnippetStorage    Shell    Context
 │               │                  │                  │           │         │
 │ snippet run   │                  │                  │           │         │
 │   mytest      │                  │                  │           │         │
 ├──────────────>│                  │                  │           │         │
 │               │                  │                  │           │         │
 │               │ load("mytest")   │                  │           │         │
 │               ├─────────────────>│                  │           │         │
 │               │                  │                  │           │         │
 │               │                  │ load("mytest")   │           │         │
 │               │                  ├─────────────────>│           │         │
 │               │                  │                  │           │         │
 │               │                  │                  │ read JSON │         │
 │               │                  │                  ├────┐      │         │
 │               │                  │                  │    │      │         │
 │               │                  │                  │<───┘      │         │
 │               │                  │                  │           │         │
 │               │                  │<─────────────────┤           │         │
 │               │                  │     Snippet      │           │         │
 │               │                  │                  │           │         │
 │               │<─────────────────┤                  │           │         │
 │               │     Snippet      │                  │           │         │
 │               │                  │                  │           │         │
 │               │ display code     │                  │           │         │
 │<──────────────┤                  │                  │           │         │
 │  "Code: ..."  │                  │                  │           │         │
 │               │                  │                  │           │         │
 │               │ confirm?         │                  │           │         │
 │<──────────────┤                  │                  │           │         │
 │               │                  │                  │           │         │
 │ yes           │                  │                  │           │         │
 ├──────────────>│                  │                  │           │         │
 │               │                  │                  │           │         │
 │               │ getShell()       │                  │           │         │
 │               ├─────────────────────────────────────────────────>│         │
 │               │                  │                  │           │         │
 │               │                  │                  │           │ execute │
 │               ├─────────────────────────────────────────────────>│         │
 │               │                  │                  │           │         │
 │               │                  │                  │           │ getContext()
 │               │                  │                  │           ├────────>│
 │               │                  │                  │           │         │
 │               │                  │                  │           │<────────┤
 │               │                  │                  │           │ context │
 │               │                  │                  │           │         │
 │               │                  │                  │           │ eval()  │
 │               │                  │                  │           ├────┐    │
 │               │                  │                  │           │    │    │
 │               │                  │                  │           │<───┘    │
 │               │                  │                  │           │         │
 │               │<─────────────────────────────────────────────────┤         │
 │               │                  │                  │    result │         │
 │               │                  │                  │           │         │
 │<──────────────┤                  │                  │           │         │
 │  output result                   │                  │           │         │
 │               │                  │                  │           │         │
```

## 3. Static Analysis Flow

```
User        AnalyseCommand    AnalyzerFactory    PHPStanAnalyzer    ContextBuilder    Process    FileSystem
 │                │                  │                  │                  │            │            │
 │ analyse        │                  │                  │                  │            │            │
 │ "function..."  │                  │                  │                  │            │            │
 ├───────────────>│                  │                  │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │ getShell()       │                  │                  │            │            │
 │                ├────────┐         │                  │                  │            │            │
 │                │        │         │                  │                  │            │            │
 │                │<───────┘         │                  │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │ buildContext(shell)                 │                  │            │            │
 │                ├────────────────────────────────────────────────────────>│            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │                  │ extract    │            │
 │                │                  │                  │                  │ namespace, │            │
 │                │                  │                  │                  │ uses, etc  │            │
 │                │                  │                  │                  ├────┐       │            │
 │                │                  │                  │                  │    │       │            │
 │                │                  │                  │                  │<───┘       │            │
 │                │                  │                  │                  │            │            │
 │                │<────────────────────────────────────────────────────────┤            │            │
 │                │                  │                  │         context  │            │            │
 │                │                  │                  │                  │            │            │
 │                │ create()         │                  │                  │            │            │
 │                ├─────────────────>│                  │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │ detectPHPStan()  │                  │            │            │
 │                │                  ├────────┐         │                  │            │            │
 │                │                  │        │         │                  │            │            │
 │                │                  │<───────┘         │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │ new PHPStanAnalyzer("/path/phpstan")             │            │
 │                │                  ├─────────────────>│                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │<─────────────────┤                  │                  │            │            │
 │                │    analyzer      │                  │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │ analyze(code, context)              │                  │            │            │
 │                ├────────────────────────────────────>│                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ generateTempFile()            │            │
 │                │                  │                  ├──────────────────────────────────────────>│
 │                │                  │                  │                  │            │            │
 │                │                  │                  │<──────────────────────────────────────────┤
 │                │                  │                  │                  │            │  temp path │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ buildCommand()   │            │            │
 │                │                  │                  ├────────┐         │            │            │
 │                │                  │                  │        │         │            │            │
 │                │                  │                  │<───────┘         │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ new Process(cmd) │            │            │
 │                │                  │                  ├─────────────────────────────>│            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ run()            │            │            │
 │                │                  │                  ├─────────────────────────────>│            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │                  │            │ execute    │
 │                │                  │                  │                  │            │ phpstan    │
 │                │                  │                  │                  │            ├────┐       │
 │                │                  │                  │                  │            │    │       │
 │                │                  │                  │                  │            │<───┘       │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │<─────────────────────────────┤            │
 │                │                  │                  │           output │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ parseOutput()    │            │            │
 │                │                  │                  ├────────┐         │            │            │
 │                │                  │                  │        │         │            │            │
 │                │                  │                  │<───────┘         │            │            │
 │                │                  │                  │                  │            │            │
 │                │                  │                  │ new AnalysisResult()          │            │
 │                │                  │                  ├────────┐         │            │            │
 │                │                  │                  │        │         │            │            │
 │                │                  │                  │<───────┘         │            │            │
 │                │                  │                  │                  │            │            │
 │                │<────────────────────────────────────┤                  │            │            │
 │                │                  │      result      │                  │            │            │
 │                │                  │                  │                  │            │            │
 │                │ displayResults() │                  │                  │            │            │
 │                ├────────┐         │                  │                  │            │            │
 │                │        │         │                  │                  │            │            │
 │                │<───────┘         │                  │                  │            │            │
 │                │                  │                  │                  │            │            │
 │<───────────────┤                  │                  │                  │            │            │
 │ formatted output                  │                  │                  │            │            │
 │                │                  │                  │                  │            │            │
```

## 4. Enhanced Doc Command Flow

```
User       DocCommand    Reflection    Docblock    DocblockFormatter    ExampleFormatter    Output
 │              │             │            │                │                    │             │
 │ doc          │             │            │                │                    │             │
 │ array_map    │             │            │                │                    │             │
 ├─────────────>│             │            │                │                    │             │
 │              │             │            │                │                    │             │
 │              │ getReflector("array_map")                 │                    │             │
 │              ├────────────>│            │                │                    │             │
 │              │             │            │                │                    │             │
 │              │<────────────┤            │                │                    │             │
 │              │  reflector  │            │                │                    │             │
 │              │             │            │                │                    │             │
 │              │ getDocComment()          │                │                    │             │
 │              ├────────────>│            │                │                    │             │
 │              │             │            │                │                    │             │
 │              │<────────────┤            │                │                    │             │
 │              │   docComment            │                │                    │             │
 │              │             │            │                │                    │             │
 │              │ new Docblock(docComment) │                │                    │             │
 │              ├─────────────────────────>│                │                    │             │
 │              │             │            │                │                    │             │
 │              │             │            │ parse()        │                    │             │
 │              │             │            ├────────┐       │                    │             │
 │              │             │            │        │       │                    │             │
 │              │             │            │<───────┘       │                    │             │
 │              │             │            │                │                    │             │
 │              │             │            │ parseExampleTag()                   │             │
 │              │             │            ├────────┐       │                    │             │
 │              │             │            │        │       │                    │             │
 │              │             │            │<───────┘       │                    │             │
 │              │             │            │                │                    │             │
 │              │<─────────────────────────┤                │                    │             │
 │              │             │  docblock  │                │                    │             │
 │              │             │            │                │                    │             │
 │              │ format(reflector, docblock)               │                    │             │
 │              ├──────────────────────────────────────────>│                    │             │
 │              │             │            │                │                    │             │
 │              │             │            │                │ formatDescription()│             │
 │              │             │            │                ├────────┐           │             │
 │              │             │            │                │        │           │             │
 │              │             │            │                │<───────┘           │             │
 │              │             │            │                │                    │             │
 │              │             │            │                │ formatParams()     │             │
 │              │             │            │                ├────────┐           │             │
 │              │             │            │                │        │           │             │
 │              │             │            │                │<───────┘           │             │
 │              │             │            │                │                    │             │
 │              │             │            │                │ formatReturn()     │             │
 │              │             │            │                ├────────┐           │             │
 │              │             │            │                │        │           │             │
 │              │             │            │                │<───────┘           │             │
 │              │             │            │                │                    │             │
 │              │             │            │                │ formatExamples(docblock.getExamples())
 │              │             │            │                ├───────────────────────────────>│             │
 │              │             │            │                │                    │             │
 │              │             │            │                │                    │ highlight() │
 │              │             │            │                │                    ├────┐        │
 │              │             │            │                │                    │    │        │
 │              │             │            │                │                    │<───┘        │
 │              │             │            │                │                    │             │
 │              │             │            │                │<───────────────────────────────┤             │
 │              │             │            │                │        formatted   │             │
 │              │             │            │                │                    │             │
 │              │<──────────────────────────────────────────┤                    │             │
 │              │             │            │    formatted doc                    │             │
 │              │             │            │                │                    │             │
 │              │ writeln()   │            │                │                    │             │
 │              ├────────────────────────────────────────────────────────────────────────────>│
 │              │             │            │                │                    │             │
 │<─────────────────────────────────────────────────────────────────────────────────────────┤
 │              │             │            │                │                    │   display   │
 │              │             │            │                │                    │             │
```

## 5. Error Handling Flow

```
User       SnippetCommand    SnippetManager    SnippetValidator    Output
 │              │                  │                  │              │
 │ snippet save │                  │                  │              │
 │ "invalid@name"                  │                  │              │
 ├─────────────>│                  │                  │              │
 │              │                  │                  │              │
 │              │ validate name    │                  │              │
 │              ├─────────────────────────────────────>│              │
 │              │                  │                  │              │
 │              │                  │                  │ regex check  │
 │              │                  │                  ├────┐         │
 │              │                  │                  │    │         │
 │              │                  │                  │<───┘         │
 │              │                  │                  │              │
 │              │<─────────────────────────────────────┤              │
 │              │                  │    valid=false   │              │
 │              │                  │                  │              │
 │              │ catch ValidationException            │              │
 │              ├────────┐         │                  │              │
 │              │        │         │                  │              │
 │              │<───────┘         │                  │              │
 │              │                  │                  │              │
 │              │ writeln("<error>...")               │              │
 │              ├────────────────────────────────────────────────────>│
 │              │                  │                  │              │
 │<─────────────────────────────────────────────────────────────────┤
 │  "Invalid snippet name..."      │                  │   display    │
 │              │                  │                  │              │
 │              │ return 1 (error code)               │              │
 │<─────────────┤                  │                  │              │
 │              │                  │                  │              │
```

## 6. Tab Completion Flow

```
User      Readline     CommandsMatcher    SnippetNameMatcher    SnippetManager
 │            │               │                    │                  │
 │ "snippet   │               │                    │                  │
 │  run my"   │               │                    │                  │
 │ [TAB]      │               │                    │                  │
 ├───────────>│               │                    │                  │
 │            │               │                    │                  │
 │            │ getMatchers() │                    │                  │
 │            ├──────────────>│                    │                  │
 │            │               │                    │                  │
 │            │<──────────────┤                    │                  │
 │            │   matchers[]  │                    │                  │
 │            │               │                    │                  │
 │            │ match("snippet run my")            │                  │
 │            ├───────────────────────────────────>│                  │
 │            │               │                    │                  │
 │            │               │                    │ list("my")       │
 │            │               │                    ├─────────────────>│
 │            │               │                    │                  │
 │            │               │                    │                  │ search cache
 │            │               │                    │                  ├────┐
 │            │               │                    │                  │    │
 │            │               │                    │                  │<───┘
 │            │               │                    │                  │
 │            │               │                    │<─────────────────┤
 │            │               │                    │  ["mytest",      │
 │            │               │                    │   "myother"]     │
 │            │               │                    │                  │
 │            │<───────────────────────────────────┤                  │
 │            │               │    completions     │                  │
 │            │               │                    │                  │
 │<───────────┤               │                    │                  │
 │  display   │               │                    │                  │
 │  completions               │                    │                  │
 │            │               │                    │                  │
```

## Flow Summary

### Key Interaction Patterns

1. **Layered Architecture**:
   - Command Layer → Business Logic → Infrastructure → Storage
   - Clear separation of concerns at each layer

2. **Dependency Injection**:
   - Factories create dependencies
   - Interfaces allow for testing with mocks

3. **Error Propagation**:
   - Exceptions bubble up from lower layers
   - Commands catch and format errors for users

4. **Context Awareness**:
   - All commands have access to Shell
   - Shell provides execution context
   - Context includes variables, scope, autoloader state

5. **Asynchronous Processing**:
   - Static analysis runs in separate process
   - Timeout protection for long-running operations
   - Process output captured and parsed

6. **Caching Strategy**:
   - SnippetManager caches loaded snippets
   - AnalyzerFactory caches binary paths
   - Invalidation on write operations

7. **User Interaction**:
   - Confirmation dialogs for destructive operations
   - Progress indication for long operations
   - Helpful error messages with suggestions
