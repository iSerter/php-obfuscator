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
 * Verifies that shared names across identifier types don't bleed.
 *
 * When a name like "item" is used as a class, property, method, AND variable,
 * disabling scrambling for one type must not affect the others.
 */
final class CrossTypeCollisionTest extends TestCase
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

    private function getFixtureCode(): string
    {
        $code = file_get_contents(__DIR__ . '/../Fixtures/cross_type_collision.php');
        $this->assertIsString($code);
        return $code;
    }

    /**
     * Only scramble variables — class, method, and property named "item" must stay.
     */
    public function testOnlyVariablesScrambledWithSharedName(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, [
            'scramble_variables' => true,
            'scramble_classes' => false,
            'scramble_methods' => false,
            'scramble_properties' => false,
            'scramble_functions' => false,
            'scramble_constants' => false,
        ]);

        // The variable assignment "$item = new ..." should be scrambled
        $this->assertStringNotContainsString('$item = new', $obfuscated);

        // Class name 'Item' must be preserved
        $this->assertStringContainsString('class Item', $obfuscated);

        // Method call ->item() must be preserved
        $this->assertMatchesRegularExpression('/->item\s*\(/', $obfuscated);

        // Property access ->item must be preserved (without parentheses)
        $this->assertMatchesRegularExpression('/->item\s*[^(]/', $obfuscated);
    }

    /**
     * Only scramble classes — variable, method, property named "item" must stay.
     */
    public function testOnlyClassesScrambledWithSharedName(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, [
            'scramble_variables' => false,
            'scramble_classes' => true,
            'scramble_methods' => false,
            'scramble_properties' => false,
            'scramble_functions' => false,
            'scramble_constants' => false,
        ]);

        // Class name should be scrambled (no "class Item")
        $this->assertStringNotContainsString('class Item', $obfuscated);

        // Variable $item preserved
        $this->assertStringContainsString('$item', $obfuscated);

        // Method call ->item() preserved
        $this->assertMatchesRegularExpression('/->item\s*\(/', $obfuscated);

        // Property access ->item preserved
        $this->assertMatchesRegularExpression('/->item[^(]/', $obfuscated);
    }

    /**
     * Only scramble methods — class, variable, property named "item" must stay.
     */
    public function testOnlyMethodsScrambledWithSharedName(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, [
            'scramble_variables' => false,
            'scramble_classes' => false,
            'scramble_methods' => true,
            'scramble_properties' => false,
            'scramble_functions' => false,
            'scramble_constants' => false,
        ]);

        // Method call ->item() should be scrambled
        $this->assertStringNotContainsString('->item()', $obfuscated);

        // Class name preserved
        $this->assertStringContainsString('class Item', $obfuscated);

        // Variable $item preserved
        $this->assertStringContainsString('$item', $obfuscated);

        // Property declaration $item should still contain 'item' (different from method)
        $this->assertMatchesRegularExpression('/public\s+string\s+\$item/', $obfuscated);
    }

    /**
     * Only scramble properties — class, variable, method named "item" must stay.
     */
    public function testOnlyPropertiesScrambledWithSharedName(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, [
            'scramble_variables' => false,
            'scramble_classes' => false,
            'scramble_methods' => false,
            'scramble_properties' => true,
            'scramble_functions' => false,
            'scramble_constants' => false,
        ]);

        // Property declaration $item should be scrambled
        $this->assertStringNotContainsString('public string $item', $obfuscated);

        // Class name preserved
        $this->assertStringContainsString('class Item', $obfuscated);

        // Variable $item preserved
        $this->assertStringContainsString('$item', $obfuscated);

        // Method declaration and call ->item() preserved
        $this->assertMatchesRegularExpression('/function\s+item\s*\(/', $obfuscated);
    }

    /**
     * Functional equivalence: all config variations produce the same output.
     *
     * @dataProvider collisionConfigProvider
     */
    public function testFunctionalEquivalenceWithSharedNames(string $label, array $overrides): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, $overrides);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(
            trim($originalOutput),
            trim($obfuscatedOutput),
            "Functional output mismatch for config: $label\nObfuscated code:\n$obfuscated"
        );
    }

    public static function collisionConfigProvider(): array
    {
        return [
            'all scrambled' => ['all scrambled', []],
            'only variables' => ['only variables', [
                'scramble_classes' => false,
                'scramble_methods' => false,
                'scramble_properties' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
            ]],
            'only classes' => ['only classes', [
                'scramble_variables' => false,
                'scramble_methods' => false,
                'scramble_properties' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
            ]],
            'only methods' => ['only methods', [
                'scramble_variables' => false,
                'scramble_classes' => false,
                'scramble_properties' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
            ]],
            'only properties' => ['only properties', [
                'scramble_variables' => false,
                'scramble_classes' => false,
                'scramble_methods' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
            ]],
        ];
    }
}
