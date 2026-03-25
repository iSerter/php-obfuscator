<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Printer;

use PhpParser\PrettyPrinter\Standard;

final class ObfuscatedPrinter extends Standard
{
    public function __construct(array $options = [])
    {
        // Default options for obfuscation: no indentation if requested
        parent::__construct($options);
    }
}
