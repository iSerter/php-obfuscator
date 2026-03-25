<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Obfuscator;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;

/**
 * Shared state across transformers and across files.
 */
final class ObfuscationContext
{
    /** @var array<string, string> */
    private array $symbolMap = [];

    public ?string $currentFilePath = null;

    public function __construct(
        public readonly Configuration $config,
        public readonly ScramblerInterface $scrambler,
        public readonly WarningRegistry $warningRegistry = new WarningRegistry()
    ) {
    }

    public function getSymbol(string $original): ?string
    {
        return $this->symbolMap[$original] ?? null;
    }

    public function setSymbol(string $original, string $scrambled): void
    {
        $this->symbolMap[$original] = $scrambled;
    }

    /**
     * @return array<string, string>
     */
    public function getSymbolMap(): array
    {
        return $this->symbolMap;
    }

    /**
     * @param array<string, string> $symbolMap
     */
    public function setSymbolMap(array $symbolMap): void
    {
        $this->symbolMap = $symbolMap;
    }
}
