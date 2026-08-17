<!--
Better WordPress Importer — usage
Copyright (c) 2026 Krafty Sprouts Media, LLC
-->

# Usage

## Dashboard

Path: **Tools → Import → Better WordPress Importer**

### 1. Choose file

Drop a WordPress export (`.xml`) or click to browse. Files upload in 8 MB chunks. The size cap is `wp_max_upload_size()`, filterable with `import_upload_size_limit`.

WXR files are allowed even when WordPress would otherwise reject XML as a media type (same approach as core’s `wp_import_handle_upload()`).

### 2. Review authors

Each author in the export is listed with login and email.

- If that email or username already exists on this site, that account is pre-selected.
- Otherwise the default is **Create new user**.
- Existing users appear in the dropdown first; create-new is last.

Turn on **Download and import file attachments** to fetch media from the original site. That option is hidden when `import_allow_fetch_attachments` is false.

### 3. Import

Progress streams over Server-Sent Events. Created / skipped / failed counts update live. A dropped browser connection does not stop the server-side import.

When it finishes, the summary and activity log stay on the page. The debug log is also at `wp-content/wxr-importer-debug.log`.

Re-running the same file does not duplicate posts (matched by GUID). Categories and tags on those existing posts are still applied, so a second pass can fix an earlier Uncategorized import.

## WP-CLI

```sh
wp wxr-importer import export.xml
wp wxr-importer import export.xml --verbose=debug
wp wxr-importer import export.xml --default-author=1
```

- `--verbose` — log level (`info` if flag is present with no value)
- `--default-author=<id>` — fallback user ID when an author in the file cannot be mapped

CLI always sets `fetch_attachments` to true.

## What gets imported

Posts, pages, custom post types, comments, post meta, categories, tags, custom taxonomies (if registered on the destination), authors, and attachments (optional).

WordPress.com-style files that omit channel-level `<wp:category>` / `<wp:tag>` lists still import terms from each item’s `<category domain="…" nicename="…">` tags. Parent category hierarchy is not in those item tags, so those terms are created at the top level.

## Requirements

- PHP 8.1 or later
- WordPress 6.0 or later (tested on current 6.x)
- `import` capability to use Tools → Import
- PHP `curl` for concurrent attachment prefetch
