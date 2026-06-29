# Project Context For Audit

This document summarizes the recent debugging context for the WordPress Importer v2 fork. Use it alongside the repository source. It is not a substitute for reading the code.

## Repository

Plugin path:

`C:\Users\kings\Local Sites\yenimi\app\public\wp-content\plugins\WordPress-Importer-v2`

Upstream/fork lineage:

- Fork of `humanmade/WordPress-Importer`.
- The upstream project is old and described as an in-development rewrite.
- The local fork has many custom changes and emergency patches.

## User Goal

The user wants a reliable WordPress XML/WXR importer for large imports, compatible with WordPress's importer system, with a better admin UI/UX and proper test coverage. The plugin can keep the same name/identity if that is the best compatibility path.

## Recent Failure History

The user tried importing `healthtian.WordPress.2020-03-06.xml`.

Initial upload failure:

- Browser showed: `Upload failed: Unexpected response from the server. The file may have been uploaded successfully. Check in the Media Library or reload the page.`
- Later enhanced JS showed: `HTTP status: 500`.
- No WordPress debug log entry appeared.
- Local Apache error log showed FastCGI rejected request size before PHP/WordPress ran:
  - `mod_fcgid: HTTP request length 30006011 (so far) exceeds MaxRequestLen (30000000)`

Temporary local config changes were made to LocalWP Apache/FastCGI:

- `FcgidMaxRequestLen 1073741824`
- `FcgidIOTimeout 3600`
- `FcgidBusyTimeout 3600`

These are local-environment changes only. They are not part of the plugin and do not affect the live site.

Upload patch:

- Browser upload was changed to use Plupload chunks (`8mb`).
- Chunk handling was added to assemble XML files server-side.
- Plupload sends chunk file names as `blob`; original filename arrives through request parameter `name`. The code was patched to validate the original filename for chunked uploads.

Import failure:

- After upload succeeded, import showed: `The import stream was interrupted. Check the server logs.`
- The import did not complete.
- UI progress showed about `990 / 4597` posts.
- WordPress admin post count increased from roughly `800` to about `1,765`.
- This proves the import really stopped partway through. It was not only a frontend status bug.

## Current Emergency Patches

These changes exist in the working tree and should be audited, not blindly accepted:

- `assets/intro.js`
  - Better upload error details.
- `templates/upload.php`
  - File input name changed to match async upload handling.
- `class-wxr-import-ui.php`
  - Chunked upload handling.
  - Plugin-local diagnostic log: `wxr-upload-debug.log`.
  - Stream diagnostics for import lifecycle.
  - Stronger SSE flush.
- `class-logger-serversentevents.php`
  - Stronger flush for log events.
- `templates/import.php`
  - Import JS cache busting with `filemtime`.
- `assets/import.js`
  - Better stream error reporting.
  - Auto-reconnect/resume attempt after EventSource failure.

These patches may be useful as short-term stabilization, but they do not solve the core architecture problem: one long request is still fragile.

## Core Architectural Problem

The current import processing depends on a single long `admin-ajax.php?action=wxr-import` request that streams progress via Server-Sent Events.

This is fragile because:

- Browser can disconnect.
- Server/proxy/FastCGI can timeout.
- PHP process can be killed.
- Host-level limits may not be configurable.
- A large import can partially mutate the database and stop before post-processing/remapping completes.

The desired rebuild should make imports resumable and batch-based.

## Current Good Parts To Evaluate

Potentially valuable code/behavior:

- XMLReader-based parsing.
- Fast preliminary scan/counting.
- Existing deduplication/rerun behavior.
- Author mapping.
- Local file import workflow.
- WP-CLI command.
- Parent/author/featured-image/menu remapping logic.
- Security hardening already added in this fork.
- Object cache flushing every 200 posts.

## Risks To Audit Carefully

- Partial imports may leave remapping incomplete.
- Rerunning may skip posts but still need comments/meta/remapping validation.
- Chunked upload temporary files need cleanup and access protection.
- XML files in uploads can be public if not removed/protected.
- Local file import path handling must not allow sensitive file reads.
- Long imports must not rely on PHP max execution time or FastCGI timeouts.
- Attachment downloading should not block the whole content import.
- Diagnostics should not leak sensitive paths or data in production.

## Desired Direction

The likely target architecture is a job-based importer:

- Create import job.
- Store job state.
- Preflight scan/index XML.
- Process small batches.
- Persist progress after every batch.
- Resume after timeout/browser close/server kill.
- Separate content import from attachment fetching where possible.
- Provide WP-CLI path for large imports.
- Provide clear UI progress, logs, pause/resume/cancel, and final report.

The auditor should verify whether this direction is best and propose a detailed build plan.
