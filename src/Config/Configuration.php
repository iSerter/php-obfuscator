<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Config;

/**
 * Immutable value object holding all obfuscation options.
 */
final class Configuration
{
    /**
     * @param string[] $ignoreVariables
     * @param string[] $ignoreFunctions
     * @param string[] $ignoreClasses
     * @param string[] $ignoreConstants
     * @param string[] $ignoreVariablesPrefix
     * @param string[] $ignoreFunctionsPrefix
     * @param string[] $ignoreClassesPrefix
     */
    public function __construct(
        public readonly string $phpVersion = '8.5',
        public readonly string $scramblerMode = 'random',
        public readonly int $scramblerMinLength = 6,
        public readonly bool $stripComments = true,
        public readonly bool $scrambleIdentifiers = true,
        public readonly bool $scrambleVariables = true,
        public readonly bool $scrambleFunctions = true,
        public readonly bool $scrambleClasses = true,
        public readonly bool $scrambleConstants = true,
        public readonly bool $scrambleMethods = true,
        public readonly bool $scrambleProperties = true,
        public readonly bool $scrambleNamespaces = false,
        public readonly bool $encodeStrings = true,
        public readonly bool $flattenControlFlow = true,
        public readonly bool $shuffleStatements = true,
        public readonly string $shuffleChunkMode = 'ratio', // fixed | ratio
        public readonly int $shuffleChunkSize = 3,
        public readonly int $shuffleChunkRatio = 20,
        public readonly array $ignoreVariables = [],
        public readonly array $ignoreFunctions = [],
        public readonly array $ignoreClasses = [],
        public readonly array $ignoreConstants = [],
        public readonly array $ignoreVariablesPrefix = [],
        public readonly array $ignoreFunctionsPrefix = [],
        public readonly array $ignoreClassesPrefix = [],
        public readonly string $userComment = '',
        public readonly bool $stripIndentation = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phpVersion: $data['php_version'] ?? '8.5',
            scramblerMode: $data['scrambler_mode'] ?? 'random',
            scramblerMinLength: $data['scrambler_min_length'] ?? 6,
            stripComments: $data['strip_comments'] ?? true,
            scrambleIdentifiers: $data['scramble_identifiers'] ?? true,
            scrambleVariables: $data['scramble_variables'] ?? true,
            scrambleFunctions: $data['scramble_functions'] ?? true,
            scrambleClasses: $data['scramble_classes'] ?? true,
            scrambleConstants: $data['scramble_constants'] ?? true,
            scrambleMethods: $data['scramble_methods'] ?? true,
            scrambleProperties: $data['scramble_properties'] ?? true,
            scrambleNamespaces: $data['scramble_namespaces'] ?? false,
            encodeStrings: $data['encode_strings'] ?? true,
            flattenControlFlow: $data['flatten_control_flow'] ?? true,
            shuffleStatements: $data['shuffle_statements'] ?? true,
            shuffleChunkMode: $data['shuffle_chunk_mode'] ?? 'ratio',
            shuffleChunkSize: $data['shuffle_chunk_size'] ?? 3,
            shuffleChunkRatio: $data['shuffle_chunk_ratio'] ?? 20,
            ignoreVariables: $data['ignore_variables'] ?? [],
            ignoreFunctions: $data['ignore_functions'] ?? [],
            ignoreClasses: $data['ignore_classes'] ?? [],
            ignoreConstants: $data['ignore_constants'] ?? [],
            ignoreVariablesPrefix: $data['ignore_variables_prefix'] ?? [],
            ignoreFunctionsPrefix: $data['ignore_functions_prefix'] ?? [],
            ignoreClassesPrefix: $data['ignore_classes_prefix'] ?? [],
            userComment: $data['user_comment'] ?? '',
            stripIndentation: $data['strip_indentation'] ?? true,
        );
    }
}
