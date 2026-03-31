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

final class ObfuscationPipelineTest extends TestCase
{
    private function captureOutput(callable $callable): string
    {
        ob_start();
        $callable();
        $output = ob_get_clean();
        return is_string($output) ? $output : '';
    }

    public function testFullPipelineWithSimpleFixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/simple_function.php';
        $code = file_get_contents($fixturePath);
        $this->assertIsString($code);

        $config = new Configuration();
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

        $obfuscated = $obfuscator->obfuscate($code, $context);

        // Functional verification
        $originalOutput = $this->captureOutput(function () use ($fixturePath) {
            require $fixturePath;
        });

        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    public function testFullPipelineWithComplexFixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/complex_application.php';
        $code = file_get_contents($fixturePath);
        $this->assertIsString($code);

        $config = new Configuration();
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

        $obfuscated = $obfuscator->obfuscate($code, $context);

        // Functional verification
        $originalOutput = $this->captureOutput(function () use ($fixturePath) {
            require $fixturePath;
        });

        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    private function runCodeInSubprocess(string $code): string
    {
        // The Obfuscator now auto-injects the _d() runtime, so no manual
        // decoder injection is needed. Just run the obfuscated code as-is.
        $tempFile = tempnam(sys_get_temp_dir(), 'obf');
        $this->assertIsString($tempFile);

        file_put_contents($tempFile, $code);

        $output = [];
        exec("php " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
        unlink($tempFile);

        if ($returnCode !== 0) {
            $this->fail("Subprocess failed with code $returnCode:\n" . implode("\n", $output) . "\nCODE DUMP:\n" . $code);
        }

        return implode("\n", $output);
    }
}
