# iserter/php-obfuscator

A modern, reliable PHP obfuscator for copyright protection. Supporting PHP 7.4 through PHP 8.5.

## Features

- **Full Modern PHP Support:** Built on `nikic/php-parser` v5.x, supporting named arguments, enums, match expressions, readonly properties, intersection types, and even PHP 8.5's pipe operator and `(void)` cast.
- **Deobfuscation Resistance:**
  - **Opaque Predicates:** Hard-to-analyze expressions in control flow flattening.
  - **Dead Code Injection:** Branches that never execute but confuse static analysis.
  - **Per-file XOR Encoding:** String literals are encoded using unique random keys.
- **Incremental Processing:** Only process changed files since the last run.
- **Multi-pass Analysis:** Scrambles symbols consistently across your entire project.
- **Clean Architecture:** Modern PSR-4 OOP codebase with 100% test coverage for core components.

## Installation

### Via Composer
```bash
composer require iserter/php-obfuscator --dev
```

### Via Docker
You can run the obfuscator without installing PHP or Composer locally:
```bash
docker run --rm -v $(pwd):/app iserter/php-obfuscator src/ -o out/
```

## GitHub Action

Protect your code automatically in your CI/CD pipeline:

```yaml
steps:
  - uses: iserter/php-obfuscator@v0.1.1
    with:
      source: 'src'
      output: 'dist'
```

## Documentation

For detailed information, check out our **Developer Guides**:

- [Getting Started](docs/dev-guide/getting-started.md) - Installation and basic usage.
- [Configuration Guide](docs/dev-guide/configuration.md) - Deep dive into all obfuscation options.
- [Troubleshooting](docs/dev-guide/troubleshooting.md) - Dealing with dynamic calls, Reflection, and more.
- [Architecture](docs/architecture/pipeline.md) - How the pipeline works.

## Quick Start

Obfuscate a single file:
```bash
vendor/bin/obfuscate src/MyScript.php -o dist/MyScript.php
```

Obfuscate an entire directory:
```bash
vendor/bin/obfuscate src/ -o dist/
```

Wipe output directory and re-obfuscate everything:
```bash
vendor/bin/obfuscate src/ -o dist/ --clean --force
```

## Configuration

You can provide a YAML configuration file to customize the obfuscation process:

```bash
vendor/bin/obfuscate src/ -o dist/ -c my-config.yaml
```

Example `my-config.yaml`:
```yaml
obfuscator:
  scrambler_mode: hex  # random, hex, numeric
  scrambler_min_length: 8
  
  strip_comments: true
  scramble_identifiers: true
  encode_strings: true
  flatten_control_flow: true
  shuffle_statements: true
  
  ignore_classes:
    - MyExternalApi
    - PublicController
```

Refer to `config/default.yaml` for a full list of available options and their descriptions.

## Comparison with yakpro-po

| Feature                     | yakpro-po                          | iserter/php-obfuscator             |
|-----------------------------|------------------------------------|------------------------------------|
| PHP version support         | Up to PHP 7.3                      | PHP 7.4–8.5 (php-parser v5.7+)     |
| Test coverage               | No tests                           | Comprehensive unit + integration   |
| Deobfuscation resistance    | Easily reversed by automated tools | Opaque predicates, dead code, per-file keys |
| Architecture                | Procedural PHP scripts             | Modern OOP, PSR-4, SOLID           |
| Configuration               | PHP config file                    | YAML config with merge logic       |

## Limitations & Warnings

The obfuscator will warn you if it detects patterns that might break during scrambling:
- **Variable variables:** `$$var`
- **Dynamic calls:** `$func()`, `$obj->$method()`
- **Reflection:** `ReflectionClass`, `ReflectionMethod`
- **eval():** Code inside `eval()` is not obfuscated.

Use the `ignore_` configuration options to exclude symbols used in these dynamic contexts.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, commit conventions, and how to submit a pull request.

## License

MIT
