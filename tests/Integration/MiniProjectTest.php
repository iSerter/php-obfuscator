<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Integration;

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

final class MiniProjectTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mini-project-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testObfuscatesMiniProjectWithExternalLib(): void
    {
        $sourceDir = __DIR__ . '/../Fixtures/MiniProject';
        $outputDir = $this->tempDir . '/output';

        // We want to obfuscate everything EXCEPT vendor
        // For this test, we'll just run it on index.php and src/
        // Actually, we can just run it on the whole thing but we want to see if vendor remains untouched if we don't process it.
        // Wait, if we don't process it, it won't be in the output directory at all!
        
        $config = new Configuration(
            scrambleIdentifiers: true,
            flattenControlFlow: false,
            shuffleStatements: false,
            encodeStrings: false
        );

        $scrambler = new NumericScrambler();
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());
        $pipeline->addTransformer(new IdentifierScrambler());

        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $fileProcessor = new FileProcessor($obfuscator);
        
        // Manual two-pass simulation for the specific files we want to obfuscate
        $filesToObfuscate = [
            $sourceDir . '/index.php',
            $sourceDir . '/src/Services/ServiceInterface.php',
            $sourceDir . '/src/Services/DatabaseService.php',
            $sourceDir . '/src/Controllers/ControllerInterface.php',
            $sourceDir . '/src/Controllers/UserController.php',
        ];

        $symbolCollector = new SymbolCollector();
        $parser = (new ParserFactory())->createParser($config);

        foreach ($filesToObfuscate as $file) {
            $stmts = $parser->parse(file_get_contents($file));
            $symbolCollector->transform($stmts, $context);
        }

        foreach ($filesToObfuscate as $file) {
            $relative = str_replace($sourceDir . '/', '', $file);
            $outputPath = $outputDir . '/' . $relative;
            $fileProcessor->obfuscateFile($file, $outputPath, $context);
        }

        // Verify index.php
        $obfuscatedIndex = file_get_contents($outputDir . '/index.php');
        $this->assertStringNotContainsString('DatabaseService', $obfuscatedIndex);
        $this->assertStringNotContainsString('UserController', $obfuscatedIndex);
        
        // Logger and log should NOT be scrambled because they were never collected
        $this->assertStringContainsString('Logger', $obfuscatedIndex);
        $this->assertStringContainsString('log', $obfuscatedIndex);
        
        // Verify that execute() WAS scrambled (because it's in MiniProject)
        $this->assertStringNotContainsString('execute', $obfuscatedIndex);
    }
}
