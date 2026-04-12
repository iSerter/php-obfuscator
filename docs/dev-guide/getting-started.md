# Getting Started

Welcome to `iserter/php-obfuscator`! This guide will help you set up and start protecting your PHP source code quickly.

## Installation

The obfuscator is designed to be used as a development tool. We recommend installing it via Composer:

```bash
composer require --dev iserter/php-obfuscator
```

Alternatively, you can clone the repository and run it directly:

```bash
git clone https://github.com/iserter/php-obfuscator.git
cd php-obfuscator
composer install
```

## Basic CLI Usage

The primary way to use the obfuscator is through the `bin/obfuscate` command.

### Obfuscating a Single File

To obfuscate a single PHP file and save the output:

```bash
bin/obfuscate path/to/source.php --output path/to/output.php
```

### Obfuscating a Directory

To obfuscate an entire project directory recursively:

```bash
bin/obfuscate src/ --output obfuscated-src/
```

This will:
1.  Recursively find all `.php` files in `src/`.
2.  Perform a first pass to collect all symbols (classes, functions, variables, etc.).
3.  Perform a second pass to obfuscate the files, ensuring cross-file symbol consistency.
4.  Copy non-PHP files (like `.css`, `.json`) to the output directory unchanged.

### Incremental Processing

The obfuscator automatically tracks file timestamps in a `.obfuscator-manifest.json` file within your output directory. Subsequent runs will only process changed files, significantly speeding up the workflow for large projects.

-   To force a full re-obfuscation: Use `--force`.
-   To clean the output directory before processing: Use `--clean`.

## Quick Example

Create a file `test.php`:

```php
<?php
function hello($name) {
    echo "Hello, $name!\n";
}
hello("World");
```

Run the obfuscator:

```bash
bin/obfuscate test.php -o test_obfuscated.php
```

The output `test_obfuscated.php` will look something like this:

```php
<?php
function _v1000($_v1001) {
    echo "Hello, " . $_v1001 . "!\n";
}
_v1000("World");
```

## Running with Docker

You can use the official Docker image to run the obfuscator without installing PHP or Composer locally:

```bash
docker run --rm -v $(pwd):/app iserter/php-obfuscator src/ -o out/
```

-   `-v $(pwd):/app`: Mounts your current directory to `/app` inside the container.
-   `src/`: The directory to obfuscate.
-   `-o out/`: The output directory.

The image supports both `linux/amd64` and `linux/arm64`. For available tags, building from source, and more, see the [Docker Guide](docker.md).

## GitHub Action Integration

To automate obfuscation in your CI/CD pipeline, add this step to your GitHub Actions workflow:

```yaml
steps:
  - uses: actions/checkout@v4

  - name: Obfuscate PHP Source
    uses: iserter/php-obfuscator@v1
    with:
      source: 'src'
      output: 'dist'
      clean: true

  - name: Upload Obfuscated Code
    uses: actions/upload-artifact@v4
    with:
      name: obfuscated-code
      path: dist/
```

This ensures your code is protected before it is ever deployed or shared as an artifact.

## Next Steps
...
-   Check the [Configuration Guide](configuration.md) to customize how your code is obfuscated.
-   Learn how to [Exclude Symbols](configuration.md#ignore-lists) that should not be renamed (e.g., public APIs or vendor-facing code).
-   Read about the [Architecture](../architecture/pipeline.md) if you want to extend the tool with custom transformers.
