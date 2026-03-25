<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Obfuscator;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;
use PHPUnit\Framework\TestCase;

final class ObfuscationContextTest extends TestCase
{
    public function testSymbolMapping(): void
    {
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext(new Configuration(), $scrambler);

        $this->assertNull($context->getSymbol('myVar'));

        $context->setSymbol('myVar', 'scrambled_123');

        $this->assertSame('scrambled_123', $context->getSymbol('myVar'));
    }

    public function testGetSymbolMap(): void
    {
        $scrambler = $this->createMock(ScramblerInterface::class);
        $context = new ObfuscationContext(new Configuration(), $scrambler);
        $context->setSymbol('a', 'b');
        $context->setSymbol('c', 'd');

        $this->assertSame(['a' => 'b', 'c' => 'd'], $context->getSymbolMap());
    }
}
