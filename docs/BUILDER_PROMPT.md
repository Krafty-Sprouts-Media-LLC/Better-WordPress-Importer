# Builder Prompt: Better WordPress Importer

You are the builder model for this repository. Your task is to implement the rebuild plan for the WordPress importer plugin, not to continue adding emergency patches.

Before changing code, read these files in order:

1. `docs/ARCHITECTURE_AUDIT.md`
2. `docs/REBUILD_PLAN.md`
3. `docs/TEST_PLAN.md`
4. `docs/UI_UX_PLAN.md`
5. `docs/MIGRATION_AND_COMPATIBILITY.md`
6. `docs/PROJECT_CONTEXT_FOR_AUDIT.md`

The intended product name is:

**Better WordPress Importer**

Keep compatibility with WordPress's importer system unless the docs identify a specific reason to change a compatibility surface. The plugin should continue to support standard WordPress WXR/XML export files and should remain usable from `Tools -> Import`.

## Primary Objective

Replace the current fragile single-request/SSE import flow with a real resumable import-job architecture.

The current emergency patches are not the target architecture. Treat them as temporary diagnostics and stabilization only. Do not build the final system around `admin-ajax.php?action=wxr-import` running the whole import in one request.

## Non-Negotiable Requirements

- Large imports must not depend on one long browser request staying alive.
- Import progress must be persisted server-side after each batch.
- A browser refresh, disconnect, PHP timeout, FastCGI kill, or server restart must not force the import to start over from scratch.
- Partial imports must be resumable without duplicates.
- Parent/author/menu/featured-image/comment/term remapping must survive interrupted imports.
- The UI must show real persisted job progress, not only client-side counters.
- The system must provide a final report: imported, skipped, failed, warnings, and recoverable errors.
- The implementation must include tests as it is built.

## Suggested Architecture

Use the audit and rebuild plan as the source of truth, but the likely target shape is:

- Import job model.
- Stored job state.
- Stored entity mapping/remapping state.
- Preflight scan/index phase.
- Batch processing phase.
- Separate finalization/remapping phase.
- Separate attachment/media handling where possible.
- Admin UI that polls progress.
- WP-CLI path for running/continuing jobs.

If you choose a different approach, document why before implementing.

## Build Strategy

Work in phases. Do not attempt a huge rewrite in one pass.

Recommended sequence:

1. Create the job/state model.
2. Create tests for job creation and persistence.
3. Add preflight scan/index behavior.
4. Add batch processing for a small subset of WXR entities.
5. Add persistent mappings.
6. Add resume/interruption tests.
7. Expand entity coverage: authors, terms, posts, comments, media, menus, featured images.
8. Add finalization/remapping.
9. Replace the old import UI with the job-based UI.
10. Update WP-CLI support.
11. Remove or retire unsafe diagnostics and emergency-only code.

## Testing Expectations

Follow `docs/TEST_PLAN.md`. At minimum, each phase should include relevant tests before it is considered done.

Required real-world validation later:

- Snapshot/reset local database.
- Run the user's real XML export.
- Simulate interruption.
- Resume.
- Verify final counts.
- Rerun the same XML.
- Verify no duplicates.
- Verify relationships and featured images.

Do not run destructive or mutating import tests against the user's current database without explicit approval.

## UI/UX Direction

The UI should be WordPress-admin-native and operational, not marketing-style.

It should support:

- Upload/select/local file source.
- Preflight validation.
- Author mapping.
- Attachment import choice.
- Start/pause/resume/cancel.
- Real server-side progress.
- Error/warning log.
- Final report.

## Compatibility Direction

Use `docs/MIGRATION_AND_COMPATIBILITY.md` to decide exact compatibility behavior. In general:

- Keep WordPress importer registration.
- Keep WXR/XML compatibility.
- Preserve WP-CLI support or provide a documented replacement.
- Preserve useful hooks where possible.
- Provide migration/cleanup for legacy temporary meta and files.

## Important Warning

The audit states that the current auto-reconnect behavior is cosmetic, not true resumability. Do not mistake rerunning with deduplication for a real resume system.

The final system must persist enough state to continue safely and complete remapping/finalization after interruption.

## Deliverables

When done with each phase, update or add documentation under `docs/`:

- What was implemented.
- What tests were added.
- What remains.
- Any compatibility decisions.
- Any migration notes.

Keep changes reviewable. Prefer smaller, coherent commits or patches.
