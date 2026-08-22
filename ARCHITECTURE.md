# OneWeb Engine Architecture & Runtime Lifecycle

## 1. High-Level Architecture

```text
                               OneWeb Application
                                       │
                                       ▼
                             ┌──────────────────┐
                             │  HTTP Request    │
                             └──────────────────┘
                                       │
                                       ▼
                             ┌──────────────────┐
                             │    Bootstrap     │
                             └──────────────────┘
                                       │
                                       ▼
                             ┌──────────────────┐
                             │ Router / Front   │
                             │   Controller     │
                             └──────────────────┘
                                       │
                        ┌──────────────┴──────────────┐
                        ▼                             ▼
             ┌─────────────────────┐       ┌────────────────────┐
             │ Form Action Engine  │       │ Template Pipeline  │
             │ (CSRF, Validation,  │       │ (Lexer, Parser,    │
             │  Insert/Update/Del) │       │  AST & Compiler)   │
             └─────────────────────┘       └────────────────────┘
                        │                             │
                        └──────────────┬──────────────┘
                                       ▼
                             ┌──────────────────┐
                             │ Database Layer   │ ◄─── PDO / MySQL / SQLite
                             └──────────────────┘
                                       │
                                       ▼
                             ┌──────────────────┐
                             │ Template Render  │
                             └──────────────────┘
                                       │
                                       ▼
                             ┌──────────────────┐
                             │  HTML Response   │
                             └──────────────────┘
```

---

## 2. Processing Pipeline

1. **Bootstrap & Environment**: Loads `config.one` and initializes PDO Database connections.
2. **Form Action Interception**: Inspects `POST` payloads for `@insert`, `@update`, or `@delete` declarations. Executes validation, CSRF verification, mass-assignment filtering, and database operations before rendering or redirecting.
3. **Compilation Pipeline**:
   - `.one` source file is tokenized by the **Lexer**.
   - Tokens are parsed into an Abstract Syntax Tree (**AST**) by the **Parser**.
   - AST nodes are compiled/cached and evaluated by the **Renderer**.
4. **Rendering Output**: Variables are automatically XSS-escaped (`{{ var }}`). Conditional branches (`@if`), iterations (`@foreach`), and includes (`@include`) are processed, generating the final pure HTML string.
