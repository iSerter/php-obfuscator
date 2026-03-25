# PHP Version Feature Matrix

Complete reference of PHP features from 7.4 to 8.5 that the obfuscator must handle correctly.

## PHP 7.4

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Typed properties | `public int $x` | Type references preserved; property name scrambled |
| Arrow functions | `fn($x) => $x * 2` | Parameter and body variables scrambled |
| Null coalescing assignment | `$x ??= $default` | Standard expression handling |
| Spread in arrays | `[...$array]` | Standard expression handling |

## PHP 8.0

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Named arguments | `foo(name: $val)` | **Critical:** argument labels must match scrambled parameter names |
| Match expression | `match($x) { 1 => 'one' }` | Arms traversed; conditions and bodies transformed |
| Union types | `int\|string` | Each type in union checked for user types |
| Nullsafe operator | `$obj?->method()` | Method name scrambled |
| Constructor promotion | `public function __construct(public int $x)` | Property name scrambled |
| Attributes | `#[Route('/')]` | Attribute class names scrambled if user-defined |
| `throw` as expression | `$x ?? throw new E()` | Standard expression handling |

## PHP 8.1

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Enums | `enum Color { case Red; }` | Enum name and case names scrambled |
| Readonly properties | `public readonly string $x` | Name scrambled; modifier preserved |
| Intersection types | `Foo&Bar` | Each type checked for user types |
| Fibers | `new Fiber(fn() => ...)` | Standard class instantiation |
| First-class callables | `strlen(...)` | Function reference scrambled if user-defined |
| `never` return type | `function fail(): never` | Built-in type, never scrambled |

## PHP 8.2

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Readonly classes | `readonly class Dto {}` | Name scrambled; modifier preserved |
| DNF types | `(A&B)\|null` | Nested union/intersection traversal |
| `true`, `false`, `null` types | `function f(): true` | Built-in types, never scrambled |
| Constants in traits | `trait T { const X = 1; }` | Constant name scrambled |
| `enum` backed by expressions | `case X = 1 + 2` | Expression in case value traversed |

## PHP 8.3

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Typed class constants | `const int X = 1` | Type preserved; constant name scrambled |
| `#[\Override]` attribute | `#[\Override] public function f()` | Attribute preserved (built-in) |
| Dynamic class constant fetch | `Foo::{$name}` | Variable scrambled; class name scrambled |
| `json_validate()` | `json_validate($str)` | Built-in function, no action |

## PHP 8.4

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Property hooks | `public string $name { get => ...; set => ...; }` | Hook bodies transformed; property name scrambled |
| Asymmetric visibility | `public private(set) string $x` | Modifiers preserved; name scrambled |
| `new` without parentheses | `new Foo()->method()` | Class name scrambled |
| `#[\Deprecated]` attribute | `#[\Deprecated] function old()` | Attribute preserved (built-in) |

## PHP 8.5

| Feature | Example | Obfuscator Notes |
|---|---|---|
| Pipe operator (`\|>`) | `$x \|> trim(...) \|> strtolower(...)` | Callable references scrambled if user-defined |
| `(void)` cast | `(void) doSomething()` | New cast type; expression inside traversed |
| Clone with | `clone($obj, ['x' => 1])` | Parsed as `FuncCall`, not `Clone_` — handle both forms |
| `#[\NoDiscard]` attribute | `#[\NoDiscard] function compute()` | Attribute preserved (built-in) |
| Final promoted properties | `public final readonly string $x` | `final` modifier preserved; name scrambled |
| Asymmetric visibility (static) | `public private(set) static int $x` | Modifiers preserved; name scrambled |
| Closures in const expressions | `const F = static fn() => 1` | Closure body traversed |
