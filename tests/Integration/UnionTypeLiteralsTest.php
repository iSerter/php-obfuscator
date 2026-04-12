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
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PHP built-in literal types (true, false, null) and type keywords
 * (self, parent, static) are never scrambled or namespace-qualified in union
 * type declarations, even when same-named variables exist in the symbol map.
 *
 * Regression test for: literal types in union type declarations are namespace-qualified.
 */
final class UnionTypeLiteralsTest extends TestCase
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

    // ── true/false/null in ConstFetch must survive when $true/$false/$null exist ──

    /**
     * When variables named $true, $false, $null exist, the constants true/false/null
     * in return statements and comparisons must not be replaced with scrambled names.
     */
    public function testBuiltinConstantsNotScrambledDespiteSameNamedVariables(): void
    {
        $code = <<<'PHP'
<?php
function helper() {
    $true = 1;
    $false = 2;
    $null = 3;
    return [$true, $false, $null];
}

function check(): bool {
    if (true) { return false; }
    return true;
}

$x = null;
echo ($x === null ? 'null' : 'bad') . "\n";
echo (check() === false ? 'false' : 'bad') . "\n";
echo (true === true ? 'true' : 'bad') . "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        // The constants must appear literally in the output
        $this->assertStringContainsString('true', $obfuscated);
        $this->assertStringContainsString('false', $obfuscated);
        $this->assertStringContainsString('null', $obfuscated);

        // Functional equivalence
        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    // ── self/parent/static in type declarations must survive ──

    /**
     * self, parent, and static return types must not be scrambled even when
     * variables named $self, $parent, $static exist.
     */
    public function testSelfParentStaticNotScrambledInTypeDeclarations(): void
    {
        $code = <<<'PHP'
<?php
class Base {
    private $self = 'me';
    private $static = 'st';

    public function getSelf(): self {
        return $this;
    }

    public function getStatic(): static {
        return new static();
    }
}

class Child extends Base {
    private $parent = 'par';

    public function getParent(): parent {
        return new parent();
    }
}

$b = new Base();
echo ($b->getSelf() instanceof Base ? 'self-ok' : 'bad') . "\n";
echo ($b->getStatic() instanceof Base ? 'static-ok' : 'bad') . "\n";

$c = new Child();
echo ($c->getParent() instanceof Base ? 'parent-ok' : 'bad') . "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        // self/parent/static must remain as type keywords
        $this->assertMatchesRegularExpression('/:\s*self\b/', $obfuscated);
        $this->assertMatchesRegularExpression('/:\s*static\b/', $obfuscated);
        $this->assertMatchesRegularExpression('/:\s*parent\b/', $obfuscated);

        // new static() and new parent() must be preserved
        $this->assertStringContainsString('new static()', $obfuscated);
        $this->assertStringContainsString('new parent()', $obfuscated);

        // Functional equivalence
        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    // ── Union types with literal types ──

    /**
     * Literal types (true, false, null) in union type declarations must not be
     * namespace-qualified or scrambled.
     */
    public function testLiteralTypesInUnionDeclarationsPreserved(): void
    {
        $code = <<<'PHP'
<?php
namespace SG_Lead_Manager;

class Result {
    public function getMessage(): string { return 'error'; }
}

class Manager {
    public function check(string $feature): Result|true {
        if ($feature === 'ok') { return true; }
        return new Result();
    }

    public function validate(string $input): false|string {
        if ($input === '') { return false; }
        return $input;
    }

    public function find(int $id): null|Result {
        if ($id <= 0) { return null; }
        return new Result();
    }

    public function combo(string $val): Result|true|null {
        if ($val === 'ok') { return true; }
        if ($val === '') { return null; }
        return new Result();
    }
}
PHP;

        $obfuscated = $this->obfuscate($code);

        // Literal types must NOT be namespace-qualified
        $this->assertStringNotContainsString('SG_Lead_Manager\\true', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\false', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\null', $obfuscated);

        // Literal types must appear in the output (as type keywords)
        $this->assertStringContainsString('|true', $obfuscated);
        $this->assertStringContainsString('false|', $obfuscated);
        $this->assertStringContainsString('null|', $obfuscated);

        // Must be valid PHP
        $parser = (new ParserFactory())->createParser(new Configuration());
        $stmts = $parser->parse($obfuscated);
        $this->assertNotNull($stmts);
    }

    // ── self/static in union types ──

    /**
     * self and static in union types must not be scrambled.
     */
    public function testSelfStaticInUnionTypesPreserved(): void
    {
        $code = <<<'PHP'
<?php
class Container {
    private $self = 'x';

    public function getOrNull(): self|null {
        return $this;
    }

    public function createOrFalse(): static|false {
        return new static();
    }
}
PHP;

        $obfuscated = $this->obfuscate($code);

        $this->assertMatchesRegularExpression('/self\|null/', $obfuscated);
        $this->assertMatchesRegularExpression('/static\|false/', $obfuscated);

        // Must be valid PHP
        $parser = (new ParserFactory())->createParser(new Configuration());
        $stmts = $parser->parse($obfuscated);
        $this->assertNotNull($stmts);
    }

    // ── Full fixture functional equivalence ──

    /**
     * The union_type_literals.php fixture must produce identical output
     * before and after obfuscation.
     */
    public function testUnionTypeLiteralsFixtureFunctionalEquivalence(): void
    {
        $fixture = __DIR__ . '/../Fixtures/union_type_literals.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * The fixture's obfuscated output must not contain namespace-qualified literals.
     */
    public function testUnionTypeLiteralsFixtureNoNamespaceQualifiedLiterals(): void
    {
        $fixture = __DIR__ . '/../Fixtures/union_type_literals.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        // None of these reserved names should be namespace-qualified
        $this->assertStringNotContainsString('SG_Lead_Manager\\true', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\false', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\null', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\self', $obfuscated);
        $this->assertStringNotContainsString('SG_Lead_Manager\\static', $obfuscated);

        // Class names should still be scrambled
        $this->assertStringNotContainsString('FeatureManager', $obfuscated);
        $this->assertStringNotContainsString('class Result', $obfuscated);
    }

    // ── Multi-file scenario: cross-file symbol map pollution ──

    /**
     * When processing multiple files with a shared context, reserved names
     * collected from variables in one file must not corrupt types in another.
     */
    public function testCrossFileSymbolMapDoesNotCorruptReservedNames(): void
    {
        $code1 = <<<'PHP'
<?php
namespace App;
function helper() {
    $true = 1;
    $false = 2;
    $null = 3;
    $self = 'me';
    return [$true, $false, $null, $self];
}
PHP;

        $code2 = <<<'PHP'
<?php
namespace Other;
class Checker {
    public function check(): true { return true; }
    public function validate(): false|string { return false; }
    public function find(): null|string { return null; }
    public function me(): self { return $this; }
    public function create(): static { return new static(); }
}
$c = new Checker();
echo ($c->check() === true ? 'true' : 'bad') . "\n";
echo ($c->validate() === false ? 'false' : 'bad') . "\n";
echo ($c->find() === null ? 'null' : 'bad') . "\n";
echo ($c->me() instanceof Checker ? 'self' : 'bad') . "\n";
echo ($c->create() instanceof Checker ? 'static' : 'bad') . "\n";
PHP;

        $config = Configuration::fromArray([
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
        ]);

        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        // First file populates the symbol map with true/false/null/self
        $obfuscator->obfuscate($code1, $context);

        // Second file must not have those reserved names scrambled
        $obfuscated = $obfuscator->obfuscate($code2, $context);

        // Reserved names must appear literally
        $this->assertStringContainsString('true', $obfuscated);
        $this->assertStringContainsString('false', $obfuscated);
        $this->assertStringContainsString('null', $obfuscated);
        $this->assertStringContainsString('self', $obfuscated);
        $this->assertStringContainsString('static', $obfuscated);
        $this->assertStringContainsString('new static()', $obfuscated);

        // Functional equivalence for second file
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $expectedOutput = "true\nfalse\nnull\nself\nstatic";
        $this->assertSame($expectedOutput, trim($obfuscatedOutput));
    }
}
