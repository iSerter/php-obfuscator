# iserter/php-obfuscator

PHP obfuscator for copyright reasons. 

Be inspired by https://github.com/pmdunggh/yakpro-po and develop an excellent and reliable PHP obfuscator.

---

## Implementation Plan

### 1. Goals & Non-Goals

**Goals:**
- Build a CLI-based PHP obfuscator that makes source code unreadable to protect intellectual property
- Support modern PHP (8.1, 8.2, 8.3, 8.4, 8.5) while maintaining backward compatibility with PHP 7.4+
- Improve upon yakpro-po's known weaknesses (easily reversible obfuscation, no modern PHP support, lack of tests)
- Provide a configurable, extensible architecture with comprehensive unit tests
- Produce obfuscated code that is functionally identical to the original

**Non-Goals:**
- Code encryption or licensing/DRM enforcement
- Obfuscation of non-PHP assets (JS, CSS, templates)
- Runtime protection or anti-debugging measures (future consideration)

---

### 2. Architecture Overview

```
┌─────────────┐     ┌────────────┐     ┌──────────────────┐     ┌───────────────┐
│  CLI Entry   │────▶│   Config   │────▶│   Obfuscation    │────▶│  File Writer  │
│  (command)   │     │   Loader   │     │    Pipeline       │     │   (output)    │
└─────────────┘     └────────────┘     └──────────────────┘     └───────────────┘
                                              │
                                    ┌─────────┼─────────┐
                                    ▼         ▼         ▼
                               ┌────────┐┌────────┐┌────────┐
                               │ Parser ││ Trans- ││ Pretty │
                               │ (AST)  ││ former ││ Printer│
                               └────────┘└────────┘└────────┘
```

**Core pipeline:** Parse PHP → Build AST → Apply transformation passes → Pretty-print back to PHP

**Key dependency:** `nikic/php-parser` v5.x (≥5.7.0 required for full PHP 8.5 support: enums, fibers, named arguments, match, intersection types, readonly properties/classes, pipe operator, `(void)` cast, clone-with, etc.)

---

### 3. Project Structure

```
php-obfuscator/
├── bin/
│   └── obfuscate                    # CLI entry point (executable)
├── src/
│   ├── CLI/
│   │   ├── Application.php          # Symfony Console application
│   │   └── ObfuscateCommand.php     # Main CLI command
│   ├── Config/
│   │   ├── Configuration.php        # Configuration value object
│   │   └── ConfigLoader.php         # Loads YAML/PHP config files
│   ├── Obfuscator/
│   │   ├── Obfuscator.php           # Orchestrates the full pipeline
│   │   └── ObfuscationContext.php   # Shared state (symbol tables, mappings)
│   ├── Parser/
│   │   └── ParserFactory.php        # Creates nikic/php-parser instances
│   ├── Transformer/
│   │   ├── TransformerInterface.php # Contract for all transformers
│   │   ├── TransformerPipeline.php  # Runs transformers in sequence
│   │   ├── IdentifierScrambler.php  # Renames variables, functions, classes, etc.
│   │   ├── StringEncoder.php        # Encrypts string literals
│   │   ├── ControlFlowFlattener.php # Flattens control flow structures
│   │   ├── StatementShuffler.php    # Shuffles statements with goto
│   │   └── CommentStripper.php      # Removes comments and whitespace
│   ├── Scrambler/
│   │   ├── ScramblerInterface.php   # Contract for name generators
│   │   ├── RandomScrambler.php      # Random identifier names
│   │   ├── HexScrambler.php         # Hex-style names
│   │   └── NumericScrambler.php     # Numeric-style names
│   ├── Printer/
│   │   └── ObfuscatedPrinter.php    # Custom pretty printer
│   └── FileProcessor/
│       ├── FileProcessor.php        # Single file processing
│       └── DirectoryProcessor.php   # Recursive directory processing
├── tests/
│   ├── Unit/
│   │   ├── Config/
│   │   │   ├── ConfigurationTest.php
│   │   │   └── ConfigLoaderTest.php
│   │   ├── Transformer/
│   │   │   ├── IdentifierScramblerTest.php
│   │   │   ├── StringEncoderTest.php
│   │   │   ├── ControlFlowFlattenerTest.php
│   │   │   ├── StatementShufflerTest.php
│   │   │   └── CommentStripperTest.php
│   │   ├── Scrambler/
│   │   │   ├── RandomScramblerTest.php
│   │   │   ├── HexScramblerTest.php
│   │   │   └── NumericScramblerTest.php
│   │   └── FileProcessor/
│   │       ├── FileProcessorTest.php
│   │       └── DirectoryProcessorTest.php
│   ├── Integration/
│   │   ├── ObfuscationPipelineTest.php   # End-to-end pipeline tests
│   │   └── CLICommandTest.php            # CLI integration tests
│   └── Fixtures/
│       ├── simple_function.php
│       ├── class_with_methods.php
│       ├── php81_enums.php
│       ├── php82_readonly_class.php
│       ├── php83_typed_constants.php
│       ├── php84_property_hooks.php
│       ├── php85_pipe_operator.php
│       ├── php85_clone_with.php
│       ├── php85_void_cast.php
│       └── complex_application.php
├── config/
│   └── default.yaml                 # Default configuration
├── docs/
│   └── PRD.md
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── .gitignore
├── LICENSE
└── README.md
```

---

### 4. Obfuscation Techniques (Transformation Passes)

Each transformer implements `TransformerInterface` and operates on the AST independently. They are applied in order by the pipeline.

#### Pass 1: Comment Stripper
- Remove all comments (doc blocks, inline, block)
- Strip unnecessary whitespace and indentation
- Optionally inject a copyright notice at the top

#### Pass 2: Identifier Scrambler
- **Variables:** Rename all user-defined variables (exclude `$this`, superglobals, and configurable ignore lists)
- **Functions:** Rename user-defined function declarations and their call sites
- **Classes/Interfaces/Traits/Enums:** Rename type declarations and usages (new, type hints, instanceof, etc.)
- **Constants:** Rename user-defined constants
- **Methods & Properties:** Rename class members (with awareness of inheritance chains)
- **Namespaces:** Optionally scramble namespace segments
- **PHP 8.x awareness:** Handle named arguments, enum cases, match arms, readonly properties, intersection/union types, attributes, pipe operator expressions, clone-with, `(void)` cast, final promoted properties
- Maintain a consistent symbol map across files for multi-file projects
- Support configurable ignore lists (exact names and prefix patterns)

#### Pass 3: String Literal Encoder
- Encode string literals using base64 + XOR with a per-file random key
- Replace string nodes with runtime decode expressions: `\call_user_func(...)` wrapping the decode logic
- Skip strings that are used as identifiers (array keys used in external APIs, etc. — configurable)
- Improvement over yakpro-po: use per-file keys and a more complex encoding scheme

#### Pass 4: Control Flow Flattener
- Convert `if/else`, `for`, `while`, `do-while`, `switch` into flattened state-machine style with `goto`
- Use opaque predicates (expressions that always evaluate to true/false but are hard to determine statically) to resist automated deobfuscation — this is a key improvement over yakpro-po
- Insert dead code branches that are never executed but appear valid

#### Pass 5: Statement Shuffler
- Reorder top-level and block-level statements randomly
- Insert `goto` labels and jumps to preserve execution order
- Configurable chunk size (fixed count or ratio-based)

---

### 5. Configuration

Default config file (`config/default.yaml`):

```yaml
obfuscator:
  # Target PHP version for parser
  php_version: "8.5"

  # Scrambler mode: random, hex, numeric
  scrambler_mode: random
  scrambler_min_length: 6

  # Transformer toggles
  strip_comments: true
  scramble_identifiers: true
  scramble_variables: true
  scramble_functions: true
  scramble_classes: true
  scramble_constants: true
  scramble_methods: true
  scramble_properties: true
  scramble_namespaces: false
  encode_strings: true
  flatten_control_flow: true
  shuffle_statements: true

  # Shuffle settings
  shuffle_chunk_mode: ratio    # fixed | ratio
  shuffle_chunk_size: 3
  shuffle_chunk_ratio: 20

  # Ignore lists
  ignore_variables: []
  ignore_functions: []
  ignore_classes: []
  ignore_constants: []
  ignore_variables_prefix: []
  ignore_functions_prefix: []
  ignore_classes_prefix: []

  # Output
  user_comment: ""
  strip_indentation: true
```

---

### 6. PHP Version Support Matrix

| Feature                     | PHP Version | Parser Support |
|-----------------------------|-------------|----------------|
| Named arguments             | 8.0+        | php-parser 5.x |
| Match expressions           | 8.0+        | php-parser 5.x |
| Union types                 | 8.0+        | php-parser 5.x |
| Enums                       | 8.1+        | php-parser 5.x |
| Fibers                      | 8.1+        | php-parser 5.x |
| Readonly properties         | 8.1+        | php-parser 5.x |
| Intersection types          | 8.1+        | php-parser 5.x |
| Readonly classes            | 8.2+        | php-parser 5.x |
| DNF types                   | 8.2+        | php-parser 5.x |
| Typed class constants       | 8.3+        | php-parser 5.x |
| `#[\Override]` attribute    | 8.3+        | php-parser 5.x |
| Property hooks              | 8.4+        | php-parser 5.x   |
| Asymmetric visibility       | 8.4+        | php-parser 5.x   |
| Pipe operator (`\|>`)       | 8.5+        | php-parser ≥5.6.0 |
| `(void)` cast               | 8.5+        | php-parser ≥5.6.0 |
| Clone with arguments        | 8.5+        | php-parser ≥5.6.0 |
| `#[\NoDiscard]` attribute   | 8.5+        | php-parser 5.x   |
| Final promoted properties   | 8.5+        | php-parser ≥5.6.0 |
| Asymmetric visibility (static) | 8.5+     | php-parser 5.x   |

---

### 7. Testing Strategy

**Unit Tests (PHPUnit):**
- Each transformer tested in isolation with small PHP snippets
- Parse input → apply single transformer → assert output AST or code matches expectations
- Scrambler classes tested for determinism (seeded), uniqueness, and length constraints
- Config loader tested with valid, invalid, and partial config files
- Tests for each PHP version-specific feature (enums, readonly, match, etc.)

**Integration Tests:**
- Full pipeline tests: obfuscate a fixture file → `eval()` or execute both original and obfuscated → assert identical output
- Directory processing tests with multi-file projects sharing symbols
- CLI command tests with various argument combinations

**Fixture-Based Regression Tests:**
- Fixture files covering all PHP 7.4–8.5 syntax features
- Each fixture has an expected behavior (output or return value)
- Obfuscated version must produce identical behavior
- These serve as a regression suite against parser/transformer bugs

**Quality Tools:**
- PHPStan (level 8) for static analysis
- PHP-CS-Fixer for code style (PSR-12)
- CI pipeline running tests on PHP 8.1, 8.2, 8.3, 8.4, 8.5

---

### 8. Dependencies

| Package                        | Version  | Purpose                            |
|--------------------------------|----------|------------------------------------|
| `nikic/php-parser`             | ^5.7     | PHP parsing and AST manipulation (PHP 8.5 support) |
| `symfony/console`              | ^6.0\|^7.0 | CLI interface                   |
| `symfony/yaml`                 | ^6.0\|^7.0 | YAML config parsing             |
| `symfony/finder`               | ^6.0\|^7.0 | Directory/file discovery         |
| **Dev dependencies**           |          |                                    |
| `phpunit/phpunit`              | ^11.0    | Unit & integration testing         |
| `phpstan/phpstan`              | ^2.0     | Static analysis                    |
| `friendsofphp/php-cs-fixer`   | ^3.0     | Code style                         |

---

### 9. Implementation Phases

#### Phase 1 — Foundation ([tasks/phase-1-foundation.md](../tasks/phase-1-foundation.md))
- [x] **1.1** Project scaffold: `composer.json`, `.gitignore`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`
- [x] **1.2** `Configuration` value object + `ConfigLoader` (YAML) + tests
- [x] **1.3** `ParserFactory` (wraps nikic/php-parser v5.7+) + tests
- [x] **1.4** `TransformerInterface` + `TransformerPipeline` + tests
- [x] **1.5** `ObfuscationContext` (shared symbol tables, ignore lists) + tests
- [x] **1.6** `Obfuscator` orchestrator + `ObfuscatedPrinter` + round-trip tests
- [x] **Milestone:** Parse a PHP file and emit it unchanged

#### Phase 2 — Core Transformers ([tasks/phase-2-core-transformers.md](../tasks/phase-2-core-transformers.md))
- [x] **2.1** `CommentStripper` transformer + tests
- [x] **2.2** `Scrambler` classes (Random, Hex, Numeric) + tests
- [x] **2.3** `IdentifierScrambler` transformer + tests (all symbol categories, ignore lists, PHP 8.0–8.5 syntax)
- [x] **2.4** `StringEncoder` transformer + tests (per-file XOR keys)
- [x] **2.5** Fixture-based integration tests (obfuscated code runs identically)
- [x] **Milestone:** Single-file obfuscation with identifier scrambling + string encoding

#### Phase 3 — Advanced Transformers ([tasks/phase-3-advanced-transformers.md](../tasks/phase-3-advanced-transformers.md))
- [x] **3.1** `ControlFlowFlattener` transformer + tests (opaque predicates, dead code injection)
- [x] **3.2** `StatementShuffler` transformer + tests (configurable chunk modes)
- [x] **3.3** Full-pipeline integration tests (all 5 transformers combined)
- [x] **Milestone:** Full obfuscation pipeline functional for single files

#### Phase 4 — File & Directory Processing ([tasks/phase-4-file-processing.md](../tasks/phase-4-file-processing.md))
- [x] **4.1** `FileProcessor` (single file in → out, error handling) + tests
- [x] **4.2** `DirectoryProcessor` (recursive, shared symbol map, two-pass) + tests
- [x] **4.3** Incremental processing (timestamp manifest, `--clean`, `--force`) + tests
- [x] **Milestone:** Obfuscate entire project directories with cross-file consistency

#### Phase 5 — CLI & Polish ([tasks/phase-5-cli.md](../tasks/phase-5-cli.md))
- [x] **5.1** `ObfuscateCommand` with Symfony Console (progress bar, summary, verbosity) + tests
- [x] **5.2** `bin/obfuscate` entry point + `composer.json` bin config
- [x] **5.3** Ship `config/default.yaml` with documented defaults
- [x] **5.4** Error handling (parse errors, I/O errors, collected summary)
- [x] **5.5** Code quality: PHPStan level 8 + PHP-CS-Fixer (PSR-12)
- [x] **Milestone:** Fully usable CLI tool

#### Phase 6 — Hardening & CI ([tasks/phase-6-hardening.md](../tasks/phase-6-hardening.md))
- [x] **6.1** GitHub Actions CI: matrix (PHP 8.1–8.4), PHPStan, CS-Fixer
- [x] **6.2** Large fixture tests (200+ line complex file, multi-file mini-project)
- [x] **6.3** Edge case handling + warnings (variable variables, dynamic calls, eval, reflection)
- [x] **6.4** Performance benchmarking (documented in README)
- [x] **6.5** Documentation: README with installation, usage, config reference, limitations
- [x] **Milestone:** Production-ready v1.0 release

#### Phase 7 — Test Coverage & Robustness ([tasks/phase-7-test-coverage.md](../tasks/phase-7-test-coverage.md))
- [ ] **7.1** Modern PHP Syntax Coverage (8.0-8.5)
- [ ] **7.2** Transformer Edge Cases (HEREDOC, try-catch, generators)
- [ ] **7.3** Scrambler Robustness (collisions, determinism)
- [ ] **7.4** File & System Resilience (encodings, permissions)
- [ ] **7.5** Integration & Frameworks (mini-project fixture)
- [ ] **Milestone:** industrial-grade reliability and >95% coverage

---

### 10. Key Improvements Over yakpro-po

| Area                    | yakpro-po                          | iserter/php-obfuscator             |
|-------------------------|------------------------------------|------------------------------------|
| PHP version support     | Up to PHP 7.3                      | PHP 7.4–8.5 (php-parser v5.7+)    |
| Test coverage           | No tests                           | Comprehensive unit + integration   |
| Deobfuscation resistance| Easily reversed by automated tools | Opaque predicates, dead code injection, per-file string keys |
| Architecture            | Procedural PHP scripts             | Modern OOP, PSR-4, SOLID principles|
| Configuration           | PHP config file                    | YAML config with validation        |
| Static analysis         | None                               | PHPStan level 8                    |
| Dependency management   | Git submodule for php-parser       | Composer with proper versioning    |