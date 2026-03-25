<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\ControlFlowFlattener;
use PHPUnit\Framework\TestCase;

final class ControlFlowFlattenerTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testFlattensFunctionBody(): void
    {
        $code = <<<'PHP'
<?php
if (!function_exists('test')) {
    function test() {
        echo "one";
        echo "two";
        echo "three";
    }
}
PHP;
        $parserFactory = new ParserFactory();
        $config = new Configuration(flattenControlFlow: true);
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);
        $parser = $parserFactory->createParser($config);
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);

        $transformer = new ControlFlowFlattener();
        $transformed = $transformer->transform($stmts, $context);

        $printer = new ObfuscatedPrinter();
        $output = $printer->prettyPrintFile($transformed);

        $this->assertStringContainsString('while', $output);
        $this->assertStringContainsString('switch', $output);
        $this->assertStringContainsString('case 1000', $output);
        $this->assertStringContainsString('case 1010', $output);
        $this->assertStringContainsString('case 1020', $output);

        // Functional verification
        ob_start();
        $cleanCode = str_replace('<?php', '', $output);
        eval($cleanCode);
        test();
        $result = ob_get_clean();

        $this->assertSame('onetwothree', $result);
    }
}
