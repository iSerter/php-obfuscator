# Contributing

Thanks for your interest in contributing to `iserter/php-obfuscator`!

## Development Setup

```bash
git clone git@github.com:iSerter/php-obfuscator.git
cd php-obfuscator
composer install
```

## Workflow

1. Fork the repo and create a branch from `main`.
2. Make your changes.
3. Add or update tests as needed.
4. Ensure all checks pass:
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse src --level 8
   vendor/bin/php-cs-fixer fix src --dry-run --diff
   ```
5. Open a pull request against `main`.

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/) to automate releases via [release-please](https://github.com/googleapis/release-please).

| Prefix   | Purpose              | Version bump |
|----------|----------------------|--------------|
| `fix:`   | Bug fix              | Patch        |
| `feat:`  | New feature          | Minor        |
| `feat!:` | Breaking change      | Major        |
| `docs:`  | Documentation only   | None         |
| `chore:` | Maintenance / CI     | None         |

Examples:
```
fix: prevent crash when input file is empty
feat: add --dry-run flag
feat!: rename --output to --out (breaking)
```

## Code Style

- Follow PSR-12. PHP-CS-Fixer enforces this automatically.
- Run `vendor/bin/php-cs-fixer fix src` to auto-format before committing.

## Reporting Issues

Open an issue on GitHub with a minimal reproduction case and the PHP version you're using.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
