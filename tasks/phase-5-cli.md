# Phase 5 — CLI & Polish

**Goal:** Build the user-facing CLI tool with good UX, error handling, and code quality enforcement.

**Depends on:** Phase 4 (file/directory processing)

## Tasks

### 5.1 CLI Application
- [x] `src/CLI/Application.php` — Symfony Console Application, single-command app
- [x] `src/CLI/ObfuscateCommand.php` — Main command with arguments and options
- [x] Progress bar for directory mode (file count)
- [x] Summary at end: files processed, files skipped, errors
- [x] **Tests:** `tests/Integration/CLICommandTest.php`
  - [x] Single file mode works
  - [x] Directory mode works
  - [x] Config file loaded
  - [x] Missing source → error
  - [x] `--dry-run` produces no output files
  - [x] Exit codes: 0 success, 1 errors encountered, 2 invalid arguments

### 5.2 Entry Point
- [x] `bin/obfuscate` — Executable PHP script, loads autoloader, runs Application
- [x] Add `bin` key to `composer.json`

### 5.3 Default Config
- [x] Ship `config/default.yaml` with all options documented via comments
- [x] ConfigLoader falls back to this when no user config provided

### 5.4 Error Handling
- [x] Parse errors: report file + line + message, continue processing
- [x] Config errors: fail fast with clear message
- [x] File I/O errors: report and continue (don't crash on one unreadable file)
- [x] All errors collected and summarized at end

### 5.5 Code Quality
- [x] `vendor/bin/phpstan analyse` at level 8 — zero errors
- [x] `vendor/bin/php-cs-fixer fix --dry-run` — zero violations (PSR-12)
- [x] Fix any issues found

## Milestone
Fully usable CLI tool: `bin/obfuscate src/ -o dist/` works end-to-end.

## Acceptance Criteria
- [x] `bin/obfuscate --help` shows usage
- [x] Single file and directory modes both work
- [x] Config file overrides defaults
- [x] Progress output shown in directory mode
- [x] Error summary at end
- [x] PHPStan level 8 + CS-Fixer clean
