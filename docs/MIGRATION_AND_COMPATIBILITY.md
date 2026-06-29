# Migration and Compatibility: Better WordPress Importer

## Plugin Identity

### Folder Name
**Keep:** `WordPress-Importer-v2`

This is a recognizable, searchable name that distinguishes it from the original `wordpress-importer` plugin. Changing it would break existing installations where users have uploaded it as a ZIP with this folder name.

### Plugin Header (`plugin.php`)
**Keep with minor updates:**

| Field | Current | v3 Recommendation |
|-------|---------|-------------------|
| Plugin Name | Better WordPress Importer | Better WordPress Importer |
| Plugin URI | wordpress.org/extend/plugins/wordpress-importer/ | Update to your fork's URL |
| Version | 2.0.3 | 3.0.0 (major bump for architecture change) |
| Text Domain | wordpress-importer | wordpress-importer (keep) |
| Author | wordpressdotorg, rmccue | Add your organization |
| Description | (same) | Add note about large file + resume support |

### Importer Registration
**Keep as-is:**
```php
register_importer(
    'wordpress',
    'WordPress (v2)',
    __( 'Import <strong>posts, pages, comments, custom fields, categories, and tags</strong> from a WordPress export (WXR) file.', 'wordpress-importer' ),
    array( $GLOBALS['wxr_importer'], 'dispatch' )
);
```

The `wordpress` slug means this replaces the legacy WordPress Importer (which also uses `wordpress`). This is the correct behavior — users should not run both. Document this clearly:
- README: "This plugin replaces the original WordPress Importer. Deactivate the original before activating this one."
- Admin notice on activation: check if the legacy plugin is active, show dismissible notice.

### URL Stability
**Keep:** All admin URLs remain under `admin.php?import=wordpress&step=N`. The job-based architecture changes the internals but the dispatcher routes step 2 to the new progress page.

### WXR_IMPORTER_URL Constant
**Keep:** Defined in `plugin.php:28-30`. Used by templates for asset loading. Unchanged.

---

## Coexistence with Original WordPress Importer

**Not supported.** Both plugins register as `wordpress`. WordPress's importer system only allows one plugin per slug. Recommendation:
1. On activation, check if `wordpress-importer` (the original) is active
2. If so, show admin notice: "Better WordPress Importer replaces the original WordPress Importer. The original has been deactivated."
3. Deactivate the original programmatically (with user notice)
4. Do NOT delete the original — user may want to switch back

If users need both (unlikely), they would need to rename one plugin's folder and change its importer slug — not something we support.

---

## Hook and Filter Compatibility

### WordPress Core Hooks (Keep)
| Hook | Location | v3 Status |
|------|----------|-----------|
| `import_start` | `class-wxr-importer.php:645` | **Keep** — fires at job start |
| `import_end` | `class-wxr-importer.php:673` | **Keep** — fires at job completion |
| `import_post_meta_key` | `class-wxr-importer.php:400` (filter) | **Keep** — still used in batch processing |
| `wp_import_post_data_processed` | `class-wxr-importer.php:982` (filter) | **Keep** |
| `wp_import_insert_post` | `class-wxr-importer.php:1003` (action) | **Keep** |
| `wp_import_post_terms` | `class-wxr-importer.php:1051` (filter) | **Keep** |
| `wp_import_set_post_terms` | `class-wxr-importer.php:1069` (action) | **Keep** |
| `wp_import_post_comments` | `class-wxr-importer.php:1462` (filter) | **Keep** |
| `wp_import_insert_comment` | `class-wxr-importer.php:1559` (action) | **Keep** |
| `wp_import_insert_term` | `class-wxr-importer.php:1958` (action) | **Keep** |
| `wp_import_insert_term_failed` | `class-wxr-importer.php:1929` (action) | **Keep** |
| `import_upload_size_limit` | `class-wxr-import-ui.php:221` (filter) | **Keep** |
| `import_allow_fetch_attachments` | `class-wxr-import-ui.php:1602` (filter) | **Keep** |
| `import_allow_create_users` | `class-wxr-import-ui.php:1611` (filter) | **Keep** |
| `import_attachment_size_limit` | `class-wxr-importer.php:2390` (filter) | **Keep** |

### Custom Hooks (WXR Importer specific)

**Keep (unchanged):**
- `wxr_importer.pre_process.post` — `class-wxr-importer.php:862`
- `wxr_importer.pre_process.post_meta` — `class-wxr-importer.php:1315`
- `wxr_importer.pre_process.comment` — `class-wxr-importer.php:1479`
- `wxr_importer.pre_process.term` — `class-wxr-importer.php:1862`
- `wxr_importer.pre_process.user` — `class-wxr-importer.php:1689`
- `wxr_importer.processed.post` — `class-wxr-importer.php:1089`
- `wxr_importer.processed.comment` — `class-wxr-importer.php:1575`
- `wxr_importer.processed.term` — `class-wxr-importer.php:1966`
- `wxr_importer.processed.user` — `class-wxr-importer.php:1783`
- `wxr_importer.process_failed.post` — `class-wxr-importer.php:1023`
- `wxr_importer.process_failed.term` — `class-wxr-importer.php:1938`
- `wxr_importer.process_failed.user` — `class-wxr-importer.php:1757`
- `wxr_importer.process_already_imported.post` — `class-wxr-importer.php:912`
- `wxr_importer.process_already_imported.comment` — `class-wxr-importer.php:1499`
- `wxr_importer.process_already_imported.term` — `class-wxr-importer.php:1879`
- `wxr_importer.process_skipped.post` — `class-wxr-importer.php:996`

**Deprecate (fired but with deprecation notice):**
- `wxr_importer.ui.header` — `templates/header.php:12`
- `wxr_importer.ui.footer` — `templates/footer.php:7`

**New (v3 additions):**
- `wxr_importer.job.created` — fires when a new import job is created
- `wxr_importer.job.status_changed` — fires when job status transitions
- `wxr_importer.job.completed` — fires when job completes
- `wxr_importer.job.failed` — fires when job fails
- `wxr_importer.job.cancelled` — fires when job is cancelled
- `wxr_importer.batch.processed` — fires after each batch, receives batch result
- `wxr_importer.admin.import_options` — existing, kept

**Removed (no replacement needed):**
- `wxr_importer.admin.import_options` — actually keep this one

**Removed (internal SSE hooks, no external consumers expected):**
- `wxr_importer.processed.post` (SSE progress emitter) — replaced by `wxr_importer.batch.processed`
- `wxr_importer.process_failed.post` (SSE progress emitter) — replaced by `wxr_importer.batch.processed`
- `wxr_importer.process_already_imported.post` (SSE progress emitter) — replaced by `wxr_importer.batch.processed`

Note: The `processed.*` hooks are still fired from the import engine. The SSE progress callbacks that listened to them are removed, but the hooks themselves remain for any external code depending on them.

---

## WP-CLI Command Compatibility

**Keep** `wp wxr-importer import <file>` as primary command.

**Backward compatibility:**
- `wp wxr-importer import file.xml` — unchanged behavior (now uses batch processor under the hood)
- `--verbose=<level>` — unchanged
- `--default-author=<id>` — unchanged

**New (additive, no breaking changes):**
- `wp wxr-importer status [<job_id>]` — show job progress
- `wp wxr-importer cancel <job_id>` — cancel a running job
- `wp wxr-importer list` — list recent jobs
- `wp wxr-importer report <job_id>` — show final report
- `wp wxr-importer clean` — clean temporary files
- `--batch-size=N` (new flag for import)
- `--no-attachments` (new flag for import)
- `--dry-run` (new flag for import)

---

## Legacy Metadata Migration

### Current Meta Keys

| Meta Key | Stored On | Purpose | v3 Status |
|----------|-----------|---------|-----------|
| `_wxr_import_info` | Attachment (import file) | Cached pre-scan data | **Migrate** to job record |
| `_wxr_import_settings` | Attachment (import file) | Import session settings | **Migrate** to job record, then delete |
| `_wxr_is_local_file` | Attachment | Flag for local file cleanup skip | **Keep** on attachment, also store in job |
| `_wxr_import_parent` | Post / Comment | Deferred parent remapping | **Keep** during import, clean up after remap |
| `_wxr_import_user_slug` | Post | Deferred author remapping | **Keep** during import, clean up after remap |
| `_wxr_import_user` | Comment | Deferred comment author remapping | **Keep** during import, clean up after remap |
| `_wxr_import_has_attachment_refs` | Post | Flag for URL remap | **Keep** during import, clean up after remap |
| `_wxr_import_term` | Post | Deferred term assignment | **Keep** during import, clean up after remap |
| `_wxr_import_menu_item` | Post (nav_menu_item) | Deferred menu item object remap | **Keep** during import, clean up after remap |

### Migration Strategy

**On plugin update (2.x → 3.0):**
1. Any existing `_wxr_import_settings` meta on attachments indicates an in-progress import from v2.x. These imports cannot be resumed in v3 (incompatible state format). On update:
   - Delete all `_wxr_import_settings` meta
   - Show admin notice: "Better WordPress Importer has been updated to v3. Any in-progress imports have been cancelled. Already-imported content is preserved."
2. All temporary `_wxr_import_*` meta from completed imports should be cleaned. Provide a cleanup tool:
   - `wp wxr-importer clean` removes all `_wxr_import_*` meta from posts and comments
   - Admin button: "Clean up legacy import data" in Tools → Import → WordPress (v2)

**On new import (v3):**
- Pre-scan data goes into `wxr_import_jobs.preflight_data` (JSON)
- Settings go into `wxr_import_jobs.options` (JSON)
- Item-level state goes into `wxr_import_items` table
- Deferred remapping markers still use post meta temporarily (same keys), cleaned up after remapping completes
- Attachments still use `_wxr_is_local_file` for cleanup logic

---

## File Cleanup

### Temporary Upload Files

**Chunk directories** (`wp-content/uploads/wxr-importer-chunks/`):
- Created during chunked uploads
- Should be cleaned: after chunk assembly (done), on cron daily for abandoned dirs
- Not created if chunked upload is not used

**Browser-uploaded XML attachments:**
- Created by Plupload, stored as private attachments
- Currently deleted after import completion (`stream_import()` cleanup)
- In v3: deleted after job completion or cancellation
- If PHP crashes mid-import: file remains. Cleaned by `wp wxr-importer clean` or daily cron.

**Local-path file attachments:**
- Attachment record deleted after import, but physical file is kept (user placed it there)
- Behavior unchanged

### Diagnostic Log
`wxr-upload-debug.log` in plugin root:
- **Remove entirely** in Phase 1 (see REBUILD_PLAN.md)
- Replace with WP_DEBUG_LOG integration
- Existing log file should be deleted on plugin update

---

## Database Schema Versioning

Track schema version in `wp_options`:
```php
update_option('wxr_importer_db_version', 1);
```

On plugin activation/update:
```php
$current = get_option('wxr_importer_db_version', 0);
if ($current < 1) {
    // Create wp_wxr_import_jobs and wp_wxr_import_items tables
    update_option('wxr_importer_db_version', 1);
}
```

---

## Uninstall Behavior

`uninstall.php`:
1. Drop `{$wpdb->prefix}wxr_import_jobs` table
2. Drop `{$wpdb->prefix}wxr_import_items` table
3. Delete all `_wxr_import_*` post meta and comment meta
4. Delete `wxr-importer-chunks` directory from uploads
5. Delete `wxr_importer_db_version` option
6. Delete any orphaned private attachments with `application/xml` mime type created by the importer
7. Do NOT delete user-imported content (posts, terms, comments) — those are now site content

---

## Upgrade Path Summary

| From | To | Action |
|------|----|--------|
| Original WP Importer | v3 | Deactivate original, activate v3 |
| v2.0.x | v3.0 | Update plugin, cancel in-progress imports, run cleanup |
| v3.0 (db schema v1) | v3.1 | Schema migration handled by version check |

---

## Deprecation Schedule

| Feature | Deprecated In | Removal In | Replacement |
|---------|---------------|------------|-------------|
| SSE streaming (`stream_import()`) | v3.0 | v4.0 | Batch processor + polling |
| `WP_Importer_Logger_ServerSentEvents` | v3.0 | v4.0 | Polling status endpoint |
| `emit_sse_message()` | v3.0 | v4.0 | REST response |
| `_wxr_import_settings` meta | v3.0 | v4.0 | `wxr_import_jobs` table |
| `processed_posts`/`processed_terms` properties | v3.0 | v3.1 | `mapping` arrays (already unused) |
| `log_upload_debug()` | v2.0.4 | v3.0 | WP_DEBUG_LOG integration |
