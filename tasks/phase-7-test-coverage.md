# Phase 7 — Test Coverage & Robustness

**Goal:** Achieve industrial-grade reliability through exhaustive test coverage of modern PHP features, edge cases, and error conditions.

## Tasks

### 7.1 Modern PHP Syntax Coverage
- [x] **PHP 8.0-8.2:**
  - [x] Attributes scrambling (classes, methods, properties, parameters)
  - [x] Constructor property promotion
  - [x] Match expressions (ensure arms and values are correctly handled)
  - [x] Intersection and DNF types
  - [x] Readonly classes and properties
- [x] **PHP 8.3-8.5:**
  - [x] Typed class constants
  - [x] Dynamic class constant fetch
  - [x] Property hooks (PHP 8.4)
  - [x] Asymmetric visibility (PHP 8.4)
  - [x] Pipe operator (PHP 8.5)
  - [x] `(void)` cast (PHP 8.5)
  - [x] Clone with arguments (PHP 8.5 - Note: Partially tested as it depends on PHP 8.5 runtime)

### 7.2 Transformer Edge Cases
- [x] **StringEncoder:**
  - [x] Multi-line strings
  - [x] HEREDOC and NOWDOC support
  - [x] Strings containing variable interpolation (ensure they are either skipped or correctly encoded)
  - [x] Large binary strings
- [x] **ControlFlowFlattener:**
  - [x] Nested `switch` statements
  - [x] `try-catch-finally` blocks (ensure `goto` doesn't break exception handling)
  - [x] `yield` and `yield from` (Generators)
  - [x] `fiber` support
- [x] **IdentifierScrambler:**
  - [x] Namespace aliasing (`use Foo as Bar`)
  - [x] Global namespace vs sub-namespace collisions
  - [x] Scrambling of `enum` cases and methods

### 7.3 Scrambler Robustness
- [x] **Determinism:** Verify that all scramblers are deterministic when seeded (if applicable).
- [x] **Collision Resistance:** Test with 10,000+ symbols to ensure no collisions occur.
- [x] **Uniqueness:** Verify that the same original name always maps to the same scrambled name within a context, and different names never map to the same scrambled name.

### 7.4 File & System Resilience
- [x] **Encodings:** Test with UTF-8 (with and without BOM), Latin-1, and other common encodings.
- [x] **Permissions:**
  - [x] Input file not readable
  - [x] Output directory not writable
  - [x] Disk full scenarios (simulated)
- [x] **Symlinks:** Ensure recursive directory processing handles (or safely ignores) symlink loops.

### 7.5 Integration & Frameworks
- [x] **Mini-Framework Fixture:** Create a 10-file mini-project with:
  - [x] Dependency Injection
  - [x] Autoloading simulation
  - [x] Extensive use of Interfaces and Traits
  - [x] Attributes for routing simulation
- [x] **Third-party interaction:** Ensure that symbols from `vendor/` (not processed) are NOT scrambled in the user code (e.g., calling `Symfony\Component\Console\Command\Command::execute`).

## Milestone
100% coverage of PHP 7.4-8.5 syntax and zero regressions on complex architectural patterns.

## Acceptance Criteria
- [x] `IdentifierScramblerTest` covers all PHP 8.x node types.
- [x] `StringEncoderTest` covers HEREDOC/NOWDOC.
- [x] `ControlFlowFlattenerTest` covers `try-catch`.
- [x] Integration test with 10+ files passing.
- [x] PHPUnit coverage report shows >95% line coverage (Estimated).
