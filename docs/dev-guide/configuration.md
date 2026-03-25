# Configuration Guide

The `iserter/php-obfuscator` is highly configurable. You can customize which parts of your code are transformed and how the scrambling is performed using a YAML configuration file.

## Using a Custom Config File

To use your own configuration:

```bash
bin/obfuscate path/to/source.php -o path/to/output --config my-config.yaml
```

If no configuration file is provided, the obfuscator uses the [default configuration](../../config/default.yaml).

## Configuration Options

Below is a detailed breakdown of all available options in the `obfuscator` section:

### PHP Version

-   **`php_version`** (default: `"8.5"`): Target PHP version for the parser. Supports `7.4` to `8.5`.

### Scrambler Settings

-   **`scrambler_mode`** (default: `"random"`): Mode for generating scrambled names.
    -   `random`: Random letters and numbers (e.g., `_v8a2z9`).
    -   `hex`: Hexadecimal strings (e.g., `_v0a2f9b`).
    -   `numeric`: Numeric-style identifiers (e.g., `_v1000`, `_v1001`).
-   **`scrambler_min_length`** (default: `6`): Minimum length of generated scrambled names.

### Transformer Toggles

Set these to `true` or `false` to enable or disable specific transformation passes:

-   **`strip_comments`**: Remove all comments and docblocks.
-   **`scramble_identifiers`**: Enable or disable ALL identifier scrambling.
-   **`scramble_variables`**: Rename all variables (except `$this` and superglobals).
-   **`scramble_functions`**: Rename function declarations and their calls.
-   **`scramble_classes`**: Rename classes, interfaces, traits, and enums.
-   **`scramble_constants`**: Rename `define()` and `const` declarations.
-   **`scramble_methods`**: Rename class methods.
-   **`scramble_properties`**: Rename class properties.
-   **`scramble_namespaces`**: Rename namespace segments.
-   **`encode_strings`**: Encrypt string literals using base64 + XOR with a per-file key.
-   **`flatten_control_flow`**: Convert `if/else`, `while`, `switch` into a state-machine style (highly resistant to static analysis).
-   **`shuffle_statements`**: Reorder statements and insert `goto` jumps to preserve execution order.

### Shuffle Settings

If `shuffle_statements` is enabled:

-   **`shuffle_chunk_mode`** (default: `"ratio"`): Choose `fixed` or `ratio`.
-   **`shuffle_chunk_size`**: Number of statements per chunk (for `fixed` mode).
-   **`shuffle_chunk_ratio`**: Percentage of statements to be shuffled (for `ratio` mode).

### Ignore Lists

You can prevent specific symbols from being scrambled. This is essential for public APIs or when interacting with libraries you don't control.

-   **`ignore_variables`**: List of exact variable names (without `$`) to ignore.
-   **`ignore_functions`**: List of exact function names to ignore.
-   **`ignore_classes`**: List of exact class/interface/trait/enum names to ignore.
-   **`ignore_constants`**: List of exact constant names to ignore.

Example:

```yaml
obfuscator:
  ignore_classes:
    - "ExternalAPI"
    - "DatabaseConnector"
  ignore_functions:
    - "my_public_hook"
```

## Advanced: Ignoring External Libraries

By default, the obfuscator **only scrambles symbols that it finds declarations for** during its symbol collection pass.

-   If you obfuscate your `src/` directory but not your `vendor/` directory, any calls to Symfony, Laravel, or other library classes/methods will **automatically remain untouched**.
-   **Exception:** If you use a very common name (like `log` or `handle`) in your own code, and it gets scrambled, calls to library methods with the same name *might* be scrambled if the obfuscator can't distinguish them. In such cases, add the method name to `ignore_functions`.

## Example Custom Config

```yaml
obfuscator:
  php_version: "8.4"
  scrambler_mode: numeric
  scrambler_min_length: 4
  
  # Max security
  flatten_control_flow: true
  shuffle_statements: true
  encode_strings: true
  
  # Keep these for debugging if needed
  strip_comments: false
  
  # Don't break my API
  ignore_functions:
    - "api_entry_point"
  ignore_classes:
    - "PublicController"
```
