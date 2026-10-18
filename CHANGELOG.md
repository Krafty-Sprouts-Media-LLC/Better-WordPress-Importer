# Changelog

All notable changes to this fork of the WordPress Importer v2 plugin are documented here.

## [2.1.11] - 2026-10-18

### Fixed
- Posts lost their categories and tags on sites with a persistent object cache (e.g. Redis on DokployPress), logging “Failed to import post_tag … A term with the name provided already exists”. Core's `term_exists()` reads through the term query cache, which stays stale while an import suspends cache invalidation, so existing terms looked missing and re-creating them failed. Existing terms are now looked up directly in the database. If WordPress still reports a same-name term (a different slug), its ID is used instead of failing.
- Long imports could die partway (seen right after ~6,500 media items, at memory_limit 384M). WordPress keeps everything it loads in the request's object cache for the whole run. The importer now clears that in-request cache every 50 items and logs memory use every 500 items to the debug log.
- Progress counters could exceed their totals (e.g. Media 6546 / 6460) after the browser reconnected to a new run, because the new run's counts were added to the old ones. Each run now tells the page to reset its counters.

## [2.1.10] - 2026-10-18

### Fixed
- Featured images were never remapped. `_thumbnail_id` is copied from the export as the attachment's ID on the original site, and the step that maps it to the newly imported attachment was commented out (and read a mapping that was never filled). Imported posts could show no featured image, or the wrong one. The remap now runs at the end of every import using the importer's post ID mapping, and only updates values that still hold the original ID.
- Re-running an interrupted import now repairs what the first run left unfinished. For posts and attachments that already exist, it re-queues any pending parent link (so “Unattached” media gets attached) and remaps featured images.

## [2.1.9] - 2026-10-18

### Fixed
- Imports with “Download and import file attachments” on could stall at “Prefetching N attachment(s)…” and then start over, repeatedly. Every attachment was downloaded before the first post was imported, with no output in between, so on a large export (6,460 files) the web server dropped the silent connection after its read timeout (300s on DokployPress). The run lock was only refreshed when progress was sent, so it looked abandoned after 5 minutes and each browser reconnect started another full, concurrent run.
  - Attachments are now prefetched in batches of 100 (`wxr_importer.prefetch_batch_size`) as the import reaches them, so posts start importing straight away and temp-disk use is limited to one batch.
  - Each batch logs its size, and long batches log progress every 15 seconds, which keeps the connection active.
  - The run lock is refreshed during downloads (new `wxr_importer.prefetch_progress` action), so a reconnect no longer starts a duplicate run.
  - Prefetched files that are never used (their attachment already existed) are deleted at the end of the import.

## [2.1.8] - 2026-10-18

### Fixed
- Uploads still failed with 502 Bad Gateway on hosts whose front proxy ends any request after 60 seconds: on a slower uplink an 8 MB chunk could not finish in time, and retrying the same size failed the same way. Chunks now start at 2 MB and halve after each gateway/timeout failure (down to 256 KB), so the upload adapts to the connection. Progress is tracked by bytes stored rather than chunk number, and shows as a percentage.
- Dashboard CSS/JS enqueued as 2.1.8 so browsers drop the cached 2.1.7 script.

## [2.1.7] - 2026-10-18

### Fixed
- Large uploads could fail with `Upload failed: Unexpected token 'B', "Bad Gateway" is not valid JSON`. When a proxy or CDN in front of PHP returned an HTML error page for one chunk, the uploader tried to parse it as JSON and gave up. Responses are now read as text first; gateway and timeout errors (502/503/504, Cloudflare 52x, 429) retry the same chunk up to three times with backoff, and the final error names the HTTP status and which part failed.
- Retried chunks can no longer be written twice. Each chunk sends its byte offset; if an earlier attempt already reached the server, the partial file is truncated back to that offset before appending.
- The final chunk (sideload plus author/count scan) now raises the PHP time limit to 300 seconds.
- Dashboard CSS/JS were still enqueued as version 2.1.5, so browsers could keep a cached copy after updating. They are now enqueued as 2.1.7.

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
