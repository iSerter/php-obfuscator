# Phase 4 — File & Directory Processing

**Goal:** Support obfuscating entire project directories with consistent cross-file symbol mapping.

**Depends on:** Phase 3 (all transformers working)

## Tasks

### 4.1 File Processor
- [x] `src/FileProcessor/FileProcessor.php` — Reads a PHP file, obfuscates via `Obfuscator`, writes output
- [x] Handles: file encoding detection, PHP open/close tags, mixed PHP/HTML files (skip non-PHP)
- [x] Error handling: invalid PHP syntax → report and skip (don't crash the whole run)
- [x] **Tests:** `tests/Unit/FileProcessor/FileProcessorTest.php`
  - [x] Valid PHP file → obfuscated output written
  - [x] Invalid PHP → error reported, no output file
  - [x] Non-PHP file → copied as-is (or skipped, configurable)

### 4.2 Directory Processor
- [x] `src/FileProcessor/DirectoryProcessor.php` — Uses Symfony Finder to discover PHP files recursively
- [x] Shared `ObfuscationContext` across all files (so class `Foo` in file A and `new Foo` in file B get the same scrambled name)
- [x] **Two-pass approach:**
  1. [x] First pass: scan all files, collect all symbol declarations
  2. [x] Second pass: obfuscate all files with the complete symbol table
- [x] Output directory mirrors source directory structure
- [x] Configurable: file extensions to process, directories to exclude
- [x] **Tests:** `tests/Unit/FileProcessor/DirectoryProcessorTest.php`
  - [x] Multi-file project with cross-file references → all scrambled consistently
  - [x] Excluded directories skipped
  - [x] Output directory structure matches input
  - [x] Non-PHP files handled per config

### 4.3 Incremental Processing
- [x] Track file modification timestamps in a JSON manifest in the output directory
- [x] On re-run: skip files that haven't changed since last obfuscation
- [x] `--clean` flag to wipe output and start fresh
- [x] `--force` flag to re-obfuscate everything
- [x] **Tests:** timestamp comparison logic, manifest read/write

## Milestone
Can obfuscate entire project directories with consistent cross-file symbol mapping and incremental processing.

## Acceptance Criteria
- Multi-file integration test: 3+ files with cross-references → all work after obfuscation
- Incremental mode: unchanged files not re-processed (verify via timestamp check)
- `--clean` removes output directory contents
- Error in one file doesn't stop processing of others
- PHPStan level 8 clean
