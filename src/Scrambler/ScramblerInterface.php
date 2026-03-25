<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Scrambler;

interface ScramblerInterface
{
    public function scramble(string $original): string;
}
