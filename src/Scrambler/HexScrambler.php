<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Scrambler;

final class HexScrambler implements ScramblerInterface
{
    private int $counter = 0;

    public function __construct(
        private readonly int $minLength = 6
    ) {
    }

    public function scramble(string $original): string
    {
        $hash = md5($original . $this->counter++);
        $name = '_0x' . substr($hash, 0, max($this->minLength, 8));
        return $name;
    }
}
