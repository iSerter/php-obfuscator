<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use PHPUnit\Framework\TestCase;

final class IdentifierScramblerTest extends TestCase
{
    public function testScramblesVariables(): void
    {
        $code = <<<'PHP'
<?php
$foo = 1;
echo $foo;
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $transformer = new IdentifierScrambler();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringNotContainsString('$foo', $output);
        $this->assertStringContainsString('$_v1000', $output);
    }

    public function testScramblesFunctions(): void
    {
        $code = <<<'PHP'
<?php
function myFunc() {
    return 1;
}
myFunc();
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        // SymbolCollector must run first to register function symbols
        $collector = new SymbolCollector();
        $stmts = $collector->transform($stmts, $context);

        $transformer = new IdentifierScrambler();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringNotContainsString('myFunc', $output);
        $this->assertStringContainsString('function _v1000', $output);
    }
}
