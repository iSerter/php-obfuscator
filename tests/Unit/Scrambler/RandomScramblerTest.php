<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Scrambler;

use ISerter\PhpObfuscator\Scrambler\RandomScrambler;
use PHPUnit\Framework\TestCase;

final class RandomScramblerTest extends TestCase
{
    public function testGeneratesValidIdentifier(): void
    {
        $scrambler = new RandomScrambler();
        $name = $scrambler->scramble('test');

        $this->assertMatchesRegularExpression('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name);
        $this->assertGreaterThanOrEqual(8, strlen($name));
    }

    public function testUniqueness(): void
    {
        $scrambler = new RandomScrambler();
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = $scrambler->scramble('test' . $i);
        }

        $this->assertCount(100, array_unique($names));
    }

    public function testCollisionResistanceWithContext(): void
    {
        $scrambler = new RandomScrambler(6);
        $config = new \ISerter\PhpObfuscator\Config\Configuration();
        $context = new \ISerter\PhpObfuscator\Obfuscator\ObfuscationContext($config, $scrambler);

        $count = 10000;
        for ($i = 0; $i < $count; $i++) {
            $context->generateUniqueSymbol('sym' . $i);
        }

        $this->assertCount($count, $context->getSymbolMap());
        // Verify reverse map also has same count
        $this->assertCount($count, array_unique($context->getSymbolMap()));
    }
}
