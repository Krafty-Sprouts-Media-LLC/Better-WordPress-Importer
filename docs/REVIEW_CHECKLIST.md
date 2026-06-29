# Review Checklist: Better WordPress Importer Rebuild

Use this checklist when reviewing builder output.

## Architecture

- Import no longer depends on one long `admin-ajax.php` or SSE request.
- Import job state is persisted server-side.
- Progress is persisted after each batch.
- Resume works after browser refresh, disconnect, PHP timeout, or process kill.
- Finalization/remapping can resume if interrupted.
- Attachment/media handling does not block the whole content import indefinitely.
- The old emergency reconnect patch is removed or no longer central.

## Data Integrity

- Rerunning the same XML does not duplicate posts, terms, comments, users, or media.
- Parent/child relationships remap correctly.
- Featured images remap correctly.
- Menu items remap correctly.
- Author mapping persists correctly.
- Comments and comment meta import correctly.
- Post meta imports correctly and unsafe serialized/plugin-specific data is handled safely.
- Partial imports do not leave permanent broken `_wxr_import_*` meta without a cleanup/finalization path.

## Security

- Temporary upload/chunk files are protected from direct web access.
- XML files are not left publicly accessible.
- Local file import is restricted to allowed directories.
- Nonces and capabilities protect every mutating action.
- XML parsing disables unsafe external entity behavior.
- Diagnostic logs are not written to web-accessible plugin root in production.
- Logs are rotated/truncated or stored safely.

## Compatibility

- Plugin remains compatible with WordPress importer registration.
- Standard WXR/XML exports import successfully.
- WP-CLI support exists or migration path is documented.
- Existing useful hooks/filters are preserved or replaced intentionally.
- Legacy temporary meta/files have a migration or cleanup path.
- Coexistence behavior with the original WordPress Importer is documented.

## Tests

- Unit tests cover parsing/indexing/job state.
- Integration tests cover core entities.
- Resume/interruption tests exist.
- Duplicate/rerun tests exist.
- Invalid XML tests exist.
- Large-file tests exist or are documented with fixture strategy.
- Upload/chunk tests exist.
- WP-CLI tests exist if CLI is supported.
- Security tests cover permissions, nonces, local paths, temp files, and XML safety.

## UI/UX

- UI is WordPress-admin-native.
- Progress comes from server-side job state, not only browser counters.
- User can pause/resume/cancel safely.
- Errors and warnings are filterable/readable.
- Final report is clear.
- Large-import warnings are clear.
- Copy does not imply success until finalization is complete.

## Real XML Validation

Only run after explicit user approval.

- Snapshot database first.
- Record initial counts.
- Run user's XML.
- Simulate interruption.
- Resume.
- Verify final counts.
- Rerun same XML.
- Verify no duplicates.
- Spot-check content, relationships, featured images, terms, comments, and menus.
