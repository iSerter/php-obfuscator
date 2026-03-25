<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\FileProcessor;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\FileProcessor\DirectoryProcessor;
use ISerter\PhpObfuscator\FileProcessor\FileProcessor;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\NumericScrambler;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DirectoryProcessorTest extends TestCase
{
    private string $tempDir;
    private string $inputDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-obfuscator-dir-test-' . uniqid();
        $this->inputDir = $this->tempDir . DIRECTORY_SEPARATOR . 'input';
        $this->outputDir = $this->tempDir . DIRECTORY_SEPARATOR . 'output';

        if (!mkdir($this->inputDir, 0777, true) && !is_dir($this->inputDir)) {
            throw new RuntimeException("Could not create input directory: " . $this->inputDir);
        }
        if (!mkdir($this->outputDir, 0777, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException("Could not create output directory: " . $this->outputDir);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testDirectoryProcessingIsConsistent(): void
    {
        $fileA = $this->inputDir . DIRECTORY_SEPARATOR . 'A.php';
        $fileB = $this->inputDir . DIRECTORY_SEPARATOR . 'B.php';
        $fileC = $this->inputDir . DIRECTORY_SEPARATOR . 'C.txt';

        file_put_contents($fileA, '<?php class Foo {}');
        file_put_contents($fileB, '<?php $foo = new Foo();');
        file_put_contents($fileC, 'just text');

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new IdentifierScrambler());

        $obfuscator = new Obfuscator(
            new ParserFactory(),
            $pipeline,
            new ObfuscatedPrinter()
        );

        $fileProcessor = new FileProcessor($obfuscator);
        $parserFactory = new ParserFactory();
        $symbolCollector = new SymbolCollector();

        $directoryProcessor = new DirectoryProcessor($fileProcessor, $parserFactory, $symbolCollector);
        $directoryProcessor->process($this->inputDir, $this->outputDir, $context);

        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'A.php');
        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'B.php');
        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'C.txt');

        $obfuscatedA = file_get_contents($this->outputDir . DIRECTORY_SEPARATOR . 'A.php');
        $this->assertIsString($obfuscatedA);
        $obfuscatedB = file_get_contents($this->outputDir . DIRECTORY_SEPARATOR . 'B.php');
        $this->assertIsString($obfuscatedB);
        $plainC = file_get_contents($this->outputDir . DIRECTORY_SEPARATOR . 'C.txt');
        $this->assertIsString($plainC);

        $this->assertEquals('just text', $plainC);

        // Extract class name from A and see if it matches in B
        // NumericScrambler uses _v1000, _v1001 etc.
        preg_match('/class (_v[0-9]+)/', $obfuscatedA, $matchesA);
        if (!isset($matchesA[1])) {
            $this->fail("Could not find scrambled class in A.php. Content: " . $obfuscatedA);
        }
        $scrambledName = $matchesA[1];

        $this->assertStringContainsString($scrambledName, $obfuscatedB, "Scrambled name from A.php not found in B.php");
    }

    public function testDirectoryProcessingCreatesNestedDirectories(): void
    {
        $nestedDir = $this->inputDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deep';
        mkdir($nestedDir, 0777, true);
        $file = $nestedDir . DIRECTORY_SEPARATOR . 'test.php';
        file_put_contents($file, '<?php echo 1;');

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $fileProcessor = new FileProcessor($obfuscator);
        $directoryProcessor = new DirectoryProcessor($fileProcessor, new ParserFactory(), new SymbolCollector());

        $directoryProcessor->process($this->inputDir, $this->outputDir, $context);

        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'test.php');
    }

    public function testIncrementalProcessing(): void
    {
        $fileA = $this->inputDir . DIRECTORY_SEPARATOR . 'A.php';
        file_put_contents($fileA, '<?php class Foo {}');

        $config = new Configuration();
        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $fileProcessor = new FileProcessor($obfuscator);
        $directoryProcessor = new DirectoryProcessor($fileProcessor, new ParserFactory(), new SymbolCollector());

        // First run
        $directoryProcessor->process($this->inputDir, $this->outputDir, $context);
        $this->assertFileExists($this->outputDir . DIRECTORY_SEPARATOR . 'A.php');
        $mtime = filemtime($this->outputDir . DIRECTORY_SEPARATOR . 'A.php');

        // Second run - should skip
        usleep(1100000); // Wait 1.1s to ensure mtime would be different if re-written
        $directoryProcessor->process($this->inputDir, $this->outputDir, $context);
        $this->assertEquals($mtime, filemtime($this->outputDir . DIRECTORY_SEPARATOR . 'A.php'), 'File was re-written but should have been skipped');

        // Run with force - should not skip
        $directoryProcessor->process($this->inputDir, $this->outputDir, $context, force: true);
        $this->assertNotEquals($mtime, filemtime($this->outputDir . DIRECTORY_SEPARATOR . 'A.php'), 'File was skipped but should have been forced');
    }
}
