<!--
Better WordPress Importer — README
Copyright (c) 2026 Krafty Sprouts Media, LLC
-->

# Better WordPress Importer

A maintained fork of [humanmade/WordPress-Importer](https://github.com/humanmade/WordPress-Importer) (WordPress Importer v2) for large WordPress export (WXR) files.

It keeps the streaming XML parser from Importer v2 and adds a single-page dashboard import, chunked uploads, author matching, find-or-create categories/tags, file logging, and concurrent attachment prefetch.

Requires **PHP 8.1+** and WordPress 6.0+.

## Features

- Drag-and-drop WXR upload in 8 MB chunks (resumable if the connection drops)
- Author review that matches existing users by **email**, then **username**, then nicename
- Categories, tags, and other taxonomies: find by slug, or create, then assign — including WordPress.com-style exports that only list terms on each `<item>`
- Live import progress over Server-Sent Events, with a created / skipped / failed summary
- Import log written to `wp-content/wxr-importer-debug.log` (independent of `WP_DEBUG`)
- Concurrent attachment prefetch (`curl_multi`, default 4 at a time) when “Download and import file attachments” is on
- WP-CLI: `wp wxr-importer import file.xml`

## Install

1. [Download the ZIP](https://github.com/Krafty-Sprouts-Media-LLC/Better-WordPress-Importer/archive/master.zip) or clone this repository into `wp-content/plugins/`.
2. Activate **Better WordPress Importer**.
3. Deactivate the original WordPress Importer / Importer v2 if either is installed (they share the `wordpress` importer slot).

## Usage

### Dashboard

1. Go to **Tools → Import**.
2. Select **Better WordPress Importer**.
3. Drop a `.xml` export (or browse). Large files upload in chunks.
4. Review authors. Existing accounts are pre-selected when email or username matches; otherwise a new user can be created.
5. Optionally fetch attachments from the original site.
6. Start the import. Safe to leave the tab open in the background; a dropped browser connection does not abort the server-side run.

After a successful finish, all three stepper steps show a green check.

### WP-CLI

```sh
wp wxr-importer import path/to/export.xml
wp wxr-importer import path/to/export.xml --verbose
wp wxr-importer import path/to/export.xml --default-author=1
```

Run `wp help wxr-importer import` for the full option list.

CLI always fetches attachments. Dashboard import asks first.

More detail: [docs/USAGE.md](docs/USAGE.md). Developer filters: [docs/FILTERS.md](docs/FILTERS.md).

## Tests

```sh
composer install
composer test
```

Copy `tests/wp-tests-config.php` (gitignored) with a dedicated test database. The suite uses `wp-phpunit/wp-phpunit` and PHPUnit 9.6.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPLv2 or later. See [LICENSE](LICENSE).

## Credits

This plugin exists because of work by many people. Full attribution is in [CREDITS.md](CREDITS.md).

- **WordPress Importer (classic):** Ryan Boren, [Jon Cave](https://profiles.wordpress.org/duck_), [Andrew Nacin](https://profiles.wordpress.org/nacin), [Peter Westwood](https://profiles.wordpress.org/westi)
- **WordPress Importer v2 / Redux:** [Ryan McCue](https://github.com/rmccue) and [humanmade contributors](https://github.com/humanmade/WordPress-Importer/graphs/contributors)
- **This fork:** [Krafty Sprouts Media, LLC](https://kraftysprouts.com) ([Kingsley Felix](https://github.com/iamkingsleyf))
