<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;
use ISerter\PhpObfuscator\Transformer\CommentStripper;
use PHPUnit\Framework\TestCase;

final class CommentStripperTest extends TestCase
{
    public function testStripsAllComments(): void
    {
        $code = <<<'PHP'
<?php
/**
 * This is a docblock
 */
function foo() {
    // Inline comment
    /* Block comment */
    echo "hi"; # Hash comment
}
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration(stripComments: true);
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $stripper = new CommentStripper();
        $transformed = $stripper->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringNotContainsString('docblock', $output);
        $this->assertStringNotContainsString('Inline comment', $output);
        $this->assertStringNotContainsString('Block comment', $output);
        $this->assertStringNotContainsString('Hash comment', $output);
        $this->assertStringContainsString('function foo()', $output);
    }

    public function testDoesNotStripWhenDisabled(): void
    {
        $code = <<<'PHP'
<?php
// Inline comment
echo "hi";
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration(stripComments: false);
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $stripper = new CommentStripper();
        $transformed = $stripper->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringContainsString('Inline comment', $output);
    }
}
