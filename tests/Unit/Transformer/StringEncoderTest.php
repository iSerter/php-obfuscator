<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\StringEncoder;
use PHPUnit\Framework\TestCase;

final class StringEncoderTest extends TestCase
{
    public function testEncodesStrings(): void
    {
        $code = <<<'PHP'
<?php
echo "secret message";
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $transformer = new StringEncoder();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringNotContainsString('secret message', $output);
        $this->assertStringContainsString('_d(', $output);
    }

    public function testDecodingWorks(): void
    {
        $code = <<<'PHP'
<?php
$val = "hello";
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $transformer = new StringEncoder();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        // Strip <?php
        $output = str_replace('<?php', '', $output);

        $val = '';
        $decoder = <<<'PHP'
if (!function_exists('_d')) {
    function _d($data, $key) {
        $decoded = base64_decode($data);
        $out = '';
        for ($i = 0; $i < strlen($decoded); $i++) {
            $out .= $decoded[$i] ^ $key[$i % strlen($key)];
        }
        return $out;
    }
}
PHP;
        eval($decoder . $output);

        $this->assertSame('hello', $val);
    }
}
