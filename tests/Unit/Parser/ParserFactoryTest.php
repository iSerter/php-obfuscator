<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Parser;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserFactoryTest extends TestCase
{
    public function testCreateParser(): void
    {
        $factory = new ParserFactory();
        $config = new Configuration();
        $parser = $factory->createParser($config);

        $this->assertInstanceOf(Parser::class, $parser);
    }

    public function testCreateTraverser(): void
    {
        $factory = new ParserFactory();
        $traverser = $factory->createTraverser();

        $this->assertInstanceOf(NodeTraverser::class, $traverser);
    }

    public function testParsesSimpleFile(): void
    {
        $factory = new ParserFactory();
        $config = new Configuration();
        $parser = $factory->createParser($config);

        $code = '<?php echo "hello";';
        $stmts = $parser->parse($code);

        $this->assertNotNull($stmts);
        $this->assertCount(1, $stmts);
    }
}
