<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Integration;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\CommentStripper;
use ISerter\PhpObfuscator\Transformer\ControlFlowFlattener;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\StatementShuffler;
use ISerter\PhpObfuscator\Transformer\StringEncoder;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

/**
 * Stress tests for complex real-world patterns.
 *
 * Each test obfuscates non-trivial code with ALL transformers enabled
 * (identifier scrambling, string encoding, control flow flattening,
 * statement shuffling) and verifies via subprocess execution that the
 * obfuscated output is functionally identical to the original.
 */
final class ComplexSystemTest extends TestCase
{
    /**
     * Obfuscate with ALL transformers enabled (maximum obfuscation).
     */
    private function obfuscateFull(string $code, array $configOverrides = []): string
    {
        $defaults = [
            'scramble_identifiers' => true,
            'scramble_variables' => true,
            'scramble_functions' => true,
            'scramble_classes' => true,
            'scramble_constants' => true,
            'scramble_methods' => true,
            'scramble_properties' => true,
            'scramble_namespaces' => false,
            'encode_strings' => true,
            'flatten_control_flow' => true,
            'shuffle_statements' => true,
            'strip_comments' => true,
        ];

        $config = Configuration::fromArray(array_merge($defaults, $configOverrides));
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());
        $pipeline->addTransformer(new StringEncoder());
        $pipeline->addTransformer(new ControlFlowFlattener());
        $pipeline->addTransformer(new StatementShuffler());

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        return $obfuscator->obfuscate($code, $context);
    }

    private function assertFunctionalEquivalence(string $code, string $obfuscated, string $context = ''): void
    {
        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(
            trim($originalOutput),
            trim($obfuscatedOutput),
            "Functional output mismatch" . ($context ? " ($context)" : '') . "\nObfuscated code:\n$obfuscated"
        );
    }

    private function runCodeInSubprocess(string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'obf');
        $this->assertIsString($tempFile);

        file_put_contents($tempFile, $code);

        $output = [];
        exec("php " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
        unlink($tempFile);

        if ($returnCode !== 0) {
            $this->fail("Subprocess failed with code $returnCode:\n" . implode("\n", $output) . "\nCODE:\n" . $code);
        }

        return implode("\n", $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 1: String comparisons and match() expressions
    // ─────────────────────────────────────────────────────────────────

    /**
     * String literal comparisons (===, !==) and match() arms must
     * produce correct results after string encoding + identifier scrambling.
     *
     * This is the #1 risk area: string values like 'active' get XOR-encoded,
     * and if the decoding is off by even one byte the comparison fails silently.
     */
    public function testStringComparisonsAndMatchExpressions(): void
    {
        $fixture = __DIR__ . '/../Fixtures/complex_strings.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscateFull($code);

        // Strings must be encoded (no plain-text 'active' in output)
        $this->assertStringNotContainsString("'active'", $obfuscated);
        $this->assertStringNotContainsString("'production'", $obfuscated);

        $this->assertFunctionalEquivalence($code, $obfuscated, 'string comparisons fixture');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 2: Associative array string keys
    // ─────────────────────────────────────────────────────────────────

    /**
     * Associative array keys are string literals. After string encoding they
     * become _d() calls — verify array access still resolves correctly.
     */
    public function testAssociativeArrayStringKeys(): void
    {
        $code = <<<'PHP'
<?php
$user = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    'age' => 30,
];

// Access by string key
echo $user['first_name'] . " " . $user['last_name'] . "\n";
echo $user['email'] . "\n";

// isset check on string key
echo isset($user['age']) ? "has age" : "no age";
echo "\n";

// Nested arrays with string keys
$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'credentials' => [
            'user' => 'admin',
            'pass' => 'secret',
        ],
    ],
];
echo $config['database']['host'] . ":" . $config['database']['port'] . "\n";
echo $config['database']['credentials']['user'] . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'associative array keys');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 3: Nested closures with captured variables
    // ─────────────────────────────────────────────────────────────────

    /**
     * Closures capture variables via use(). When those variable names are
     * scrambled, the binding must remain consistent between the outer scope
     * and the closure's use() list. Nesting makes this harder.
     */
    public function testNestedClosuresWithCapturedVariables(): void
    {
        $fixture = __DIR__ . '/../Fixtures/complex_closures.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'nested closures fixture');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 4: Null-safe operator chains and optional chaining
    // ─────────────────────────────────────────────────────────────────

    /**
     * Null-safe property access (?->) and method calls must work with
     * scrambled property/method names. Mixed null and non-null paths.
     */
    public function testNullSafeOperatorChains(): void
    {
        $code = <<<'PHP'
<?php
class Address {
    public function __construct(
        private string $city,
        private ?string $zip = null
    ) {}
    public function getCity(): string { return $this->city; }
    public function getZip(): ?string { return $this->zip; }
}

class Profile {
    public function __construct(
        private ?Address $address = null
    ) {}
    public function getAddress(): ?Address { return $this->address; }
}

class Account {
    public function __construct(
        private ?Profile $profile = null
    ) {}
    public function getProfile(): ?Profile { return $this->profile; }
}

// Full chain — all non-null
$acct1 = new Account(new Profile(new Address("NYC", "10001")));
echo $acct1->getProfile()?->getAddress()?->getCity() . "\n";
echo $acct1->getProfile()?->getAddress()?->getZip() . "\n";

// Partial null chain — profile is null
$acct2 = new Account(null);
echo ($acct2->getProfile()?->getAddress()?->getCity() ?? "no city") . "\n";

// Partial null chain — address is null
$acct3 = new Account(new Profile(null));
echo ($acct3->getProfile()?->getAddress()?->getCity() ?? "no city") . "\n";
echo ($acct3->getProfile()?->getAddress()?->getZip() ?? "no zip") . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'null-safe chains');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 5: Try/catch with multiple exception types
    // ─────────────────────────────────────────────────────────────────

    /**
     * Exception class names in catch blocks must be scrambled consistently
     * with the class declarations. Non-catch throws must also resolve.
     */
    public function testTryCatchWithCustomExceptions(): void
    {
        $code = <<<'PHP'
<?php
class AppException extends \RuntimeException {}
class NotFoundError extends AppException {
    public function __construct(string $item) {
        parent::__construct("Not found: " . $item);
    }
}
class ValidationError extends AppException {
    public function __construct(string $field) {
        parent::__construct("Invalid: " . $field);
    }
}

function riskyLookup(string $key): string {
    if ($key === 'missing') {
        throw new NotFoundError($key);
    }
    if ($key === '') {
        throw new ValidationError('key');
    }
    return "found:" . $key;
}

// Catch specific types
try {
    echo riskyLookup('hello') . "\n";
} catch (NotFoundError $e) {
    echo "NFE: " . $e->getMessage() . "\n";
} catch (ValidationError $e) {
    echo "VE: " . $e->getMessage() . "\n";
}

try {
    echo riskyLookup('missing') . "\n";
} catch (NotFoundError $e) {
    echo "NFE: " . $e->getMessage() . "\n";
} catch (ValidationError $e) {
    echo "VE: " . $e->getMessage() . "\n";
}

try {
    echo riskyLookup('') . "\n";
} catch (NotFoundError $e) {
    echo "NFE: " . $e->getMessage() . "\n";
} catch (ValidationError $e) {
    echo "VE: " . $e->getMessage() . "\n";
}

// Catch parent type
try {
    riskyLookup('missing');
} catch (AppException $e) {
    echo "App: " . $e->getMessage() . "\n";
}
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'try/catch exception types');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 6: Generator functions with yield and complex state
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generators preserve state across yield points. Variable scrambling
     * must be consistent across the entire generator body, and string
     * encoding must not break yielded values.
     */
    public function testGeneratorsWithYieldAndState(): void
    {
        $code = <<<'PHP'
<?php
function fibonacci(int $limit): \Generator {
    $a = 0;
    $b = 1;
    $count = 0;
    while ($count < $limit) {
        yield $a;
        $temp = $a;
        $a = $b;
        $b = $temp + $b;
        $count++;
    }
}

function keyedPairs(): \Generator {
    $items = ['alpha' => 10, 'beta' => 20, 'gamma' => 30];
    foreach ($items as $key => $value) {
        yield $key => $value * 2;
    }
}

// Fibonacci
$fibs = [];
foreach (fibonacci(8) as $num) {
    $fibs[] = $num;
}
echo implode(",", $fibs) . "\n";

// Keyed generator
$pairs = [];
foreach (keyedPairs() as $k => $v) {
    $pairs[] = "$k=$v";
}
echo implode(",", $pairs) . "\n";

// Generator with send()
function accumulator(): \Generator {
    $sum = 0;
    while (true) {
        $value = yield $sum;
        if ($value === null) break;
        $sum += $value;
    }
}

$acc = accumulator();
$acc->current();
$acc->send(5);
$acc->send(3);
echo $acc->send(7) . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'generators');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 7: Spread operator, variadics, and array unpacking
    // ─────────────────────────────────────────────────────────────────

    /**
     * Variadic parameters (...$args), spread in function calls, and
     * array unpacking must work after identifier scrambling.
     */
    public function testSpreadOperatorAndVariadics(): void
    {
        $code = <<<'PHP'
<?php
function sum(int ...$numbers): int {
    $total = 0;
    foreach ($numbers as $n) {
        $total += $n;
    }
    return $total;
}

// Direct variadic call
echo sum(1, 2, 3, 4, 5) . "\n";

// Spread array into function call
$nums = [10, 20, 30];
echo sum(...$nums) . "\n";

// Array unpacking
$first = [1, 2, 3];
$second = [4, 5, 6];
$merged = [...$first, ...$second];
echo implode(",", $merged) . "\n";

// String-keyed array unpacking
$defaults = ['color' => 'red', 'size' => 'large'];
$overrides = ['size' => 'small', 'weight' => 'light'];
$final = [...$defaults, ...$overrides];
echo $final['color'] . "," . $final['size'] . "," . $final['weight'] . "\n";

// Variadic with type hints in a class method
class Formatter {
    public static function join(string $sep, string ...$parts): string {
        return implode($sep, $parts);
    }
}
echo Formatter::join("-", "a", "b", "c") . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'spread operator');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 8: Complex inheritance with abstract + interface + trait
    // ─────────────────────────────────────────────────────────────────

    /**
     * Deep inheritance chains, interface implementation, and trait usage
     * must remain consistent after class/method/property scrambling.
     * Method resolution order and string returns must survive.
     */
    public function testComplexInheritanceChain(): void
    {
        $code = <<<'PHP'
<?php
interface Describable {
    public function describe(): string;
}

interface Countable2 {
    public function getCount(): int;
}

trait HasName {
    private string $name;
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}

abstract class BaseEntity implements Describable {
    use HasName;
    abstract public function getType(): string;
    public function describe(): string {
        return $this->getType() . ":" . $this->getName();
    }
}

class Collection extends BaseEntity implements Countable2 {
    private array $items = [];

    public function __construct(string $name) {
        $this->setName($name);
    }

    public function getType(): string { return "collection"; }

    public function add(string $item): void {
        $this->items[] = $item;
    }

    public function getCount(): int {
        return count($this->items);
    }

    public function getItems(): array {
        return $this->items;
    }
}

class SortedCollection extends Collection {
    public function getType(): string { return "sorted"; }

    public function add(string $item): void {
        parent::add($item);
        // sort is on the internal array, we simulate by getting + re-adding
    }

    public function getSorted(): array {
        $items = $this->getItems();
        sort($items);
        return $items;
    }
}

$col = new SortedCollection("fruits");
$col->add("banana");
$col->add("apple");
$col->add("cherry");

echo $col->describe() . "\n";
echo $col->getCount() . "\n";
echo implode(",", $col->getSorted()) . "\n";
echo $col->getName() . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'inheritance chain');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 9: Complex string interpolation in various contexts
    // ─────────────────────────────────────────────────────────────────

    /**
     * String interpolation with variables, property accesses, array access,
     * and curly-brace expressions inside double-quoted strings and heredocs.
     * String encoding converts each interpolated part into _d() + concat.
     */
    public function testComplexStringInterpolation(): void
    {
        $code = <<<'PHP'
<?php
class Item {
    public string $label;
    public int $qty;
    public function __construct(string $label, int $qty) {
        $this->label = $label;
        $this->qty = $qty;
    }
}

$item = new Item("Widget", 5);
$prefix = ">>>";

// Simple variable interpolation
echo "Item: $prefix\n";

// Property access in curly braces
echo "Label: {$item->label}\n";
echo "Qty: {$item->qty}\n";

// Array access in interpolation
$data = ['key' => 'value', 'num' => 42];
echo "Key is {$data['key']}\n";
echo "Num is {$data['num']}\n";

// Multiple interpolations in one string
$first = "Hello";
$second = "World";
echo "$first, $second! Count={$item->qty}\n";

// Heredoc with interpolation
$name = "Tester";
echo <<<EOT
Name: $name
Item: {$item->label}
Done
EOT;
echo "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'string interpolation');
    }

    // ─────────────────────────────────────────────────────────────────
    // Test 10: Chained ternary, null coalescing, and complex conditionals
    // ─────────────────────────────────────────────────────────────────

    /**
     * Complex conditional expressions where string encoding + control flow
     * flattening + variable scrambling all interact. Ternary chains,
     * null coalescing (?? and ??=), and nested conditionals with string returns.
     */
    public function testComplexConditionalsWithStrings(): void
    {
        $code = <<<'PHP'
<?php
function classify(int $score): string {
    // Nested ternary (parenthesized for clarity)
    return ($score >= 90) ? 'excellent'
        : (($score >= 70) ? 'good'
        : (($score >= 50) ? 'average' : 'poor'));
}

function getOrDefault(?array $config, string $key): string {
    // Null coalescing on nested array
    return $config[$key] ?? 'default_value';
}

function processFlags(array $flags): string {
    $result = '';

    // Complex conditional chains with string comparison
    foreach ($flags as $flag => $enabled) {
        if ($flag === 'verbose' && $enabled) {
            $result .= 'V';
        } elseif ($flag === 'debug' && $enabled) {
            $result .= 'D';
        } elseif ($flag === 'quiet' && !$enabled) {
            $result .= 'Q';
        }
    }

    return $result === '' ? 'none' : $result;
}

echo classify(95) . "\n";
echo classify(75) . "\n";
echo classify(55) . "\n";
echo classify(30) . "\n";

echo getOrDefault(['host' => 'localhost'], 'host') . "\n";
echo getOrDefault(['host' => 'localhost'], 'port') . "\n";
echo getOrDefault(null, 'anything') . "\n";

$flags = ['verbose' => true, 'debug' => false, 'quiet' => false];
echo processFlags($flags) . "\n";

// Null coalescing assignment
$settings = [];
$settings['theme'] ??= 'dark';
$settings['lang'] ??= 'en';
echo $settings['theme'] . "," . $settings['lang'] . "\n";
PHP;

        $obfuscated = $this->obfuscateFull($code);
        $this->assertFunctionalEquivalence($code, $obfuscated, 'complex conditionals');
    }
}
