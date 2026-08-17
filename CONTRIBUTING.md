<!--
Better WordPress Importer — contributing guide
Copyright (c) 2026 Krafty Sprouts Media, LLC
-->

# Contributing

Thanks for helping with Better WordPress Importer.

## Process

1. Open an issue first when the change is more than a small fix.
2. Keep commits small. Explain *why* in the message.
3. Open a pull request. If it closes an issue, include `Fixes #123` in the PR body.
4. Include PHPUnit coverage for behaviour changes.
5. A maintainer will review before merge.

## Requirements

- PHP 8.1+
- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Tabs for indentation, Yoda conditions, `array()` syntax, `elseif`, declared visibility

This fork is **not** PHP 5.2 compatible (that was an upstream rule). Match existing code in this repository.

## Tests

```sh
composer install
composer test
```

Point `tests/wp-tests-config.php` at a dedicated database (the file is gitignored). Do not run the suite against a live site database.

New tests go in `tests/*Test.php` with fixtures under `tests/data/`.

## Licensing

By contributing, you license your work under the [GPLv2 or later](LICENSE), the same license as WordPress and the upstream importer.
