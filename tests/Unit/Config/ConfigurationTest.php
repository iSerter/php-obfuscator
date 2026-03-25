<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Unit\Config;

use ISerter\PhpObfuscator\Config\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Configuration();

        $this->assertSame('8.5', $config->phpVersion);
        $this->assertSame('random', $config->scramblerMode);
        $this->assertTrue($config->stripComments);
        $this->assertTrue($config->scrambleIdentifiers);
        $this->assertEmpty($config->ignoreVariables);
    }

    public function testFromArray(): void
    {
        $data = [
            'php_version' => '8.1',
            'scrambler_mode' => 'hex',
            'strip_comments' => false,
            'ignore_variables' => ['foo', 'bar'],
        ];

        $config = Configuration::fromArray($data);

        $this->assertSame('8.1', $config->phpVersion);
        $this->assertSame('hex', $config->scramblerMode);
        $this->assertFalse($config->stripComments);
        $this->assertSame(['foo', 'bar'], $config->ignoreVariables);
        // Ensure defaults are still there for missing keys
        $this->assertTrue($config->scrambleIdentifiers);
    }
}
