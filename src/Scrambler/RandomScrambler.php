<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Scrambler;

final class RandomScrambler implements ScramblerInterface
{
    private const CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const ALL_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    public function __construct(
        private readonly int $minLength = 6
    ) {
    }

    public function scramble(string $original): string
    {
        // For RandomScrambler, we don't necessarily need it to be deterministic based on $original
        // unless we want to avoid re-generating the same name for the same original across multiple calls
        // without a central map. But the ObfuscationContext handles the mapping.

        $length = max($this->minLength, 8);
        $name = self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        for ($i = 1; $i < $length; $i++) {
            $name .= self::ALL_CHARS[random_int(0, strlen(self::ALL_CHARS) - 1)];
        }

        return $name;
    }
}
