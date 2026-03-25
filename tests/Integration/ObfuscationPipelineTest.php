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

final class ObfuscationPipelineTest extends TestCase
{
    private function captureOutput(callable $callable): string
    {
        ob_start();
        $callable();
        $output = ob_get_clean();
        return is_string($output) ? $output : '';
    }

    public function testFullPipelineWithSimpleFixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/simple_function.php';
        $code = file_get_contents($fixturePath);
        $this->assertIsString($code);

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());
        $pipeline->addTransformer(new StringEncoder());
        $pipeline->addTransformer(new ControlFlowFlattener());
        $pipeline->addTransformer(new StatementShuffler());

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        $obfuscated = $obfuscator->obfuscate($code, $context);

        // Functional verification
        $originalOutput = $this->captureOutput(function () use ($fixturePath) {
            require $fixturePath;
        });

        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    public function testFullPipelineWithComplexFixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/complex_application.php';
        $code = file_get_contents($fixturePath);
        $this->assertIsString($code);

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new CommentStripper());
        $pipeline->addTransformer(new IdentifierScrambler());
        $pipeline->addTransformer(new StringEncoder());
        $pipeline->addTransformer(new ControlFlowFlattener());
        $pipeline->addTransformer(new StatementShuffler());

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        $obfuscated = $obfuscator->obfuscate($code, $context);

        // Functional verification
        $originalOutput = $this->captureOutput(function () use ($fixturePath) {
            require $fixturePath;
        });

        $obfuscatedOutput = $this->runCodeInSubprocess($obfuscated);
        $this->assertSame(trim($originalOutput), trim($obfuscatedOutput));
    }

    private function runCodeInSubprocess(string $code): string
    {
        $decoder = <<<'PHP'
if (!function_exists('_d')) {
    function _d($data, $key) {
        $decoded = base64_decode($data);
        $out = '';
        for ($i = 0; $i < strlen($decoded); $i++) {
            $out .= $decoded[$i] ^ $key[$i % strlen($key)];
        }
        return $out;
    }
}
PHP;
        $tempFile = tempnam(sys_get_temp_dir(), 'obf');
        $this->assertIsString($tempFile);

        // Remove existing declare(strict_types=1) and add it at the very top
        $code = preg_replace('/declare\s*\(\s*strict_types\s*=\s*[01]\s*\)\s*;/', '', $code);
        $this->assertIsString($code);

        if (strpos($code, '<?php') === false) {
            $code = "<?php\ndeclare(strict_types=1);\n" . $code;
        } else {
            $code = str_replace('<?php', "<?php\ndeclare(strict_types=1);\n", $code);
        }

        // If there is a namespace, we MUST put the decoder after it, or use global namespace for decoder.
        // Actually, we can just put the decoder in the global namespace before anything else if we use { } for everything.
        // But the printer doesn't use { } for namespaces by default.

        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $code, $matches)) {
            $namespace = $matches[0];
            $code = str_replace($namespace, $namespace . "\n" . $decoder . "\n", $code);
        } else {
            // No namespace, just inject after <?php / declare
            $code = preg_replace('/declare\s*\(\s*strict_types\s*=\s*[01]\s*\)\s*;/', "$0\n" . $decoder . "\n", $code);
        }
        $this->assertIsString($code);

        file_put_contents($tempFile, $code);

        $output = [];
        exec("php " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
        unlink($tempFile);

        if ($returnCode !== 0) {
            $this->fail("Subprocess failed with code $returnCode:\n" . implode("\n", $output) . "\nCODE DUMP:\n" . $code);
        }

        return implode("\n", $output);
    }
}
