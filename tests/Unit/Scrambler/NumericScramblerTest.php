<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Scrambler;

use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use PHPUnit\Framework\TestCase;

final class NumericScramblerTest extends TestCase
{
    public function testGeneratesValidIdentifier(): void
    {
        $scrambler = new NumericScrambler();
        $name = $scrambler->scramble('test');

        $this->assertStringStartsWith('_v', $name);
        $this->assertMatchesRegularExpression('/^_[0-9a-zA-Z_]+$/', $name);
    }

    public function testUniqueness(): void
    {
        $scrambler = new NumericScrambler();
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = $scrambler->scramble('test' . $i);
        }

        $this->assertCount(100, array_unique($names));
    }
}
