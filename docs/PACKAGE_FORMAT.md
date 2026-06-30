# Better Package Format v1.0

> **Status: Phase 2 (deferred).** The Better Package format is planned for a follow-up release alongside the exporter.

## Purpose

The Better Package Format is an alternative to the standard WXR XML file. It is designed for:

1. **Large sites** — supports chunked, resumable export and import
2. **Unreliable connections** — split into small files, any one can be retried
3. **Rich metadata** — includes media manifests, checksums, export logs
4. **Plugin data integrity** — preserves all postmeta including large serialized values without XML-encoding issues

---

## File Extension

`.bwxr` — a ZIP archive containing the package files described below.

MIME type: `application/x-better-wxr` (custom, for upload validation)

The importer auto-detects the format by inspecting the file:
- `.xml` extension + starts with `<?xml` or `<rss`: treat as WXR XML
- `.bwxr` or `.zip` extension: treat as Better Package
- Any file uploaded: inspect first 4 bytes — `PK\x03\x04` (ZIP magic) = Better Package, `<?xm` = WXR XML

---

## Package Structure

```
export-2024-12-15-site-name.bwxr  (ZIP archive)
│
├── manifest.json                 # Required — package metadata
├── site.json                     # Required — site-level data
├── authors.json                  # Required — all authors
├── terms.json                    # Required — all terms
│
├── content/                      # Required — content chunks
│   ├── posts-00001.json          # Posts 1-500
│   ├── posts-00002.json          # Posts 501-1000
│   └── posts-NNNNN.json
│
├── media/                        # Optional — media manifest
│   └── manifest.json
│
├── checksums.sha256              # Required — integrity verification
│
└── export.log                    # Optional — export process log
```

---

## Schema: manifest.json

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/manifest.json",
    "format": "better-wxr",
    "format_version": "1.0",
    "source_site": {
        "title": "Site Title",
        "url": "https://example.com",
        "description": "Site description",
        "language": "en-US",
        "timezone": "UTC",
        "wxr_version": "1.2",
        "generator": "Better WordPress Importer 1.x"
    },
    "export": {
        "exported_at": "2024-12-15T12:00:00Z",
        "exported_by": 1,
        "total_posts": 4597,
        "total_pages": 12,
        "total_media": 6460,
        "total_users": 17,
        "total_terms": 599,
        "total_comments": 8230,
        "filters_applied": {
            "post_types": ["post", "page", "custom_cpt"],
            "date_start": null,
            "date_end": null,
            "authors": "all",
            "statuses": ["publish", "draft", "pending", "private", "future"]
        }
    },
    "files": {
        "site": "site.json",
        "authors": "authors.json",
        "terms": "terms.json",
        "content_chunks": 10,
        "content_chunk_size": 500,
        "media_manifest": "media/manifest.json"
    },
    "checksums": {
        "algorithm": "sha256",
        "file": "checksums.sha256"
    }
}
```

---

## Schema: site.json

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/site.json",
    "title": "Site Title",
    "link": "https://example.com",
    "description": "Site description",
    "pubDate": "Mon, 15 Dec 2024 12:00:00 +0000",
    "language": "en-US",
    "wxr_version": "1.2",
    "base_site_url": "https://example.com",
    "base_blog_url": "https://example.com",
    "generator": "https://wordpress.org/?v=6.7",
    "options": {
        "default_category": 1,
        "default_comment_status": "open",
        "default_ping_status": "open",
        "posts_per_page": 10,
        "date_format": "F j, Y",
        "time_format": "g:i a",
        "start_of_week": 1,
        "timezone_string": "UTC",
        "blog_public": 1,
        "default_role": "subscriber",
        "thumbnail_size_w": 150,
        "thumbnail_size_h": 150,
        "medium_size_w": 300,
        "medium_size_h": 300,
        "large_size_w": 1024,
        "large_size_h": 1024,
        "uploads_use_yearmonth_folders": 1
    }
}
```

---

## Schema: authors.json

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/authors.json",
    "authors": [
        {
            "id": 1,
            "login": "admin",
            "email": "admin@example.com",
            "display_name": "Admin User",
            "first_name": "Admin",
            "last_name": "User",
            "nicename": "admin",
            "url": "https://example.com",
            "registered": "2020-01-01T00:00:00Z",
            "status": 0,
            "roles": ["administrator"]
        }
    ]
}
```

---

## Schema: terms.json

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/terms.json",
    "terms": [
        {
            "id": 1,
            "taxonomy": "category",
            "slug": "uncategorized",
            "name": "Uncategorized",
            "description": "Default category",
            "parent": 0,
            "count": 42,
            "meta": {}
        },
        {
            "id": 2,
            "taxonomy": "post_tag",
            "slug": "health",
            "name": "Health",
            "description": "Health-related posts",
            "parent": 0,
            "count": 156,
            "meta": {}
        },
        {
            "id": 3,
            "taxonomy": "custom_taxonomy",
            "slug": "custom-term",
            "name": "Custom Term",
            "description": "",
            "parent": 0,
            "count": 12,
            "meta": {
                "custom_tax_field": "custom_value"
            }
        }
    ]
}
```

---

## Schema: content/posts-NNNNN.json

Each chunk file is a JSON array of post objects:

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/posts.json",
    "chunk_index": 1,
    "total_chunks": 10,
    "start_id": 1,
    "end_id": 500,
    "posts": [
        {
            "id": 1,
            "type": "post",
            "title": "Hello World",
            "slug": "hello-world",
            "status": "publish",
            "date": "2024-01-01T00:00:00+00:00",
            "date_gmt": "2024-01-01T00:00:00+00:00",
            "modified": "2024-06-15T12:00:00+00:00",
            "modified_gmt": "2024-06-15T12:00:00+00:00",
            "author": {
                "id": 1,
                "login": "admin",
                "display_name": "Admin User",
                "email": "admin@example.com"
            },
            "guid": "https://example.com/?p=1",
            "content": "<p>Post content with <strong>HTML</strong>.</p>",
            "excerpt": "<p>Excerpt text.</p>",
            "comment_status": "open",
            "ping_status": "open",
            "password": "",
            "parent": {
                "id": 0,
                "slug": null
            },
            "menu_order": 0,
            "is_sticky": false,
            "mime_type": null,
            "attachment_url": null,

            "terms": {
                "category": [
                    { "id": 1, "slug": "uncategorized", "name": "Uncategorized" }
                ],
                "post_tag": [
                    { "id": 2, "slug": "health", "name": "Health" },
                    { "id": 5, "slug": "wellness", "name": "Wellness" }
                ],
                "custom_taxonomy": [
                    { "id": 3, "slug": "custom-term", "name": "Custom Term" }
                ]
            },

            "meta": {
                "_thumbnail_id": { "value": 123, "serialized": false },
                "_edit_last": { "value": 1, "serialized": false },
                "custom_field": { "value": "simple value", "serialized": false },
                "complex_field": {
                    "value": "a:3:{s:4:\"name\";s:9:\"Test Name\";s:5:\"value\";i:42;s:4:\"meta\";a:1:{s:3:\"key\";s:5:\"value\";}}",
                    "serialized": true,
                    "php_type": "array",
                    "unserialized_size": 152
                }
            },

            "comments": [
                {
                    "id": 1,
                    "author": "Commenter Name",
                    "author_email": "email@example.com",
                    "author_url": "https://example.com",
                    "author_ip": "127.0.0.1",
                    "date": "2024-01-02T00:00:00+00:00",
                    "date_gmt": "2024-01-02T00:00:00+00:00",
                    "content": "<p>Comment text with HTML.</p>",
                    "approved": 1,
                    "karma": 0,
                    "type": "",
                    "agent": "Mozilla/5.0",
                    "parent": 0,
                    "user_id": 0,
                    "meta": {
                        "custom_comment_field": { "value": "value", "serialized": false }
                    }
                }
            ]
        }
    ]
}
```

### Meta value representation

Each meta entry is an object with:
- `value` — the serialized string as stored in WordPress (string)
- `serialized` — whether the value is a PHP serialized string (boolean)
- `php_type` — the PHP type when unserialized: `string`, `integer`, `double`, `boolean`, `array`, `object`, `null` (null if not serialized)
- `unserialized_size` — approximate byte size when unserialized (integer, for size estimates and timeout planning)

This allows the importer to:
- Import large meta as the original stored value whenever possible
- Detect incomplete class objects before importing
- Warn about large meta values before processing
- Surface repeated failures as explicit failed/retryable items instead of silently dropping plugin meta

---

## Schema: media/manifest.json

```json
{
    "$schema": "https://schemas.better-wp-importer.org/better-wxr-1.0/media.json",
    "media": [
        {
            "id": 100,
            "attached_to_post": 5,
            "title": "featured-image",
            "slug": "featured-image",
            "description": "Image description",
            "caption": "Image caption",
            "alt_text": "Alt text for accessibility",
            "url": "https://example.com/wp-content/uploads/2024/01/featured-image.jpg",
            "file": "2024/01/featured-image.jpg",
            "mime_type": "image/jpeg",
            "filesize": 245678,
            "width": 1200,
            "height": 800,
            "sizes": {
                "thumbnail": {
                    "file": "featured-image-150x150.jpg",
                    "width": 150,
                    "height": 150,
                    "mime_type": "image/jpeg",
                    "filesize": 12345
                },
                "medium": {
                    "file": "featured-image-300x200.jpg",
                    "width": 300,
                    "height": 200,
                    "mime_type": "image/jpeg",
                    "filesize": 34567
                },
                "large": {
                    "file": "featured-image-1024x683.jpg",
                    "width": 1024,
                    "height": 683,
                    "mime_type": "image/jpeg",
                    "filesize": 198765
                }
            },
            "meta": {
                "_wp_attachment_image_alt": { "value": "Alt text", "serialized": false }
            }
        }
    ]
}
```

---

## Schema: checksums.sha256

```
# Better WXR Package Checksums
# Generated: 2024-12-15T12:00:00Z
# Algorithm: SHA-256

a1b2c3d4e5f6...  manifest.json
b2c3d4e5f6a1...  site.json
c3d4e5f6a1b2...  authors.json
d4e5f6a1b2c3...  terms.json
e5f6a1b2c3d4...  content/posts-00001.json
f6a1b2c3d4e5...  content/posts-00002.json
...
a1b2c3d4e5f6...  media/manifest.json
```

The importer MUST verify checksums before importing. If any file fails verification, the importer reports exactly which file is corrupt and offers to:
- Skip the corrupt chunk
- Retry the import (re-extract from ZIP)
- Abort the import

---

## Importing a Better Package

### Detection
```php
function detect_format( $file_path ) {
    // Check extension first
    $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
    
    if ( $ext === 'bwxr' || $ext === 'zip' ) {
        // Verify it's a valid ZIP with manifest.json
        $zip = new ZipArchive();
        if ( $zip->open( $file_path ) === true ) {
            if ( $zip->locateName( 'manifest.json' ) !== false ) {
                $zip->close();
                return 'better-package';
            }
            $zip->close();
        }
    }
    
    if ( $ext === 'xml' ) {
        $head = file_get_contents( $file_path, false, null, 0, 100 );
        if ( strpos( $head, '<?xml' ) !== false || strpos( $head, '<rss' ) !== false ) {
            return 'wxr-xml';
        }
    }
    
    // Auto-detect by content
    $head = file_get_contents( $file_path, false, null, 0, 4 );
    if ( $head === "PK\x03\x04" ) {
        return 'better-package'; // ZIP magic bytes
    }
    if ( strpos( $head, '<?xm' ) === 0 ) {
        return 'wxr-xml';
    }
    
    return 'unknown';
}
```

### Import pipeline for Better Package
```
1. Extract ZIP to temp directory
2. Verify checksums (checksums.sha256)
3. Read manifest.json → validate format version
4. Read site.json → validate site metadata
5. Read authors.json → create author mapping
6. Read terms.json → import all terms
7. For each content chunk (in order):
   a. Read chunk JSON
   b. Import each post (create post → import meta chunks → import comments → assign terms)
   c. Same sub-step checkpointing as WXR XML import
   d. Track progress: chunk N of M
8. Read media/manifest.json → queue attachment downloads
9. Run remapping (parents, featured images, menu items, URLs)
10. Finalize: cleanup temp directory, mark job complete
```

### Chunk-level resume
If the import is interrupted mid-chunk:
- The chunk file is already extracted
- The import resumes at the next unprocessed entity within the chunk
- No need to re-extract or re-parse the chunk JSON
- The parsed chunk data is cached in a transient for the duration of that chunk's processing

If the import is interrupted between chunks:
- Extracted files remain in the temp directory
- Import resumes at the next chunk index
- Already-imported chunks are skipped (entity-level idempotency)

---

## Advantages Over WXR XML

| Concern | WXR XML | Better Package |
|---------|---------|----------------|
| **Single-file limit** | One 500MB XML file | Split into 500-post chunks |
| **XML encoding issues** | CDATA escaping, entities | JSON — no encoding needed |
| **Streaming read** | Yes, but fragile | JSON chunks are independent |
| **Resumable export** | No | Yes — chunks written independently |
| **Resumable import** | Requires careful queue/payload handling | Chunks are independent units |
| **Integrity verification** | Manual | Built-in SHA-256 checksums |
| **Meta value size** | All values in one XML node | Per-meta metadata (serialized flag, size) |
| **Media manifest** | Embedded in XML as items | Separate structured manifest |
| **Human readable** | Yes (verbose) | Yes (JSON) |
| **Tool support** | Any XML parser | Standard JSON + ZIP tools |
| **Disk I/O** | Streaming append | Write chunk, flush, move on |
| **Memory** | SAX/XMLReader needed | json_decode() one chunk at a time |

---

## Versioning

The format version is `1.0`. Future versions:
- `1.1`: Add support for WooCommerce product data, orders, coupons
- `1.2`: Add support for block editor reusable blocks and templates
- `2.0`: Add support for incremental/delta exports (only changed content)

The importer must reject unknown major versions (e.g., `3.0` when it only supports `1.x` and `2.x`).

---

## File Size Estimates

For the real-world test file (4,597 posts, ~150-200 meta per post):

| Component | Estimated Size |
|-----------|---------------|
| manifest.json | ~2 KB |
| site.json | ~1 KB |
| authors.json | ~1 KB |
| terms.json | ~50 KB |
| content/ (10 chunks × 500 posts) | ~15-25 MB total |
| media/manifest.json | ~500 KB |
| checksums.sha256 | ~2 KB |
| **Total (uncompressed)** | **~16-26 MB** |
| **Total (ZIP compressed)** | **~5-10 MB** |

Comparable WXR XML for the same data: ~17-30 MB uncompressed.

The Better Package format is roughly the same total size as WXR XML but much more reliable for large imports because of chunking and checksums.
