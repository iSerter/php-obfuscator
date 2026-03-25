<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Scrambler;

use ISerter\PhpObfuscator\Scrambler\HexScrambler;
use PHPUnit\Framework\TestCase;

final class HexScramblerTest extends TestCase
{
    public function testGeneratesValidIdentifier(): void
    {
        $scrambler = new HexScrambler();
        $name = $scrambler->scramble('test');

        $this->assertStringStartsWith('_0x', $name);
        $this->assertMatchesRegularExpression('/^_[0-9a-zA-Z_]+$/', $name);
    }

    public function testUniqueness(): void
    {
        $scrambler = new HexScrambler();
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = $scrambler->scramble('test' . $i);
        }

        $this->assertCount(100, array_unique($names));
    }
}
