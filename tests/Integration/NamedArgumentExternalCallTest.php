<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Integration;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\CommentStripper;
use ISerter\PhpObfuscator\Transformer\ControlFlowFlattener;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\StatementShuffler;
use ISerter\PhpObfuscator\Transformer\StringEncoder;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for named-argument scrambling against NON-obfuscated callees.
 *
 * A named argument's label refers to the *callee's* parameter name. When the
 * callee lives outside the obfuscation pass (a Composer vendor package, PHP
 * core, WordPress core), its parameters are NOT renamed, so the label must be
 * left untouched. The obfuscator previously scrambled any label whose name
 * happened to collide with a scrambled local variable/parameter anywhere in the
 * source, producing calls PHP cannot resolve:
 *
 *     PHP Fatal error: Unknown named parameter $_0x4cba0d1c
 *
 * This was invisible to the existing suite because every named-argument fixture
 * called code obfuscated in the SAME pass — there, the label and the parameter
 * are renamed consistently, so it "worked". These tests reproduce the real-world
 * split: an obfuscated caller invoking a separately-defined, NON-obfuscated
 * library, while ALSO calling its own (obfuscated) functions by name.
 *
 * @see \ISerter\PhpObfuscator\Transformer\IdentifierScrambler named-argument handling
 */
final class NamedArgumentExternalCallTest extends TestCase
{
    /**
     * The "vendor library" — defined separately and NEVER obfuscated, exactly
     * like a package under vendor/. Its parameter names must survive verbatim so
     * that named-argument calls from the obfuscated caller still resolve.
     */
    private const VENDOR_LIB = <<<'PHP'
<?php
final class ExternalConfig
{
    public function __construct(
        public int $timeout = 30,
        public string $mode = 'default'
    ) {}
}

final class ExternalClient
{
    public function __construct(
        public string $key,
        public ?ExternalConfig $config = null
    ) {}

    public function describe(): string
    {
        return "key={$this->key}"
            . " timeout=" . ($this->config?->timeout ?? 0)
            . " mode=" . ($this->config?->mode ?? 'none');
    }
}

function external_greet(string $name, string $greeting = 'Hello'): string
{
    return "$greeting, $name!";
}
PHP;

    /**
     * The caller — this IS obfuscated. It deliberately declares local variables
     * ($timeout, $config, $mode, $key) whose names collide with the vendor's
     * parameter labels, which is what seeded the symbol map and triggered the
     * original bug. It also calls its OWN function (internal_join) by name to
     * prove that legitimate internal named-argument scrambling still works.
     */
    private const CALLER = <<<'PHP'
<?php
require __DIR__ . '/vendor_lib.php';

function internal_join(string $left, string $right, string $sep = '-'): string
{
    return $left . $sep . $right;
}

// Locals whose names collide with vendor named-argument labels. The buggy
// obfuscator registered these in the global symbol map and then rewrote the
// matching labels below, breaking the calls into the non-obfuscated vendor.
$timeout = 99;
$config  = 'localvalue';
$mode    = 'localmode';
$key     = 'localkey';

$client = new ExternalClient(key: 'SECRET', config: new ExternalConfig(timeout: 120, mode: 'fast'));
echo $client->describe() . "\n";
echo external_greet(name: 'World', greeting: 'Hi') . "\n";

// Named call into our OWN (obfuscated) function — label MUST track the renamed param.
echo internal_join(left: 'a', right: 'b', sep: '+') . "\n";

// Touch the locals so nothing is optimised away.
echo "$timeout|$config|$mode|$key\n";
PHP;

    /**
     * Obfuscate a single source string. Defaults mirror a realistic plugin build
     * (variables scrambled; functions/classes/methods left for autoloading), but
     * any flag can be overridden per test.
     *
     * @param array<string, bool|string|int> $configOverrides
     */
    private function obfuscate(string $code, array $configOverrides = []): string
    {
        $defaults = [
            'scramble_identifiers' => true,
            'scramble_variables'   => true,
            'scramble_functions'   => false,
            'scramble_classes'     => false,
            'scramble_constants'   => false,
            'scramble_methods'     => false,
            'scramble_properties'  => false,
            'scramble_namespaces'  => false,
            'encode_strings'       => false,
            'flatten_control_flow' => false,
            'shuffle_statements'   => false,
            'strip_comments'       => true,
        ];

        $config = Configuration::fromArray(array_merge($defaults, $configOverrides));
        $context = new ObfuscationContext($config, new NumericScrambler());

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());
        if ($config->encodeStrings) {
            $pipeline->addTransformer(new StringEncoder());
        }
        if ($config->flattenControlFlow) {
            $pipeline->addTransformer(new ControlFlowFlattener());
        }
        if ($config->shuffleStatements) {
            $pipeline->addTransformer(new StatementShuffler());
        }

        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());

        return $obfuscator->obfuscate($code, $context);
    }

    private function runFile(string $file): string
    {
        $output = [];
        exec('php ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);
        $text = implode("\n", $output);

        $this->assertSame(0, $returnCode, "Subprocess failed:\n$text");

        return trim($text);
    }

    // ─────────────────────────────────────────────────────────────────
    // Functional: obfuscated caller + non-obfuscated vendor, run for real
    // ─────────────────────────────────────────────────────────────────

    /**
     * The headline regression: named arguments into a non-obfuscated library
     * must keep resolving after obfuscation, and the program output must be
     * byte-for-byte identical to the un-obfuscated run.
     */
    public function testNamedArgsIntoNonObfuscatedVendorStillResolve(): void
    {
        $dir = sys_get_temp_dir() . '/obf-namedargs-' . bin2hex(random_bytes(6));
        mkdir($dir);

        try {
            // Vendor library is copied verbatim — never obfuscated.
            file_put_contents($dir . '/vendor_lib.php', self::VENDOR_LIB);

            // Baseline: original caller against the vendor.
            file_put_contents($dir . '/caller_plain.php', self::CALLER);
            $expected = $this->runFile($dir . '/caller_plain.php');

            // Obfuscated caller against the SAME (non-obfuscated) vendor.
            $obfuscated = $this->obfuscate(self::CALLER);
            file_put_contents($dir . '/caller_obf.php', $obfuscated);
            $actual = $this->runFile($dir . '/caller_obf.php');

            $this->assertSame(
                $expected,
                $actual,
                "Obfuscated output diverged from the original.\n\nObfuscated caller:\n$obfuscated"
            );

            // Sanity-check the baseline itself is what we expect.
            $this->assertStringContainsString('key=SECRET timeout=120 mode=fast', $expected);
            $this->assertStringContainsString('Hi, World!', $expected);
            $this->assertStringContainsString('a+b', $expected);
        } finally {
            @unlink($dir . '/vendor_lib.php');
            @unlink($dir . '/caller_plain.php');
            @unlink($dir . '/caller_obf.php');
            @rmdir($dir);
        }
    }

    /**
     * Same scenario with the heavy transformers (string encoding, control-flow
     * flattening, statement shuffling) enabled — the configuration a real
     * release build uses.
     */
    public function testNamedArgsResolveUnderFullObfuscation(): void
    {
        $dir = sys_get_temp_dir() . '/obf-namedargs-full-' . bin2hex(random_bytes(6));
        mkdir($dir);

        try {
            file_put_contents($dir . '/vendor_lib.php', self::VENDOR_LIB);
            file_put_contents($dir . '/caller_plain.php', self::CALLER);
            $expected = $this->runFile($dir . '/caller_plain.php');

            $obfuscated = $this->obfuscate(self::CALLER, [
                'encode_strings'       => true,
                'flatten_control_flow' => true,
                'shuffle_statements'   => true,
            ]);
            file_put_contents($dir . '/caller_obf.php', $obfuscated);
            $actual = $this->runFile($dir . '/caller_obf.php');

            $this->assertSame($expected, $actual, "Obfuscated output diverged.\n\n$obfuscated");
        } finally {
            @unlink($dir . '/vendor_lib.php');
            @unlink($dir . '/caller_plain.php');
            @unlink($dir . '/caller_obf.php');
            @rmdir($dir);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Static: inspect the emitted source directly
    // ─────────────────────────────────────────────────────────────────

    /**
     * Labels that target a non-obfuscated callee must appear verbatim, even when
     * an identically named local variable was scrambled.
     */
    public function testExternalNamedArgLabelsArePreserved(): void
    {
        $obfuscated = $this->obfuscate(self::CALLER);

        foreach (['key:', 'config:', 'timeout:', 'mode:', 'name:', 'greeting:'] as $label) {
            $this->assertStringContainsString(
                $label,
                $obfuscated,
                "External named-argument label '$label' must be preserved verbatim."
            );
        }
    }

    /**
     * The colliding locals must still be scrambled as variables — the fix only
     * spares the named-argument *labels*, not the variables themselves.
     */
    public function testCollidingLocalVariablesAreStillScrambled(): void
    {
        $obfuscated = $this->obfuscate(self::CALLER);

        foreach (['$timeout', '$config', '$mode', '$key'] as $var) {
            $this->assertStringNotContainsString(
                $var . ' ',
                $obfuscated,
                "Local variable '$var' should have been scrambled."
            );
        }
    }

    /**
     * Named arguments aimed at the caller's OWN obfuscated function must be
     * scrambled (the original, valid behaviour) so they keep matching the renamed
     * parameters. The literal labels must NOT survive.
     */
    public function testInternalNamedArgLabelsAreScrambled(): void
    {
        $obfuscated = $this->obfuscate(self::CALLER);

        foreach (['left:', 'right:', 'sep:'] as $label) {
            $this->assertStringNotContainsString(
                $label,
                $obfuscated,
                "Internal named-argument label '$label' should have been scrambled."
            );
        }
    }
}
