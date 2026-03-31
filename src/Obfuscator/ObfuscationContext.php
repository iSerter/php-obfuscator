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

    /** @var array<string, string> */
    private array $reverseMap = [];

    /** @var array<string, true> Symbols collected from function declarations */
    private array $functionSymbols = [];

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
        $this->reverseMap[$scrambled] = $original;
    }

    public function isScrambledUsed(string $scrambled): bool
    {
        return isset($this->reverseMap[$scrambled]);
    }

    public function generateUniqueSymbol(string $original): string
    {
        $symbol = $this->getSymbol($original);
        if ($symbol !== null) {
            return $symbol;
        }

        $attempts = 0;
        do {
            $scrambled = $this->scrambler->scramble($original);
            $attempts++;
            if ($attempts > 100) {
                // Highly unlikely with random scrambler, but possible with others if poorly designed
                throw new \RuntimeException("Could not generate a unique scrambled name for: $original");
            }
        } while ($this->isScrambledUsed($scrambled));

        $this->setSymbol($original, $scrambled);
        return $scrambled;
    }

    public function addFunctionSymbol(string $name): void
    {
        $this->functionSymbols[$name] = true;
    }

    public function isFunctionSymbol(string $name): bool
    {
        return isset($this->functionSymbols[$name]);
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
        $this->reverseMap = array_flip($symbolMap);
    }
}
