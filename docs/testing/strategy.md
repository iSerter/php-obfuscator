# Testing Strategy

## Principles

1. **Every transformer has isolated unit tests.** Each pass is tested independently with small PHP snippets.
2. **Obfuscated code must be functionally identical.** Integration tests execute both original and obfuscated code, asserting identical output.
3. **Every PHP version feature has a fixture.** Regression tests catch parser/transformer bugs when PHP syntax evolves.
4. **Static analysis catches type errors early.** PHPStan level 8 on all source code.

## Test Categories

### Unit Tests (`tests/Unit/`)

Test individual components in isolation.

**Scrambler Tests:**
- Deterministic output with seeded random (same seed → same names)
- All generated names are valid PHP identifiers
- No collisions within a single context
- Minimum length constraint respected
- Each mode (random, hex, numeric) produces correct format

**Transformer Tests:**
- Parse a small PHP snippet → apply one transformer → assert expected output
- Example for IdentifierScrambler:
  ```php
  // Input
  $input = '<?php function hello($name) { return "Hi " . $name; }';

  // After scrambling (seeded), verify:
  // - function name changed
  // - parameter name changed consistently
  // - string literal untouched
  // - return statement preserved
  ```
- Example for StringEncoder:
  ```php
  // Input
  $input = '<?php echo "secret";';

  // After encoding, verify:
  // - no literal "secret" in output
  // - eval/exec of output produces "secret"
  ```

**Config Tests:**
- Load valid YAML → correct Configuration object
- Load partial YAML → defaults fill in
- Load invalid YAML → meaningful error
- Override via CLI arguments

### Integration Tests (`tests/Integration/`)

Test the full pipeline end-to-end.

**Pipeline Tests:**
```php
public function testObfuscatedCodeProducesSameOutput(): void
{
    $original = file_get_contents(__DIR__ . '/../Fixtures/calculator.php');
    $obfuscated = $this->obfuscator->obfuscate($original);

    // Execute original
    $originalOutput = $this->executePhp($original);

    // Execute obfuscated
    $obfuscatedOutput = $this->executePhp($obfuscated);

    $this->assertSame($originalOutput, $obfuscatedOutput);
}
```

**Multi-file Tests:**
- Two files: one defines a class, other uses it
- Obfuscate both with shared context
- Execute obfuscated versions together → same result as originals

**CLI Tests:**
- Test `bin/obfuscate` with various flags
- Test file and directory modes
- Test config file loading
- Test error cases (missing file, invalid PHP, etc.)

### Fixture Files (`tests/Fixtures/`)

Each fixture is a self-contained PHP script that produces deterministic output.

| Fixture | PHP Version | What It Tests |
|---|---|---|
| `simple_function.php` | 7.4+ | Basic function with variables |
| `class_with_methods.php` | 7.4+ | Class, properties, methods, inheritance |
| `php80_named_args.php` | 8.0+ | Named arguments in function calls |
| `php80_match.php` | 8.0+ | Match expressions |
| `php80_union_types.php` | 8.0+ | Union type declarations |
| `php81_enums.php` | 8.1+ | Enum declarations and usage |
| `php81_readonly.php` | 8.1+ | Readonly properties |
| `php81_intersection.php` | 8.1+ | Intersection types |
| `php81_fibers.php` | 8.1+ | Fiber usage |
| `php82_readonly_class.php` | 8.2+ | Readonly classes |
| `php82_dnf_types.php` | 8.2+ | DNF type expressions |
| `php83_typed_constants.php` | 8.3+ | Typed class constants |
| `php84_property_hooks.php` | 8.4+ | Property hooks |
| `php84_asymmetric_vis.php` | 8.4+ | Asymmetric visibility |
| `php85_pipe_operator.php` | 8.5+ | Pipe operator chains |
| `php85_clone_with.php` | 8.5+ | Clone with arguments |
| `php85_void_cast.php` | 8.5+ | Void cast usage |
| `php85_final_promoted.php` | 8.5+ | Final promoted properties |
| `complex_application.php` | 7.4+ | Large multi-pattern file |

### Quality Checks

**PHPStan (level 8):**
- Run on all `src/` code
- Strict types enforced
- No `@phpstan-ignore` without justification

**PHP-CS-Fixer (PSR-12):**
- Consistent code style across the project
- Run in CI as a check (not auto-fix)

## CI Matrix

```yaml
php: ['8.1', '8.2', '8.3', '8.4', '8.5']
```

- All unit + integration tests run on each PHP version
- Fixtures requiring newer PHP versions are skipped on older versions using `#[RequiresPhp('>=8.5')]`
- PHPStan and CS-Fixer run on latest PHP only

## Coverage Target

- **Transformers:** 100% line coverage (these are the critical path)
- **Scramblers:** 100% line coverage
- **Config:** 90%+ line coverage
- **FileProcessor/DirectoryProcessor:** 80%+ line coverage
- **CLI:** Integration test coverage (not line-level)
