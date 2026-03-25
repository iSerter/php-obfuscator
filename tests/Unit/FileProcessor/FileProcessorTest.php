<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\FileProcessor;

use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\FileProcessor\FileProcessingException;
use ISerter\PhpObfuscator\FileProcessor\FileProcessor;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\ObfuscatorInterface;
use ISerter\PhpObfuscator\Scrambler\ScramblerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileProcessorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-obfuscator-test-' . uniqid();
        if (!mkdir($this->tempDir, 0777, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException("Could not create temp directory: " . $this->tempDir);
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

    public function testObfuscateFileWritesOutput(): void
    {
        $inputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'input.php';
        $outputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output.php';
        $code = '<?php echo "hello";';
        $obfuscatedCode = '<?php echo "obfuscated";';

        file_put_contents($inputPath, $code);

        $obfuscator = $this->createMock(ObfuscatorInterface::class);
        $context = new ObfuscationContext(new Configuration(), $this->createMock(ScramblerInterface::class));

        $obfuscator->expects($this->once())
            ->method('obfuscate')
            ->with($code, $context)
            ->willReturn($obfuscatedCode);

        $processor = new FileProcessor($obfuscator);
        $processor->obfuscateFile($inputPath, $outputPath, $context);

        $this->assertFileExists($outputPath);
        $this->assertEquals($obfuscatedCode, file_get_contents($outputPath));
    }

    public function testObfuscateFileThrowsExceptionOnInvalidPhp(): void
    {
        $inputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'input.php';
        $outputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output.php';
        $code = '<?php invalid php';

        file_put_contents($inputPath, $code);

        $obfuscator = $this->createMock(ObfuscatorInterface::class);
        $context = new ObfuscationContext(new Configuration(), $this->createMock(ScramblerInterface::class));

        $obfuscator->expects($this->once())
            ->method('obfuscate')
            ->willThrowException(new RuntimeException('Parse error'));

        $processor = new FileProcessor($obfuscator);

        $this->expectException(FileProcessingException::class);
        $this->expectExceptionMessage('Parse error');

        $processor->obfuscateFile($inputPath, $outputPath, $context);
    }

    public function testCopyFile(): void
    {
        $inputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'input.txt';
        $outputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'output.txt';
        $content = 'just some text';

        file_put_contents($inputPath, $content);

        $obfuscator = $this->createMock(ObfuscatorInterface::class);
        $processor = new FileProcessor($obfuscator);
        $processor->copyFile($inputPath, $outputPath);

        $this->assertFileExists($outputPath);
        $this->assertEquals($content, file_get_contents($outputPath));
    }

    public function testObfuscateFileCreatesDirectoryIfMissing(): void
    {
        $inputPath = $this->tempDir . DIRECTORY_SEPARATOR . 'input.php';
        $outputDir = $this->tempDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deeply';
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . 'output.php';
        $code = '<?php echo "hello";';
        $obfuscatedCode = '<?php echo "obfuscated";';

        file_put_contents($inputPath, $code);

        $obfuscator = $this->createMock(ObfuscatorInterface::class);
        $context = new ObfuscationContext(new Configuration(), $this->createMock(ScramblerInterface::class));

        $obfuscator->expects($this->once())
            ->method('obfuscate')
            ->willReturn($obfuscatedCode);

        $processor = new FileProcessor($obfuscator);
        $processor->obfuscateFile($inputPath, $outputPath, $context);

        $this->assertDirectoryExists($outputDir);
        $this->assertFileExists($outputPath);
    }
}
