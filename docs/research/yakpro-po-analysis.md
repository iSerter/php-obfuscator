# yakpro-po Analysis

Research notes on [pmdunggh/yakpro-po](https://github.com/pmdunggh/yakpro-po) — the primary inspiration for this project.

## What It Does Well

- **AST-based approach:** Uses nikic/php-parser for reliable parsing. This is the correct architectural decision.
- **Configurable granularity:** ~50+ config options allowing fine-grained control over what gets obfuscated.
- **Ignore lists:** Both exact-name and prefix-based ignore lists per symbol category (variables, functions, classes, constants).
- **Incremental processing:** Saves timestamps to skip re-obfuscating unchanged files.
- **Multiple scramble modes:** identifier, hexa, numeric — offers variety in output appearance.

## What It Does Poorly (Our Improvement Opportunities)

### 1. Easily Reversible Obfuscation
- Multiple online deobfuscators (decoder.code.blog, deobfuscation.com) claim ~99.5% restoration rates.
- The goto-based control flow flattening is deterministic and trivially reversible by automated tools.
- String "encryption" is simple and the decryption function is inline in the output.

**Our approach:** Opaque predicates, dead code injection, per-file random keys, more complex encoding.

### 2. No PHP 8.x Support
- Built on php-parser 4.x — no support for enums, readonly, match, named arguments, union/intersection types, property hooks, pipe operator, etc.
- Cannot parse modern PHP codebases.

**Our approach:** php-parser v5.7+ with explicit handling of all PHP 8.0–8.5 features.

### 3. No Test Suite
- Zero tests. No way to verify correctness or catch regressions.
- Users discover bugs in production.

**Our approach:** Comprehensive PHPUnit tests (unit + integration + fixture-based regression). PHPStan level 8.

### 4. Procedural Architecture
- Code is organized as PHP scripts under `/include` with procedural style.
- Hard to extend, test, or maintain.

**Our approach:** Modern OOP with PSR-4 autoloading, SOLID principles, Symfony Console.

### 5. Scalability Issues
- PHP garbage collector segfaults on large codebases (~5000 files).
- Requires manual `ulimit` workaround.

**Our approach:** Monitor memory usage, process files in batches if needed.

## Obfuscation Techniques Comparison

| Technique | yakpro-po | Our Target |
|---|---|---|
| Comment stripping | Yes | Yes |
| Identifier scrambling | Yes (vars, funcs, classes, etc.) | Yes + PHP 8.x awareness |
| String encryption | Basic (easily reversed) | Per-file XOR keys, layered encoding |
| Control flow (goto) | Deterministic | + Opaque predicates, dead code |
| Statement shuffling | Yes (configurable chunks) | Yes (configurable) |
| Dead code injection | No | Yes |
| Opaque predicates | No | Yes |
| Named argument handling | No | Yes |
| Enum/match handling | No | Yes |

## Config Options Worth Adopting

From yakpro-po's config, these are worth carrying forward:
- Per-category scramble toggles (variables, functions, classes, constants, methods, properties, namespaces)
- Per-category ignore lists (exact and prefix)
- Scrambler mode selection (random, hex, numeric)
- Min scrambled name length
- Shuffle chunk mode (fixed vs ratio) and size
- User comment injection
- Strip indentation toggle
- Parser mode / target PHP version
