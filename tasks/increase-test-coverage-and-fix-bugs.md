# Task: Increase Test Coverage & Ensure Reliable Obfuscation

## Background

The obfuscator had several critical bugs discovered during real-world use with the SG Lead Manager WordPress plugin. The core issue was that config flags (`scramble_classes: false`, `scramble_methods: false`, etc.) were not properly respected, leading to fatal errors in obfuscated output. These bugs were fixed (see commits), but the test suite didn't catch them because it only tested the "everything enabled" path.

## Bugs Found & Fixed (2026-03-31)

1. **`_d()` string decoder never injected** — `StringEncoder` generated calls to `_d()` but `Obfuscator` never emitted the function definition. Tests passed only because they manually prepended the decoder.

2. **`_d()` namespace scoping** — Injecting `_d()` before a `namespace` declaration is illegal PHP. The runtime must be injected AFTER the namespace line so it's defined within that namespace scope.

3. **`SymbolCollector` ignored config flags** — Added ALL symbols to the map unconditionally. When `scramble_classes: false`, class names were still mapped and then replaced by the catch-all in `IdentifierScrambler`.

4. **`IdentifierScrambler` used wrong config flags** — Methods checked `scrambleFunctions`, properties checked `scrambleVariables`, constants checked `scrambleFunctions`. Added proper `shouldScrambleMethod()`, `shouldScrambleProperty()`, `shouldScrambleConstant()` methods.

5. **Identifier catch-all was context-unaware** — Replaced ANY identifier found in the symbol map regardless of context. A variable named `$prefix` caused `$wpdb->prefix` to be scrambled. A variable `$time` caused `time()` function calls to be scrambled. Replaced with context-aware handlers for MethodCall, PropertyFetch, StaticCall, ClassConstFetch, etc.

6. **`VarLikeIdentifier` type not preserved** — The catch-all created plain `Identifier` for property names that require `VarLikeIdentifier`, causing PHP-Parser type errors.

7. **FuncCall handler didn't check `scrambleFunctions`** — Would scramble any function call if the name happened to exist in the symbol map (e.g., `time()` scrambled because `$time` was a variable).

## Required Test Cases

### 1. Config Flag Isolation Tests

Each `scramble_*` flag must be testable independently. Create a test fixture with all identifier types and verify:

```
Test matrix (for each flag individually set to false while others are true):
- scramble_classes: false    -> class names preserved in declarations AND usages
- scramble_methods: false    -> method names preserved in declarations AND calls
- scramble_properties: false -> property names preserved in declarations AND accesses
- scramble_constants: false  -> constant names preserved in declarations AND fetches
- scramble_functions: false  -> function names preserved in declarations AND calls
- scramble_variables: false  -> variable names preserved everywhere
```

**Test fixture should include:**
```php
<?php
namespace TestApp;

class UserService {
    public const MAX_USERS = 100;
    private string $name;
    private int $count = 0;

    public function getName(): string { return $this->name; }
    public static function create(string $name): self {
        $instance = new self();
        $instance->name = $name;
        return $instance;
    }
}

function helper(): int { return time(); }

$svc = UserService::create("test");
echo $svc->getName();
echo UserService::MAX_USERS;
echo helper();
```

For each config flag set to `false`:
- Parse + obfuscate with that config
- Assert the relevant identifiers are preserved in the output
- Assert OTHER identifiers ARE scrambled (proving isolation)
- Execute the obfuscated code in a subprocess and verify output matches original

### 2. Cross-Type Name Collision Tests

Verify that shared names across identifier types don't bleed:

```php
<?php
class time { // class named 'time'
    public $time;   // property named 'time'
    public function time() {} // method named 'time'
}
$time = new time(); // variable named 'time'
$time->time();       // method call
echo $time->time;    // property access
time();              // PHP built-in function call (should NEVER be scrambled)
```

When `scramble_variables: true` but `scramble_classes/methods/properties/functions: false`:
- `$time` should be scrambled
- `class time`, `->time()`, `->time`, and `time()` (built-in) should NOT be scrambled

### 3. Namespace + _d() Runtime Tests

- Test obfuscation of namespaced file with `encode_strings: true` → verify `_d()` is injected after namespace
- Test obfuscation of non-namespaced file → verify `_d()` is injected after `<?php`/`declare`
- Test that obfuscated output is executable without any manual decoder injection
- Test multiple files in the same namespace → verify `function_exists` guard prevents redefinition

### 4. WordPress-like Integration Test

Create a fixture that mimics WordPress plugin patterns:

```php
<?php
namespace MyPlugin;

class Schema {
    public const DB_VERSION = 5;
    public static function maybe_upgrade(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'my_table';
        $charset = $wpdb->get_charset_collate();
        // ...
    }
}

class Queue {
    private const HOOK = 'my_cron_hook';
    public static function schedule(): void {
        if (!\wp_next_scheduled(self::HOOK)) {
            \wp_schedule_event(\time(), 'hourly', self::HOOK);
        }
    }
}
```

With config `scramble_classes/methods/constants/properties/functions: false, scramble_variables: true`:
- Verify class names, method names, constants are preserved
- Verify `global $wpdb` reference is preserved (when in `ignore_variables`)
- Verify `$wpdb->prefix` property access is NOT scrambled
- Verify `\time()` and `\wp_next_scheduled()` function calls are NOT scrambled
- Verify local variables ARE scrambled

### 5. Functional Equivalence Tests

For EVERY test fixture (simple, complex, WordPress-like):
- Run original code, capture output
- Obfuscate, run obfuscated code in subprocess, capture output
- Assert outputs match

This is already done for the pipeline tests but should be expanded to cover all config variations.

### 6. Edge Cases

- **Named arguments:** `func(name: $val)` — the arg name should follow `scramble_variables` flag
- **Enum cases:** Should follow `scramble_classes` flag
- **Interface/Trait names:** Should follow `scramble_classes` flag
- **`__construct`, `__toString`, etc.:** Magic methods should NEVER be scrambled regardless of config
- **`$this`:** Should NEVER be scrambled
- **Superglobals:** `$_GET`, `$_POST`, etc. should NEVER be scrambled
- **`declare(strict_types=1)` + namespace + _d():** Verify correct ordering

## Implementation Notes

- Tests should use `Configuration::fromArray()` with explicit overrides, not rely on default.yaml
- Each test should be self-contained (no dependency on other tests' state)
- Use subprocess execution for functional tests to avoid namespace/function pollution
- The `Obfuscator` now auto-injects `_d()`, so tests should NOT manually inject it
- Consider adding a `--verify` flag to the CLI that runs the obfuscated code and compares output
