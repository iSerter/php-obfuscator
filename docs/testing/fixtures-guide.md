# Test Fixtures Guide

## What Makes a Good Fixture

Each fixture file must:
1. Be a **self-contained** PHP script (no external dependencies)
2. Produce **deterministic output** via `echo` or `return`
3. Exercise a **specific PHP feature** or combination of features
4. Be **executable** on the target PHP version
5. Include a header comment describing what it tests

## Fixture Template

```php
<?php
// Fixture: [description]
// PHP Version: [minimum version]
// Tests: [what obfuscation aspects this exercises]

// ... code that uses the feature ...

// Output a deterministic result
echo json_encode($result);
```

## Writing Fixtures for New PHP Features

When PHP introduces new syntax, add a fixture that:
1. Uses the new syntax in a way that involves identifiers (so scrambling is testable)
2. Produces output that depends on the code running correctly
3. Combines the new feature with existing features (inheritance, closures, etc.)

### Example: PHP 8.5 Pipe Operator

```php
<?php
// Fixture: Pipe operator with user-defined functions
// PHP Version: 8.5
// Tests: IdentifierScrambler must follow pipe callable references

function doubleIt(int $n): int {
    return $n * 2;
}

function addTen(int $n): int {
    return $n + 10;
}

$result = 5 |> doubleIt(...) |> addTen(...);
echo $result; // 20
```

After obfuscation, `doubleIt` and `addTen` should be scrambled consistently in both the declaration and the pipe expression, and executing the obfuscated code should still output `20`.

### Example: PHP 8.5 Clone With

```php
<?php
// Fixture: Clone-with on readonly class
// PHP Version: 8.5
// Tests: Scrambler handles clone() as FuncCall, not Clone_

readonly class Point {
    public function __construct(
        public int $x,
        public int $y,
    ) {}
}

$a = new Point(1, 2);
$b = clone($a, ['x' => 10]);
echo json_encode(['x' => $b->x, 'y' => $b->y]); // {"x":10,"y":2}
```

## Validation Process

For each fixture, the integration test:
1. Reads the original file
2. Obfuscates it through the full pipeline
3. Executes the original → captures output
4. Executes the obfuscated version → captures output
5. Asserts outputs are identical
6. Optionally: asserts the obfuscated source no longer contains original identifiers

## Skipping Fixtures on Older PHP

Use PHPUnit attributes to skip fixtures that require newer PHP:

```php
#[RequiresPhp('>=8.5')]
public function testPipeOperatorFixture(): void
{
    $this->assertObfuscatedOutputMatches('php85_pipe_operator.php');
}
```
