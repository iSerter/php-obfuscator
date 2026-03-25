<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\FileProcessor;

use Exception;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\ObfuscatorInterface;
use RuntimeException;

final class FileProcessor
{
    public function __construct(
        private readonly ObfuscatorInterface $obfuscator
    ) {
    }

    /**
     * @throws FileProcessingException
     * @throws RuntimeException
     */
    public function obfuscateFile(string $inputPath, string $outputPath, ObfuscationContext $context): void
    {
        if (!is_file($inputPath) || !is_readable($inputPath)) {
            throw new RuntimeException("Input file not found or not readable: $inputPath");
        }

        $code = file_get_contents($inputPath);
        if ($code === false) {
            throw new RuntimeException("Could not read input file: $inputPath");
        }

        try {
            $obfuscatedCode = $this->obfuscator->obfuscate($code, $context);
            $this->writeToOutput($outputPath, $obfuscatedCode);
        } catch (Exception $e) {
            throw new FileProcessingException($inputPath, $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public function copyFile(string $inputPath, string $outputPath): void
    {
        if (!is_file($inputPath) || !is_readable($inputPath)) {
            throw new RuntimeException("Input file not found or not readable: $inputPath");
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Could not create directory: $dir");
            }
        }

        if (!copy($inputPath, $outputPath)) {
            throw new RuntimeException("Could not copy file: $inputPath to $outputPath");
        }
    }

    private function writeToOutput(string $outputPath, string $content): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Could not create directory: $dir");
            }
        }

        if (file_put_contents($outputPath, $content) === false) {
            throw new RuntimeException("Could not write output file: $outputPath");
        }
    }
}
