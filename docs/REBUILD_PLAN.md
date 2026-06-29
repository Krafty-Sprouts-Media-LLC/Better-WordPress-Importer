# Rebuild Plan: Better WordPress Importer

This plan describes a phased rebuild from the current fragile SSE-based architecture to a resumable, job-based importer.

---

## Phase 0: Safe Test Setup and Database Snapshot Strategy

**Goal:** Create a safe, repeatable test environment so changes can be validated against real WXR files without risk to production data.

**Files touched:** None (tooling/infra only).

**Implementation steps:**
1. Create a disposable local WordPress site (LocalWP site clone or `wp-env`).
2. Take a database snapshot before every test run: `wp db export pre-import.sql`.
3. Create a test harness script (`bin/test-import.sh`) that:
   - Restores the DB snapshot
   - Resets the plugin to a known state
   - Runs the import (via WP-CLI for batch, via UI for manual)
   - Records: start time, end time, post counts, term counts, comment counts
   - Writes results to `test-results/{date}-{file}.json`
4. Prepare test XML files:
   - Small: < 100 items (quick smoke test)
   - Medium: ~1,000 items (normal use)
   - Large: `healthtian.WordPress.2020-03-06.xml` (~4,597 posts from project context)
   - Invalid: Malformed XML, missing required fields, corrupted nodes
5. Install WordPress unit test suite (`bin/install-wp-tests.sh` already exists).

**Risks:** Low. This is tooling only.

**Acceptance criteria:**
- `wp db import pre-import.sql` restores database to identical state
- Test harness completes and records results automatically
- All three XML test files are available and committed to test fixtures (or `.gitignore`'d)

**Rollback:** Delete the disposable site.

---

## Phase 1: Stabilize Current Importer for Controlled Testing

**Goal:** Fix critical security and reliability issues in the current code so it can serve as a baseline for testing, without changing architecture.

**Files likely touched:**
- `class-wxr-import-ui.php` — diagnostic log relocation
- `class-wxr-importer.php` — minor hardening
- `class-wxr-import-info.php` — extend for item index
- `plugin.php` — version bump to 2.0.4
- `CHANGELOG.md` — entries

**Implementation steps:**
1. **Remove `log_upload_debug()` from plugin root** (S3.1).
   - Either gate it behind `WP_DEBUG && defined('WXR_IMPORTER_DIAGNOSTICS')`
   - Or write to `wp-content/uploads/wxr-debug/` with `.htaccess` + `index.php` protection
   - Or integrate with `error_log()` so it goes to `WP_DEBUG_LOG`
2. **Add chunk directory protection for nginx/IIS.**
   - Add `location = /wp-content/uploads/wxr-importer-chunks/ { deny all; }` documentation
   - Add `web.config` generation for IIS alongside `.htaccess`
3. **Add cleanup cron for abandoned chunk directories.**
   - Hook into `wp_scheduled_delete` to remove chunk dirs older than 1 day
4. **Fix `xml` extension in `.gitignore`** — currently blocks all `*.xml`, which would block test fixture XMLs. Use `/tests/fixtures/*.xml` pattern or remove.
5. **Extend `get_preliminary_information()` to record item byte offsets** (preparation for Phase 2).
   - During the lightweight scan, track `$reader->nodeType === XMLReader::ELEMENT && $reader->name === 'item'` byte positions
   - Store as `item_positions` array in `WXR_Import_Info`
   - This does not change the scan's O(n) nature and adds trivial memory (~10 bytes per item)
6. **Make cache-flush interval configurable.**
   - Add `'cache_flush_interval' => 200` to `WXR_Importer` options
7. **Version bump to 2.0.4** with changelog entries.

**Risks:**
- Changing diagnostic log path may surprise a developer relying on the current path (low — this is a local fork)
- Extending `_wxr_import_info` stored meta may need a migration path if old imports exist (low — the meta is temporary, re-parsed on each import)

**Acceptance criteria:**
- `wxr-upload-debug.log` is no longer written to plugin root directory
- Chunk directories older than 1 day are cleaned up by WordPress cron
- `WXR_Import_Info` contains `item_positions` array after pre-scan
- Existing import flow still works end-to-end
- All existing tests pass (the one test that exists)

**Rollback:** Git revert the phase; no database schema changes.

---

## Phase 2: Introduce Import Job Model

**Goal:** Create the database tables and PHP classes for a job-based import system that can persist state across requests.

**Files likely touched (new):**
- `class-wxr-import-job.php` — job model (represents one import session)
- `class-wxr-import-item.php` — item model (represents one entity to import)
- `class-wxr-import-job-repository.php` — DB access layer
- `install.php` — table creation on plugin activation

**Files likely touched (modified):**
- `plugin.php` — load new classes, register activation hook
- `class-wxr-importer.php` — add batch processing mode (new method `import_batch()`)
- `CHANGELOG.md`

**Implementation steps:**

### 2.1 Database schema
```sql
CREATE TABLE {$wpdb->prefix}wxr_import_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    file_path VARCHAR(500) NOT NULL,
    xml_cursor_item INT UNSIGNED NOT NULL DEFAULT 0,
    total_posts INT UNSIGNED NOT NULL DEFAULT 0,
    total_comments INT UNSIGNED NOT NULL DEFAULT 0,
    total_terms INT UNSIGNED NOT NULL DEFAULT 0,
    total_users INT UNSIGNED NOT NULL DEFAULT 0,
    total_media INT UNSIGNED NOT NULL DEFAULT 0,
    processed_posts INT UNSIGNED NOT NULL DEFAULT 0,
    processed_comments INT UNSIGNED NOT NULL DEFAULT 0,
    processed_terms INT UNSIGNED NOT NULL DEFAULT 0,
    processed_users INT UNSIGNED NOT NULL DEFAULT 0,
    processed_media INT UNSIGNED NOT NULL DEFAULT 0,
    failed_items INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_items INT UNSIGNED NOT NULL DEFAULT 0,
    options LONGTEXT DEFAULT NULL,
    preflight_data LONGTEXT DEFAULT NULL,
    item_manifest LONGTEXT DEFAULT NULL,
    user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY status (status),
    KEY user_id (user_id)
) {$charset_collate};

CREATE TABLE {$wpdb->prefix}wxr_import_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    old_id VARCHAR(100) NOT NULL DEFAULT '',
    new_id BIGINT UNSIGNED DEFAULT NULL,
    title VARCHAR(500) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    KEY job_status (job_id, status),
    KEY job_type (job_id, entity_type)
) {$charset_collate};
```

### 2.2 Job model class
```php
class WXR_Import_Job {
    public $id, $status, $file_path, $xml_cursor_item;
    public $total_posts, $total_comments, $total_terms, $total_users, $total_media;
    public $processed_posts, $processed_comments, $processed_terms, $processed_users, $processed_media;
    public $failed_items, $skipped_items;
    public $options, $preflight_data, $item_manifest;
    public $user_id, $created_at, $updated_at;

    // Derived
    public function total_items() { return $this->total_posts + $this->total_comments + $this->total_terms + $this->total_users + $this->total_media; }
    public function processed_items() { return $this->processed_posts + $this->processed_comments + $this->processed_terms + $this->processed_users + $this->processed_media; }
    public function percent_complete() { return $this->total_items() > 0 ? min(100, round(($this->processed_items() / $this->total_items()) * 100)) : 0; }
    public function is_running() { return in_array($this->status, ['scanning', 'processing', 'remapping']); }
    public function is_terminal() { return in_array($this->status, ['complete', 'failed', 'cancelled']); }
}
```

### 2.3 Batch processing method in WXR_Importer
Add `import_batch($file, $start_item_index, $batch_size, $item_manifest)`:
- Open XML, scan (or seek using manifest) to item at `$start_item_index`
- Process `$batch_size` items
- Return result: `{ processed, skipped, failed, warnings, next_item_index, is_complete }`
- Reuse existing `process_post()`, `process_term()`, `process_comment()`, `process_author()` methods
- Track remapping state in job meta (serialize `$this->mapping` arrays to job options after each batch)

### 2.4 Activation hook
Create table on plugin activation. Use `dbDelta()` for schema management. Store schema version in `wp_options`.

**Risks:**
- Table creation may fail on some hosts with restricted database permissions — handle gracefully, fall back to post-meta-based storage if needed
- Manifest for very large files (50k+ items) may be large JSON — validate size before storing

**Acceptance criteria:**
- Tables created on plugin activation
- `WXR_Import_Job` can be created, updated, and queried
- `WXR_Importer::import_batch()` processes a slice of items correctly
- Existing SSE import path still works (not yet removed)
- WP-CLI import still works (not yet migrated)

**Rollback:** Deactivate plugin, drop tables, revert code. No existing data depends on these tables.

---

## Phase 3: Batch/Resumable Processing Engine

**Goal:** Replace the single long request with a WP-Cron-driven batch processor that persists progress and resumes after interruption.

**Files likely touched (new):**
- `class-wxr-import-processor.php` — orchestrates batch processing
- `class-wxr-import-remapper.php` — handles post-processing remapping in batches

**Files likely touched (modified):**
- `class-wxr-importer.php` — mapping state serialization, batch import
- `class-wxr-import-job.php` — add status transition methods
- `class-wxr-import-ui.php` — new `stream_import()` using batches; add polling endpoint
- `class-command.php` — use batch processor
- `plugin.php` — register cron hooks
- `assets/import.js` — polling-based progress instead of SSE
- `templates/import.php` — updated JS config

**Implementation steps:**

### 3.1 Batch processor (`class-wxr-import-processor.php`)
```
WXR_Import_Processor:
  process_job(job_id):
    1. Load job from DB
    2. If job.is_terminal() → return
    3. Load item manifest from job
    4. Create WXR_Importer with saved mapping state (from job options)
    5. Call importer->import_batch(file, cursor, batch_size=10, manifest)
    6. Save mapping state back to job options
    7. Update job counters
    8. If batch.is_complete → finalize job
    9. Else → schedule next cron in 10 seconds
    10. Return batch result
```

### 3.2 Cron integration
- Register a custom cron interval: `wxr_importer_batch_interval` (every 30 seconds)
- Hook: `wxr_importer_process_batch` — picks up one pending job, processes one batch
- Admin-ajax accelerator endpoint: `wp_ajax_wxr-import-batch` — processes a batch immediately (triggered by JS polling or "Process Now" button)
- Rate limit: maximum 1 batch per 5 seconds per job
- Lock mechanism: `get_transient('wxr_import_job_lock_' . $job_id)` prevents concurrent batch execution

### 3.3 State serialization
After each batch, serialize mapping arrays to job options:
- `mapping.post`, `mapping.comment`, `mapping.term`, `mapping.term_id`, `mapping.user`, `mapping.user_slug`
- `requires_remapping.post`, `requires_remapping.comment`
- `url_remap`, `featured_images`

On resume, deserialize from job options into `WXR_Importer::$mapping` etc.

### 3.4 Post-processing remapping (Phase 3b)
- After `xml_cursor_item >= total_items`, job enters `remapping` status
- Remapping processes `requires_remapping.post` and `requires_remapping.comment` in batches
- URL remapping and featured image remapping run as separate batch steps
- Each remapping batch saves progress (tracks which post IDs have been processed)

### 3.5 Attachment download isolation (Phase 3c)
- During content import, attachments are marked as pending with original URL stored
- After all content is imported, a separate processor downloads attachments 3 at a time
- Attachment failures are recorded but do not block job completion
- Jobs that have content done but attachments pending get status `complete` with a sub-status `attachments_pending`

### 3.6 JS changes
- Replace `EventSource` with `setInterval` polling to `admin-ajax.php?action=wxr-import-status&job_id=N`
- Poll interval: 2 seconds while processing, 10 seconds if idle
- Status response includes: counters, percent, current phase, last 5 log entries
- Cancel button: POST to `admin-ajax.php?action=wxr-cancel-import` (already exists)
- Show "Connection lost — processing continues on server" if poll fails (not "import interrupted")

### 3.7 WP-CLI changes
```php
// class-command.php: replace single import() call with batch loop
while (!$job->is_terminal()) {
    $processor->process_job($job->id);
    WP_CLI::log(sprintf('Progress: %d/%d items (%d%%)', 
        $job->processed_items(), $job->total_items(), $job->percent_complete()
    ));
}
```
Add `--batch-size=N` and `--no-attachments` flags.

**Risks:**
- Serialized mapping arrays may be large for very big imports (100k+ posts) — use compression, split across multiple option rows, or store in a dedicated table
- Cron on low-traffic sites may not fire reliably — the JS polling provides a backup trigger
- Lock mechanism must handle PHP crashes leaving stale locks — use short transients (60s) rather than persistent locks
- `XMLReader` fast-forward to cursor position is O(n) — acceptable for network interruptions (rare) but document that WP-CLI is preferred for huge files

**Acceptance criteria:**
- Import starts, processes 10 items per batch, persists state, and continues in next cron run
- Browser refresh during import: progress is recovered, not reset
- Server kill during import: cron picks up where it left off within 30 seconds
- Re-running a completed import: detected, job finishes immediately with 0 new items
- WP-CLI import processes all batches synchronously without cron
- Attachment failures do not prevent content import completion
- Final remapping runs after all content is imported

**Rollback:** The old SSE path in `stream_import()` is preserved until Phase 6. Switching back is a code revert.

---

## Phase 4: Admin UI/UX Rebuild

**Goal:** Build a job-centric admin interface that exposes the new capabilities: pause/resume, progress with rate, final report, error filtering.

**Files likely touched (new):**
- `assets/job-status.js` — polling-based progress UI
- `templates/job-progress.php` — new progress page template

**Files likely touched (modified):**
- `class-wxr-import-ui.php` — new dispatch routes for job progress, status endpoint, final report
- `templates/import.php` — replaced by job-progress.php
- `assets/import.css` — extended styles
- `plugin.php` — new AJAX endpoints

**Implementation steps:**
1. Add AJAX endpoint `wxr-import-status` returning full job state + recent log entries.
2. Add AJAX endpoint `wxr-import-pause` / `wxr-import-resume` (sets job status).
3. Build new progress page showing:
   - Status banner: "Importing (batch 47 of 460)..." with animated indicator
   - Overall progress bar with percentage and "X of Y items"
   - Per-type stat cards: posts, media, users, comments, terms — each showing done/total
   - Elapsed time counter
   - Current rate: "~12 items/min"
   - Warnings count with expandable list
   - Phase indicator: "Processing posts → Remapping → Downloading attachments"
4. Final report page (reached when job status = complete):
   - Summary: imported X, skipped Y, failed Z
   - Per-type breakdown
   - Warnings list
   - "Download full log" button
   - "Run another import" link
5. Error filter UI: tabs for All | Warnings | Errors | Skipped.
6. Large import preflight warnings:
   - If > 1000 items: "This is a large import. Processing will continue in the background. You can close this page and return later."
   - If > 5000 items: "Very large import detected. Consider using WP-CLI: `wp wxr-importer import /path/to/file.xml`"
7. Safe rerun messaging:
   - On the intro page, after a previous import: "You previously imported X items. Running another import will skip duplicates automatically."
   - Link to view last import report.

**Risks:**
- Existing users rely on the current 3-step flow — keep steps 0-1 (upload + author mapping) unchanged
- Polling many items at once could be slow — paginate responses, return deltas since last poll

**Acceptance criteria:**
- User can upload XML, configure authors, start import
- Progress updates in near-real-time (2s poll interval)
- User can close browser and return — progress is recovered
- User can cancel import — items already processed are preserved
- Final report shows accurate counts matching database
- Rerun message appears correctly for repeat imports

**Rollback:** The old `templates/import.php` + SSE JS is preserved in version control. The new UI routes can coexist behind a feature flag (`?v2=1`) during testing.

---

## Phase 5: WP-CLI and Automation Support

**Goal:** Make WP-CLI the recommended path for very large imports, with full feature parity with the web UI.

**Files likely touched:**
- `class-command.php` — expanded with subcommands
- `class-wxr-import-processor.php` — CLI-compatible progress output

**Implementation steps:**
1. Add subcommands:
   - `wp wxr-importer import <file>` — full import (existing, enhanced)
   - `wp wxr-importer status [<job_id>]` — show job progress
   - `wp wxr-importer cancel <job_id>` — cancel a running job
   - `wp wxr-importer list` — list recent jobs
   - `wp wxr-importer report <job_id>` — show final report
   - `wp wxr-importer clean` — clean up abandoned chunk dirs and temporary files

2. Add flags to import:
   - `--batch-size=50` — items per batch
   - `--no-attachments` — skip attachment download
   - `--user-mapping='{"olduser":"newuser"}'` — JSON author mapping
   - `--default-author=1` — already exists
   - `--verbose=debug` — already exists

3. Progress bar for CLI:
   - Integrate with `WP_CLI\Utils\make_progress_bar()` for visual feedback
   - Show rate, elapsed, ETA

4. Add `--dry-run` flag:
   - Performs pre-scan and validation only
   - Reports item counts, potential issues, validates XML
   - Does not modify database

**Risks:**
- Changing the CLI command signature could break scripts — keep backward compatibility, only add new optional flags

**Acceptance criteria:**
- `wp wxr-importer import large-file.xml --batch-size=50` completes successfully
- `wp wxr-importer status` shows running job progress
- `wp wxr-importer cancel` stops a running job cleanly
- `wp wxr-importer import --dry-run` validates XML without importing

**Rollback:** The old `class-command.php` behavior is preserved. New subcommands are additive.

---

## Phase 6: Migration and Cleanup from Legacy Internals

**Goal:** Remove deprecated code paths, clean up temporary data, and finalize the migration from old-style meta storage to job tables.

**Files likely touched:**
- `class-wxr-importer.php` — remove old `stream_import()` SSE path, remove deprecated properties
- `class-wxr-import-ui.php` — remove SSE streaming, remove Plupload direct handling (use new upload handler)
- `plugin.php` — remove old AJAX hooks, add new ones
- `assets/import.js` — remove SSE code, keep polling code

**Implementation steps:**
1. Migrate any in-progress `_wxr_import_settings` meta to job records on plugin update.
2. Remove old SSE streaming code:
   - `WP_Importer_Logger_ServerSentEvents` — deprecated, kept for reference
   - `stream_import()` — replaced
   - `emit_sse_message()` — replaced
   - `imported_post()`, `imported_comment()`, `imported_term()`, `imported_user()`, `already_imported_post()` — replaced by polling status endpoint
3. Remove deprecated class properties:
   - `WXR_Importer::$processed_terms` — unused
   - `WXR_Importer::$processed_posts` — unused
   - `WXR_Importer::$processed_menu_items` — unused
   - `WXR_Importer::$menu_item_orphans` — unused
   - `WXR_Importer::$missing_menu_items` — unused
4. Remove `log_upload_debug()` entirely or move to dev-only behind a constant.
5. Add cleanup of legacy `_wxr_import_*` post meta keys (optional, run on plugin activation).
6. Ensure chunked upload temporary directories are cleaned up on uninstall.
7. Add uninstall.php that:
   - Drops `wp_wxr_import_jobs` and `wp_wxr_import_items` tables
   - Removes all `_wxr_import_*` post meta
   - Deletes `wxr-importer-chunks` directory
   - Removes plugin options

**Risks:**
- Removing SSE path may break existing in-progress imports during plugin update — add a migration notice explaining that in-progress imports will be cancelled
- Users relying on SSE hooks for custom integrations need migration path — document replacement hooks

**Acceptance criteria:**
- All legacy `_wxr_import_*` meta is migrated or cleaned up
- No SSE-related code remains in active paths
- Uninstall leaves no database residue
- Plugin update from 2.0.x to 3.0 is documented with upgrade notes

**Rollback:** Full plugin downgrade requires restoring from backup. This is a major version bump (3.0).

---

## Summary: File Map by Phase

| File | Phase 0 | Phase 1 | Phase 2 | Phase 3 | Phase 4 | Phase 5 | Phase 6 |
|------|---------|---------|---------|---------|---------|---------|---------|
| `plugin.php` | | Modify | Modify | Modify | Modify | | Modify |
| `install.php` | | | **New** | | | | Modify |
| `uninstall.php` | | | | | | | **New** |
| `class-wxr-import-job.php` | | | **New** | Modify | | | |
| `class-wxr-import-item.php` | | | **New** | | | | |
| `class-wxr-import-job-repository.php` | | | **New** | Modify | Modify | | |
| `class-wxr-import-processor.php` | | | | **New** | Modify | Modify | |
| `class-wxr-import-remapper.php` | | | | **New** | | | |
| `class-wxr-importer.php` | | Modify | Modify | Modify | | | Modify |
| `class-wxr-import-ui.php` | | Modify | | Modify | Modify | | Modify |
| `class-wxr-import-info.php` | | Modify | | | | | |
| `class-command.php` | | | | Modify | | Modify | |
| `class-logger-serversentevents.php` | | | | | | | Deprecate |
| `class-logger.php` | | | | | | | |
| `templates/import.php` | | | | | Replace | | |
| `templates/job-progress.php` | | | | | **New** | | |
| `templates/intro.php` | | | | | Modify | | |
| `assets/import.js` | | | | Modify | Replace | | Remove old |
| `assets/job-status.js` | | | | | **New** | | |
| `tests/*` | Modify | Modify | Modify | Modify | Modify | Modify | Modify |
