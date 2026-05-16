# Repository Guidelines

## Codebase summary

This package provides Castor Ledgering, a double-entry bookkeeping library for PHP. Core source code lives in `src/`, Symfony integration in `bundle/`, examples in `examples/`, and PHPUnit tests in `tests/`.

## Development setup

Use devenv for a consistent local PHP toolchain and Postgres service:

```bash
devenv shell
devenv up
composer install
```

The default integration database is exposed through `TEST_DATABASE_URI=postgres://user:secret@127.0.0.1:5432/ledgering`.

## Checks

Run the same checks used by CI:

```bash
composer ci
composer pr
composer rector:check
composer rector
composer mago:fmt:check
composer mago:fmt
composer mago:lint
composer mago:analyze
composer test
composer test:unit
composer test:integration
composer test:e2e
```

Use `composer pr` before opening a pull request; it applies Rector and Mago formatting before linting, analysis, and tests.
