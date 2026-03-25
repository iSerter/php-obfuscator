<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Scrambler;

use InvalidArgumentException;

final class ScramblerFactory
{
    public function createScrambler(string $mode, int $minLength = 6): ScramblerInterface
    {
        return match ($mode) {
            'random' => new RandomScrambler($minLength),
            'hex' => new HexScrambler($minLength),
            'numeric' => new NumericScrambler(),
            default => throw new InvalidArgumentException(sprintf('Unsupported scrambler mode: %s', $mode)),
        };
    }
}
