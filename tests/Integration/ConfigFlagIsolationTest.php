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
 * Tests that each scramble_* config flag is respected independently.
 *
 * For each flag set to false (while others remain true), the relevant
 * identifiers must be preserved in the output while other identifier
 * types ARE scrambled.
 */
final class ConfigFlagIsolationTest extends TestCase
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

    private function getFixtureCode(): string
    {
        $code = file_get_contents(__DIR__ . '/../Fixtures/config_isolation.php');
        $this->assertIsString($code);
        return $code;
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

    // ── scramble_classes: false ──────────────────────────────────────

    public function testClassNamesPreservedWhenScrambleClassesFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_classes' => false]);

        // Class name must appear in declaration and usages
        $this->assertStringContainsString('UserService', $obfuscated);

        // Other identifiers SHOULD be scrambled (methods, functions, etc.)
        $this->assertStringNotContainsString('getName', $obfuscated);
        $this->assertStringNotContainsString('helper', $obfuscated);
    }

    public function testClassNamesScrambledWhenScrambleClassesTrue(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_classes' => true]);

        // Class name must be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    // ── scramble_methods: false ──────────────────────────────────────

    public function testMethodNamesPreservedWhenScrambleMethodsFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_methods' => false]);

        // Method names must appear in declarations and call sites
        $this->assertStringContainsString('getName', $obfuscated);
        $this->assertStringContainsString('create', $obfuscated);

        // Classes should still be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    public function testMethodNamesScrambledWhenScrambleMethodsTrue(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_methods' => true]);

        $this->assertStringNotContainsString('getName', $obfuscated);
        $this->assertStringNotContainsString('create', $obfuscated);
    }

    // ── scramble_properties: false ───────────────────────────────────

    public function testPropertyNamesPreservedWhenScramblePropertiesFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_properties' => false]);

        // Property names must be preserved in declarations and accesses
        $this->assertStringContainsString('$name', $obfuscated);
        $this->assertStringContainsString('$count', $obfuscated);

        // Classes should still be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    public function testPropertyNamesScrambledWhenScramblePropertiesTrue(): void
    {
        $code = $this->getFixtureCode();
        // Disable variables too so $name as variable doesn't keep 'name' in output
        $obfuscated = $this->obfuscate($code, [
            'scramble_properties' => true,
            'scramble_variables' => true,
        ]);

        // Property declarations use VarLikeIdentifier; they should be scrambled
        // Note: 'name' may appear in string literals, so check property syntax
        $this->assertStringNotContainsString('->name', $obfuscated);
    }

    // ── scramble_constants: false ────────────────────────────────────

    public function testConstantNamesPreservedWhenScrambleConstantsFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_constants' => false]);

        // Constant name must be preserved in declaration and fetch
        $this->assertStringContainsString('MAX_USERS', $obfuscated);

        // Classes should still be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    public function testConstantNamesScrambledWhenScrambleConstantsTrue(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_constants' => true]);

        $this->assertStringNotContainsString('MAX_USERS', $obfuscated);
    }

    // ── scramble_functions: false ────────────────────────────────────

    public function testFunctionNamesPreservedWhenScrambleFunctionsFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_functions' => false]);

        // Function name preserved in declaration and call
        $this->assertStringContainsString('helper', $obfuscated);

        // Classes should still be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    public function testFunctionNamesScrambledWhenScrambleFunctionsTrue(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_functions' => true]);

        $this->assertStringNotContainsString('helper', $obfuscated);
    }

    // ── scramble_variables: false ────────────────────────────────────

    public function testVariableNamesPreservedWhenScrambleVariablesFalse(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_variables' => false]);

        // Variable names preserved
        $this->assertStringContainsString('$svc', $obfuscated);
        $this->assertStringContainsString('$instance', $obfuscated);

        // Classes should still be scrambled
        $this->assertStringNotContainsString('UserService', $obfuscated);
    }

    public function testVariableNamesScrambledWhenScrambleVariablesTrue(): void
    {
        $code = $this->getFixtureCode();
        $obfuscated = $this->obfuscate($code, ['scramble_variables' => true]);

        $this->assertStringNotContainsString('$svc', $obfuscated);
        $this->assertStringNotContainsString('$instance', $obfuscated);
    }

    // ── Functional equivalence for each config variation ─────────────

    /**
     * @dataProvider configVariationsProvider
     */
    public function testObfuscatedOutputMatchesOriginal(string $label, array $overrides): void
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

    public static function configVariationsProvider(): array
    {
        return [
            'all enabled' => ['all enabled', []],
            'scramble_classes=false' => ['scramble_classes=false', ['scramble_classes' => false]],
            'scramble_methods=false' => ['scramble_methods=false', ['scramble_methods' => false]],
            'scramble_properties=false' => ['scramble_properties=false', ['scramble_properties' => false]],
            'scramble_constants=false' => ['scramble_constants=false', ['scramble_constants' => false]],
            'scramble_functions=false' => ['scramble_functions=false', ['scramble_functions' => false]],
            'scramble_variables=false' => ['scramble_variables=false', ['scramble_variables' => false]],
            'only variables' => ['only variables', [
                'scramble_classes' => false,
                'scramble_methods' => false,
                'scramble_properties' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
                'scramble_variables' => true,
            ]],
            'nothing scrambled' => ['nothing scrambled', [
                'scramble_classes' => false,
                'scramble_methods' => false,
                'scramble_properties' => false,
                'scramble_constants' => false,
                'scramble_functions' => false,
                'scramble_variables' => false,
            ]],
        ];
    }
}
