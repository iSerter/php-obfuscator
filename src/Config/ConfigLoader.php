<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Config;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    /**
     * @param string|null $filePath Path to YAML or PHP config file
     */
    public function load(?string $filePath = null): Configuration
    {
        $defaultConfigPath = __DIR__ . '/../../config/default.yaml';
        $defaultData = Yaml::parseFile($defaultConfigPath);
        $defaultConfigData = $defaultData['obfuscator'] ?? $defaultData;

        if ($filePath === null) {
            return Configuration::fromArray($defaultConfigData);
        }

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" not found.', $filePath));
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'yaml' || $extension === 'yml') {
            $data = Yaml::parseFile($filePath);
        } elseif ($extension === 'php') {
            $data = require $filePath;
        } else {
            throw new InvalidArgumentException(sprintf('Unsupported configuration file extension: %s. Use .yaml, .yml or .php', $extension));
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" must return an array.', $filePath));
        }

        // The PRD mentions an "obfuscator" key in the YAML example
        $userConfigData = $data['obfuscator'] ?? $data;

        // Simple merge: user config overrides default
        $configData = array_merge($defaultConfigData, $userConfigData);

        return Configuration::fromArray($configData);
    }
}
