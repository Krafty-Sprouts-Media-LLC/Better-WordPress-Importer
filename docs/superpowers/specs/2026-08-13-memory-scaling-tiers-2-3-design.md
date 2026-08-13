# Memory Scaling — Tier 2 & Tier 3 Design (Not Implemented)

**Date:** 2026-08-13
**Status:** Reference design only. Neither tier is built. See [2026-08-13-durable-import-logging-design.md](2026-08-13-durable-import-logging-design.md) for Tier 1 (auto-disable prefill above a size threshold), which **is** implemented in `WXR_Importer::import_start()`.

## When to build these

Not for imports in the hundreds or low thousands of posts — see the math in the session that produced this doc: a few hundred KB to low single-digit MB of tracking-array overhead at that scale, against a typical 256MB+ `memory_limit`. Build Tier 2 only once real imports are consistently in the tens of thousands of posts, or Tier 3 only if resumability across timeouts/crashes (not just memory) becomes a real requirement.

## Tier 2 — Periodic state flush

### Problem

`WXR_Importer` keeps four structures in PHP instance memory for the entire duration of one `import()` call, each growing by one entry per item processed:

- `$this->mapping` — old ID → new ID, per entity type (`post`, `comment`, `term`, `term_id`, `user`, `user_slug`)
- `$this->exists` — dedup lookup cache, per entity type
- `$this->url_remap` — old attachment URL → new URL
- `$this->requires_remapping` — post/comment IDs needing a second pass

None of it is cleared until the whole run finishes.

### Design

Add a `WXR_Importer_State_Store` (or similar) that each of the four structures reads/writes through instead of a bare array:

- Every N processed items (a `wxr_importer.state_flush_interval` filter, default e.g. 500), flush any array entries added since the last flush to persistent storage, then unset them from the in-memory array.
- On lookup (`isset($this->mapping['post'][$id])`-style checks throughout the class), check the in-memory array first; on miss, check persistent storage before concluding "not found."
- Storage backend, in order of preference:
  1. **Object cache**, if a persistent one is configured (`wp_using_ext_object_cache()`) — zero schema, fast, already the right tool for exactly this (transient key-value lookups during a bounded-lifetime operation).
  2. **A dedicated table** (`wp_wxr_import_state`: columns `import_id`, `entity_type`, `old_id`, `new_id`/`value`) if no object cache is available — needs `dbDelta()` on activation, a tracked schema version option, and cleanup in `uninstall.php` and at the end of a successful import.
  3. Transients as a last resort only — every flush is a write to `wp_options` (must be non-autoloaded), noisier and slower than either option above, but requires no new table.
- Keying: scope every stored entry to the current import's attachment ID, so concurrent/separate imports (or a stale entry from a prior run) never collide.
- Cleanup: delete all state for an import ID when `import_end()` runs. For an import that dies mid-run without reaching `import_end()`, stale state needs either a TTL (object cache/transients handle this natively) or a cron sweep (dedicated table).

### What this buys, and what it doesn't

Bounds memory regardless of import size, in exchange for periodic DB/cache round-trips. Does **not** provide resumability — if the request dies mid-run, the persisted state helps a fresh retry skip re-creating already-imported content (since `post_exists()` etc. still work off the DB, independent of this cache), but there's still no way to resume the XML parse itself from where it stopped; a retry re-reads the file from byte zero. That gap is what Tier 3 closes.

## Tier 3 — Job-queue architecture

### Problem

Beyond memory, the current design has no true resumability: `import()` is one long-running synchronous call inside one HTTP request (or one CLI process). A timeout, crash, or (pre-`ignore_user_abort`) a dropped connection loses all progress; a retry starts over from the beginning of the file.

### Design

Replace the single-request model with three phases, persisted in dedicated tables:

1. **Preflight** — read the WXR file once (already-existing `get_preliminary_information()` is most of this), and additionally record a manifest: one row per entity (post/comment/term/user) with its byte offset in the file, so a later phase can seek directly to entity N instead of re-parsing from the start. Seed a queue table with one row per entity, status `pending`.
2. **Batch processing** — a dispatcher (WP-Cron recurring event, or a client-side polling loop hitting an AJAX endpoint repeatedly) invokes a processor that: opens the file, seeks to the next `pending` queue row's byte offset, processes it, marks it `done` (or `failed`, with the error recorded), and repeats until a time budget (e.g. 20 seconds, filterable) is spent — then returns. The next invocation picks up wherever the queue says work stopped.
3. **Completion** — once no `pending` rows remain, run the existing `post_process()` remapping pass (parent/author/menu-item resolution), then mark the job `complete`.

New tables (schema roughly):
- `wp_wxr_import_jobs` — one row per import: attachment ID, options (mapping, fetch_attachments, etc.), overall status, counts.
- `wp_wxr_import_queue` — one row per entity: job ID, entity type, byte offset, status, error message if failed.
- `wp_wxr_import_log` — structured per-job log (superset of what `WP_Importer_Logger_File` does today, but queryable/filterable by job).

Requires: `dbDelta()` schema + a tracked schema-version option + upgrade routine, activation/uninstall hooks, pause/resume/cancel semantics (a status column the dispatcher checks before each batch), and a real UI rework to show job history rather than a single live progress page.

### Why this is the expensive tier

Every piece above is new, persistent, versioned infrastructure — not a refactor of existing code. It's also the one tier where getting the byte-offset seeking and resume-from-arbitrary-entity logic wrong has real correctness risk (duplicate or skipped entities on resume), so it needs real test coverage (large-file fixtures, forced-timeout/kill tests) before trusting it on production data.

## Relationship to `better-wordpress-importer`

The sibling project at `wp-content/plugins/better-wordpress-importer` already implements a version of Tier 3 (`wp_better_import_jobs`/`_queue`/`_log`, time-based batching, byte-offset manifest). If this plugin ever needs Tier 3, check that codebase first rather than building it a second time from this doc.
