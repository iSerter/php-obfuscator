<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\FileProcessor;

final class ProcessingSummary
{
    /** @var string[] */
    private array $errors = [];
    private int $processedCount = 0;
    private int $skippedCount = 0;

    public function addError(string $filePath, string $message): void
    {
        $this->errors[] = sprintf('%s: %s', $filePath, $message);
    }

    public function incrementProcessed(): void
    {
        $this->processedCount++;
    }

    public function incrementSkipped(): void
    {
        $this->skippedCount++;
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
