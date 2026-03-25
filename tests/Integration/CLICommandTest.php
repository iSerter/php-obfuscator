<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Integration;

use ISerter\PhpObfuscator\CLI\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CLICommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-obfuscator-cli-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
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

    public function testObfuscateSingleFile(): void
    {
        $application = new Application();
        $command = $application->find('obfuscate');
        $commandTester = new CommandTester($command);

        $inputFile = $this->tempDir . '/input.php';
        $outputFile = $this->tempDir . '/output.php';
        file_put_contents($inputFile, '<?php echo "hello world";');

        $commandTester->execute([
            'source' => $inputFile,
            '--output' => $outputFile,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists($outputFile);
        $this->assertStringContainsString('Obfuscated:', $commandTester->getDisplay());

        $outputContent = file_get_contents($outputFile);
        $this->assertIsString($outputContent);
        $this->assertStringNotContainsString('hello world', $outputContent);
    }

    public function testObfuscateDirectory(): void
    {
        $application = new Application();
        $command = $application->find('obfuscate');
        $commandTester = new CommandTester($command);

        $inputDir = $this->tempDir . '/src';
        $outputDir = $this->tempDir . '/dist';
        mkdir($inputDir);
        file_put_contents($inputDir . '/A.php', '<?php class A {}');
        file_put_contents($inputDir . '/B.php', '<?php $a = new A();');

        $commandTester->execute([
            'source' => $inputDir,
            '--output' => $outputDir,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertDirectoryExists($outputDir);
        $this->assertFileExists($outputDir . '/A.php');
        $this->assertFileExists($outputDir . '/B.php');
        $this->assertStringContainsString('Successfully processed directory', $commandTester->getDisplay());
    }

    public function testDryRunDoesNotCreateFiles(): void
    {
        $application = new Application();
        $command = $application->find('obfuscate');
        $commandTester = new CommandTester($command);

        $inputFile = $this->tempDir . '/input.php';
        $outputFile = $this->tempDir . '/output-dry.php';
        file_put_contents($inputFile, '<?php echo 1;');

        $commandTester->execute([
            'source' => $inputFile,
            '--output' => $outputFile,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileDoesNotExist($outputFile);
        $this->assertStringContainsString('[Dry-run] Would obfuscate file', $commandTester->getDisplay());
    }

    public function testMissingOutputFails(): void
    {
        $application = new Application();
        $command = $application->find('obfuscate');
        $commandTester = new CommandTester($command);

        $inputFile = $this->tempDir . '/input.php';
        file_put_contents($inputFile, '<?php echo 1;');

        $commandTester->execute([
            'source' => $inputFile,
        ]);

        $this->assertEquals(2, $commandTester->getStatusCode());
        $this->assertStringContainsString('Output path is required', $commandTester->getDisplay());
    }
}
