<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\FileProcessor;

use Exception;
use RuntimeException;

final class FileProcessingException extends RuntimeException
{
    public function __construct(
        private readonly string $filePath,
        string $message = "",
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
