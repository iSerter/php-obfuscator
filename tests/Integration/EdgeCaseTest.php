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
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\StringEncoder;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

/**
 * Edge case tests: magic methods, $this, superglobals, enums,
 * interface/trait names, named arguments, declare+namespace+_d() ordering.
 */
final class EdgeCaseTest extends TestCase
{
    private function obfuscate(string $code, array $configOverrides = []): string
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
            'encode_strings' => false,
            'flatten_control_flow' => false,
            'shuffle_statements' => false,
            'strip_comments' => true,
        ];

        $config = Configuration::fromArray(array_merge($defaults, $configOverrides));
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());

        if ($config->encodeStrings) {
            $pipeline->addTransformer(new StringEncoder());
        }

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        return $obfuscator->obfuscate($code, $context);
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

    // ── Magic methods ────────────────────────────────────────────────

    /**
     * Magic methods (__construct, __toString, etc.) should NEVER be scrambled.
     */
    public function testMagicMethodsNeverScrambled(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    private string $val;
    public function __construct(string $val) { $this->val = $val; }
    public function __toString(): string { return $this->val; }
    public function __get(string $name): mixed { return null; }
    public function __set(string $name, mixed $value): void {}
    public function __isset(string $name): bool { return false; }
    public function __unset(string $name): void {}
    public function __call(string $name, array $args): mixed { return null; }
    public static function __callStatic(string $name, array $args): mixed { return null; }
    public function __invoke(): string { return $this->val; }
    public function __debugInfo(): array { return ['val' => $this->val]; }
    public function __clone(): void {}
    public function __destruct() {}
}
$f = new Foo("test");
echo $f . "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        $magicMethods = [
            '__construct', '__toString', '__get', '__set',
            '__isset', '__unset', '__call', '__callStatic',
            '__invoke', '__debugInfo', '__clone', '__destruct',
        ];

        foreach ($magicMethods as $method) {
            $this->assertStringContainsString($method, $obfuscated, "Magic method $method was scrambled");
        }

        // Class name should be scrambled
        $this->assertStringNotContainsString('class Foo', $obfuscated);
    }

    // ── $this ────────────────────────────────────────────────────────

    /**
     * $this should NEVER be scrambled.
     */
    public function testThisNeverScrambled(): void
    {
        $code = <<<'PHP'
<?php
class Bar {
    private int $x = 5;
    public function getX(): int { return $this->x; }
}
$b = new Bar();
echo $b->getX() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('$this', $obfuscated);
        $this->assertStringNotContainsString('$b', $obfuscated);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    // ── Superglobals ─────────────────────────────────────────────────

    /**
     * Superglobals ($_GET, $_POST, etc.) should NEVER be scrambled.
     */
    public function testSuperglobalsNeverScrambled(): void
    {
        $code = <<<'PHP'
<?php
$_GET['test'] = 'value';
$_POST['data'] = 'info';
$_SERVER['HOST'] = 'localhost';
$_SESSION['id'] = '123';
$_COOKIE['token'] = 'abc';
$_REQUEST['all'] = 'yes';
$_ENV['HOME'] = '/home';
$_FILES['upload'] = [];
$GLOBALS['x'] = 42;
$local = $_GET['test'];
echo $local . "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        $superglobals = ['$_GET', '$_POST', '$_SERVER', '$_SESSION', '$_COOKIE', '$_REQUEST', '$_ENV', '$_FILES', '$GLOBALS'];
        foreach ($superglobals as $sg) {
            $this->assertStringContainsString($sg, $obfuscated, "Superglobal $sg was scrambled");
        }

        // Local variable should be scrambled
        $this->assertStringNotContainsString('$local', $obfuscated);
    }

    // ── Enum cases ───────────────────────────────────────────────────

    /**
     * Enum case names follow scramble_classes flag.
     */
    public function testEnumCasesFollowScrambleClassesFlag(): void
    {
        $code = <<<'PHP'
<?php
enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';
}
echo Status::Active->value . "\n";
PHP;

        // With scramble_classes=true, enum cases get scrambled
        $obfuscated = $this->obfuscate($code, ['scramble_classes' => true]);
        $this->assertStringNotContainsString('Active', $obfuscated);

        // With scramble_classes=false, enum cases preserved
        $obfuscated = $this->obfuscate($code, ['scramble_classes' => false]);
        $this->assertStringContainsString('Active', $obfuscated);
        $this->assertStringContainsString('Inactive', $obfuscated);
    }

    // ── Interface and Trait names ────────────────────────────────────

    /**
     * Interface names follow scramble_classes flag.
     */
    public function testInterfaceNamesFollowScrambleClassesFlag(): void
    {
        $code = <<<'PHP'
<?php
interface Renderable {
    public function render(): string;
}
class Page implements Renderable {
    public function render(): string { return "page"; }
}
$p = new Page();
echo $p->render() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, ['scramble_classes' => false]);
        $this->assertStringContainsString('Renderable', $obfuscated);
        $this->assertStringContainsString('Page', $obfuscated);

        $obfuscated = $this->obfuscate($code, ['scramble_classes' => true]);
        $this->assertStringNotContainsString('Renderable', $obfuscated);
        $this->assertStringNotContainsString('Page', $obfuscated);
    }

    /**
     * Trait names follow scramble_classes flag.
     */
    public function testTraitNamesFollowScrambleClassesFlag(): void
    {
        $code = <<<'PHP'
<?php
trait Greetable {
    public function hello(): string { return "hello"; }
}
class Person {
    use Greetable;
}
$p = new Person();
echo $p->hello() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, ['scramble_classes' => false]);
        $this->assertStringContainsString('Greetable', $obfuscated);
        $this->assertStringContainsString('Person', $obfuscated);

        $obfuscated = $this->obfuscate($code, ['scramble_classes' => true]);
        $this->assertStringNotContainsString('Greetable', $obfuscated);
        $this->assertStringNotContainsString('Person', $obfuscated);
    }

    // ── declare(strict_types=1) + namespace + _d() ordering ─────────

    /**
     * Verify correct ordering: declare → namespace → _d() → code.
     */
    public function testDeclareNamespaceDecoderOrdering(): void
    {
        $code = <<<'PHP'
<?php
declare(strict_types=1);

namespace EdgeTest;

class Msg {
    public function get(): string { return "hello from edge"; }
}

$m = new Msg();
echo $m->get() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, ['encode_strings' => true]);

        // All three must be present in order
        $declarePos = strpos($obfuscated, 'declare');
        $nsPos = strpos($obfuscated, 'namespace EdgeTest');
        $dPos = strpos($obfuscated, 'function _d(');

        $this->assertNotFalse($declarePos);
        $this->assertNotFalse($nsPos);
        $this->assertNotFalse($dPos);

        $this->assertLessThan($nsPos, $declarePos, 'declare must come before namespace');
        $this->assertLessThan($dPos, $nsPos, 'namespace must come before _d()');

        // Must be executable
        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    // ── Functional equivalence for edge_cases fixture ────────────────

    /**
     * edge_cases.php fixture with all scrambling: functional equivalence.
     */
    public function testEdgeCasesFixtureFunctionalEquivalence(): void
    {
        $fixture = __DIR__ . '/../Fixtures/edge_cases.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * edge_cases.php: magic methods preserved even when all scrambling is on.
     */
    public function testEdgeCasesFixtureMagicMethodsPreserved(): void
    {
        $fixture = __DIR__ . '/../Fixtures/edge_cases.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('__toString', $obfuscated);
        $this->assertStringContainsString('__construct', $obfuscated);
    }

    /**
     * Ignore variables list: specified variables are not scrambled.
     */
    public function testIgnoreVariablesList(): void
    {
        $code = <<<'PHP'
<?php
$keep = "preserved";
$scramble = "hidden";
echo $keep . " " . $scramble . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, [
            'ignore_variables' => ['keep'],
        ]);

        $this->assertStringContainsString('$keep', $obfuscated);
        $this->assertStringNotContainsString('$scramble', $obfuscated);
    }

    /**
     * Ignore classes list: specified classes are not scrambled.
     */
    public function testIgnoreClassesList(): void
    {
        $code = <<<'PHP'
<?php
class KeepMe {
    public function run(): string { return "kept"; }
}
class ScrambleMe {
    public function run(): string { return "gone"; }
}
$a = new KeepMe();
$b = new ScrambleMe();
echo $a->run() . " " . $b->run() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, [
            'ignore_classes' => ['KeepMe'],
        ]);

        $this->assertStringContainsString('KeepMe', $obfuscated);
        $this->assertStringNotContainsString('ScrambleMe', $obfuscated);
    }

    /**
     * Ignore functions list: specified functions are not scrambled.
     */
    public function testIgnoreFunctionsList(): void
    {
        $code = <<<'PHP'
<?php
function keepFunc(): string { return "kept"; }
function scrambleFunc(): string { return "gone"; }
echo keepFunc() . " " . scrambleFunc() . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, [
            'ignore_functions' => ['keepFunc'],
        ]);

        $this->assertStringContainsString('keepFunc', $obfuscated);
        $this->assertStringNotContainsString('scrambleFunc', $obfuscated);
    }

    /**
     * Ignore constants list: specified constants are not scrambled.
     */
    public function testIgnoreConstantsList(): void
    {
        $code = <<<'PHP'
<?php
class Config {
    public const KEEP_THIS = 'kept';
    public const SCRAMBLE_THIS = 'gone';
}
echo Config::KEEP_THIS . " " . Config::SCRAMBLE_THIS . "\n";
PHP;

        $obfuscated = $this->obfuscate($code, [
            'ignore_constants' => ['KEEP_THIS'],
        ]);

        $this->assertStringContainsString('KEEP_THIS', $obfuscated);
        $this->assertStringNotContainsString('SCRAMBLE_THIS', $obfuscated);
    }
}
