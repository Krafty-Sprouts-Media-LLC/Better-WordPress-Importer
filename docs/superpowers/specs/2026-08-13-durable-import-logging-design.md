# Durable Import Logging + Baseline Import Test — Design

**Date:** 2026-08-13
**Status:** Implemented

## Context

This repo is a frozen (2017, `Version: 2.0`) copy of [humanmade/WordPress-Importer](https://github.com/humanmade/WordPress-Importer), never adopted upstream. The user wants to bring it back to life for internal use across their own WordPress projects. Rather than guessing what's broken by reading 10-year-old code, the first concrete step is to get real visibility into what the importer actually does during a run, then use that evidence to drive any compatibility/bug fixes.

The plugin already has a small PSR-3-style logging framework (`WP_Importer_Logger` base class in [class-logger.php](../../../class-logger.php), with `_HTML`, `_CLI`, and `_ServerSentEvents` subclasses). All three only echo live to the current request/response — nothing persists. The base class's own `log()` appends to an in-memory `$messages` array that is never read back. There is currently no way to review what happened during an import after the fact, independent of PHP's own error log / `WP_DEBUG`.

This site's `wp-config.php` has `WP_DEBUG_LOG` enabled, writing to `wp-content/debug.log`.

Note on repo/git: this plugin folder is untracked. The directory that appeared to be its git repo turned out to be rooted at the whole user home directory with `origin` pointing to an unrelated project (`Krafty-Sprouts-Media-LLC/IMGverse-Search`) — so no commits are made for this work; this doc is a plain working file, not something committed to git.

## Goals

- Add a durable, file-based log of every import run, independent of `WP_DEBUG`.
- Keep all existing live feedback (dashboard HTML output, SSE progress, WP-CLI output) working unchanged.
- Use the resulting log from a real test import as the evidence base for any subsequent compatibility work — not speculation.

## Non-goals

- Fixing any specific importer bug (none has been identified yet — this is instrumentation, not a bugfix).
- Log rotation, size limits, or structured (JSON-lines) output — YAGNI until a real need shows up.
- Changing anything about `class-wxr-importer.php`'s import logic itself.

## Design

### 1. `WP_Importer_Logger_File`

New class ([class-logger-file.php](../../../class-logger-file.php)), following the existing subclass pattern. Extends `WP_Importer_Logger`. Writes one line per log call to `wp-content/wxr-importer-debug.log` (sits next to `debug.log`, named distinctly so the two are never confused in a directory listing).

Line format: `[YYYY-MM-DD H:i:s] [LEVEL] message {"context":"as json"}` — plain text, so it's greppable/tailable, with context appended as JSON only when non-empty. Uses `error_log( $line, 3, $path )` to append, so there's no held file handle across requests — imports can span multiple AJAX requests via the SSE flow.

### 2. `WP_Importer_Logger_Multi`

New class ([class-logger-multi.php](../../../class-logger-multi.php)). Holds an array of logger instances; its `log()` forwards the call to each one. This lets us keep the existing HTML/CLI/SSE logger active for live feedback *and* add the file logger simultaneously, with no changes to `class-wxr-importer.php` (which only ever talks to whatever single logger object it's given via `set_logger()`).

### 3. Wiring — done

- [plugin.php](../../../plugin.php): requires the two new files.
- [class-wxr-import-ui.php](../../../class-wxr-import-ui.php) `get_importer()`: SSE logger wrapped in a `WP_Importer_Logger_Multi` alongside the new file logger.
- [class-command.php](../../../class-command.php) `import()`: CLI logger wrapped the same way.

## Testing plan

Two-stage, not one — a 100MB+ file as the *first* test would tell us about PHP's default upload/execution limits, not about the importer, since `wp-config.php` has no `upload_max_filesize` / `post_max_size` / `max_execution_time` overrides and the importer has no `set_time_limit()` call of its own.

1. **Baseline: small WXR file.** Export a small WXR from this site (Tools → Export, a handful of posts/pages/comments/tags/custom fields) and import it back via the dashboard. Confirms the new file logger captures the full run correctly and gives a known-good baseline to compare later runs against.
2. **Scale test: 100MB+ WXR file, via WP-CLI** (`wp wxr-importer import <file>`), not the browser dashboard — CLI reads the file from disk (no upload cap) and CLI php.ini has no execution time limit by default. This isolates the test to the importer's actual behavior at scale (the `XMLReader`-based streaming parser it already uses in [class-wxr-importer.php:122](../../../class-wxr-importer.php#L122) should handle this without loading the whole file into memory, but has not been verified in practice).

The `wp-content/wxr-importer-debug.log` output from both runs is the evidence used to decide what (if anything) needs fixing next — no fixes are made speculatively as part of this change.

## Status

Code is written (`class-logger-file.php`, `class-logger-multi.php`, wiring in `plugin.php` / `class-wxr-import-ui.php` / `class-command.php`). Not yet tested against a real import. Next: run the small-file baseline test.
