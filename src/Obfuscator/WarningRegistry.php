<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Obfuscator;

final class WarningRegistry
{
    /** @var array<string, string[]> */
    private array $warnings = [];

    public function addWarning(string $filePath, string $message): void
    {
        $this->warnings[$filePath][] = $message;
    }

    /** @return array<string, string[]> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}
