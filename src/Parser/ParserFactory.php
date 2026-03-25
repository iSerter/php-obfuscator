<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Parser;

use ISerter\PhpObfuscator\Config\Configuration;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory as PhpParserFactory;

final class ParserFactory
{
    public function createParser(Configuration $config): Parser
    {
        $factory = new PhpParserFactory();

        // nikic/php-parser v5 handles versioning differently than v4.
        // It mostly uses the latest version by default.
        // For v5, create() doesn't take many arguments.
        // PHP 8.5 support is in latest 5.x.
        return $factory->createForNewestSupportedVersion();
    }

    public function createTraverser(): NodeTraverser
    {
        return new NodeTraverser();
    }
}
