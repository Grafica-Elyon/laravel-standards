# Laravel Standards

Internal Laravel package for standardizing Pint, PHPStan, native Git hooks, PHPUnit-driven TDD and Action Pattern Architecture guidance.

## Installation

Install the package as a development dependency in a Laravel project:

```bash
composer require --dev elyon/laravel-standards
```

## Usage

Run the installer from the host project:

```bash
php artisan standards:install
```

Use `--force` to overwrite all managed files and Composer scripts without prompts.

## Configuration

The installer publishes `pint.json`, `phpstan.neon`, and `.githooks/pre-commit`. It also adds the `test`, `lint`, `lint-check`, and `analyse` Composer scripts after confirmation.

After `git init`, rerun `standards:install` to configure Git's native `core.hooksPath` to use `.githooks` automatically.

## Exporting stubs

Re-publish the editable tooling stubs with:

```bash
php artisan standards:export
```

## TDD and Action Pattern Architecture skills (delivered via Laravel Boost)

Laravel Boost automatically detects this package's TDD and Action Pattern Architecture skills. Run `php artisan boost:install` once to materialize it in your enabled AI agents.

## Contributing

Run the package quality checks before submitting changes:

```bash
composer lint-check
composer analyse
composer test
```

## License

MIT.
