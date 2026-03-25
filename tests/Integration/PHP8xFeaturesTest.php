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

final class PHP8xFeaturesTest extends TestCase
{
    public function testObfuscatesPHP8xFeatures(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/php8x_features.php';
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
        $this->assertStringNotContainsString('MyEnum', $obfuscated);
        $this->assertStringNotContainsString('MyInterface', $obfuscated);
        $this->assertStringNotContainsString('MyTrait', $obfuscated);
        $this->assertStringNotContainsString('MyAttribute', $obfuscated);
        $this->assertStringNotContainsString('promotedProp', $obfuscated);
        $this->assertStringNotContainsString('staticProp', $obfuscated);
        $this->assertStringNotContainsString('MY_CONST', $obfuscated);
        $this->assertStringNotContainsString('CaseA', $obfuscated);
        $this->assertStringNotContainsString('CaseB', $obfuscated);
        $this->assertStringNotContainsString('doSomething', $obfuscated);
        $this->assertStringNotContainsString('traitMethod', $obfuscated);
        $this->assertStringNotContainsString('testEnums', $obfuscated);

        // Verify that the obfuscated code is still valid PHP
        $parser = (new ParserFactory())->createParser($config);
        $stmts = $parser->parse($obfuscated);
        $this->assertNotNull($stmts);
    }
}
