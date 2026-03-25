# Phase 2 — Core Transformers

**Goal:** Implement comment stripping, identifier scrambling, and string encoding — the three most impactful obfuscation passes.

**Depends on:** Phase 1 (pipeline infrastructure)

## Tasks

### 2.1 Comment Stripper [DONE]
- [x] `src/Transformer/CommentStripper.php` — Remove all comments from AST nodes, optionally inject a header comment
- [x] **Tests:** `tests/Unit/Transformer/CommentStripperTest.php`
  - Doc blocks, inline comments, block comments all removed
  - Header comment injection works
  - Code behavior unchanged after stripping

### 2.2 Scrambler Classes [DONE]
- [x] `src/Scrambler/ScramblerInterface.php` — Contract: `scramble(string $original): string`
- [x] `src/Scrambler/RandomScrambler.php` — Random identifier-style names
- [x] `src/Scrambler/HexScrambler.php` — Hex-prefixed names (`_0x...`)
- [x] `src/Scrambler/NumericScrambler.php` — Numeric-prefixed names (`_123...`)
- [x] All accept a seed for determinism and a min-length parameter
- [x] **Tests:** `tests/Unit/Scrambler/RandomScramblerTest.php` (+ Hex, Numeric)
  - Seeded output is deterministic
  - Output is a valid PHP identifier
  - No collisions in 10,000 generated names
  - Min length respected

### 2.3 Identifier Scrambler [DONE]
- [x] `src/Transformer/IdentifierScrambler.php` — NodeVisitor that renames user-defined symbols
- [x] **Scope:** variables, functions, classes, interfaces, traits, enums, constants, methods, properties
- [x] **Must preserve:** `$this`, superglobals, magic methods, magic constants, built-in PHP symbols
- [x] **Must handle:** named arguments (PHP 8.0), enum cases (8.1), match arms (8.0), readonly (8.1/8.2), property hooks (8.4), pipe operator callables (8.5), clone-with (8.5), final promoted props (8.5)
- [x] Uses `ObfuscationContext` for consistent cross-reference mapping
- [x] Configurable ignore lists (exact names + prefixes) per symbol category
- [x] **Tests:** `tests/Unit/Transformer/IdentifierScramblerTest.php`
  - Each symbol category scrambled correctly
  - Ignore lists respected
  - Named arguments updated to match scrambled parameters
  - Cross-references consistent (function declaration + call site use same scrambled name)
  - PHP 8.x features handled (one test per feature)
- See `docs/architecture/ast-node-handling.md` for full node mapping

### 2.4 String Encoder [DONE]
- [x] `src/Transformer/StringEncoder.php` — Replace string literals with runtime decode expressions
- [x] Encoding: base64 + XOR with per-file random key
- [x] Decode function injected at top of file (itself obfuscated)
- [x] Configurable skip list for strings that shouldn't be encoded
- [x] **Tests:** `tests/Unit/Transformer/StringEncoderTest.php`
  - Original string not present in output
  - Executing output produces same string
  - Empty strings handled
  - Binary-safe strings handled
  - Skip list respected

### 2.5 Fixture Integration Tests [DONE]
- [x] Create fixture files in `tests/Fixtures/` (see `docs/testing/fixtures-guide.md`)
- [x] `tests/Integration/ObfuscationPipelineTest.php` — for each fixture:
  - Obfuscate with comment stripping + identifier scrambling + string encoding
  - Execute original and obfuscated → assert identical output
  - Assert original identifiers absent from obfuscated output

## Milestone
Can obfuscate a single PHP file with comment stripping, identifier scrambling, and string encoding. Obfuscated code runs identically to the original.

## Acceptance Criteria
- All unit tests pass for each component
- Integration tests pass: obfuscated fixtures produce identical output
- No original user identifiers or string literals visible in obfuscated output
- PHPStan level 8 clean
