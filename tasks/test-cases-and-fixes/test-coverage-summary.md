# Test Coverage Implementation Summary

## What was done

Added 68 new tests (120 total, up from 52) with 435 assertions covering all required test cases from the parent task plus 10 additional complex system stress tests.

## New Test Files

### 1. `tests/Integration/ConfigFlagIsolationTest.php` (21 tests)
- Tests each `scramble_*` flag independently (set to false while others true)
- Verifies identifiers are preserved/scrambled as expected
- Data-driven functional equivalence tests for 9 config variations
- All use subprocess execution to verify obfuscated code runs correctly

### 2. `tests/Integration/CrossTypeCollisionTest.php` (9 tests)
- Shared name "item" used as class, property, method, AND variable
- Verifies disabling one type doesn't affect others
- Tests: only-variables, only-classes, only-methods, only-properties
- Functional equivalence for all variations

### 3. `tests/Integration/NamespaceDecoderTest.php` (7 tests)
- `_d()` injected after `namespace` declaration (not before)
- `_d()` injected after `declare(strict_types=1)` in non-namespaced files
- `function_exists` guard with `__NAMESPACE__` present
- Subprocess execution proves no manual decoder needed
- Double-include guard prevents redefinition
- No `_d()` when `encode_strings: false`

### 4. `tests/Integration/WordPressIntegrationTest.php` (8 tests)
- WordPress-like plugin code with namespaces, constants, methods
- Config: only `scramble_variables: true` (typical WP plugin config)
- Verifies class/method/constant/property names preserved
- Local variables scrambled
- External function calls (`\time()`) not scrambled even when sharing name with variable
- Functional equivalence with and without string encoding

### 5. `tests/Integration/EdgeCaseTest.php` (13 tests)
- 12 magic methods never scrambled (`__construct`, `__toString`, etc.)
- `$this` never scrambled
- All 9 superglobals (`$_GET`, `$_POST`, etc.) never scrambled
- Enum cases follow `scramble_classes` flag
- Interface/Trait names follow `scramble_classes` flag
- `declare` + `namespace` + `_d()` ordering verified
- Ignore lists (`ignore_variables`, `ignore_classes`, `ignore_functions`, `ignore_constants`)
- Functional equivalence for edge_cases fixture

### 6. `tests/Integration/ComplexSystemTest.php` (10 tests)
All tests run with FULL obfuscation pipeline (identifiers + string encoding + control flow flattening + statement shuffling).

- **String comparisons & match()** — verifies `===` and `match()` with encoded strings
- **Associative array keys** — nested string-keyed arrays with encoded keys
- **Nested closures** — `use()` bindings consistent after variable scrambling
- **Null-safe operator chains** — `?->method()?->prop` with scrambled names
- **Try/catch with custom exceptions** — exception class hierarchy + scrambled names
- **Generators** — fibonacci, keyed yields, `send()` with scrambled state vars
- **Spread/variadic operators** — `...$args`, array unpacking, variadic methods
- **Complex inheritance** — abstract + interface + trait with scrambled everything
- **String interpolation** — `"{$obj->prop}"`, heredoc, array access in strings
- **Complex conditionals** — ternary chains, `??`, `??=` with encoded strings

## New Test Fixtures

- `tests/Fixtures/config_isolation.php` — class, methods, properties, constants, functions, variables
- `tests/Fixtures/cross_type_collision.php` — shared name across all identifier types
- `tests/Fixtures/wordpress_like.php` — namespaced WP plugin pattern
- `tests/Fixtures/namespace_decoder.php` — namespaced file for decoder injection
- `tests/Fixtures/namespace_decoder_no_ns.php` — non-namespaced with declare(strict_types)
- `tests/Fixtures/edge_cases.php` — interface, trait, enum, magic methods
- `tests/Fixtures/complex_strings.php` — string comparisons, match(), array keys, interpolation
- `tests/Fixtures/complex_closures.php` — nested closures, pipeline, array callbacks

## Bugs Found & Fixed

### Bug 8: FuncCall scrambled built-in PHP functions when variable shared the name
**Symptom:** `$count = count($arr)` → variable `$count` added "count" to symbol map → `count()` call scrambled to `_v1009()` → fatal error.
**Root cause:** FuncCall handler checked only if name existed in shared symbol map, not whether it was actually declared as a function.
**Fix:** Added `functionSymbols` tracking to `ObfuscationContext`. `SymbolCollector` registers function declarations via `addFunctionSymbol()`. `IdentifierScrambler` FuncCall handler uses `isFunctionSymbol()` instead of `getSymbol()`.
**Files:** `ObfuscationContext.php`, `SymbolCollector.php`, `IdentifierScrambler.php`

### Bug 9: Name catch-all handler scrambled function call names as class references
**Symptom:** Even after Bug 8 fix, the catch-all `Name` handler (for class usages in extends/implements/type hints) still replaced function call names found in the symbol map.
**Root cause:** The `Name` handler fired on ALL `Name` nodes, including the Name child inside `FuncCall` nodes.
**Fix:** FuncCall handler sets `is_func_call_name` attribute on the Name node. Name handler skips nodes with that attribute.
**Files:** `IdentifierScrambler.php`

### Bug 10: NullsafeMethodCall and NullsafePropertyFetch not handled
**Symptom:** `$obj?->method()` — method declaration scrambled but null-safe call site left with original name → "Call to undefined method".
**Root cause:** `IdentifierScrambler` handled `MethodCall` and `PropertyFetch` but not their PHP 8.0 null-safe variants.
**Fix:** Extended MethodCall handler to also match `NullsafeMethodCall`, PropertyFetch handler to also match `NullsafePropertyFetch`.
**Files:** `IdentifierScrambler.php`
