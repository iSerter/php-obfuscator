<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Scrambler;

final class NumericScrambler implements ScramblerInterface
{
    private int $counter = 1000;

    public function __construct()
    {
    }

    public function scramble(string $original): string
    {
        return '_v' . ($this->counter++);
    }
}
