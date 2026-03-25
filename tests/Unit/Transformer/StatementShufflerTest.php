<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\StatementShuffler;
use PHPUnit\Framework\TestCase;

final class StatementShufflerTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testShufflesFunctionBody(): void
    {
        $code = <<<'PHP'
<?php
if (!function_exists('test')) {
    function test() {
        $x = 1;
        $y = 2;
        $z = $x + $y;
        return $z;
    }
}
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration(shuffleStatements: true);
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $transformer = new StatementShuffler();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringContainsString('goto', $output);
        $this->assertStringContainsString(':', $output); // labels

        // Functional verification
        ob_start();
        $cleanCode = str_replace('<?php', '', $output);
        eval($cleanCode);
        $result = test();
        ob_get_clean();

        $this->assertSame(3, $result);
    }
}
