# Troubleshooting

Obfuscating PHP can be tricky, especially with dynamic code or complex architectures. This guide covers common issues and how to resolve them.

## Common Issues

### 1. "Undefined function" or "Class not found"

**Problem:** Your obfuscated code fails with an undefined function or class error.
**Cause:** This usually happens when you use dynamic calls (e.g., `$func = 'my_function'; $func();`) and the function name was scrambled, but the string `'my_function'` was not.
**Solution:**
-   **Preferred:** Avoid dynamic calls where possible.
-   **Alternative:** Add the function/class name to the [Ignore Lists](configuration.md#ignore-lists).

### 2. "Wrong named parameter"

**Problem:** You get a `TypeError` regarding unknown named parameters.
**Cause:** If you use PHP 8 named arguments (e.g., `myFunc(name: "value")`), the parameter name must match the function's declaration. If the function's parameter name was scrambled, the call site must also be scrambled.
**Solution:**
-   Ensure you are using the **directory processing mode** (`bin/obfuscate dir/`) so that the obfuscator can collect all symbols and apply them consistently across all files.
-   If the function is part of an external library (in `vendor/`), make sure you are **not** obfuscating the `vendor/` directory.

### 3. Magic Methods and Native PHP Hooks

**Problem:** `__construct`, `__get`, `__set`, etc., are causing issues.
**Cause:** The obfuscator is designed to **exempt magic methods** (starting with `__`) from scrambling by default.
**Solution:**
-   If you find a magic method being scrambled, please [report it as a bug](/bug).
-   If you have custom "magic-like" methods that shouldn't be renamed, add them to `ignore_functions` or `ignore_methods` (via `ignore_functions` in config).

### 4. Reflection API

**Problem:** Code using `ReflectionClass` or `ReflectionMethod` fails.
**Cause:** Reflection depends on the original names of classes and methods. If they are scrambled, Reflection will look for the old names and fail.
**Solution:**
-   Add classes or methods used with Reflection to the ignore lists.
-   **Tip:** If your project uses a lot of Reflection (e.g., for Dependency Injection or ORM), you may need to disable `scramble_classes` or `scramble_methods`.

### 5. String Encryption Issues

**Problem:** Your code fails with an error related to `_d()` function.
**Cause:** `encode_strings` is enabled, and the `_d` helper function (the decoder) was not injected or is missing.
**Solution:**
-   The obfuscator automatically injects the `_d` function into the output files.
-   If you are obfuscating multiple files, ensure they all have access to this function.

## Performance Tips

### Slow Obfuscation

For large projects (thousands of files), the first run can be slow.
-   **Incremental Processing:** Subsequent runs will be much faster as the obfuscator only processes changed files.
-   **Exclude Directories:** Use your shell to exclude non-PHP directories if they contain many files (though `bin/obfuscate` already only processes `.php` files).

### Large Memory Usage

If you encounter memory limit errors:
-   Increase the memory limit: `php -d memory_limit=2G bin/obfuscate src/ -o out/`.
-   This is often necessary during the "Symbol Collection" pass for very large projects where a massive symbol map is built in memory.

## Getting More Information

To see exactly what the obfuscator is doing, increase the verbosity:

```bash
bin/obfuscate src/ -o out/ -v    # Verbose
bin/obfuscate src/ -o out/ -vv   # Very verbose (shows symbol collection)
bin/obfuscate src/ -o out/ -vvv  # Debug (shows AST transformations)
```

Check the **Warnings** section at the end of the output. The obfuscator will warn you if it detects potentially dangerous dynamic code (like `eval()`, `$$var`, or dynamic method calls).
