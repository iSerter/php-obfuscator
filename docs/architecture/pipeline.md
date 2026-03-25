# Obfuscation Pipeline Architecture

## Overview

The obfuscator uses an AST-based pipeline: **Parse → Transform → Print**. This is the standard approach for source-to-source code transformation and is how yakpro-po works as well.

```
Source PHP File(s)
       │
       ▼
┌──────────────┐
│  PHP Parser  │  nikic/php-parser v5.7+
│  (Lexer+AST) │  Produces a full Abstract Syntax Tree
└──────┬───────┘
       │
       ▼
┌──────────────────────────┐
│  Transformer Pipeline    │  Applies passes in sequence
│                          │
│  1. CommentStripper      │  Remove comments/whitespace
│  2. IdentifierScrambler  │  Rename all user symbols
│  3. StringEncoder        │  Encrypt string literals
│  4. ControlFlowFlattener │  Flatten control structures
│  5. StatementShuffler    │  Reorder + goto
└──────┬───────────────────┘
       │
       ▼
┌──────────────────┐
│  Pretty Printer  │  Emit valid PHP from transformed AST
└──────┬───────────┘
       │
       ▼
Obfuscated PHP File(s)
```

## Key Design Decisions

### 1. AST-Based (Not Regex/Token-Based)

Working on the AST guarantees structural correctness. Regex-based approaches break on edge cases (strings containing code-like patterns, heredocs, etc.). Token-based approaches lack semantic understanding (can't distinguish a local variable from a parameter with the same name).

### 2. Independent Transformer Passes

Each transformer implements `TransformerInterface` and can be:
- Enabled/disabled independently via config
- Tested in isolation
- Applied in any order (though the default order is optimized)

This follows the **Visitor Pattern** — each transformer is a `NodeVisitor` that walks the AST.

### 3. Shared ObfuscationContext

The `ObfuscationContext` holds:
- **Symbol tables:** mapping original names → scrambled names
- **Ignore lists:** names and prefixes to preserve
- **File metadata:** current file path, per-file random seeds
- **Cross-file state:** when processing directories, the symbol map is shared so that `class Foo` in file A and `new Foo` in file B both resolve to the same scrambled name

### 4. Two Processing Modes

- **Single file:** `FileProcessor` — parse, transform, print one file
- **Directory:** `DirectoryProcessor` — recursively discover PHP files, process all with a shared context, write to output directory. Supports incremental mode (skip unchanged files).

## Transformer Ordering Rationale

1. **CommentStripper first** — removes noise before other passes operate
2. **IdentifierScrambler second** — renames symbols while the AST is still "clean" (no injected goto/labels)
3. **StringEncoder third** — operates on string literal nodes; must run after identifier scrambling to avoid encoding already-scrambled names used as strings
4. **ControlFlowFlattener fourth** — restructures control flow; easier to work with before statement shuffling adds more goto jumps
5. **StatementShuffler last** — final pass that reorders everything; must be last because it changes statement positions

## Extension Points

Adding a new transformer:
1. Create a class implementing `TransformerInterface`
2. Register it in `TransformerPipeline`
3. Add a config toggle
4. Write unit tests with fixture PHP snippets
