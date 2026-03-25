# AST Node Handling Reference

## Overview

This document maps PHP language constructs to their `nikic/php-parser` v5.x AST node types and describes how each obfuscation pass should handle them.

## Node Types by Category

### Declarations

| PHP Construct | AST Node | Scramble? | Notes |
|---|---|---|---|
| `function foo()` | `Stmt\Function_` | Name: yes | Also scramble matching `Expr\FuncCall` nodes |
| `class Foo` | `Stmt\Class_` | Name: yes | Also scramble `new Foo`, type hints, `instanceof` |
| `interface Foo` | `Stmt\Interface_` | Name: yes | Same as class |
| `trait Foo` | `Stmt\Trait_` | Name: yes | Also scramble `use Foo` inside classes |
| `enum Foo` | `Stmt\Enum_` | Name: yes | Enum cases: scramble `EnumCase` names |
| `const FOO = 1` | `Stmt\Const_` | Name: yes | Also scramble `Expr\ConstFetch` |
| `namespace Foo\Bar` | `Stmt\Namespace_` | Optional | Disabled by default — can break autoloading |

### Class Members

| PHP Construct | AST Node | Scramble? | Notes |
|---|---|---|---|
| `public $prop` | `Stmt\Property` | Name: yes | Must handle inheritance — same name in parent/child |
| `public function method()` | `Stmt\ClassMethod` | Name: yes | Don't scramble magic methods (`__construct`, etc.) |
| `class const X` | `Stmt\ClassConst` | Name: yes | Also scramble `ClassName::X` references |
| `public final readonly string $x` (promoted) | `Param` with visibility | Name: yes | Check `isFinal()` for PHP 8.5 |

### Expressions

| PHP Construct | AST Node | Scramble? | Notes |
|---|---|---|---|
| `$var` | `Expr\Variable` | Name: yes | Skip `$this`, superglobals |
| `foo()` | `Expr\FuncCall` | Name: yes | Only if user-defined |
| `$obj->method()` | `Expr\MethodCall` | Method name: yes | |
| `Class::method()` | `Expr\StaticCall` | Both: yes | |
| `new Foo()` | `Expr\New_` | Class: yes | |
| `$x instanceof Foo` | `Expr\Instanceof_` | Class: yes | |
| `$a \|> $b` | `Expr\BinaryOp\Pipe` | Operands: recurse | PHP 8.5 pipe operator |
| `(void) $x` | `Expr\Cast\Void_` | Operand: recurse | PHP 8.5 void cast |
| `clone($obj, [...])` | `Expr\FuncCall` | Args: recurse | PHP 8.5 clone-with (NOT `Expr\Clone_`) |
| `match ($x) { ... }` | `Expr\Match_` | Arms: recurse | PHP 8.0+ |
| `fn($x) => $x + 1` | `Expr\ArrowFunction` | Params/body: yes | |
| `function() { ... }` | `Expr\Closure` | Params/body: yes | |

### Type References

| PHP Construct | AST Node | Scramble? | Notes |
|---|---|---|---|
| `Foo` (simple type) | `Name` or `Name\FullyQualified` | Yes (if user class) | Don't touch built-in types (`int`, `string`, etc.) |
| `Foo\|Bar` | `UnionType` | Recurse into types | PHP 8.0+ |
| `Foo&Bar` | `IntersectionType` | Recurse into types | PHP 8.1+ |
| `(Foo&Bar)\|null` | `UnionType` containing `IntersectionType` | Recurse | PHP 8.2+ DNF types |

### Named Arguments (PHP 8.0+)

```php
foo(name: $value);
```

The `Arg` node has a `name` property. When scrambling function parameters, the named argument labels must be updated to match. This requires tracking parameter names across function declarations and call sites.

**Critical:** If a function is in an ignore list (not scrambled), its parameter names in call sites must also be preserved.

## Nodes to Never Scramble

- `$this`
- Superglobals: `$_GET`, `$_POST`, `$_SESSION`, `$_COOKIE`, `$_SERVER`, `$_FILES`, `$_ENV`, `$_REQUEST`, `$GLOBALS`
- Magic methods: `__construct`, `__destruct`, `__call`, `__callStatic`, `__get`, `__set`, `__isset`, `__unset`, `__sleep`, `__wakeup`, `__serialize`, `__unserialize`, `__toString`, `__invoke`, `__set_state`, `__clone`, `__debugInfo`
- Magic constants: `__LINE__`, `__FILE__`, `__DIR__`, `__FUNCTION__`, `__CLASS__`, `__TRAIT__`, `__METHOD__`, `__NAMESPACE__`
- Built-in functions, classes, interfaces (PHP standard library)
- Attributes: `#[\Override]`, `#[\NoDiscard]`, `#[\Deprecated]`, etc.
