<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Obfuscator;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

final class ObfuscatorTest extends TestCase
{
    public function testRoundTripUnchanged(): void
    {
        $parserFactory = new ParserFactory();
        $pipeline = new TransformerPipeline(); // Empty pipeline
        $printer = new ObfuscatedPrinter();
        $config = new Configuration(userComment: ''); // Ensure no user comment added
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext($config, $scrambler);

        $obfuscator = new Obfuscator($parserFactory, $pipeline, $printer);

        $code = "<?php\n\necho 'hello world';";
        $result = $obfuscator->obfuscate($code, $context);

        // Pretty printer might change formatting slightly, but functionally same
        $this->assertStringContainsString("echo 'hello world';", $result);
    }

    public function testAddsUserComment(): void
    {
        $parserFactory = new ParserFactory();
        $pipeline = new TransformerPipeline();
        $printer = new ObfuscatedPrinter();
        $config = new Configuration(userComment: 'My Custom Copyright');
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext($config, $scrambler);

        $obfuscator = new Obfuscator($parserFactory, $pipeline, $printer);

        $code = "<?php echo 'hi';";
        $result = $obfuscator->obfuscate($code, $context);

        $this->assertStringContainsString('My Custom Copyright', $result);
        $this->assertStringContainsString("echo 'hi';", $result);
    }
}
