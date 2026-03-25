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

final class EncodingTest extends TestCase
{
    public function testHandlesUTF8WithBOM(): void
    {
        $bom = "\xEF\xBB\xBF";
        $code = $bom . '<?php echo "hello";';

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new IdentifierScrambler());

        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $obfuscated = $obfuscator->obfuscate($code, $context);

        $this->assertNotEmpty($obfuscated);
        $this->assertStringContainsString('hello', $obfuscated);
        // The BOM should probably be stripped or ignored by the parser, 
        // and the printer will emit a normal PHP file.
        $this->assertStringNotContainsString($bom, $obfuscated);
    }
}
