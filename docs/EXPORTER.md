# Exporter Design: Better WordPress Importer

> **Status: Phase 2 (deferred).** This document describes the planned exporter. The MVP (1.0.0) focuses on import reliability. Export ships in a follow-up release.

## 1. Architecture

The exporter is a parallel subsystem alongside the importer. It reuses the job infrastructure (tables, queue, cron, progress UI) but has its own engine.

### Shared Infrastructure (import + export)
- `better_import_jobs` table (with `job_type` column: `import` | `export`)
- `better_import_queue` table
- `Better_Import_Job` model (base class for both import and export jobs)
- `Better_Import_Processor` orchestration pattern (shared base class)
- Progress UI: same polling pattern, different templates

### Exporter-specific
- `class-better-exporter.php` — export engine
- `class-better-export-job.php` — extends base job model
- `class-better-export-processor.php` — extends base processor
- `class-better-wxr-writer.php` — streaming WXR XML writer
- `class-better-package-writer.php` — Better Package ZIP writer
- `class-better-media-resolver.php` — attach media to filtered exports
- `templates/export-settings.php` — export configuration page
- `templates/export-progress.php` — export progress page
- `assets/js/export-progress.js` — export progress UI

---

## 2. Export Formats

### Format A: WordPress WXR (Compatibility Mode)

Standard WXR XML following the WordPress export format. This is the same format produced by `Tools → Export` in WordPress core, but with better performance and background processing.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:wfw="http://wellformedweb.org/CommentAPI/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:wp="http://wordpress.org/export/1.2/">

<channel>
    <title>Site Title</title>
    <link>https://example.com</link>
    <description>Site description</description>
    <pubDate>Mon, 15 Dec 2024 12:00:00 +0000</pubDate>
    <language>en-US</language>
    <wp:wxr_version>1.2</wp:wxr_version>
    <wp:base_site_url>https://example.com</wp:base_site_url>
    <wp:base_blog_url>https://example.com</wp:base_blog_url>

    <!-- Authors -->
    <wp:author>...</wp:author>

    <!-- Categories -->
    <wp:category>...</wp:category>

    <!-- Tags -->
    <wp:tag>...</wp:tag>

    <!-- Custom Terms -->
    <wp:term>...</wp:term>

    <!-- Items (posts, pages, CPTs, attachments, nav_menu_items) -->
    <item>
        <title>Post Title</title>
        <link>https://example.com/post-title/</link>
        <pubDate>Mon, 01 Jan 2024 00:00:00 +0000</pubDate>
        <dc:creator><![CDATA[admin]]></dc:creator>
        <guid isPermaLink="false">https://example.com/?p=1</guid>
        <description></description>
        <content:encoded><![CDATA[Post content...]]></content:encoded>
        <excerpt:encoded><![CDATA[Excerpt...]]></excerpt:encoded>
        <wp:post_id>1</wp:post_id>
        <wp:post_date>2024-01-01 00:00:00</wp:post_date>
        <wp:post_date_gmt>2024-01-01 00:00:00</wp:post_date_gmt>
        <wp:post_modified>2024-06-15 12:00:00</wp:post_modified>
        <wp:post_modified_gmt>2024-06-15 12:00:00</wp:post_modified_gmt>
        <wp:comment_status>open</wp:comment_status>
        <wp:ping_status>open</wp:ping_status>
        <wp:post_name>post-title</wp:post_name>
        <wp:status>publish</wp:status>
        <wp:post_parent>0</wp:post_parent>
        <wp:menu_order>0</wp:menu_order>
        <wp:post_type>post</wp:post_type>
        <wp:post_password></wp:post_password>
        <wp:is_sticky>0</wp:is_sticky>

        <!-- Categories assigned to this post -->
        <category domain="category" nicename="uncategorized"><![CDATA[Uncategorized]]></category>
        <category domain="post_tag" nicename="tag-name"><![CDATA[Tag Name]]></category>

        <!-- Post meta -->
        <wp:postmeta>
            <wp:meta_key>_thumbnail_id</wp:meta_key>
            <wp:meta_value><![CDATA[123]]></wp:meta_value>
        </wp:postmeta>
        <wp:postmeta>
            <wp:meta_key>custom_field</wp:meta_key>
            <wp:meta_value><![CDATA[value]]></wp:meta_value>
        </wp:postmeta>

        <!-- Comments -->
        <wp:comment>
            <wp:comment_id>1</wp:comment_id>
            <wp:comment_author><![CDATA[Commenter]]></wp:comment_author>
            <wp:comment_author_email>email@example.com</wp:comment_author_email>
            <wp:comment_author_url>https://example.com</wp:comment_author_url>
            <wp:comment_author_IP>127.0.0.1</wp:comment_author_IP>
            <wp:comment_date>2024-01-02 00:00:00</wp:comment_date>
            <wp:comment_date_gmt>2024-01-02 00:00:00</wp:comment_date_gmt>
            <wp:comment_content><![CDATA[Comment text]]></wp:comment_content>
            <wp:comment_approved>1</wp:comment_approved>
            <wp:comment_type></wp:comment_type>
            <wp:comment_parent>0</wp:comment_parent>
            <wp:comment_user_id>0</wp:comment_user_id>
            <wp:commentmeta>
                <wp:meta_key>custom_comment_field</wp:meta_key>
                <wp:meta_value><![CDATA[value]]></wp:meta_value>
            </wp:commentmeta>
        </wp:comment>
    </item>

    <!-- ... more items ... -->
</channel>
</rss>
```

**Sub-format detection:** The importer detects WXR-compatible XML by the presence of `<rss>` + `<channel>` + `<wp:wxr_version>` elements. No format negotiation needed — this is universal.

### Format B: Better Package Format

For large sites or unreliable connections, a multi-file package is more reliable than one giant XML file.

#### Package structure
```
export-2024-12-15-site-name.zip
│
├── manifest.json           # Overall metadata and file index
├── site.json               # Channel-level data (title, URL, authors, base URLs)
├── terms.json              # All terms (categories, tags, custom taxonomies)
├── chunks/
│   ├── posts-0001.json     # Posts 1-500 as JSON records
│   ├── posts-0002.json     # Posts 501-1000
│   └── posts-NNNN.json     # ...
├── media/
│   └── manifest.json       # Media file listing with original URLs
└── checksums.sha256        # SHA-256 of each file in the package
```

#### manifest.json
```json
{
    "format": "better-wxr-1.0",
    "source_site": "https://example.com",
    "exported_at": "2024-12-15T12:00:00Z",
    "generator": "Better WordPress Importer 1.x",
    "wxr_version": "1.2",
    "counts": {
        "posts": 4597,
        "pages": 12,
        "media": 6460,
        "users": 17,
        "terms": 599,
        "comments": 8230
    },
    "files": {
        "site": "site.json",
        "terms": "terms.json",
        "chunks": [
            "chunks/posts-0001.json",
            "chunks/posts-0002.json",
            "chunks/posts-0010.json"
        ],
        "media_manifest": "media/manifest.json"
    },
    "chunk_size": 500,
    "total_chunks": 10
}
```

#### site.json
```json
{
    "title": "Site Title",
    "link": "https://example.com",
    "description": "Site description",
    "language": "en-US",
    "wxr_version": "1.2",
    "base_site_url": "https://example.com",
    "base_blog_url": "https://example.com",
    "authors": [
        {
            "id": 1,
            "login": "admin",
            "email": "admin@example.com",
            "display_name": "Admin User",
            "first_name": "Admin",
            "last_name": "User"
        }
    ]
}
```

#### terms.json
```json
[
    {
        "id": 1,
        "taxonomy": "category",
        "slug": "uncategorized",
        "name": "Uncategorized",
        "description": "",
        "parent": 0
    },
    {
        "id": 2,
        "taxonomy": "post_tag",
        "slug": "health",
        "name": "Health",
        "description": "Health-related posts",
        "parent": 0
    }
]
```

#### chunks/posts-NNNN.json
```json
[
    {
        "id": 1,
        "type": "post",
        "title": "Hello World",
        "slug": "hello-world",
        "status": "publish",
        "date": "2024-01-01T00:00:00Z",
        "date_gmt": "2024-01-01T00:00:00Z",
        "modified": "2024-06-15T12:00:00Z",
        "modified_gmt": "2024-06-15T12:00:00Z",
        "author": "admin",
        "content": "Post content...",
        "excerpt": "Excerpt...",
        "comment_status": "open",
        "ping_status": "open",
        "password": "",
        "parent": 0,
        "menu_order": 0,
        "guid": "https://example.com/?p=1",
        "is_sticky": false,
        "terms": {
            "category": ["uncategorized"],
            "post_tag": ["health", "wellness"]
        },
        "meta": {
            "_thumbnail_id": 123,
            "_edit_last": 1,
            "custom_field": "value"
        },
        "comments": [
            {
                "id": 1,
                "author": "Commenter",
                "author_email": "email@example.com",
                "author_url": "https://example.com",
                "author_ip": "127.0.0.1",
                "date": "2024-01-02T00:00:00Z",
                "date_gmt": "2024-01-02T00:00:00Z",
                "content": "Comment text",
                "approved": 1,
                "type": "",
                "parent": 0,
                "user_id": 0,
                "meta": {}
            }
        ]
    }
]
```

#### media/manifest.json
```json
[
    {
        "id": 100,
        "post_id": 5,
        "url": "https://example.com/wp-content/uploads/2024/01/image.jpg",
        "file": "2024/01/image.jpg",
        "title": "image",
        "alt": "Description",
        "mime_type": "image/jpeg",
        "filesize": 245678,
        "width": 1200,
        "height": 800,
        "sizes": {
            "thumbnail": { "file": "image-150x150.jpg", "width": 150, "height": 150 },
            "medium": { "file": "image-300x200.jpg", "width": 300, "height": 200 },
            "large": { "file": "image-1024x683.jpg", "width": 1024, "height": 683 }
        }
    }
]
```

### Format Selection

| Criteria | WXR XML | Better Package |
|----------|---------|----------------|
| **Site size** | < 5,000 posts | Any size |
| **Meta size** | Small meta values | Large/complex meta |
| **Portability** | Universal — any importer | Only this plugin |
| **Resumability** | Not resumable (single file) | Fully resumable (chunked) |
| **Validation** | XML validation only | JSON schema + checksums |
| **Human readable** | Yes (XML) | Partially (JSON) |
| **Compression** | Can be gzipped | ZIP natively |

---

## 3. Export Engine

### 3.1 Export Job Lifecycle

```
CREATED → QUERYING → GENERATING → PACKAGING → COMPLETE
    ↓         ↓           ↓            ↓
  (set     (query      (generate    (create ZIP
   params)  posts,      chunks,      if Better
            terms,      write to     Package
            users)      disk)        format)
```

### 3.2 Export Processor

```php
class Better_Export_Processor {
    /**
     * Process one batch of the export job.
     *
     * Phases:
     *   querying:    Determine total counts, build post ID lists
     *   generating:  Write XML/JSON chunks to temp directory
     *   packaging:   Assemble final file(s)
     *
     * @since Phase 2
     *
     * @param Better_Export_Job $job         The export job.
     * @param int            $max_seconds Max wall-clock time for this batch.
     *
     * @return array Batch result with progress counters.
     */
    public function process_batch( Better_Export_Job $job, int $max_seconds = 25 ): array {
        $start = microtime( true );
        
        switch ( $job->phase ) {
            case 'querying':
                return $this->phase_query_content( $job, $start, $max_seconds );
            
            case 'generating':
                return $this->phase_generate_chunks( $job, $start, $max_seconds );
            
            case 'packaging':
                return $this->phase_package_export( $job, $start, $max_seconds );
            
            default:
                return array( 'is_complete' => true );
        }
    }
    
    /**
     * Phase 1: Query all content and build ordered ID lists.
     *
     * For small sites (< 5,000 posts): one batch.
     * For large sites: query in batches of 1,000, checkpoint after each.
     */
    protected function phase_query_content( $job, $start_time, $max_seconds ) {
        // Build ordered lists of post IDs, term IDs, user IDs
        // Respect filters: post type, date range, author, status, taxonomy
        
        // Checkpoint: save queried IDs to job options
        // Next batch continues from last queried ID
    }
    
    /**
     * Phase 2: Generate export chunks.
     *
     * Each chunk = N posts written to a temp file.
     * For WXR format: append to single XML file with streaming write.
     * For Better Package: write JSON chunk files.
     */
    protected function phase_generate_chunks( $job, $start_time, $max_seconds ) {
        $chunk_size = 100; // posts per chunk
        
        while ( ( microtime( true ) - $start_time ) < $max_seconds ) {
            $post_ids = $this->get_next_post_chunk( $job, $chunk_size );
            if ( empty( $post_ids ) ) {
                $job->advance_phase( 'packaging' );
                return array( 'is_complete' => false, 'phase' => 'packaging' );
            }
            
            $this->write_chunk( $job, $post_ids );
            $job->increment_cursor( count( $post_ids ) );
        }
        
        return array( 'is_complete' => false, 'phase' => 'generating' );
    }
    
    /**
     * Write one chunk of posts to the temp output file.
     */
    protected function write_chunk( $job, array $post_ids ) {
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            $meta = get_post_meta_all( $post_id ); // custom helper for all meta
            $terms = wp_get_object_terms_all( $post_id ); // all taxonomies
            $comments = get_comments( array( 'post_id' => $post_id ) );
            
            if ( $job->format === 'wxr' ) {
                $this->write_wxr_item( $job->output_handle, $post, $meta, $terms, $comments );
            } else {
                $this->write_json_item( $job, $post, $meta, $terms, $comments );
            }
        }
        
        // Flush to disk after each chunk
        if ( $job->output_handle ) {
            fflush( $job->output_handle );
        }
    }
    
    /**
     * Phase 3: Package the export.
     *
     * WXR format: close XML file, optionally gzip.
     * Better Package: create ZIP, add checksums, create manifest.
     */
    protected function phase_package_export( $job, $start_time, $max_seconds ) {
        if ( $job->format === 'wxr' ) {
            $this->finalize_wxr_file( $job );
        } else {
            $this->create_package_zip( $job );
        }
        
        $job->mark_complete();
        return array( 'is_complete' => true, 'download_url' => $job->get_download_url() );
    }
}
```

### 3.3 WXR XML Writing

Use streaming XML write — never build the entire XML in memory:

```php
protected function initialize_wxr_file( $job ) {
    $file = $job->get_temp_file_path();
    $handle = fopen( $file, 'wb' );
    
    // Write header
    fwrite( $handle, $this->get_wxr_header( $job ) );
    
    // Write channel opening
    fwrite( $handle, "<channel>\n" );
    
    // Write site metadata
    fwrite( $handle, $this->get_wxr_site_info( $job ) );
    
    // Write authors
    foreach ( $job->get_authors() as $author ) {
        fwrite( $handle, $this->format_wxr_author( $author ) );
    }
    
    // Write terms (categories first, then tags, then custom)
    foreach ( $job->get_terms() as $term ) {
        fwrite( $handle, $this->format_wxr_term( $term ) );
    }
    
    $job->output_handle = $handle;
    $job->output_file   = $file;
}

protected function finalize_wxr_file( $job ) {
    if ( $job->output_handle ) {
        fwrite( $job->output_handle, "</channel>\n</rss>\n" );
        fclose( $job->output_handle );
    }
    
    // Optionally gzip
    if ( $job->get_option( 'compress' ) ) {
        $this->gzip_file( $job->output_file );
    }
}
```

### 3.5 Media Attachment for Filtered Exports

**Problem:** WordPress core's exporter has a known bug — when you filter exports by category, author, or date range, featured images and media embedded in post content are not included in the export. The plugin `export-media-with-selected-content` (by DKZR) fixes this by hooking into the `export_query` filter to add attachment IDs. Our exporter doesn't have this bug because we query posts directly, but we build the same logic into our own media resolver for completeness and correctness.

**Solution:** `class-better-media-resolver.php` always runs during the querying phase, regardless of whether filters are applied. It finds all media that should be included:

```php
/**
 * Resolve all media attachments that should be included in the export.
 *
 * This runs ALWAYS — even for unfiltered "all content" exports —
 * to ensure featured images, galleries, and embedded media are
 * never silently dropped.
 *
 * @since 1.0.0
 */
class Better_Media_Resolver {
    /**
     * Given a list of post IDs to export, find all attached media.
     *
     * @param int[] $post_ids Post IDs selected for export.
     * @return int[] Attachment IDs to include.
     */
    public function resolve( array $post_ids ): array {
        global $wpdb;
        $media_ids = array();

        if ( empty( $post_ids ) ) {
            return $media_ids;
        }

        // 1. Featured images (_thumbnail_id)
        $thumb_ids = $wpdb->get_col( sprintf(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_thumbnail_id'
             AND post_id IN (%s)",
            implode( ',', $post_ids )
        ) );
        $media_ids = array_merge( $media_ids, array_map( 'intval', $thumb_ids ) );

        // 2. Media uploaded to these posts (post_parent)
        $child_ids = $wpdb->get_col( sprintf(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_parent IN (%s)",
            implode( ',', $post_ids )
        ) );
        $media_ids = array_merge( $media_ids, array_map( 'intval', $child_ids ) );

        // 3. Media referenced in post_content
        $content_ids = $this->find_media_in_content( $post_ids );
        $media_ids = array_merge( $media_ids, $content_ids );

        // 4. Media referenced by URL in post_content (src="...uploads/...")
        $url_ids = $this->find_media_by_url_in_content( $post_ids );
        $media_ids = array_merge( $media_ids, $url_ids );

        return array_unique( array_filter( $media_ids ) );
    }

    /**
     * Find media referenced in post_content via:
     * - wp-image-{id} CSS class
     * - wp-att-{id} CSS class
     * - [gallery ids="1,2,3"] shortcode
     * - [playlist ids="4,5"] shortcode
     * - <!-- wp:gallery {"ids":[1,2,3]} --> Gutenberg block
     * - <!-- wp:image {"id":6} --> Gutenberg block
     * - <!-- wp:audio {"id":7} --> Gutenberg block
     * - <!-- wp:video {"id":8} --> Gutenberg block
     */
    protected function find_media_in_content( array $post_ids ): array {
        global $wpdb;
        $ids = array();

        // Query post_content from selected posts
        $contents = $wpdb->get_results( sprintf(
            "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN (%s)",
            implode( ',', $post_ids )
        ) );

        foreach ( $contents as $post ) {
            // wp-image-{id} and wp-att-{id} classes
            preg_match_all( '#(wp-image-|wp-att-)(\d+)#', $post->post_content, $m );
            foreach ( $m[2] as $id ) {
                $ids[] = (int) $id;
            }

            // [gallery ids="1,2,3"] and [playlist ids="4,5"]
            preg_match_all(
                '#\[(gallery|playlist)\s+.*ids=["\']([\d\s,]*)["\']#',
                $post->post_content, $m
            );
            foreach ( $m[2] as $id_list ) {
                foreach ( explode( ',', $id_list ) as $id ) {
                    $ids[] = (int) trim( $id );
                }
            }

            // <!-- wp:gallery {"ids":[1,2,3]} -->
            preg_match_all( '#<!-- wp:gallery ({[^}]+})#', $post->post_content, $m );
            foreach ( $m[1] as $json ) {
                $data = json_decode( $json );
                if ( isset( $data->ids ) && is_array( $data->ids ) ) {
                    foreach ( $data->ids as $id ) {
                        $ids[] = (int) $id;
                    }
                }
            }

            // <!-- wp:image {"id":6} -->, <!-- wp:audio {"id":7} -->, <!-- wp:video {"id":8} -->
            preg_match_all( '#<!-- wp:(image|audio|video) ({[^}]+})#', $post->post_content, $m );
            foreach ( $m[2] as $i => $json ) {
                $data = json_decode( $json );
                if ( isset( $data->id ) ) {
                    $ids[] = (int) $data->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Find media by URL references in post_content.
     *
     * Builds a URL → attachment ID map from all media, then
     * scans post_content for src/href attributes matching upload URLs.
     */
    protected function find_media_by_url_in_content( array $post_ids ): array {
        global $wpdb;
        $ids = array();

        // Build URL → ID map from all attachments (chunked for memory)
        $upload_dir = wp_get_upload_dir();
        $base_url   = $upload_dir['baseurl'];

        $attachments = $wpdb->get_results(
            "SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment'",
            OBJECT_K
        );

        if ( empty( $attachments ) ) {
            return $ids;
        }

        $url_map = array();
        foreach ( $attachments as $id => $att ) {
            $url_map[ $att->guid ] = (int) $id;
        }

        // Also map _wp_attached_file values to IDs (chunked)
        $chunk_size = 1000;
        $all_ids    = array_keys( $attachments );
        for ( $i = 0; $i < count( $all_ids ); $i += $chunk_size ) {
            $chunk = array_slice( $all_ids, $i, $chunk_size );
            $chunk[] = 0;
            $metas = $wpdb->get_results( sprintf(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file'
                 AND post_id IN (%s)",
                implode( ',', $chunk )
            ) );
            foreach ( $metas as $meta ) {
                $url_map[ $base_url . '/' . $meta->meta_value ] = (int) $meta->post_id;
            }
        }

        // Scan content for URLs
        $contents = $wpdb->get_results( sprintf(
            "SELECT post_content FROM {$wpdb->posts} WHERE ID IN (%s)",
            implode( ',', $post_ids )
        ) );

        foreach ( $contents as $post ) {
            preg_match_all( '#(?:src|href)=["\']([^"\']+)["\']#i', $post->post_content, $m );
            foreach ( $m[1] as $url ) {
                if ( isset( $url_map[ $url ] ) ) {
                    $ids[] = $url_map[ $url ];
                }
                // Also try without scheme
                $url_no_scheme = preg_replace( '#^https?:#', '', $url );
                if ( $url_no_scheme !== $url && isset( $url_map[ $url_no_scheme ] ) ) {
                    $ids[] = $url_map[ $url_no_scheme ];
                }
            }
        }

        return $ids;
    }
}
```

**Usage in export pipeline:**

```php
// Phase querying in Better_Export_Processor
protected function phase_query_content( $job ) {
    // Get post IDs matching the user's filters
    $post_ids = $this->get_filtered_post_ids( $job );

    // Always resolve attached media
    $resolver   = new Better_Media_Resolver();
    $media_ids  = $resolver->resolve( $post_ids );

    // Merge into export list
    $all_ids = array_unique( array_merge( $post_ids, $media_ids ) );

    $job->set_export_post_ids( $all_ids );
    $job->set_media_attachments( $media_ids ); // tracked separately for media manifest

    // Separate counts for honest UI
    $job->total_posts = count( $post_ids );
    $job->total_media = count( $media_ids );
}
```

This means:
- **All content export:** already works because WordPress returns attachments with `post_type = 'any'`. Our explicit media resolver is belt-and-suspenders.
- **Filtered export (by category, author, etc.):** featured images, gallery images, and embedded media are always included. No additional checkbox needed — it's automatic and correct.
- **WXR output:** attachment posts appear as `<item>` elements with `wp:post_type = attachment` and `wp:attachment_url`.
- **Better Package output:** attachments appear in `media/manifest.json` with full metadata + their `post_parent` reference.

### 3.6 Meta Exclusion Rules

```php
// Default excluded meta keys (internal WordPress, not useful to export)
const DEFAULT_META_EXCLUDE = [
    '_edit_lock',
    '_edit_last',
    '_wp_old_slug',
    '_wp_old_date',
    '_transient_*',       // pattern match
    '_wc_session_*',       // pattern match
    '_expiration_*',       // pattern match
];

// Filter hook
$excluded = apply_filters( 'better_exporter.exclude_meta_keys', self::DEFAULT_META_EXCLUDE, $post_id );
```

---

## 4. File Cleanup

### Temp directory
```
wp-content/uploads/wxr-exports/
├── job-123/
│   ├── output.xml           (WXR format — appended incrementally)
│   ├── chunks/              (Better Package — chunk files)
│   ├── manifest.json        (Better Package)
│   └── export.zip           (Better Package final)
├── job-124/
│   └── ...
└── .htaccess                (Deny from all)
```

Cleanup rules:
- Completed exports: deleted 7 days after completion (cron)
- Abandoned exports (> 24h inactive): deleted on daily cron
- Download link expires after 7 days
- Files deleted immediately if job is cancelled

---

## 5. Registration with WordPress Export System

The plugin registers as an export provider alongside the core exporter:

```php
// In plugin.php
add_action( 'admin_menu', function() {
    // Register under Tools > Export
    add_submenu_page(
        'tools.php',
        __( 'Better Export', 'better-wordpress-importer' ),
        __( 'Better Export', 'better-wordpress-importer' ),
        'export',
        'better-export',
        array( $GLOBALS['better_exporter_ui'], 'dispatch' )
    );
} );

// Optional: integrate with core export page
add_action( 'export_filters', function() {
    echo '<p><a href="' . admin_url( 'tools.php?page=better-export' ) . '" class="button">';
    esc_html_e( 'Use Better Exporter (supports large sites, background processing)', 'better-wordpress-importer' );
    echo '</a></p>';
} );
```

This means the user sees TWO export options under Tools:
1. **Export** (WordPress core) — original
2. **Better Export** (this plugin) — enhanced

And TWO import options:
1. **WordPress** (original importer)
2. **WordPress (v2)** (this plugin)
