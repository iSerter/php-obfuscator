<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Config;

use InvalidArgumentException;
use ISerter\PhpObfuscator\Config\ConfigLoader;
use ISerter\PhpObfuscator\Config\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $tempFile;

    protected function tearDown(): void
    {
        if (isset($this->tempFile) && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testLoadYamlFile(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_config') . '.yaml';
        $content = <<<YAML
obfuscator:
  php_version: "8.4"
  scrambler_mode: "numeric"
  strip_comments: false
YAML;
        file_put_contents($this->tempFile, $content);

        $loader = new ConfigLoader();
        $config = $loader->load($this->tempFile);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertSame('8.4', $config->phpVersion);
        $this->assertSame('numeric', $config->scramblerMode);
        $this->assertFalse($config->stripComments);
    }

    public function testFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $loader = new ConfigLoader();
        $loader->load('non_existent_file.yaml');
    }

    public function testUnsupportedExtension(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_config') . '.txt';
        file_put_contents($this->tempFile, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported configuration file extension');

        $loader = new ConfigLoader();
        $loader->load($this->tempFile);
    }
}
