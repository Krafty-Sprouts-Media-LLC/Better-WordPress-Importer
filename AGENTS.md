# AGENTS.md — Better WordPress Importer

> **Purpose:** This file instructs AI coding agents (and human contributors) on how to work with this codebase. It supplements the skill files in `/.agents/` with project-specific rules derived from the architecture audit.

## Project identity

- **Plugin:** Better WordPress Importer (`WordPress-Importer-v2`)
- **Registered as:** `wordpress` (Tools → Import → WordPress (v2))
- **Fork of:** [humanmade/WordPress-Importer](https://github.com/humanmade/WordPress-Importer)
- **Text Domain:** `wordpress-importer`
- **Target:** WordPress 5.0+ | PHP 7.4+
- **License:** GPLv2+

## Audit status (June 2026)

The full codebase was audited. See `docs/ARCHITECTURE_AUDIT.md`. Key findings driving the rebuild:

1. **The single SSE request architecture is fragile** — imports over a few thousand items fail due to timeouts (FastCGI, proxy, browser). This is the root cause of all reliability issues.
2. **No true resumability** — the auto-reconnect JS patch is cosmetic. Progress resets on reconnect.
3. **Diagnostic log writes to plugin root** (`wxr-upload-debug.log`) — web-accessible, no rotation. Must be moved to `WP_DEBUG_LOG` integration.
4. **Cron/job architecture is the target** — not another long-running request.

There are uncommitted emergency patches in the working tree (chunked upload, reconnect, diagnostics). These are temporary. Do not build on them — they will be replaced in the rebuild.

## Reference: skill files in `/.agents/`

Load the relevant skill before writing code in that domain:

| Skill | When |
|-------|------|
| `wp-plugin-development` | Plugin architecture, hooks, Settings API, activation/uninstall |
| `wp-standards.md` | File headers, docblocks, naming, escaping, sanitization, security checklist |
| `wp-performance` | Performance profiling, caching, query optimization |
| `wp-wpcli-and-ops` | WP-CLI commands, deployment, testing |
| `wp-rest-api` | REST API endpoints (if added) |
| `wp-plugin-directory-guidelines` | wp.org submission compliance |
| `blueprint` | Playground blueprints |

## Workflow: rebuild phases

Development follows the phased plan in `docs/REBUILD_PLAN.md`. Current phase is **Phase 1** (stabilize for controlled testing). Next phases:

```
Phase 1 → Phase 2 (Job Model) → Phase 3 (Batch Processor) → Phase 4 (UI Rebuild) → Phase 5 (WP-CLI) → Phase 6 (Cleanup)
```

Always check `docs/REBUILD_PLAN.md` for the current phase's acceptance criteria before writing code.

## Coding standards

### PHP

#### File headers (`@since` is mandatory)

Every PHP file must start with a file-level docblock:

```php
<?php
/**
 * [ One-line summary of what this file/class is for. ]
 *
 * @package WordPress_Importer_v2
 * @since 3.0.0
 */
```

For the main plugin file (`plugin.php`), use the full WordPress plugin header:

```php
<?php
/**
 * Plugin Name: Better WordPress Importer
 * Plugin URI:  https://github.com/Krafty-Sprouts-Media-LLC/KSM-WordPress-Importer
 * Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file. Resumable, batch-based, large-file safe. Fork of humanmade/WordPress-Importer.
 * Version:     3.0.0
 * Author:      Krafty Sprouts Media, LLC
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordpress-importer
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WordPress_Importer_v2
 * @since 3.0.0
 */

defined( 'ABSPATH' ) || exit;
```

#### Class files

Every class file needs both a file-level and class-level docblock:

```php
<?php
/**
 * Import job model — represents one import session.
 *
 * Replaces the fragile SSE-based import with persisted job state
 * that survives timeouts, browser closes, and server restarts.
 *
 * @package WordPress_Importer_v2
 * @since 3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import job model.
 *
 * @since 3.0.0
 */
class WXR_Import_Job {
```

#### Public methods

Every `public` method must have a complete PHPDoc block:

```php
/**
 * Create a new import job from an uploaded WXR file.
 *
 * Performs a preflight scan, records item counts and byte offsets,
 * and persists job state to the database.
 *
 * @since 3.0.0
 *
 * @param int    $attachment_id WordPress attachment ID of the WXR file.
 * @param string $file_path     Absolute filesystem path to the WXR file.
 * @param array  $options       Import options (author mapping, fetch_attachments, etc.).
 *
 * @return WXR_Import_Job|WP_Error Job instance on success, error otherwise.
 */
public static function create( int $attachment_id, string $file_path, array $options = array() ) {
```

#### Protected/private methods

Document non-trivial methods. Simple helpers (<10 lines, obvious purpose) can skip the full docblock but should still have `@since`:

```php
/**
 * Advance the XML reader to the item at the given index in the manifest.
 *
 * Uses pre-recorded byte offsets from the preflight scan for O(1) seeks.
 * Falls back to sequential reading if the manifest is missing.
 *
 * @since 3.0.0
 *
 * @param XMLReader $reader       Active XML reader instance.
 * @param array     $manifest     Item manifest from preflight scan.
 * @param int       $target_index Zero-based item index to advance to.
 *
 * @return bool True on success, false if the reader reached EOF.
 */
protected function seek_to_item( XMLReader $reader, array $manifest, int $target_index ): bool {
```

#### `@since` tag rules

| Location | Required | Value |
|----------|----------|-------|
| File-level docblock | Yes | `@since 3.0.0` |
| Class docblock | Yes | `@since 3.0.0` |
| Public method | Yes | `@since 3.0.0` |
| Protected/private non-trivial | Yes | `@since 3.0.0` |
| Hook registration comment | Yes | `@since 3.0.0` |
| Property | Recommended | `@since 3.0.0` |
| New methods after 3.0.0 | Yes | `@since 3.1.0` (actual version) |

#### Properties

```php
/**
 * Mapping of old entity IDs to new local IDs.
 *
 * Structure: { entity_type: { old_id: new_id, ... }, ... }
 * Entity types: 'post', 'comment', 'term', 'term_id', 'user', 'user_slug'.
 *
 * @since 3.0.0
 * @var array<string, array<int, int>>
 */
protected array $mapping = array();
```

#### Inline comments

Rule: explain **why**, not what. Never add comments that restate the code.

```php
// Use array_key_exists, not isset — isset returns false for null values
// and we need to preserve explicitly saved null mappings.
if ( array_key_exists( $old_id, $this->mapping['post'] ) ) {
```

Never:
- Section dividers (`// ============ SETTINGS ============`)
- Disabled/old code (delete it — git has the history)
- TODO markers — use `// @todo Issue #N: Description` if you must

### Naming

| Element | Style | Example | Prefix |
|---------|-------|---------|--------|
| Class | PascalCase | `WXR_Import_Job` | `WXR_Import_` |
| Method | snake_case | `get_job_by_id()` | — |
| Variable | snake_case | `$import_job` | — |
| Constant | UPPER_SNAKE | `MAX_WXR_VERSION` | — |
| Hook (action) | `wxr_importer.` | `wxr_importer.job.completed` | `wxr_importer.` |
| Hook (filter) | `wxr_importer.` | `wxr_importer.admin.import_options` | `wxr_importer.` |
| AJAX action | `wxr_` | `wxr-import-status` | `wxr-` |
| Nonce action | `wxr.` | `wxr.import:123` | `wxr.` |
| DB table | `wxr_import_` | `wxr_import_jobs` | `wxr_import_` |
| Option key | `wxr_importer_` | `wxr_importer_db_version` | `wxr_importer_` |
| Post meta (temp) | `_wxr_import_` | `_wxr_import_parent` | `_wxr_import_` |
| Transient | `wxr_import_` | `wxr_import_job_lock_123` | `wxr_import_` |

### Escaping output (always)

```php
echo esc_html( $message );            // HTML text content
echo esc_attr( $value );              // HTML attribute
echo esc_url( $url );                 // URL in href/src
echo esc_textarea( $text );           // Textarea content
wp_kses( $html, $allowed_tags );      // Trusted HTML with known tag whitelist
```

### Sanitizing input (always)

```php
sanitize_text_field( wp_unslash( $_POST['name'] ) );  // Text
absint( $_GET['id'] );                                  // Integer
sanitize_key( wp_unslash( $_POST['key'] ) );           // Slugs/keys
esc_url_raw( wp_unslash( $_POST['url'] ) );            // URL for storage
```

Always `wp_unslash()` before sanitize.

### Security checklist (per feature)

- [ ] Nonce verification on all forms and AJAX endpoints
- [ ] `current_user_can( 'import' )` or `current_user_can( 'upload_files' )` on admin actions
- [ ] Input sanitized with appropriate WordPress function
- [ ] Output escaped with appropriate WordPress function
- [ ] `$wpdb->prepare()` for all SQL queries with variables
- [ ] No user input concatenated into SQL strings
- [ ] `wp_safe_redirect()` + `exit` for redirects
- [ ] File paths validated with `realpath()` + boundary checks (stay inside uploads)

## Database: custom tables vs. post meta

The rebuild introduces custom tables (`wp_wxr_import_jobs`, `wp_wxr_import_items`). Rules:

- Table creation: `dbDelta()` in an activation hook
- Schema version tracked in `wp_options` (`wxr_importer_db_version`)
- Upgrade routine checks version and applies migrations
- `uninstall.php` drops tables and cleans meta
- Legacy `_wxr_import_*` post meta remains as the temporary remapping mechanism during imports

## Testing

Tests live in `tests/` and use PHPUnit via WordPress's test suite:

```bash
# Install test suite (one-time)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
phpunit
```

Test conventions:
- One test file per source class: `tests/test-class-wxr-importer.php`
- Fixtures in `tests/fixtures/` (XML files, not committed unless small)
- `WP_UnitTestCase` base class provides DB transaction rollback
- Test method naming: `test_method_name_scenario()` — e.g. `test_process_post_duplicate_skipped()`

## Git workflow

- **Do not commit** until explicitly asked
- Uncommitted emergency patches (visible in `git diff`) will be replaced in the rebuild — do not build on them
- Commit messages: imperative, present tense, 50-char subject line
- The `.gitignore` currently blocks `*.xml`; this needs updating so test fixtures can be committed

## Key files reference

| File | Role | Status |
|------|------|--------|
| `plugin.php` | Bootstrap, registration, hooks | Active — will get new AJAX endpoints |
| `class-wxr-importer.php` | Import engine (parsing, dedup, remapping) | Keep logic, add `import_batch()` |
| `class-wxr-import-ui.php` | Admin UI controller | Rewrite for job-based UI |
| `class-command.php` | WP-CLI command | Enhance with subcommands |
| `class-wxr-import-info.php` | Pre-scan DTO | Extend with item manifest |
| `class-logger.php` | Logger abstraction | Keep as-is |
| `class-logger-serversentevents.php` | SSE logger | Deprecate in Phase 6 |
| `class-logger-cli.php` | CLI logger | Keep as-is |
| `class-logger-html.php` | HTML logger | Keep as-is |
| `templates/*.php` | Admin page templates | Rewrite in Phase 4 |
| `assets/import.js` | SSE consumer + progress UI | Replace with polling-based JS |
| `assets/intro.js` | Plupload upload UI | Keep with minor fixes |
| `assets/import.css` | Progress page styles | Extend for new UI |
| `assets/intro.css` | Upload page styles | Keep with minor fixes |

## Priority order for fixes

1. **Must fix before production:** Diagnostic log removal (S3.1), chunk dir web protection (S3.2)
2. **Should fix during rebuild:** Architecture to job-based (R2.1), true resume (R2.2), state persistence (R2.3)
3. **Nice to have:** Cache flush configurability (P4.3), prefill query optimization (P4.2)
