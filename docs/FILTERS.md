<!--
Better WordPress Importer — filters
Copyright (c) 2026 Krafty Sprouts Media, LLC
-->

# Filters

Useful hooks for site-specific behaviour. Names with a dot (`wxr_importer.*`) are from Importer v2.

| Filter | Default | Purpose |
| --- | --- | --- |
| `import_allow_create_users` | `true` | Show “Create new user” on author review |
| `import_allow_fetch_attachments` | `true` | Show the fetch-attachments checkbox |
| `import_upload_size_limit` | `wp_max_upload_size()` | Max WXR upload size |
| `import_attachment_size_limit` | `0` (unlimited) | Max size for a fetched attachment |
| `wxr_importer.prefetch_attachments` | `true` | Concurrent attachment prefetch before the main loop |
| `wxr_importer.prefetch_concurrency` | `4` | How many attachment downloads at once |
| `wxr_importer.auto_disable_prefill` | `true` | Skip upfront post/comment/term prefetch on large files |
| `wxr_importer.prefill_threshold_bytes` | `20 * MB_IN_BYTES` | Size above which prefill is skipped |
| `wxr_importer.admin.import_options` | importer options array | Dashboard/CLI importer options |
| `wxr_importer.pre_process.post` | post data | Return empty to skip a post |
| `wxr_importer.pre_process.term` | term data | Return empty to skip a term |
| `wxr_importer.pre_process.user` | user data | Return empty to skip a user |
| `wxr_importer.pre_process.comment` | comment data | Return empty to skip a comment |
| `wxr_importer.pre_process.post_meta` | meta row | Return empty to skip a meta key |
| `import_post_meta_key` | meta key | Return false to skip that key |
| `wp_import_post_terms` | terms on a post | Adjust terms before assign |

Actions of note: `import_start`, `import_end`, `wxr_importer.processed.post`, `wxr_importer.processed.term`, `wxr_importer.processed.user`.
