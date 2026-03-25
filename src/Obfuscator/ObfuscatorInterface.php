<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Obfuscator;

interface ObfuscatorInterface
{
    public function obfuscate(string $code, ?ObfuscationContext $context = null): string;
}
