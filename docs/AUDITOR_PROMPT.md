# Auditor Prompt: WordPress Importer v2 Rebuild Audit

You are auditing a WordPress plugin fork named `WordPress-Importer-v2`. It is a heavily modified fork of `humanmade/WordPress-Importer`, itself an old WordPress Importer v2 rewrite. Your job is not to apply small bug fixes. Your job is to audit the whole system and propose the proper way to rebuild/restructure it while keeping the good parts.

Read the repository carefully before proposing changes. Treat the current codebase as a working but fragile prototype with useful pieces.

## Core Goal

Design a robust, production-ready WordPress XML/WXR importer that remains compatible with WordPress's import system, keeps the plugin name/identity usable, supports large XML files, survives server timeouts, and has a better admin UI/UX.

The plugin should still behave like a WordPress importer:

- Available through WordPress admin importer flow, ideally under `Tools -> Import`.
- Compatible with standard WordPress WXR/XML export files.
- Safe to rerun without duplicating already-imported content.
- Usable as a replacement for the legacy WordPress Importer plugin.
- Preserve or deliberately migrate existing WP-CLI support if present.
- Preserve useful hooks/filters where reasonable, or document replacements.

## Current Problem

The existing importer processes a large WXR import through one long `admin-ajax.php?action=wxr-import` EventSource/SSE request. This is fragile. In a real local test with `healthtian.WordPress.2020-03-06.xml`, the import reached about `990 / 4597` posts, then stopped. WordPress admin post count increased from roughly `800` posts to about `1,765`, proving the import genuinely stopped partway through, not merely a frontend display issue.

There were also upload failures:

- Browser upload returned `Upload failed: Unexpected response from the server... HTTP status: 500`.
- Local Apache/FastCGI rejected large request bodies before WordPress/PHP ran, so `WP_DEBUG_LOG` had no errors.
- Later chunked upload patches avoided the single huge upload request, but upload stability does not solve long import processing.

The current emergency patches are not the desired long-term solution. They added diagnostics, chunked upload, and reconnect behavior, but the real architecture still needs to be evaluated.

## Important Files To Inspect

Start with these files:

- `plugin.php`
- `class-wxr-import-ui.php`
- `class-wxr-importer.php`
- `class-command.php`
- `class-wxr-import-info.php`
- `class-logger*.php`
- `templates/*.php`
- `assets/*.js`
- `assets/*.css`
- `README.md`
- `CHANGELOG.md`
- `tests/*`

Also inspect current uncommitted changes. Do not assume they are all correct.

## Known Useful Parts To Evaluate For Preservation

Evaluate whether these should be kept, rewritten, or isolated behind cleaner APIs:

- XML parsing through `XMLReader`.
- Preliminary fast scan/counting of WXR files.
- Author mapping workflow.
- Existing deduplication behavior for posts, terms, comments, and media.
- Existing remapping logic for parents, featured images, menu items, authors, comments, and terms.
- WP-CLI command support.
- Safe local file path workflow for large/offline imports.
- Security hardening around capabilities, local file access, escaping, and XML handling.
- Logging abstractions.

## Architecture Questions To Answer

Give clear recommendations on these:

1. Should this remain a fork of the existing importer code, or should it become a cleaner plugin with an internal legacy importer adapter?
2. Should the plugin name and importer registration stay the same? If yes, what should remain stable for compatibility?
3. What is the safest architecture for large imports: custom job table, post meta job records, Action Scheduler, WP-Cron, REST polling, admin-ajax polling, WP-CLI first, or another approach?
4. How should import state be represented so an interrupted import can resume without duplicate posts?
5. Can `XMLReader` resume from a cursor safely, or should the XML be transformed/indexed into an item queue before processing?
6. Should the system pre-index item positions/IDs, process directly in batches, or create a normalized queue of WXR entities?
7. How should dependencies between entities be handled: authors, terms, posts, attachments, comments, menus, parents, featured images?
8. How should attachment fetching be isolated so content import can complete even if remote media fails?
9. What should the error model be: fatal job failure, recoverable item failure, warning, skipped, retryable?
10. What compatibility promises should be made for old imports, WP-CLI, hooks, and UI URLs?

## Required Deliverables

Save your audit and plan into a `/docs` folder. Split into multiple files if the answer is long. Recommended files:

- `docs/ARCHITECTURE_AUDIT.md`
- `docs/REBUILD_PLAN.md`
- `docs/TEST_PLAN.md`
- `docs/UI_UX_PLAN.md`
- `docs/MIGRATION_AND_COMPATIBILITY.md`

Each file should be specific and implementation-oriented. Avoid generic advice.

## Architecture Audit Requirements

In `ARCHITECTURE_AUDIT.md`, include:

- A concise summary of the current architecture.
- The main reliability risks.
- The main security risks.
- The main performance risks.
- The main compatibility risks.
- Which existing parts are worth keeping.
- Which existing parts should be retired.
- Whether the emergency patches should be kept, replaced, or reverted.

## Rebuild Plan Requirements

In `REBUILD_PLAN.md`, propose a phased rebuild:

- Phase 0: safe test setup and database snapshot strategy.
- Phase 1: stabilize current importer enough for controlled testing.
- Phase 2: introduce a proper import job model.
- Phase 3: batch/resumable processing engine.
- Phase 4: admin UI/UX rebuild.
- Phase 5: WP-CLI and automation support.
- Phase 6: migration/cleanup from legacy internals.

For each phase, include:

- Goal.
- Files/modules likely touched.
- Implementation steps.
- Risks.
- Acceptance criteria.
- Rollback strategy.

## Testing Requirements

In `TEST_PLAN.md`, include:

- Unit tests for WXR parsing and item normalization.
- Integration tests for posts, pages, custom post types, terms, comments, users, media, parents, menus, and featured images.
- Resume/interruption tests.
- Duplicate/rerun tests.
- Invalid XML tests.
- Large XML tests.
- Browser upload tests, including chunked upload.
- WP-CLI tests.
- Security tests for permissions, nonces, local file paths, XML entity handling, and temporary files.
- Performance tests with realistic files.

Include a plan for testing the user's real XML export safely:

- Make a local DB snapshot first.
- Run import in a disposable local site or resettable database.
- Record initial counts.
- Run import.
- Simulate interruption.
- Resume.
- Verify final counts and spot-check content.
- Verify no duplicate posts on rerun.

## UI/UX Requirements

In `UI_UX_PLAN.md`, propose a WordPress-admin-native interface that supports:

- Upload or choose XML file.
- Use a server-local XML file for very large imports.
- Preflight scan and validation.
- Author mapping.
- Attachment import option.
- Clear warnings for large imports.
- Job progress with counts, elapsed time, rate, current phase, warnings, and errors.
- Pause/resume/cancel.
- Error log with filters.
- Final report with imported/skipped/failed counts.
- Safe rerun/resume messaging.

Do not propose a marketing-style UI. This is an operational admin tool.

## Compatibility Requirements

In `MIGRATION_AND_COMPATIBILITY.md`, define:

- Whether plugin folder/name/header should remain the same.
- How it registers with WordPress's importer system.
- Whether it can coexist with the original WordPress Importer plugin.
- How old hooks/filters/actions are handled.
- How WP-CLI command compatibility is preserved or changed.
- How in-progress/old temporary upload files are cleaned up.
- How legacy metadata such as `_wxr_import_settings`, `_wxr_import_info`, `_wxr_is_local_file`, and importer attachment records should be migrated or retired.

## Design Preference

Prefer a robust resumable job architecture over one long request. The likely target shape is:

- Upload/choose XML.
- Create an import job.
- Preflight scan/index.
- Process small batches through REST/admin-ajax/WP-Cron/WP-CLI.
- Persist progress after each batch.
- Resume safely after timeout, refresh, browser close, or server kill.
- Keep a final report.

But do not blindly accept this. Audit the code and WordPress constraints, then recommend the best design.

## Output Style

Be direct and concrete. Include file references. Distinguish:

- Must fix before production.
- Should fix during rebuild.
- Nice to have.

Do not simply say "add queues" or "use Action Scheduler" without explaining why, how, and what tradeoffs exist.

The end result should be a build-ready plan that a developer can execute in phases, with tests and acceptance criteria.
