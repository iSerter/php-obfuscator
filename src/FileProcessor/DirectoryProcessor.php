<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\FileProcessor;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use RuntimeException;
use Symfony\Component\Finder\Finder;

final class DirectoryProcessor
{
    public function __construct(
        private readonly FileProcessor $fileProcessor,
        private readonly ParserFactory $parserFactory,
        private readonly SymbolCollector $symbolCollector
    ) {
    }

    public function process(
        string $inputDir,
        string $outputDir,
        ObfuscationContext $context,
        bool $force = false,
        bool $clean = false,
        ?callable $onProgress = null
    ): ProcessingSummary {
        $summary = new ProcessingSummary();
        $inputDir = realpath($inputDir);
        if ($inputDir === false) {
            throw new RuntimeException("Input directory not found: $inputDir");
        }

        if ($clean && is_dir($outputDir)) {
            $this->removeDirectory($outputDir);
        }

        $incrementalManager = new IncrementalManager($outputDir);
        if ($clean) {
            $incrementalManager->clearManifest();
        }

        if (!$force) {
            $context->setSymbolMap($incrementalManager->getSymbols());
        }

        $finder = new Finder();
        $finder->files()->in($inputDir);
        $files = iterator_to_array($finder);
        $totalFiles = count($files);

        // Pass 1: Symbol Collection
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                if ($force || $incrementalManager->shouldProcess($file->getRealPath(), $inputDir)) {
                    $context->currentFilePath = $file->getRelativePathname();
                    $this->collectSymbols($file->getRealPath(), $context);
                }
            }
            if ($onProgress) {
                $onProgress($totalFiles, 'Collecting symbols...');
            }
        }

        // Pass 2: Obfuscation & Copying
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $outputPath = $outputDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($file->getExtension() === 'php') {
                if ($force || $incrementalManager->shouldProcess($file->getRealPath(), $inputDir)) {
                    $context->currentFilePath = $file->getRelativePathname();
                    try {
                        $this->fileProcessor->obfuscateFile($file->getRealPath(), $outputPath, $context);
                        $incrementalManager->updateTimestamp($file->getRealPath(), $inputDir);
                        $summary->incrementProcessed();
                    } catch (FileProcessingException $e) {
                        $summary->addError($file->getRelativePathname(), $e->getMessage());
                    } catch (\Exception $e) {
                        $summary->addError($file->getRelativePathname(), $e->getMessage());
                    }
                } else {
                    $summary->incrementSkipped();
                }
            } else {
                if ($force || $incrementalManager->shouldProcess($file->getRealPath(), $inputDir)) {
                    try {
                        $this->fileProcessor->copyFile($file->getRealPath(), $outputPath);
                        $incrementalManager->updateTimestamp($file->getRealPath(), $inputDir);
                        $summary->incrementProcessed();
                    } catch (\Exception $e) {
                        $summary->addError($file->getRelativePathname(), $e->getMessage());
                    }
                } else {
                    $summary->incrementSkipped();
                }
            }
            if ($onProgress) {
                $onProgress($totalFiles, 'Processing files...');
            }
        }

        $incrementalManager->setSymbols($context->getSymbolMap());
        $incrementalManager->saveManifest();

        return $summary;
    }

    private function collectSymbols(string $filePath, ObfuscationContext $context): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        try {
            $parser = $this->parserFactory->createParser($context->config);
            $stmts = $parser->parse($code);
            if ($stmts === null) {
                return;
            }

            $this->symbolCollector->transform($stmts, $context);
        } catch (\Exception $e) {
            // Skip
        }
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
}
