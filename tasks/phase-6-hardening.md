# Phase 6 — Hardening & CI

**Goal:** Production readiness — CI pipeline, edge case handling, performance, documentation.

**Depends on:** Phase 5 (CLI complete)

## Tasks

### 6.1 GitHub Actions CI
- [x] `.github/workflows/ci.yml`
  - [x] Matrix: PHP 8.1, 8.2, 8.3, 8.4
  - [x] Steps: `composer install`, `phpunit`, `phpstan`, `php-cs-fixer --dry-run`
- [x] Run on: push to main, pull requests

### 6.2 Large Fixture Tests
- [x] `tests/Fixtures/complex_application.php` — 200+ line file with classes, interfaces, enums, closures, match, named args, traits, inheritance, etc.
- [x] Multi-file fixture support verified via `DirectoryProcessorTest`
- [x] Verify obfuscated versions execute identically

### 6.3 Edge Case Handling
- [x] **Variable variables** (`$$var`) — detect and warn
- [x] **Dynamic function calls** (`$func()`) — detect and warn
- [x] **`eval()`** — warn
- [x] **Reflection** — documented limitations
- [x] **String-based class references** — detect and warn
- [x] Add warnings to CLI output when these patterns are found

### 6.4 Performance
- [x] Identified bottlenecks (scrambler lookups)
- [x] Optimized string encoding to skip constant contexts
- [x] Documented performance in README

### 6.5 Documentation
- [x] `README.md`: installation, quick start, usage examples, configuration reference
- [x] Mention all supported PHP versions (7.4–8.5)
- [x] Document known limitations
- [x] Add examples of obfuscated output

## Milestone
Production-ready v1.0 release.

## Acceptance Criteria
- [x] CI configuration complete
- [x] Complex fixtures pass
- [x] Edge cases produce warnings
- [x] README complete with usage guide
- [x] Tagged v1.0.0 release (Ready for tagging)
