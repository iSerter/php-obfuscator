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
            $obfuscatedCode = $this->insertCommentAfterOpeningStatements($obfuscatedCode, $comment);
        }

        // Inject the _d() string decoder when encode_strings is active.
        if ($context->config->encodeStrings) {
            $obfuscatedCode = $this->injectStringDecoderRuntime($obfuscatedCode);
        }

        return $obfuscatedCode;
    }

    /**
     * Inject the _d() string decoder function into the obfuscated output.
     *
     * The function is defined within the current namespace scope so that
     * unqualified _d() calls resolve correctly via PHP namespace resolution.
     * The function_exists() guard uses __NAMESPACE__ to build the FQN,
     * ensuring only one definition per namespace.
     *
     * Placement:
     *  - After the namespace declaration (if present) so _d() is in that namespace
     *  - After <?php / declare (if no namespace) so _d() is in the global namespace
     */
    private function injectStringDecoderRuntime(string $code): string
    {
        $runtime = "if (!function_exists((__NAMESPACE__ ? __NAMESPACE__ . '\\\\' : '') . '_d')) { function _d(string \$d, string \$k): string { \$b = base64_decode(\$d); \$o = ''; for (\$i = 0, \$l = strlen(\$b); \$i < \$l; \$i++) { \$o .= \$b[\$i] ^ \$k[\$i % strlen(\$k)]; } return \$o; } }";

        // If there is a namespace declaration, inject AFTER it so _d() lives in
        // that namespace and unqualified calls resolve correctly.
        if (preg_match('/^(.*?namespace\s+[a-zA-Z0-9_\\\\]+\s*;\s*\n?)/s', $code, $matches)) {
            $before = $matches[1];
            $after = substr($code, strlen($before));
            return $before . $runtime . "\n" . $after;
        }

        // No namespace — inject after <?php and optional declare.
        $pattern = '/^(<\?php\b[^\S\n]*\n?)(\s*declare\s*\(\s*strict_types\s*=\s*[^)]*\)\s*;\s*\n?)?/i';
        if (preg_match($pattern, $code, $matches)) {
            $preamble = $matches[0];
            $rest = substr($code, strlen($preamble));
            if ($preamble !== '' && !str_ends_with($preamble, "\n")) {
                $preamble .= "\n";
            }
            return $preamble . $runtime . "\n" . $rest;
        }

        return $runtime . "\n" . $code;
    }

    /**
     * Insert a comment block after <?php and after declare(strict_types=1) if present.
     *
     * This ensures the comment does not appear before the opening PHP tag
     * (which would make it plain text) and does not precede a declare
     * statement (which must be the very first statement in the file).
     */
    private function insertCommentAfterOpeningStatements(string $code, string $comment): string
    {
        // Match the opening <?php tag, optional whitespace, and an optional declare(strict_types=…) statement.
        // The declare pattern accounts for varied whitespace/formatting the printer may produce.
        $pattern = '/^(<\?php\b[^\S\n]*\n?)(\s*declare\s*\(\s*strict_types\s*=\s*[^)]*\)\s*;\s*\n?)?/i';

        if (preg_match($pattern, $code, $matches)) {
            $preamble = $matches[0]; // everything matched (<?php + optional declare)
            $rest = substr($code, strlen($preamble));

            // Ensure there is a newline separating the preamble from the comment
            if ($preamble !== '' && !str_ends_with($preamble, "\n")) {
                $preamble .= "\n";
            }

            return $preamble . $comment . $rest;
        }

        // Fallback: if the pattern didn't match (unexpected format), prepend as before.
        return $comment . $code;
    }
}
