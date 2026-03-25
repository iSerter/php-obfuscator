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
}
