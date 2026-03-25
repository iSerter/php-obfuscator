<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Integration;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

final class PHP84_85FeaturesTest extends TestCase
{
    public function testObfuscatesPHP84_85Features(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/php84_85_features.php';
        $code = file_get_contents($fixturePath);
        $this->assertNotFalse($code);

        $config = new Configuration(
            flattenControlFlow: false,
            shuffleStatements: false,
            encodeStrings: false
        );

        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new IdentifierScrambler());

        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $obfuscated = $obfuscator->obfuscate($code, $context);

        $this->assertNotEmpty($obfuscated);
        $this->assertStringNotContainsString('MyClass', $obfuscated);
        $this->assertStringNotContainsString('wrap', $obfuscated);
        $this->assertStringNotContainsString('testFiber', $obfuscated);
        $this->assertStringNotContainsString('process', $obfuscated);

        // Verify that the obfuscated code is still valid PHP
        $parser = (new ParserFactory())->createParser($config);
        $stmts = $parser->parse($obfuscated);
        $this->assertNotNull($stmts);
    }
}
