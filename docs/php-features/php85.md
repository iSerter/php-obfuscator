# PHP 8.5 Features — Obfuscator Impact

PHP 8.5 was released November 20, 2025. This document covers the new syntax features and their impact on the obfuscator.

## New Syntax Requiring Parser Support

### 1. Pipe Operator (`|>`)

```php
$result = $input |> trim(...) |> strtolower(...) |> ucfirst(...);
```

- **AST Node:** `Expr\BinaryOp\Pipe`
- **Parser support:** nikic/php-parser ≥5.6.0
- **Obfuscator impact:** Since `Pipe` extends `BinaryOp`, existing binary-op traversal should handle it. However, the right-hand side is typically a first-class callable — if it references a user-defined function, the identifier scrambler must rename it consistently.

### 2. Clone With Arguments

```php
$new = clone($original, ['name' => 'updated']);
```

- **AST Node:** `Expr\FuncCall` (NOT `Expr\Clone_`)
- **Parser support:** nikic/php-parser ≥5.6.0
- **Obfuscator impact:** **Critical change.** The old `clone $obj` used `Expr\Clone_`. The new `clone($obj, [...])` is parsed as a function call. Any code that specifically looks for `Expr\Clone_` will miss this form. Transformer logic must handle both.

### 3. `(void)` Cast

```php
(void) someFunction();
```

Used to suppress `#[\NoDiscard]` warnings.

- **AST Node:** `Expr\Cast\Void_`
- **Parser support:** nikic/php-parser ≥5.6.0
- **Obfuscator impact:** New cast node type. Any exhaustive cast handling (e.g., in the control flow flattener or pretty printer) must include `Void_`.

### 4. Final Promoted Properties

```php
class Foo {
    public function __construct(
        public final readonly string $name
    ) {}
}
```

- **AST Node:** `Param` with `MODIFIER_FINAL` flag
- **Parser support:** nikic/php-parser ≥5.6.0 (`Param::isFinal()` in ≥5.6.2)
- **Obfuscator impact:** The identifier scrambler handles promoted properties via `Param` nodes. The `final` modifier doesn't change scrambling logic but must be preserved in output.

## Features Using Existing Syntax (No New Parser Nodes)

These features are parsed correctly by existing php-parser versions because they use already-supported syntax constructs:

| Feature | Why No Parser Change |
|---|---|
| `#[\NoDiscard]` attribute | Attributes are already generic — parser handles arbitrary attribute names |
| `#[\Override]` on properties | Same — attribute placement is validated at runtime, not parse time |
| `#[\Deprecated]` on traits/constants | Same |
| `#[\DelayedTargetValidation]` | Same |
| Closures in constant expressions | Parser already parses closures everywhere; the restriction was in the engine |
| Asymmetric visibility on static props | Modifier flags already existed from PHP 8.4 |

## Deprecations to Be Aware Of

The obfuscator should **not emit deprecated syntax** in its output:

| Deprecated | Replacement | Action |
|---|---|---|
| Backtick operator (`` `cmd` ``) | `shell_exec('cmd')` | Normalize in output |
| `(boolean)`, `(integer)`, `(double)` | `(bool)`, `(int)`, `(float)` | Normalize in output |
| `case 1;` (semicolon) | `case 1:` (colon) | Normalize in output |

## Required php-parser Version

**Minimum:** v5.6.0 for PHP 8.5 syntax support
**Recommended:** v5.7.0 (includes edge case fixes for pipe operator with arrow functions)

## Test Fixtures Needed

- `php85_pipe_operator.php` — pipe chains with built-in and user-defined callables
- `php85_clone_with.php` — clone-with on readonly classes, nested clone-with
- `php85_void_cast.php` — `(void)` cast with `#[\NoDiscard]` functions
- `php85_final_promoted.php` — final promoted properties in constructors
- `php85_mixed.php` — combination of all PHP 8.5 features in one file
