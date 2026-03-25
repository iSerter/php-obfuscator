<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Obfuscator;

use InvalidArgumentException;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;

final class Obfuscator implements ObfuscatorInterface
{
    public function __construct(
        private readonly ParserFactory $parserFactory,
        private readonly TransformerPipeline $pipeline,
        private readonly ObfuscatedPrinter $printer
    ) {
    }

    public function obfuscate(string $code, ?ObfuscationContext $context = null): string
    {
        if ($context === null) {
            throw new InvalidArgumentException('Obfuscation context is required.');
        }

        // Strip UTF-8 BOM if present
        if (str_starts_with($code, "\xEF\xBB\xBF")) {
            $code = substr($code, 3);
        }

        $parser = $this->parserFactory->createParser($context->config);
        $stmts = $parser->parse($code);

        if ($stmts === null) {
            throw new InvalidArgumentException('Could not parse PHP code.');
        }

        $transformedStmts = $this->pipeline->apply($stmts, $context);

        $obfuscatedCode = $this->printer->prettyPrintFile($transformedStmts);

        if ($context->config->userComment !== '') {
            $comment = "/*\n" . $context->config->userComment . "\n*/\n";
            $obfuscatedCode = $comment . $obfuscatedCode;
        }

        return $obfuscatedCode;
    }
}
