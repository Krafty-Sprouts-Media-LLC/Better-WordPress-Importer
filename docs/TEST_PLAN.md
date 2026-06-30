# Test Plan V2: Better WordPress Importer

> **MVP scope: import only.** Exporter and Better Package format tests are deferred to Phase 2.

## Test Fixtures

| File | Contents | Purpose |
|------|----------|---------|
| `small-export.xml` | 20 posts, 5 terms, 2 users, 3 comments | Quick smoke test |
| `full-export.xml` | 100+ entities, all types, meta, comments, menus | Integration test |
| `large-meta-post.xml` | 1 post with 200 meta rows, 50 comments | Sub-step checkpointing test |
| `heavy-meta-value.xml` | 1 post with 5MB serialized meta value | Timeout/recovery test |
| `partial-import.xml` | Posts that simulate mid-import crash | Resume test |
| `duplicate-posts.xml` | Same GUIDs as small-export | Idempotency test |
| `invalid-malformed.xml` | Corrupted XML | Error handling test |
| `invalid-incomplete-class.xml` | Serialized `__PHP_Incomplete_Class` in meta | Safety test |
| `wxr-1.0.xml` | WXR 1.0 format | Backward compatibility test |
| `empty.xml` | Valid XML, no items | Edge case test |
| `large-5000.xml` | Generated: 5,000 posts with 5 meta each | Performance test |

## Unit Tests

### Import Job

```text
test_job_create_with_small_file()
  -> creates job with correct counts and compact manifest

test_job_create_with_large_file()
  -> stores compact manifest for more than 500 entities

test_job_manifest_structure()
  -> manifest entries have i, t, id, title fields

test_job_manifest_has_no_byte_offsets()
  -> manifest entries do not contain byte_offset, b, or raw XML payload

test_job_status_transitions()
  -> created -> scanned -> queued -> processing -> remapping -> complete

test_job_pause_resume()
  -> processing -> paused -> processing

test_job_cancel()
  -> any active status -> cancelled

test_job_mapping_state_serialization()
  -> get/set mapping state survives JSON roundtrip
```

### Queue Items

```text
test_queue_item_initial_state()
  -> status=pending, step=parse, step_cursor=0

test_queue_item_step_advancement()
  -> advance_step('import_meta', 0, 200) persists correctly

test_queue_item_terminal_detection()
  -> terminal statuses are complete, skipped, failed

test_queue_item_retry_increment()
  -> attempts counter increments on failure

test_queue_item_error_persistence()
  -> error_message, error_code, last_error_at survive save/reload
```

### Manifest and Payload Queue

```text
test_build_manifest_ordering()
  -> users before terms before posts before attachments

test_manifest_storage_no_cap()
  -> manifest for 10k entities stored without truncation

test_queue_population_from_manifest()
  -> one queue row per manifest entry

test_parse_entity_payload_once()
  -> first processing pass stores gzipped parsed_payload

test_resume_uses_parsed_payload_without_reopening_xml()
  -> sub-step resume reads queue payload, not XMLReader

test_payload_deleted_on_completion()
  -> parsed_payload cleared when item reaches a terminal state

test_in_progress_without_payload_reparses_once()
  -> crash recovery restores missing payload, then resumes
```

### Entity Processing

```text
test_step_create_post()
  -> wp_insert_post succeeds, new_entity_id set, step advances to import_meta

test_step_create_post_duplicate_skipped()
  -> existing fully imported post by GUID is skipped

test_step_create_post_partial_detected()
  -> existing post missing imported meta is resumed, not blindly skipped

test_step_import_meta_chunk()
  -> imports 25 meta rows, saves cursor=25, returns more_work

test_step_import_meta_chunk_resume()
  -> with cursor=50, imports rows 50-75

test_step_import_meta_idempotent()
  -> re-importing same meta rows creates no duplicates

test_step_import_meta_incomplete_class_flagged()
  -> unsafe serialized object is logged and handled according to policy

test_step_import_comments_chunk()
  -> imports 10 comments, saves cursor

test_step_assign_terms()
  -> all terms assigned in one sub-step

test_step_complete_cleanup()
  -> parsed_payload cleared and temporary import markers removed
```

### Time-Based Batch Processing

```text
test_batch_stops_at_time_limit()
  -> process_batch() returns near configured time budget

test_batch_single_entity_timeout()
  -> entity exceeding timeout is marked failed/retryable, next entity can proceed

test_batch_respects_lock()
  -> concurrent process_batch() calls: second returns locked

test_batch_reports_honest_counters()
  -> imported, skipped, failed, partial counters are accurate

test_batch_checkpoint_saved_after_each_step()
  -> queue item step and cursor survive process interruption

test_batch_cron_integration()
  -> better_importer_process_batch hook triggers one batch
```

### Idempotency

```text
test_full_rerun_no_duplicates()
  -> complete import, re-import, all entities skipped

test_rerun_recovers_partial_meta()
  -> interrupted meta import resumes and restores missing rows

test_rerun_does_not_double_assign_terms()
  -> post terms unchanged on re-import

test_rerun_preserves_manual_edits()
  -> manually edited post content not overwritten unless user opts in

test_post_exists_guid_match()
  -> GUID-based detection works for posts

test_comment_exists_author_date_match()
  -> sha1(author:date) detection works

test_term_exists_taxonomy_slug_match()
  -> sha1(taxonomy:slug) detection works
```

### Integration Tests

```text
test_full_import_all_types()
  -> all posts, terms, users, comments from full-export.xml imported correctly

test_full_import_meta_integrity()
  -> all meta rows imported, values match XML

test_full_import_plugin_meta_preserved()
  -> SEO, Link Whisper, WooCommerce, and custom plugin meta are not dropped

test_full_import_with_active_plugins()
  -> import succeeds with representative active plugins

test_resume_after_browser_close()
  -> start import, close browser, reopen, progress continues from checkpoint

test_resume_after_php_crash()
  -> simulate crash mid-meta, restart, meta import continues from saved cursor

test_resume_after_server_restart()
  -> cron picks up job and continues processing

test_entity_timeout_preserves_other_work()
  -> one failed entity does not block the whole import
```

## Large Fixture Tests

```text
test_large_import_completes()
  -> 5,000 posts x 5 meta rows imported

test_large_import_memory_stable()
  -> memory does not grow linearly with entity count

test_large_import_manifest_size()
  -> compact manifest stays small and contains no payload data

test_large_import_no_repeated_scan_regression()
  -> XML entity parsing count is close to entity count, not batch count x cursor depth

test_large_import_time_based_batch()
  -> batch duration follows time budget regardless of entity count
```

## Real 11,673-Entity Fixture

```text
test_real_import_completes()
  -> all 4,597 posts imported when media is disabled
  -> 599 terms imported
  -> 17 users imported
  -> zero duplicates

test_real_import_post_meta_integrity()
  -> spot-check 50 random posts: meta count matches WXR

test_real_import_stall_regression()
  -> "Scanning XML batch from entity 7220" does not repeat
  -> import maintains steady progress through all entities

test_real_rerun_safety()
  -> re-import after completion creates 0 duplicate posts and 0 duplicate meta rows
```

## Browser/UI Tests

```text
test_upload_chunked_large_file()
  -> large XML uploaded in chunks and assembled correctly

test_progress_ui_during_scanning()
  -> scanning phase shows entity counts

test_progress_ui_during_processing()
  -> current entity name and sub-step displayed

test_progress_ui_counters_accurate()
  -> imported/skipped/failed/partial counts match database

test_pause_functionality()
  -> click Pause, status changes to paused, no new batch starts

test_resume_functionality()
  -> click Resume, status changes to processing, continues from checkpoint

test_cancel_functionality()
  -> click Cancel, status cancelled, imported posts preserved

test_failed_item_retry_ui()
  -> failed entity shown with Retry action
```

## WP-CLI Tests

```text
test_cli_full_import()
  -> wp better-importer import small-export.xml completes

test_cli_resume()
  -> kill import, rerun command, resumes instead of restarting

test_cli_dry_run()
  -> --dry-run validates XML, reports counts, no DB changes

test_cli_status()
  -> wp better-importer status shows current job progress

test_cli_cancel()
  -> wp better-importer cancel stops running job
```

## Security Tests

```text
test_import_capability_required()
  -> user without import capability receives 403

test_ajax_batch_nonce_verification()
  -> batch endpoint rejects invalid nonce

test_ajax_status_nonce_verification()
  -> status endpoint rejects invalid nonce

test_ajax_pause_nonce_verification()
  -> pause endpoint rejects invalid nonce

test_local_file_path_traversal()
  -> ../../wp-config.php rejected

test_xml_entity_injection()
  -> XXE prevented

test_xml_billion_laughs()
  -> entity expansion attack mitigated

test_chunk_upload_web_access()
  -> chunk directory not listable via web
```

## Smoke Test Plan

```bash
wp better-importer generate-fixture --posts=20 --meta-per-post=5 --output=/tmp/test-import.xml
wp better-importer import /tmp/test-import.xml --verbose
wp post list --post_type=post --format=count
wp better-importer import /tmp/test-import.xml --verbose
timeout 5 wp better-importer import /tmp/test-import.xml --verbose || true
wp better-importer import /tmp/test-import.xml --verbose
```
