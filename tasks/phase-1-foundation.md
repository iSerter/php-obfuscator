# Phase 1 — Foundation (Scaffold & Core Pipeline)

**Goal:** Set up the project skeleton and build the parse→transform→print pipeline that can round-trip a PHP file unchanged.

## Tasks

### 1.1 Project Scaffold [DONE]
- [x] Initialize `composer.json` with PSR-4 autoloading (`ISerter\PhpObfuscator` → `src/`)
- [x] Dependencies: `nikic/php-parser ^5.7`, `symfony/console ^6.0|^7.0`, `symfony/yaml ^6.0|^7.0`, `symfony/finder ^6.0|^7.0`
- [x] Dev: `phpunit/phpunit ^11.0`, `phpstan/phpstan ^2.0`, `friendsofphp/php-cs-fixer ^3.0`
- [x] Create `.gitignore`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`
- [x] Run `composer install` to validate

### 1.2 Configuration [DONE]
- [x] `src/Config/Configuration.php` — Immutable value object holding all obfuscation options with sensible defaults
- [x] `src/Config/ConfigLoader.php` — Loads YAML config, merges with defaults, validates
- [x] **Tests:** `tests/Unit/Config/ConfigurationTest.php`, `tests/Unit/Config/ConfigLoaderTest.php`
  - Valid config → correct object
  - Partial config → defaults fill in
  - Invalid config → exception with clear message

### 1.3 Parser Factory [DONE]
- [x] `src/Parser/ParserFactory.php` — Wraps `nikic/php-parser` v5 creation with configured PHP version
- [x] Should return both a parser and a node traverser
- [x] **Tests:** `tests/Unit/Parser/ParserFactoryTest.php` — parses a simple PHP file, returns valid AST

### 1.4 Transformer Pipeline [DONE]
- [x] `src/Transformer/TransformerInterface.php` — Contract: receives AST + context, returns modified AST
- [x] `src/Transformer/TransformerPipeline.php` — Accepts ordered list of transformers, applies them sequentially
- [x] **Tests:** `tests/Unit/Transformer/TransformerPipelineTest.php` — pipeline with no-op transformer returns unchanged AST

### 1.5 Obfuscation Context [DONE]
- [x] `src/Obfuscator/ObfuscationContext.php` — Holds symbol tables (original→scrambled), ignore lists, per-file state
- [x] Shared across transformers and across files in directory mode
- [x] **Tests:** `tests/Unit/Obfuscator/ObfuscationContextTest.php`

### 1.6 Obfuscator Orchestrator [DONE]
- [x] `src/Obfuscator/Obfuscator.php` — Wires together parser, pipeline, printer. Public API: `obfuscate(string $code): string`
- [x] `src/Printer/ObfuscatedPrinter.php` — Extends php-parser's PrettyPrinter for output control
- [x] **Tests:** `tests/Unit/Obfuscator/ObfuscatorTest.php` — round-trip test (parse + print with no transformers = same code)


## Milestone
Parse a PHP file and emit it back unchanged. All unit tests pass. PHPStan clean.

## Acceptance Criteria
- `composer install` succeeds
- `vendor/bin/phpunit` runs and passes
- `vendor/bin/phpstan analyse` passes at level 8
- Round-trip test: input PHP → parse → print → output is valid PHP with same behavior
