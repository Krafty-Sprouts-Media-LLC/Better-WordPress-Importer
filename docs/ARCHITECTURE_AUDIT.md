# Architecture Audit: Better WordPress Importer

## 1. Current Architecture Summary

The plugin consists of ~6,000 lines of PHP and ~400 lines of JS across:

| File | Lines | Role |
|------|-------|------|
| `plugin.php` | 62 | Entry point: loader, importer registration, AJAX hooks |
| `class-wxr-importer.php` | 2,575 | Core import engine: XMLReader parsing, entity processing, deduplication, remapping |
| `class-wxr-import-ui.php` | 1,739 | Admin UI controller: 3-step flow, chunked upload, SSE streaming, diagnostic logging |
| `class-wxr-import-info.php` | 25 | DTO for pre-scan results |
| `class-command.php` | 66 | WP-CLI command |
| `class-logger*.php` | ~250 | Logger abstraction (base, CLI, HTML, SSE) |
| `templates/*.php` | ~250 | Admin page templates |
| `assets/*.js` | ~420 | Browser JS: Plupload, SSE consumption, progress rendering |
| `assets/*.css` | ~400 | Admin UI styling |
| `tests/*` | ~10 | Skeleton tests only |

**Data flow:**
1. User uploads/selects XML → stored as private attachment
2. Step 1→2: `get_preliminary_information()` scans XML via `XMLReader` (lightweight, no `expand()` on items) → caches `_wxr_import_info` post meta
3. Step 2→3: Author mapping chosen, settings saved as `_wxr_import_settings` post meta
4. Step 3: Browser opens `EventSource` to `admin-ajax.php?action=wxr-import&id=N`
5. `stream_import()` loads settings from post meta, creates `WXR_Importer` + SSE logger, calls `$importer->import($file)`
6. Import opens XML with `XMLReader`, reads top-level nodes sequentially, calls `expand()` on `<item>`, `<wp:author>`, `<wp:category>`, `<wp:tag>`, `<wp:term>`
7. Each entity is parsed → deduplication check → `wp_insert_*` → tracked in `$this->mapping` arrays
8. Post-process phase remaps parents, authors, menu items, featured images, attachment URLs
9. On completion: settings deleted, file/attachment cleaned up, SSE `complete` event emitted

**Import processing order within a single pass:**
All entity types are processed in document order as they appear in the WXR. Authors, categories, tags, and terms preceding `<item>` elements get processed first; then posts; then leftover remapping runs in `post_process()`.

---

## 2. Main Reliability Risks

### R2.1: Single long request is the root cause of failure (critical)
`class-wxr-import-ui.php:1413` — `$importer->import($file)` runs synchronously inside one `admin-ajax.php` request. The 2024 failure with `healthtian.WordPress.2020-03-06.xml` (stopped at 990/4597 posts) demonstrates this directly. Causes:
- PHP `max_execution_time` (even with `set_time_limit(0)`, some hosts enforce hard limits)
- FastCGI/FPM request timeout (`FcgidIOTimeout`, `request_terminate_timeout`)
- Proxy/gateway timeout (nginx `proxy_read_timeout`, Cloudflare 100s limit)
- Browser disconnect
- PHP out-of-memory on very large files

### R2.2: No true resumability — reconnect is cosmetic (critical)
`assets/import.js:229-241` — The auto-reconnect/resume patch opens a *new* EventSource connection. The server starts the import from scratch — `stream_import()` creates a new `WXR_Importer` instance with empty mapping arrays at line 1340. The `resetProgress()` call on JS line 24 resets the UI counters because there is no server-side mechanism to tell the browser "we already processed N posts." The only "resume" safety net is GUID-based deduplication (`class-wxr-importer.php:872`) which skips already-imported posts, but the XML must be re-parsed from the beginning on every attempt.

### R2.3: In-memory mapping arrays are volatile (high)
`class-wxr-importer.php:59-65` — All mapping state (`$this->mapping`, `$this->requires_remapping`, `$this->exists`, `$this->url_remap`, `$this->featured_images`) exists only in PHP memory. If the process dies, everything collected since the last cache flush is lost. On restart, posts are skipped by GUID but terms/comments may re-attempt if dedup misses, and remapping built during the partial run must be reconstructed.

### R2.4: Post-processing depends on full import completion (high)
`class-wxr-importer.php:577-583` — `post_process()`, `replace_attachment_urls_in_content()`, and `remap_featured_images()` only run after the XML parsing loop finishes. If the process dies before these run, posts are imported but parent/author/menu/featured_image relationships are left broken with temporary `_wxr_import_*` meta keys.

### R2.5: Re-running skips posts but may miss comment/term updates (medium)
`class-wxr-importer.php:900-918` — When `post_exists()` returns true, the post is skipped but comments on that post *are* re-processed (line 915). If a post was imported in a partial run, was missing some comments because the import stopped, and is re-imported, comments imported in the first run will be skipped and new ones added — this is correct. However, if the post was imported but post-processing never ran, the `_wxr_import_*` meta keys are not cleaned up and remapping never happens on subsequent passes because the post is skipped entirely.

---

## 3. Main Security Risks

### S3.1: Diagnostic log writes to plugin root (high — must fix before production)
`class-wxr-import-ui.php:997-1009` — `log_upload_debug()` writes to `__DIR__ . '/wxr-upload-debug.log'` inside the plugin directory. This file is:
- Web-accessible: `https://site.com/wp-content/plugins/WordPress-Importer-v2/wxr-upload-debug.log`
- Contains server paths, user IDs, upload sessions, request metadata, PHP error details
- Never rotated or truncated — grows indefinitely
- No capability check on who accesses it

The untracked `wxr-upload-debug.log` file in the repo root confirms this is active.

### S3.2: Chunk directory has inadequate access protection (medium)
`class-wxr-import-ui.php:745-751` — Creates `.htaccess` with `Deny from all` (Apache 2.2 syntax only) and `index.php`. On nginx, IIS, or Apache 2.4+ without `AllowOverride`, this offers no protection. Chunk parts are named `{session}/{n}.part` and contain raw XML data potentially accessible via URL.

### S3.3: Attachment XML files in uploads before cleanup (medium)
Browser-uploaded WXR files exist as private attachments in `wp-content/uploads/` during import. While `post_status = 'private'`, the physical file may be directly accessible if the webserver serves uploads directory listings or the URL is guessed. Cleanup at `class-wxr-import-ui.php:1433-1442` only runs after successful import completion — a crashed import leaves the file.

### S3.4: `wp_insert_post` with `import_id` bypasses capability checks for CPTs (low)
`class-wxr-importer.php:956` — Using `import_id` allows setting arbitrary post IDs, which could theoretically collide with existing content if the WXR is crafted maliciously.

### S3.5: Local file path restricted to uploads but exposes structure (low)
`class-wxr-import-ui.php:377` — The uploads base path is printed in the admin UI (`intro.php:51`), showing server directory structure.

---

## 4. Main Performance Risks

### P4.1: `XMLReader::expand()` on large items can cause memory spikes (medium)
`class-wxr-importer.php:454` — Each `<item>` node is expanded into a DOMDocument. For posts with very large `content:encoded` (e.g., posts with embedded base64 images), this can spike memory. The fast pre-scan avoids this, but the main import does not.

### P4.2: Pre-fill queries load all existing posts into memory (medium)
`class-wxr-importer.php:2420-2426` — `prefill_existing_posts()` loads `SELECT ID, guid FROM wp_posts` unfiltered. On a site with 100k+ posts, this is a large result set loaded entirely into PHP memory. Same pattern repeats for comments (line 2471) and terms (line 2523).

### P4.3: Object cache flush every 200 posts is a heuristic (low)
`class-wxr-importer.php:1093-1095` — `wp_cache_flush()` every 200 posts is reasonable but hardcoded. Different post sizes and server memory limits may need different intervals.

### P4.4: Content URL replacement uses SQL-level REPLACE (low)
`class-wxr-importer.php:2305-2318` — `replace_attachment_urls_in_content()` runs `UPDATE wp_posts SET post_content = REPLACE(...)` which is efficient for few URLs but does a full table scan per URL remap rule when `aggressive_url_search` is enabled.

---

## 5. Main Compatibility Risks

### C5.1: Registered as 'wordpress' — conflicts with legacy importer
`plugin.php:53` — The importer is registered with slug `wordpress`. This means it cannot coexist with the original WordPress Importer plugin (which also registers as `wordpress`). The importer name "WordPress (v2)" distinguishes it in the UI but not internally.

### C5.2: Hooks/filters are a mix of upstream and custom
The plugin fires WordPress core's `import_start`/`import_end` actions, filters on `import_post_meta_key`, `http_request_timeout`, and custom hooks prefixed with `wxr_importer.*`. The upstream `humanmade/WordPress-Importer` hooks are partially preserved but the namespacing is inconsistent.

### C5.3: XML version compatibility
`class-wxr-importer.php:15` — `MAX_WXR_VERSION = 1.2`. WXR files from newer WordPress versions (3.0+) may contain elements this parser doesn't handle, though in practice the format is stable.

---

## 6. Existing Parts Worth Keeping

| Component | Verdict | Rationale |
|-----------|---------|-----------|
| `XMLReader`-based parsing in `get_reader()` | **Keep** | Efficient, streaming, well-implemented security (entity loading disabled) |
| Fast pre-scan in `get_preliminary_information()` | **Keep** | Lightweight depth-tracking approach, ~2s for 17MB file |
| Deduplication: GUID for posts, author:date hash for comments, taxonomy:slug hash for terms | **Keep** | Proven approach, works across re-runs |
| Remapping logic: `post_process_posts()`, `post_process_comments()`, `post_process_menu_item()`, `remap_featured_images()` | **Keep, adapt for batch** | Solid logic, needs to be callable incrementally |
| Logger abstraction (`WP_Importer_Logger` + subclasses) | **Keep** | Clean PSR-3-inspired interface, extensible |
| Author mapping UI and data model | **Keep** | Well-designed, handles override + mapping |
| Cancel button + `handle_cancel_import()` | **Keep** | Proven UX pattern |
| Local file path workflow (`handle_local_file()`) | **Keep** | Well-secured, essential for large imports |
| XML corruption detection (HTML error pages in meta, incomplete class objects) | **Keep** | Hard-won edge cases, essential |
| `safe_expand()` helper with libxml error handling | **Keep** | Centralized error handling |
| Attachment local-file fallback in `fetch_remote_file()` | **Keep** | Valuable for offline/local dev |
| WP-CLI command | **Keep** | Direct path for server-side large imports |
| Cache flush every N posts | **Keep, make configurable** | Prevents memory exhaustion |

---

## 7. Existing Parts That Should Be Retired

| Component | Rationale |
|-----------|-----------|
| Single-pass SSE import (`stream_import()` → `$importer->import()`) | Root cause of all interruption failures. Replace with batch processing. |
| `processed_terms`, `processed_posts`, `processed_menu_items`, `menu_item_orphans`, `missing_menu_items` (line 52-56) | Deprecated by the `mapping` arrays. Code comments say "TODO: REMOVE THESE." Already unused except for `processed_terms` which is referenced nowhere. |
| `log_upload_debug()` writing to plugin root | Security risk. Replace with proper WP debug log integration or job-level persistent log. |
| Reconnect/resume in `import.js` without server-side resume | Cosmetic — resets progress and restarts import from scratch. Replace with true resume against a job state. |
| `_wxr_import_settings` as the sole import session token | Too limited. Replace with a proper job table that records progress per batch. |
| Direct `wp_delete_attachment()`/`wp_delete_post()` in `stream_import()` cleanup | Couples cleanup to SSE path. Move to job-level lifecycle. |
| Hardcoded `8mb` chunk size in `render_upload_form()` | Should be filterable, tied to server config. |

---

## 8. Emergency Patches — Evaluation

| Patch | Action |
|-------|--------|
| Chunked upload (`handle_async_chunked_upload()`) | **Keep logic, rewrite for maintainability**. The chunking approach works. Should be extracted to a dedicated upload handler class. Add nginx/IIS protection for chunk dir. Add cleanup cron for abandoned chunk dirs. |
| Diagnostic logging (`log_upload_debug()`, `summarize_upload_files()`, etc.) | **Replace**. Diagnostic logging is useful during development but must not write to plugin root. Integrate with WP_DEBUG_LOG or a job-persisted log. |
| Reconnect behavior in `import.js` | **Replace**. The auto-reconnect confuses users by resetting progress. True resume requires server-side job state. |
| Flush strengthening in SSE logger and `emit_sse_message()` | **Keep** the `ob_flush()` calls — they're harmless and help for certain server configs. |
| Cache busting with `filemtime()` in asset enqueues | **Keep** — standard practice, should have been there from the start. |
| File input name changed to `import` in `upload.php` | **Keep** — needed for Plupload to work. |
| `FcgidMaxRequestLen` etc. in project context doc | **Not a patch** — these are local server config changes excluded from the plugin. |

---

## 9. Architecture Decision Recommendations

### Q1: Fork vs. clean plugin with legacy adapter?
**Recommendation: Clean rebuild as the same plugin identity.** The current code has too many intertwined concerns (streaming, parsing, UI, upload) to incrementally refactor into a job-based architecture. However, keep the plugin slug, registration name, and folder name for compatibility. The parsing, deduplication, and remapping logic in `class-wxr-importer.php` is salvageable as an import *engine* behind a new job controller.

### Q2: Keep plugin name and registration?
**Yes.** "Better WordPress Importer" registered as `wordpress` under `Tools → Import`. The name is recognizable and distinguishes it from the original humanmade/WordPress-Importer. The `wordpress` slug means it replaces the legacy importer — document this clearly.

### Q3: Safest architecture for large imports?
**Recommendation: Custom import job table + WP-Cron batches + admin-ajax polling, with WP-CLI as a parallel path.**

Rationale:
- **Action Scheduler**: Robust but introduces a dependency. Good if the site already uses it (WooCommerce, etc.), but the plugin should work standalone. Could be an optional backend.
- **WP-Cron**: Built-in, no external dependency. Downside: relies on HTTP requests hitting the site to trigger. On low-traffic sites, jobs stall. Mitigation: offer both WP-Cron and admin-ajax polling.
- **Custom job table**: Gives full control over state schema. Avoids polluting post meta with dozens of keys per import. Enables SQL-based progress queries.
- **REST API**: Cleaner than admin-ajax for polling but requires authentication handling. `admin-ajax.php` works out of the box with WordPress nonces.
- **WP-CLI first**: Best for very large imports (175MB+ files mentioned in README). Already implemented. Keep and enhance. Can share the same engine as the web UI path.

**Chosen design:** Custom `wp_wxr_import_jobs` table + `wp_wxr_import_items` table. Processing via WP-Cron recurring task, with admin-ajax as an optional accelerator (process-next-batch-now button). WP-CLI runs batches synchronously in a loop. UI polls via admin-ajax for progress.

### Q4: How should import state be represented for resume?
**Recommendation: Job table with batch-level progress tracking.**

```
wp_wxr_import_jobs:
  id, status (pending|scanning|processing|remapping|complete|failed|cancelled),
  file_path, xml_cursor_position, total_posts, total_comments, total_terms,
  total_users, processed_posts, processed_comments, processed_terms,
  processed_users, created_at, updated_at, options (JSON)

wp_wxr_import_items:
  id, job_id, entity_type (post|comment|term|user),
  old_id, new_id, status (pending|imported|skipped|failed),
  error_message, created_at
```

The cursor into the XML is recorded as a byte offset after each batch. On resume, `XMLReader::open()` + seek to cursor position and scan forward to the next `<item>` (XMLReader cannot seek to an arbitrary byte; the cursor is approximate, and the engine fast-forwards to the next item boundary).

### Q5: Can `XMLReader` resume from a cursor safely?
**No — cannot seek precisely.** `XMLReader::open()` reads from the beginning. The byte cursor after a batch is approximate because it points to a position mid-stream. On resume, open the file, seek to the stored byte offset via `fseek()` on a separate file handle, then `XMLReader::open()` from the beginning and fast-forward `read()` calls until `XMLReader::nodeType === ELEMENT && XMLReader::name === 'item'`. This is O(items-already-processed) in I/O but faster than re-parsing full expanded nodes. **Better alternative:** During the pre-scan, record the byte offset of each `<item>` element's opening tag. This makes resume O(1) — seek to the exact item.

### Q6: Pre-index or process directly in batches?
**Recommendation: Pre-index item positions during pre-scan.** The pre-scan (`get_preliminary_information()`) already does a lightweight traverse. Extend it to record a manifest of byte offsets for each `<item>`, `<wp:author>`, `<wp:category>`, `<wp:tag>`, and `<wp:term>`. This enables:
- Accurate total counts without re-scanning
- O(1) seek-to-item for resume
- Batch slicing by item index (items 0-49, 50-99, etc.)
- Dependency ordering (authors before posts, terms before posts)

Cost: The manifest for 5,000 items is ~50KB (trivial). Store as JSON in a job meta field or a dedicated table.

### Q7: How should entity dependencies be handled?
**Recommendation: Multi-pass ordered processing within each batch, with deferred remapping.**

Within a batch of N items:
1. **Process authors** (if any in this batch — usually all at the start)
2. **Process terms** (categories, tags, custom taxonomies)
3. **Process posts** (posts, pages, CPTs, nav_menu_items)
4. **Process comments** (child of posts)
5. **Deferred remapping** — track items needing parent/author/menu references, resolve after all items in the batch are processed

Global remapping (across all batches):
- Run after all batches complete, or incrementally
- Featured images: update `_thumbnail_id` when both post and attachment are imported
- Parents: update `post_parent` when parent post appears in a later batch
- Menu items: update `_menu_item_object_id` when target post/term appears later
- URL remapping: update `post_content` after all attachments are downloaded

### Q8: How to isolate attachment fetching?
**Recommendation: Separate attachment import phase with async download.**

After content import (posts, terms, comments):
1. Collect all attachment URLs into a queue with post IDs
2. Download attachments in small batches (3-5 at a time) with HTTP timeout per file
3. Failed downloads are recorded; content import is already complete
4. URL remapping happens after downloads — posts with `_wxr_import_has_attachment_refs` get their content updated

This allows content import to complete even if remote media servers are slow, offline, or return errors.

### Q9: Error model?
**Recommendation: Four-tier error classification.**

| Level | Behavior | Examples |
|-------|----------|----------|
| **Fatal** | Job stops, requires intervention | XML file unreadable, database connection lost |
| **Recoverable item** | Item skipped, job continues, logged | Invalid post type, malformed XML node |
| **Warning** | Item imported with degraded fidelity | Missing parent reference, unknown user slug |
| **Skipped** | Item deliberately skipped, counted | attachment with `fetch_attachments=false`, duplicate |

Each batch returns a result object: `{ processed: 50, skipped: 3, failed: 1, warnings: [...] }`. Job status transitions to `failed` only for fatal errors. Recoverable failures increment a `failed_items` counter.

### Q10: Compatibility promises?
Detailed in `MIGRATION_AND_COMPATIBILITY.md`. In summary:
- Keep plugin slug `wordpress`, folder name `WordPress-Importer-v2`
- Keep `register_importer('wordpress', 'WordPress (v2)', ...)`
- Keep WP-CLI command `wp wxr-importer import`
- Fire `import_start`/`import_end` actions
- Preserve key hooks: `wxr_importer.pre_process.*`, `wxr_importer.processed.*`, `wxr_importer.process_failed.*`
- Deprecate old-style `processed_posts`/`processed_terms` properties (already commented as "TODO: REMOVE")
- Migrate `_wxr_import_settings` and `_wxr_import_info` meta to job table on next import
- Add cleanup for abandoned chunk directories and XML attachment files
