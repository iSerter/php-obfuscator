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
 * Tests obfuscation of WordPress-like plugin code.
 *
 * Verifies that with typical WP plugin config (only scramble_variables: true),
 * class names, method names, constants, properties, and external function calls
 * are preserved while local variables are scrambled.
 */
final class WordPressIntegrationTest extends TestCase
{
    private function obfuscate(string $code, array $configOverrides = []): string
    {
        $defaults = [
            'scramble_identifiers' => true,
            'scramble_variables' => true,
            'scramble_functions' => false,
            'scramble_classes' => false,
            'scramble_constants' => false,
            'scramble_methods' => false,
            'scramble_properties' => false,
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

    /**
     * Class names preserved with scramble_classes: false.
     */
    public function testClassNamesPreserved(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('Schema', $obfuscated);
        $this->assertStringContainsString('Queue', $obfuscated);
    }

    /**
     * Method names preserved with scramble_methods: false.
     */
    public function testMethodNamesPreserved(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('getVersion', $obfuscated);
        $this->assertStringContainsString('getHook', $obfuscated);
        $this->assertStringContainsString('getName', $obfuscated);
    }

    /**
     * Constant names preserved with scramble_constants: false.
     */
    public function testConstantNamesPreserved(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('DB_VERSION', $obfuscated);
        $this->assertStringContainsString('HOOK', $obfuscated);
    }

    /**
     * Local variables are scrambled with scramble_variables: true.
     */
    public function testLocalVariablesScrambled(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        // Local variables should be scrambled
        $this->assertStringNotContainsString('$version', $obfuscated);
        $this->assertStringNotContainsString('$queue', $obfuscated);
    }

    /**
     * Property names preserved with scramble_properties: false.
     * $this->name should still show "name" as property.
     */
    public function testPropertyAccessPreserved(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        // Property 'name' should be preserved in property access
        $this->assertMatchesRegularExpression('/->name/', $obfuscated);
    }

    /**
     * Functional equivalence: obfuscated output matches original.
     */
    public function testFunctionalEquivalence(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * With encode_strings enabled on top of WP config, output still works.
     */
    public function testWithStringEncodingEnabled(): void
    {
        $fixture = __DIR__ . '/../Fixtures/wordpress_like.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code, ['encode_strings' => true]);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * Verify that external WP-like function calls (\time(), etc.) are preserved
     * even when variables share the same name.
     */
    public function testExternalFunctionCallsNotScrambled(): void
    {
        $code = <<<'PHP'
<?php
namespace MyPlugin;

$time = \time();
echo $time > 0 ? "ok" : "fail";
echo "\n";
PHP;

        $obfuscated = $this->obfuscate($code);

        // Variable $time should be scrambled
        $this->assertStringNotContainsString('$time', $obfuscated);

        // \time() function call should NOT be scrambled
        $this->assertMatchesRegularExpression('/\\\\?time\s*\(/', $obfuscated);

        // Functional check
        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }
}
