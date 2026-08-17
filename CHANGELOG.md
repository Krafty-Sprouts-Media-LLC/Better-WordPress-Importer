# Changelog

All notable changes to this fork of the WordPress Importer v2 plugin are documented here.

## [2.1.6] - 2026-08-17

### Changed
- README rewritten for this fork (install, dashboard/CLI usage, features). Added CREDITS.md (classic Importer, humanmade v2, Krafty Sprouts Media), SECURITY.md, docs/USAGE.md, docs/FILTERS.md, and GitHub issue/PR templates. CONTRIBUTING.md updated for PHP 8.1 and this repository.

## [2.1.5] - 2026-08-17

### Fixed
- Step 3 of the dashboard stepper stayed as a numbered circle after “Import complete!” while steps 1 and 2 already showed a green check. The Import step is now marked done on a successful finish.

## [2.1.4] - 2026-08-17

### Changed
- Plugin identity is now **Better WordPress Importer** (Plugins screen, Tools → Import, README, Composer package). Internal importer slug (`wordpress`) and text domain are unchanged so it still occupies the standard WordPress importer slot.

## [2.1.3] - 2026-08-17

### Fixed
- Posts imported from WordPress.com-style WXR files (no channel-level `<wp:category>` / `<wp:tag>` / `<wp:term>` lists — terms only on each `<item>`) all landed in Uncategorized, and tags/other taxonomies were dropped the same way. The importer only assigned terms that had already been mapped from those channel-level nodes; misses were stored as `_wxr_import_term` post meta and never applied in `post_process()`. Item-level terms are now found by slug, or created, then assigned — including on re-import of posts that already exist.

## [2.1.2] - 2026-08-17

### Fixed
- Author review always defaulted to “Create new user …” and never checked whether the WXR author already exists on this site. Matching is now done server-side: email first (unique, survives a renamed login), then `user_login`, then nicename. A match is pre-selected in the dropdown; “Create new user” stays available at the bottom for authors who are genuinely new. Existing users are listed from `get_users()` rather than a REST `/wp/v2/users` call (which omitted emails in `view` context, used nicename instead of login, capped at 100, and was fired once per author).

## [2.1.1] - 2026-08-17

### Fixed
- Dashboard WXR upload failed with "Sorry, you are not allowed to upload this file type." `handle_chunk_upload()` finalized the assembled file via `wp_handle_sideload()`, which runs WordPress's MIME sniff (`wp_check_filetype_and_ext()`). WXR files contain HTML in post content, so `finfo` often reports `text/html` or `text/xml` instead of `application/xml` — a mismatch WordPress rejects even when `.xml` is allowed via `upload_mimes`. The chunked upload now skips that sniff the same way core's `wp_import_handle_upload()` does (`test_type => false`), while still requiring a `.xml` extension before any chunk is stored.

## [2.1.0] - 2026-08-13

### Added
- `WP_Importer_Logger_File` — writes every import log entry to `wp-content/wxr-importer-debug.log`, persisted independent of `WP_DEBUG`, so a run can be reviewed after the fact.
- `WP_Importer_Logger_Multi` — composite logger that forwards log calls to multiple loggers at once, used to keep the existing dashboard/CLI/SSE live output working alongside the new file log.
- End-of-run import summary (created / skipped-as-duplicate / failed counts), shown once a dashboard import finishes.
- Accessibility pass on the import progress page: `aria-live` status/summary regions, `aria-label` on each progress bar, table captions and `scope="col"` headers, so screen readers get meaningful updates instead of silent DOM changes.
- Real PHPUnit test suite (`tests/ImportTest.php`) using an actual WXR fixture (`tests/data/small-export.xml`), covering post/postmeta/term/comment import and re-import deduplication. Modernized `phpunit.xml.dist` and `tests/bootstrap.php` to use `wp-phpunit/wp-phpunit` + `yoast/phpunit-polyfills` instead of requiring a manual SVN checkout. Verified green (6/6) on PHP 8.4.16 with PHPUnit 9.6.36 — `wp-phpunit` 6.9.5's `expectDeprecated()` only supports up to PHPUnit 9.6, not 10/11.
- Single-page import app (`templates/app.php`, `assets/app.js`, `assets/app.css`) replacing the old 3-step full-page-reload flow: drag-and-drop chunked upload (`WXR_Import_UI::handle_chunk_upload()`, 8&nbsp;MB chunks via `wp_ajax_wxr-import-upload-chunk`), author mapping rendered from JSON (`wp/v2/users` REST endpoint for the existing-user list), and the import step wired to the existing SSE stream via a new `wp_ajax_wxr-import-start` endpoint. Icons are inline SVG rather than Dashicons, matching current WordPress UI direction (Dashicons is closed to new icons; new core UI has moved to SVG). Old Plupload-based upload flow, `templates/intro.php`/`select-options.php`/`upload.php`, and `assets/intro.js`/`intro.css` removed as dead code.
- Concurrent attachment prefetching: when `fetch_attachments` is on, `WXR_Importer::find_attachment_urls_for_prefetch()` does a lightweight pass to collect all attachment URLs, then `prefetch_remote_files()` downloads them concurrently (default 4 at a time, `curl_multi`) before the main import loop starts. `fetch_remote_file()` uses a prefetched file when available and transparently falls back to its normal synchronous fetch otherwise — the main loop's order, deduplication, and every other behavior is unchanged. Filterable via `wxr_importer.prefetch_attachments` (bool) and `wxr_importer.prefetch_concurrency` (int, default 4).
- Type declarations (parameters and return types) added across every method in `class-wxr-importer.php`, using PHP 8.1 union types (e.g. `array|WP_Error`) where a method can return either a value or an error. No `strict_types` — coercion behavior is unchanged, this is purely self-documenting/catchable typing. Two methods (`cmpr_strlen()`, `bump_request_timeout()`) were deliberately left untyped because they override `WP_Importer` (WP core's base class) and PHP requires override signatures to stay compatible with the parent's.
- `import_start()` now skips the upfront `prefill_existing_posts/comments/terms` DB preload (which loads entire tables into memory) for WXR files over 20&nbsp;MB by default, falling back to a per-item DB lookup instead — duplicates are still caught, just checked one at a time rather than all held in memory at once. Filterable via `wxr_importer.auto_disable_prefill` (bool) and `wxr_importer.prefill_threshold_bytes` (int, default 20MB); logs a notice when it kicks in.

### Changed
- Dashboard import (`WXR_Import_UI::get_importer()`) and WP-CLI import (`WXR_Import_Command::import()`) now log to file in addition to their existing live output.
- Progress events now carry a `created` / `skipped` / `failed` status instead of being lumped into one generic delta — a failed post/term/user no longer silently counts the same as a successful one.
- `composer.json`: bumped dev tooling to current major versions (`wpcs` ^3.1, `phpcs` ^3.9), added `phpunit`/`yoast/phpunit-polyfills`/`wp-phpunit` for a modern test setup, dropped the unused `composer/installers` runtime dependency, and set an honest `php >=8.1` floor matching what this fork actually runs on.

### Fixed
- Dashboard import step 3 (`WXR_Import_UI::display_import_step()`) called `flush()` before `render_header()` had sent the admin page's HTTP headers, triggering "headers already sent" on any environment that treats that as fatal (e.g. this site's Laravel Acorn error handler converts PHP warnings to exceptions). The flush now happens after the header is rendered, matching the ordering already used correctly by the SSE streaming import path.
- `WXR_Import_UI::stream_import()` never called `ignore_user_abort( true )`, so a dropped browser connection killed the import server-side mid-post instead of letting it finish. It now keeps running to completion regardless of the client connection.
- A browser's `EventSource` auto-reconnecting mid-import (after `ignore_user_abort`, above, keeps the original pass alive server-side) could start a second, fully concurrent `$importer->import()` pass over the same file — the direct cause of progress showing above 100% (client-side counters never reset across reconnects and both passes emitted their own full set of progress events), and a real risk of racing `post_exists()` checks against the DB. `stream_import()` now acquires a heartbeat-based run lock (`_wxr_import_running` post meta) before starting; a reconnect while a pass is already running logs a notice and exits instead of starting a duplicate.
- `WXR_Importer::get_reader()` had a code comment claiming external XML entities were disabled for security, but the actual `libxml_disable_entity_loader()` calls were commented out (dead code). Replaced with real, current hardening: `LIBXML_NONET` on `XMLReader::open()` plus `LOADDTD`/`SUBST_ENTITIES` parser properties disabled — explicit defense-in-depth against XXE, since this parses user-uploaded XML.
- `WXR_Importer::$options` was assigned in the constructor and read in 12 places but never declared as a class property, triggering a PHP 8.2+ deprecated-dynamic-property notice on every import. Now declared alongside the other properties.
- The dashboard import page silently showed "Import complete!" even when the import failed outright (e.g. missing file) — the SSE `complete` event's error message was being dropped by the JS instead of displayed. Now shown clearly, prefixed in plain language.
- `EventSource` retries silently and indefinitely on connection loss by default, leaving the page frozen on "Now importing." forever with no indication anything was wrong. After 3 consecutive failed reconnect attempts, the page now shows a plain-language notice (and clears it automatically if the connection recovers).
- `process_menu_item_meta()` referenced an undefined `$item` variable when a menu item's associated object type was unrecognized (now uses `$post_id`). `post_process_comments()` referenced an undefined `$comment_ID` (wrong case) when writing back remapped comment data, so `wp_update_comment()` was never told which comment to update — comment parent/author remapping was silently a no-op. Both found while adding type declarations; both fixed.

## [2.0] - 2017-07-18

Last state of the upstream [humanmade/WordPress-Importer](https://github.com/humanmade/WordPress-Importer) repository before this fork's changes began. See upstream README for original project history.
