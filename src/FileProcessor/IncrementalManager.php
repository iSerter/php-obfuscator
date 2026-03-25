<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\FileProcessor;

use RuntimeException;

final class IncrementalManager
{
    private const MANIFEST_FILE = '.obfuscator-manifest.json';

    /** @var array{timestamps: array<string, int>, symbols: array<string, string>} */
    private array $manifest = [
        'timestamps' => [],
        'symbols' => []
    ];

    public function __construct(
        private readonly string $outputDir
    ) {
        $this->loadManifest();
    }

    public function shouldProcess(string $filePath, string $inputDir): bool
    {
        $relativePath = $this->getRelativePath($filePath, $inputDir);
        if (!isset($this->manifest['timestamps'][$relativePath])) {
            return true;
        }

        $mtime = filemtime($filePath);
        if ($mtime === false) {
            return true;
        }

        return $mtime > $this->manifest['timestamps'][$relativePath];
    }

    public function updateTimestamp(string $filePath, string $inputDir): void
    {
        $relativePath = $this->getRelativePath($filePath, $inputDir);
        $mtime = filemtime($filePath);
        if ($mtime !== false) {
            $this->manifest['timestamps'][$relativePath] = $mtime;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getSymbols(): array
    {
        return $this->manifest['symbols'];
    }

    /**
     * @param array<string, string> $symbols
     */
    public function setSymbols(array $symbols): void
    {
        $this->manifest['symbols'] = $symbols;
    }

    public function saveManifest(): void
    {
        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0777, true) && !is_dir($this->outputDir)) {
                throw new RuntimeException("Could not create output directory: " . $this->outputDir);
            }
        }

        $manifestPath = $this->outputDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        file_put_contents($manifestPath, json_encode($this->manifest, JSON_PRETTY_PRINT));
    }

    public function clearManifest(): void
    {
        $this->manifest = [
            'timestamps' => [],
            'symbols' => []
        ];
        $manifestPath = $this->outputDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        if (file_exists($manifestPath)) {
            unlink($manifestPath);
        }
    }

    private function loadManifest(): void
    {
        $manifestPath = $this->outputDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        if (file_exists($manifestPath)) {
            $content = file_get_contents($manifestPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    /** @var array<string, int> $timestamps */
                    $timestamps = $decoded['timestamps'] ?? [];
                    /** @var array<string, string> $symbols */
                    $symbols = $decoded['symbols'] ?? [];

                    $this->manifest = [
                        'timestamps' => $timestamps,
                        'symbols' => $symbols
                    ];
                }
            }
        }
    }

    private function getRelativePath(string $filePath, string $inputDir): string
    {
        $realFilePath = realpath($filePath);
        $realInputDir = realpath($inputDir);

        if ($realFilePath === false || $realInputDir === false) {
            return $filePath;
        }

        return str_replace($realInputDir . DIRECTORY_SEPARATOR, '', $realFilePath);
    }
}
