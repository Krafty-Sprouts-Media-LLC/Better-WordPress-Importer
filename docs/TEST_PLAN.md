# Test Plan: Better WordPress Importer

## Test Infrastructure

### Framework
PHPUnit via WordPress's test suite. Test runner: `phpunit` (configured in `phpunit.xml.dist`).

### Test Fixtures
Create `tests/fixtures/` directory with:

| File | Description | Item Count |
|------|-------------|------------|
| `basic-export.xml` | Posts, pages, categories, tags, one author | ~20 items |
| `full-export.xml` | All entity types: posts, pages, CPT, attachments, comments, terms, menus, authors | ~100 items |
| `large-export.xml` | Generated or subset of real WXR, ~5,000 posts | ~5,000 items |
| `invalid-malformed.xml` | Corrupted XML — missing closing tags, invalid characters | N/A |
| `invalid-missing-fields.xml` | Valid XML but missing wp:post_type, wp:status, etc. | ~10 items |
| `invalid-corrupted-meta.xml` | Postmeta containing HTML error pages | ~5 items |
| `invalid-incomplete-class.xml` | Serialized `__PHP_Incomplete_Class` in meta | ~3 items |
| `duplicate-posts.xml` | Same GUIDs as basic-export but different content | ~10 items |
| `empty.xml` | Valid XML with no items | 0 items |
| `wxr-1.0.xml` | WXR 1.0 format (older exports) | ~15 items |
| `wxr-1.2.xml` | WXR 1.2 format (current) | ~15 items |
| `healthtian-small.xml` | First 500 items from real healthtian export (if permissible) | ~500 items |

### Database Snapshot Testing
For integration tests that modify the DB:
1. Use `WP_UnitTestCase`'s built-in transaction rollback (standard for WP tests)
2. For tests requiring real file I/O, use `WP_Filesystem_Mock` or `vfsStream`
3. Large import tests: run against a separate test database restored from snapshot

---

## Unit Tests

### WXR_Importer

#### XML Parsing
```
test_get_reader_valid_xml()
  → returns XMLReader instance for valid file

test_get_reader_missing_file()
  → returns WP_Error for nonexistent file

test_get_reader_invalid_xml()
  → returns WP_Error for malformed XML

test_safe_expand_valid_node()
  → returns DOMElement for valid node

test_safe_expand_malformed_node()
  → returns false and logs warning when node is corrupted

test_parse_post_node_valid()
  → returns data array with correct keys: post_type, post_title, guid, etc.
  → comments and terms arrays populated correctly
  → meta array includes wp:postmeta children

test_parse_post_node_missing_type()
  → handles missing wp:post_type gracefully

test_parse_post_node_auto_draft()
  → returns WP_Error with code 'wxr_importer.post.cannot_import_draft'

test_parse_post_node_invalid_node()
  → returns WP_Error for false/null/non-object node

test_parse_author_node_valid()
  → returns data array with user_login, user_email, display_name, etc.

test_parse_author_node_invalid()
  → returns WP_Error for corrupted node

test_parse_term_node_category()
  → taxonomy set to 'category'
  → uses wp:category_nicename for slug

test_parse_term_node_tag()
  → taxonomy set to 'post_tag'
  → uses wp:tag_slug for slug

test_parse_term_node_generic()
  → taxonomy read from wp:term_taxonomy

test_parse_comment_node_valid()
  → returns array with comment_author, comment_content, commentmeta

test_parse_meta_node_valid()
  → returns array with key and value

test_parse_meta_node_corrupted_value()
  → returns null when value contains <!DOCTYPE html>, Fatal error, or error-page HTML

test_parse_meta_node_missing_key()
  → returns null when wp:meta_key is empty

test_parse_category_node_with_domain()
  → taxonomy from 'domain' attribute, slug from 'nicename'

test_parse_category_node_tag_domain()
  → 'tag' domain mapped to 'post_tag' taxonomy
```

#### Deduplication
```
test_post_exists_by_guid()
  → returns existing post ID when GUID matches

test_post_exists_new()
  → returns false for unknown GUID

test_post_exists_without_prefill()
  → falls back to post_exists() WordPress function

test_comment_exists_by_author_date()
  → returns existing comment ID

test_comment_exists_new()
  → returns false for unknown comment

test_term_exists_by_taxonomy_slug()
  → returns existing term ID

test_term_exists_new()
  → returns false for unknown taxonomy:slug pair

test_prefill_existing_posts()
  → populates $this->exists['post'] with GUID→ID mapping

test_prefill_existing_posts_empty_db()
  → handles empty database gracefully

test_prefill_existing_comments()
  → populates $this->exists['comment'] with sha1(author:date)→ID mapping

test_prefill_existing_terms()
  → populates $this->exists['term'] with sha1(taxonomy:slug)→ID mapping
```

#### Entity Processing
```
test_process_post_simple()
  → inserts post with correct title, content, status, type

test_process_post_with_import_id()
  → uses import_id to set specific post ID

test_process_post_duplicate_skipped()
  → skips when GUID already exists

test_process_post_missing_type()
  → logs warning and returns false

test_process_post_invalid_type()
  → logs warning when post_type not registered

test_process_post_with_meta()
  → inserts post and all meta keys

test_process_post_with_terms()
  → assigns categories/tags to post

test_process_post_sticky()
  → calls stick_post() when is_sticky=1

test_process_post_cache_flush()
  → triggers wp_cache_flush every 200 posts

test_process_post_parent_remapping()
  → stores _wxr_import_parent when parent not yet imported

test_process_post_author_remapping()
  → maps author slug to existing user ID, stores _wxr_import_user_slug for deferred

test_process_post_attachment_refs_detected()
  → sets _wxr_import_has_attachment_refs when content matches REGEX_HAS_ATTACHMENT_REFS

test_process_author_creates_user()
  → creates WP_User with random password

test_process_author_duplicate_skipped()
  → skips when user_login already mapped

test_process_author_with_slug_override()
  → uses custom slug from set_user_slug_overrides()

test_process_author_mapping_sets_both_id_and_slug()
  → mapping['user'][old_id] and mapping['user_slug'][old_slug] both set

test_process_term_category()
  → creates category with wp_insert_term

test_process_term_duplicate_skipped()
  → skips when taxonomy:slug hash already exists

test_process_term_tag_to_post_tag()
  → maps 'tag' taxonomy to 'post_tag'

test_process_comment_on_new_post()
  → inserts comment with post_id, author, content

test_process_comment_on_existing_post()
  → skips duplicate via comment_exists

test_process_comment_parent_remapping()
  → stores _wxr_import_parent when parent not yet imported

test_process_menu_item_post_type()
  → updates _menu_item_object_id when post already imported

test_process_menu_item_taxonomy()
  → updates _menu_item_object_id when term already imported

test_process_menu_item_deferred()
  → stores _wxr_import_menu_item when target not yet imported
```

#### Post-Processing / Remapping
```
test_post_process_posts_parent_remap()
  → updates post_parent when parent becomes available

test_post_process_posts_author_remap()
  → updates post_author when author becomes available

test_post_process_posts_url_remap()
  → replaces attachment URLs in post_content

test_post_process_posts_cleans_temp_meta()
  → deletes _wxr_import_parent, _wxr_import_user_slug, _wxr_import_has_attachment_refs

test_post_process_comments_parent_remap()
  → updates comment_parent when parent becomes available

test_post_process_comments_user_remap()
  → updates user_id when author becomes available

test_post_process_menu_item_post_type_remap()
  → updates _menu_item_object_id for deferred menu items

test_remap_featured_images()
  → updates _thumbnail_id to new attachment ID

test_remap_featured_images_no_change()
  → does not update if IDs match

test_replace_attachment_urls_in_content()
  → URL replacement via SQL-level REPLACE

test_replace_attachment_urls_longest_first()
  → sorts URLs by length descending to avoid substring mismatches
```

#### Security and Validation
```
test_is_valid_meta_key_skips_attachment_meta()
  → returns false for _wp_attached_file, _wp_attachment_metadata

test_is_valid_meta_key_skips_edit_lock()
  → returns false for _edit_lock

test_is_valid_meta_key_allows_other()
  → returns the key for anything else

test_value_has_incomplete_class_object()
  → returns true for __PHP_Incomplete_Class objects

test_value_has_incomplete_class_nested()
  → returns true when array contains incomplete class

test_value_has_incomplete_class_valid()
  → returns false for normal objects and arrays

test_max_attachment_size_default()
  → returns 0 (unlimited) by default

test_max_attachment_size_filtered()
  → returns filtered value

test_bump_request_timeout()
  → returns 60 regardless of input

test_http_request_timeout_filter_added()
  → filter added during import_start
```

#### Fetching Attachments
```
test_fetch_remote_file_success()
  → downloads file, returns upload array

test_fetch_remote_file_http_error()
  → returns WP_Error on non-200 response

test_fetch_remote_file_size_mismatch()
  → returns WP_Error when content-length doesn't match

test_fetch_remote_file_zero_size()
  → returns WP_Error on empty file

test_fetch_remote_file_too_large()
  → returns WP_Error when exceeds max_attachment_size

test_fetch_remote_file_local_fallback()
  → copies local file when source URL matches upload directory

test_process_attachment_skipped_when_disabled()
  → returns false when fetch_attachments=false

test_process_attachment_url_remap()
  → records url_remap for both http and https variants
```

#### Pre-Scan / Preliminary Information
```
test_get_preliminary_information_basic()
  → returns WXR_Import_Info with correct counts

test_get_preliminary_information_posts_count()
  → post_count matches number of non-attachment items

test_get_preliminary_information_media_count()
  → media_count matches items with wp:post_type=attachment

test_get_preliminary_information_authors()
  → users array populated with author data

test_get_preliminary_information_version()
  → version property set from wp:wxr_version

test_get_preliminary_information_item_positions()
  → item_positions array contains byte offsets for each <item>
```

#### Edge Cases
```
test_cmpr_strlen()
  → sorts by string length descending

test_sort_comments_by_id()
  → sorts comments array by comment_id

test_sort_comments_by_id_missing_id()
  → handles missing comment_id gracefully

test_import_start_file_checks()
  → returns WP_Error for missing/unreadable file

test_import_end_restores_state()
  → re-enables cache invalidation, term counting, comment counting
```

---

## Integration Tests

### Full Import Pipeline
```
test_full_import_posts()
  → all posts from fixture appear in wp_posts

test_full_import_pages()
  → page post_type, hierarchy preserved

test_full_import_custom_post_types()
  → CPT registered before import → posts imported correctly

test_full_import_categories()
  → categories created with correct slugs and parent relationships

test_full_import_tags()
  → tags assigned to posts

test_full_import_custom_taxonomies()
  → custom taxonomy terms created and assigned

test_full_import_comments()
  → comments attached to correct posts with correct author/date/content

test_full_import_comment_meta()
  → comment meta imported alongside comments

test_full_import_authors()
  → authors created or mapped as configured

test_full_import_media_with_fetching()
  → attachments downloaded and created

test_full_import_media_without_fetching()
  → attachments skipped but not fatal

test_full_import_featured_images()
  → _thumbnail_id remapped to new attachment ID

test_full_import_parent_posts()
  → post_parent remapped correctly

test_full_import_menu_items()
  → nav_menu_item posts with _menu_item_object_id remapped

test_full_import_sticky_posts()
  → sticky posts marked via stick_post()
```

### Rerun and Deduplication
```
test_rerun_full_import_no_duplicates()
  → running import twice produces no duplicate posts

test_rerun_adds_new_comments_only()
  → second import adds only comments not present after first

test_rerun_preserves_meta()
  → existing post meta not duplicated or corrupted

test_rerun_does_not_reassign_terms()
  → term relationships not duplicated
```

### Resume and Interruption
```
test_resume_after_partial_import()
  → interrupt after N batches, resume → total items = expected total
  → no duplicate posts
  → remapping completes correctly

test_resume_preserves_mapping_state()
  → author/term mapping persisted across batches

test_resume_from_empty_state()
  → fresh import with no prior state starts at item 0

test_resume_after_completion()
  → resuming completed job detects completion, returns immediately
```

### Invalid and Edge Case XML
```
test_invalid_xml_rejected()
  → malformed XML returns WP_Error from get_reader

test_missing_required_fields_skipped()
  → items without wp:post_type skipped with warning

test_auto_draft_posts_skipped()
  → posts with wp:status=auto-draft not imported

test_corrupted_postmeta_skipped()
  → meta values containing HTML error pages skipped

test_incomplete_class_meta_skipped()
  → serialized __PHP_Incomplete_Class meta skipped with warning

test_empty_xml_completes_cleanly()
  → XML with no items returns success with 0 imported

test_very_long_content_handled()
  → post with 1MB+ content imported without memory error
```

### Parent/Author/Menu Relationships
```
test_parent_imported_after_child()
  → child post imported first, parent remapped in post_process

test_author_not_yet_imported()
  → post assigned to current user, remapped when author imported

test_menu_item_target_not_yet_imported()
  → stored in _wxr_import_menu_item, remapped in post_process

test_circular_parent_references()
  → handled gracefully (detected and left as top-level)
```

### Attachment and Media
```
test_attachment_download_failure_non_blocking()
  → content import completes even when attachment download fails

test_attachment_url_remap_in_content()
  → <img src="old-url"> replaced with new URL

test_attachment_url_remap_http_to_https()
  → both http and https variants replaced

test_attachment_guid_updated_when_option_set()
  → GUID updated to new URL when update_attachment_guids=true

test_attachment_guid_preserved_when_option_false()
  → GUID unchanged when update_attachment_guids=false
```

---

## Large XML Tests

```
test_large_xml_completes_within_memory_limit()
  → 5000-item XML completes without exceeding 256MB

test_large_xml_prefill_performance()
  → prefill_existing_posts completes in < 5s for 5k existing posts

test_large_xml_pre_scan_performance()
  → get_preliminary_information completes in < 10s for 100MB file

test_large_xml_batch_processing()
  → 100 batches of 50 items complete without timeout

test_large_xml_resume_after_kill()
  → process 30%, kill, resume → 100% complete, no duplicates
```

---

## Browser Upload Tests

These require browser automation (Puppeteer/Playwright or manual testing):

```
test_chunked_upload_assembles_correctly()
  → 30MB file uploaded in 8MB chunks → assembled file matches original

test_chunked_upload_missing_chunk_handled()
  → chunk upload fails gracefully with error message

test_chunked_upload_invalid_extension()
  → non-.xml file rejected

test_chunked_upload_cleanup()
  → chunk directories and part files removed after assembly

test_standard_upload_small_file()
  → < 1MB file uploads without chunking

test_upload_error_surface_to_user()
  → upload failure shows specific error message in UI

test_upload_permission_check()
  → user without upload_files capability receives error
```

---

## WP-CLI Tests

```
test_cli_import_basic()
  → wp wxr-importer import file.xml succeeds

test_cli_import_verbose()
  → --verbose flag produces detailed output

test_cli_import_default_author()
  → --default-author=1 maps unknown authors to user 1

test_cli_import_nonexistent_file()
  → returns error for missing file

test_cli_import_dry_run()
  → --dry-run validates XML and reports counts without importing

test_cli_status_command()
  → wp wxr-importer status shows running job progress

test_cli_cancel_command()
  → wp wxr-importer cancel stops running job

test_cli_list_command()
  → wp wxr-importer list shows recent jobs

test_cli_clean_command()
  → wp wxr-importer clean removes temporary files
```

---

## Security Tests

```
test_import_capability_required()
  → user without 'import' capability receives 403 on dispatch()

test_upload_files_capability_required()
  → user without 'upload_files' capability cannot upload

test_ajax_nonce_verification()
  → wxr-import-upload rejects requests without valid nonce

test_cancel_nonce_verification()
  → wxr-cancel-import rejects requests without valid nonce

test_local_file_path_traversal_blocked()
  → ../wp-config.php rejected (outside uploads)

test_local_file_outside_uploads_rejected()
  → /etc/passwd rejected

test_local_file_requires_readable()
  → unreadable file returns appropriate error

test_xml_entity_loading_disabled()
  → external entities not loaded (XXE prevention)

test_chunk_directory_web_accessible()
  → (manual) verify .htaccess/index.php blocks directory listing

test_diagnostic_log_not_public()
  → (manual) verify debug log is not web-accessible

test_output_escaping()
  → all error messages rendered through esc_html()
  → user input not output raw

test_sql_injection_in_import_id()
  → numeric import IDs sanitized before query
```

---

## Performance Tests

```
test_baseline_memory_profile()
  → record memory usage importing 100, 500, 1000, 5000 items
  → verify sub-linear memory growth (periodic cache flushes working)

test_baseline_time_profile()
  → record time importing 100, 500, 1000, 5000 items
  → verify batch overhead is acceptable (< 500ms per batch)

test_prefill_memory_on_large_site()
  → site with 100k posts, 50k comments, 10k terms
  → prefill memory within acceptable bounds
```

---

## Real WXR Validation Plan

For testing with `healthtian.WordPress.2020-03-06.xml`:

1. **Make a full database backup**: `wp db export pre-healthtian.sql`
2. **Record initial state**:
   - Post count: `wp post list --format=count`
   - Comment count: `wp comment list --format=count`
   - Term count: `wp term list --format=count`
   - User count: `wp user list --format=count`
3. **Run import** via WP-CLI (bypasses web UI): `wp wxr-importer import healthtian.xml --verbose`
4. **Record final state**: same counts
5. **Verify**:
   - No post count exceeds expected (imported + original)
   - Spot-check 10 random imported posts for content, meta, author, featured image
   - Spot-check 5 categories for term count consistency
   - Check that no `_wxr_import_*` temporary meta remains
6. **Simulate interruption**:
   - Restore DB to pre-import state
   - Start import, kill after 30 seconds
   - Record partial state
   - Resume import
   - Verify final state matches full import
7. **Rerun safety**:
   - Run import again on already-imported site
   - Verify 0 new posts (all skipped as duplicates)
   - Verify no duplicate terms, comments, or meta
8. **Save results**: `test-results/healthtian-{date}.json`
