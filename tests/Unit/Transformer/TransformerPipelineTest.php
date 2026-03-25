<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Transformer;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;
use ISerter\PhpObfuscator\Transformer\TransformerInterface;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

final class TransformerPipelineTest extends TestCase
{
    public function testApplyEmptyPipelineReturnsSameNodes(): void
    {
        $pipeline = new TransformerPipeline();
        $nodes = []; // Empty AST
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext(new Configuration(), $scrambler);

        $result = $pipeline->apply($nodes, $context);

        $this->assertSame($nodes, $result);
    }

    public function testApplySequentialTransformers(): void
    {
        $pipeline = new TransformerPipeline();

        $transformer1 = $this->createMock(TransformerInterface::class);
        $transformer2 = $this->createMock(TransformerInterface::class);

        $nodes1 = [$this->createMock(Node::class)];
        $nodes2 = [$this->createMock(Node::class)];
        $nodes3 = [$this->createMock(Node::class)];

        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext(new Configuration(), $scrambler);

        $transformer1->expects($this->once())
            ->method('transform')
            ->with($nodes1, $context)
            ->willReturn($nodes2);

        $transformer2->expects($this->once())
            ->method('transform')
            ->with($nodes2, $context)
            ->willReturn($nodes3);

        $pipeline->addTransformer($transformer1);
        $pipeline->addTransformer($transformer2);

        $result = $pipeline->apply($nodes1, $context);

        $this->assertSame($nodes3, $result);
    }
}
