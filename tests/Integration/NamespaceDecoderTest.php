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
 * Tests _d() string decoder runtime injection in various contexts:
 * - After namespace declaration
 * - After <?php / declare in non-namespaced files
 * - Functional correctness via subprocess execution
 * - function_exists guard prevents redefinition
 */
final class NamespaceDecoderTest extends TestCase
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
            'encode_strings' => true,
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
        $pipeline->addTransformer(new StringEncoder());

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
     * Namespaced file: _d() must be injected AFTER the namespace declaration.
     */
    public function testDecoderInjectedAfterNamespace(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        // _d() function must be present
        $this->assertStringContainsString('function _d(', $obfuscated);

        // Must come AFTER namespace declaration
        $nsPos = strpos($obfuscated, 'namespace DecoderTest;');
        $dPos = strpos($obfuscated, 'function _d(');
        $this->assertNotFalse($nsPos);
        $this->assertNotFalse($dPos);
        $this->assertGreaterThan($nsPos, $dPos, '_d() must be injected after namespace declaration');

        // Must NOT appear before namespace
        $beforeNs = substr($obfuscated, 0, $nsPos);
        $this->assertStringNotContainsString('function _d(', $beforeNs);
    }

    /**
     * Non-namespaced file with declare: _d() must be injected after declare.
     */
    public function testDecoderInjectedAfterDeclare(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder_no_ns.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        // _d() function must be present
        $this->assertStringContainsString('function _d(', $obfuscated);

        // Must come after declare(strict_types=1) — printer may add space
        $declarePos = strpos($obfuscated, 'declare');
        $dPos = strpos($obfuscated, 'function _d(');
        $this->assertNotFalse($declarePos);
        $this->assertNotFalse($dPos);
        $this->assertGreaterThan($declarePos, $dPos, '_d() must be injected after declare');
    }

    /**
     * The function_exists guard must use __NAMESPACE__ to build the FQN.
     */
    public function testDecoderHasFunctionExistsGuard(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $this->assertStringContainsString('function_exists', $obfuscated);
        $this->assertStringContainsString('__NAMESPACE__', $obfuscated);
    }

    /**
     * Namespaced file: obfuscated output must be executable without manual decoder.
     */
    public function testNamespacedFileExecutesCorrectly(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * Non-namespaced file with declare + string encoding: executable.
     */
    public function testNonNamespacedFileExecutesCorrectly(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder_no_ns.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code);

        $originalOutput = $this->runCodeInSubprocess($code);
        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);

        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    /**
     * When encode_strings is false, _d() should NOT be injected.
     */
    public function testNoDecoderWhenEncodeStringsFalse(): void
    {
        $fixture = __DIR__ . '/../Fixtures/namespace_decoder.php';
        $code = file_get_contents($fixture);
        $this->assertIsString($code);

        $obfuscated = $this->obfuscate($code, ['encode_strings' => false]);

        $this->assertStringNotContainsString('function _d(', $obfuscated);
        $this->assertStringNotContainsString('_d(', $obfuscated);
    }

    /**
     * Multiple includes of _d() in same namespace: function_exists prevents redefinition.
     * Simulate by concatenating two obfuscated files in the same namespace.
     */
    public function testFunctionExistsGuardPreventsRedefinition(): void
    {
        $code1 = <<<'PHP'
<?php
namespace SharedNs;
echo "hello " . "world" . "\n";
PHP;

        $code2 = <<<'PHP'
<?php
namespace SharedNs;
echo "foo " . "bar" . "\n";
PHP;

        $obfuscated1 = $this->obfuscate($code1);
        $obfuscated2 = $this->obfuscate($code2);

        // Both should inject _d()
        $this->assertStringContainsString('function _d(', $obfuscated1);
        $this->assertStringContainsString('function _d(', $obfuscated2);

        // Combine: strip <?php from second file and wrap in same namespace
        $combined = $obfuscated1 . "\n" . preg_replace('/^<\?php\s*\n?/', '', $obfuscated2);

        // The combined code should execute without "Cannot redeclare" error
        $output = $this->runCodeInSubprocess($combined);
        $this->assertStringContainsString('hello world', $output);
    }
}
