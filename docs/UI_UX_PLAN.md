# UI/UX Plan: Better WordPress Importer

## Design Principles

This is an operational administration tool, not a consumer product. The interface must be:
- **Clear**: Show exactly what will happen and what is happening
- **Resilient**: Handle interruptions without losing operator confidence
- **Informative**: Provide actionable feedback, not just raw status
- **Honest**: Distinguish between "import is fine" and "something went wrong but we handled it"
- **WordPress-native**: Use core components, patterns, and visual language

---

## Screen 1: Import Setup (Upload + Choose File)

**Current state:** `templates/intro.php` + `templates/upload.php` — clean card layout, drag-drop zone, media library picker, local file path. This is good. Keep with minor enhancements.

**Changes:**
1. Add "Previous imports" section at the bottom:
   - If a completed job exists: "Last import: 1,234 posts, 56 media, 8 users — [View Report]"
   - If a cancelled job exists: "A previous import was cancelled after processing 423 posts. Starting a new import will skip already-imported items."
2. Add preflight warning for large imports:
   - If user selects a file > 10MB: "This is a large file (~X MB). For best results, copy the file to the server and use the 'file already on server' option below. Browser uploads over 100MB may be unreliable."
3. The Plupload chunked upload UI does not need visible changes — it's WordPress-standard.
4. Add a "Validate XML" button next to the "Select from Media Library" button:
   - Runs a preflight scan only (no import)
   - Shows modal: "XML looks valid. Contains: 4,597 posts, 320 media, 12 authors, 850 comments, 45 terms. Ready to import."
   - Or: "XML has issues: 3 malformed item nodes will be skipped."
5. File path form: Add a "Browse" button if possible (limited by browser security for server paths).

**Status indicator during upload:**
- Current Plupload progress bar — keep as-is
- After upload success, show file summary: "healthtian.xml (17.3 MB) — Uploaded successfully"

---

## Screen 2: Import Settings (Author Mapping + Options)

**Current state:** `templates/select-options.php` — two-column summary grid + author mapping cards + attachment checkbox. Good. Keep with enhancements.

**Changes:**
1. Add "Import name" field (optional):
   - "Label this import: [______________]" — stored in job record for identification in job list
   - Default: filename
2. Author mapping:
   - Current per-author card with dropdown + create-new field — keep
   - Add "Assign all to current user" quick button
   - Add "Create new users for all unmatched" quick button
3. Import options card:
   - Current: "Download and import file attachments" checkbox — keep
   - Add: "Aggressive URL search (slower, catches more URL references)" — only for expert users
   - Add: "Update attachment GUIDs to new URLs (compatibility with v1 importer)" — off by default
4. Summary grid: add a "Potential issues" section at the bottom:
   - "8 authors need mapping (posts will otherwise be assigned to you)"
   - "320 media attachments — downloading may take significant time"
   - "File contains posts from 3 custom post types not registered on this site — these will be imported but may not display correctly"
5. Estimates section:
   - "Estimated time: ~5-10 minutes for content, plus attachment downloading time"
   - "Processing will continue in the background. You can close this page and return."

---

## Screen 3: Import Progress (The Core Redesign)

**Current state:** `templates/import.php` + `assets/import.js` — SSE-based, reconnect is cosmetic, no resume. This needs the most redesign.

**New design:** Polling-based progress page with job persistence.

### Layout

```
┌─────────────────────────────────────────────────────────┐
│  ⬡  WordPress Importer                          [Cancel]│
│                                                         │
│  ┌─ Status Banner ────────────────────────────────────┐ │
│  │ ✓  Import complete                                 │ │
│  │    Imported 4,597 items in 12m 34s                 │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Overall Progress ─────────────────────────────────┐ │
│  │  ████████████████████████████░░  97%   4,460/4,597 │ │
│  │  Processing: posts (batch 89/92)                   │ │
│  │  Elapsed: 11m 23s  ·  Rate: ~390 items/min         │ │
│  │  Est. remaining: ~1 min                            │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ By Type ──────────────────────────────────────────┐ │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐     │ │
│  │  │📄 Posts│ │🖼 Media │ │👤 Users│ │💬 Comm.│     │ │
│  │  │  4,200 │ │    150 │ │      8 │ │     50 │     │ │
│  │  │  done  │ │  done  │ │  done  │ │  done  │     │ │
│  │  └────────┘ └────────┘ └────────┘ └────────┘     │ │
│  │  ┌────────┐                                        │ │
│  │  │🏷 Terms │                                        │ │
│  │  │     45 │                                        │ │
│  │  │  done  │                                        │ │
│  │  └────────┘                                        │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Log ───────────────────────────────────────────────┐│
│  │  Filters: [All ▼] [Warnings (3)] [Errors (0)]      ││
│  │                                                     ││
│  │  ⚠ 11:23:45  Skipped post "Old Draft": auto-draft  ││
│  │  ⚠ 11:23:44  Could not map author "deleted_user"   ││
│  │  ℹ 11:23:40  Imported "Hello World" (post)         ││
│  │  ℹ 11:23:39  Imported "Sample Page" (page)         ││
│  │  ...                                               ││
│  │                                                     ││
│  │  [Show all log entries] [Download full log (.txt)]  ││
│  └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### States

**Processing:**
- Status banner: "Importing... (You can close this page — processing continues on the server)"
- Animated spinner or pulsing progress bar
- "Pause" button (sets job status to `paused`, stops cron processing)

**Paused:**
- Status banner: "Import paused — 2,340 of 4,597 items processed"
- "Resume" button and "Cancel" button

**Complete:**
- Status banner: green, "Import complete"
- All stat cards show full counts
- "View Final Report" button
- "Import Another File" button

**Failed:**
- Status banner: red, "Import failed — [error message]"
- "Retry failed items" button
- "View Error Log" button

**Cancelled:**
- Status banner: yellow, "Import cancelled — 1,890 items were imported successfully"
- "Start New Import" button
- "Resume Import" button (re-creates job from same file with same settings)

### Log behavior
- Log entries stream in via polling (latest N entries per poll)
- Default: show last 20 entries
- "Show all" loads full log from server
- "Download full log" generates a .txt file
- Filters persist across polls
- Log is capped at 1000 entries on server side

---

## Screen 4: Final Report

**New screen.** Reached after import completes or from "View Report" link.

### Layout

```
┌─────────────────────────────────────────────────────────┐
│  ✓  Import Complete — December 15, 2024 at 2:34 PM     │
│                                                         │
│  ┌─ Summary ───────────────────────────────────────────┐│
│  │  Total time:     12 minutes 34 seconds              ││
│  │  File:           healthtian.WordPress.2020-03-06.xml││
│  │  File size:      17.3 MB                            ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  ┌─ Imported ──────────────────────────────────────────┐│
│  │  ✓ 4,597  Posts, pages, and custom post types      ││
│  │  ✓   320  Media attachments                         ││
│  │  ✓     8  Users                                     ││
│  │  ✓   892  Comments                                  ││
│  │  ✓    45  Categories, tags, and terms               ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  ┌─ Skipped (already existed) ─────────────────────────┐│
│  │  ○    12  Posts                                     ││
│  │  ○     3  Comments                                  ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  ┌─ Failed ────────────────────────────────────────────┐│
│  │  ✕     5  Media attachments (remote server errors)  ││
│  │  ✕     2  Posts (invalid post type)                 ││
│  │  [View details]                                     ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  ┌─ Warnings ──────────────────────────────────────────┐│
│  │  ⚠     3  Authors mapped to different users         ││
│  │  ⚠     1  Post parent not found (left as top-level) ││
│  │  [View all 4 warnings]                              ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  [Download Full Report (.txt)]  [Import Another File]   │
└─────────────────────────────────────────────────────────┘
```

---

## Screen 5: Import History

**New screen.** Accessible from a "Import History" link on the import setup page.

### Layout

```
┌─────────────────────────────────────────────────────────┐
│  Import History                                         │
│                                                         │
│  ┌─ Jobs ──────────────────────────────────────────────┐│
│  │  Date          File            Status   Items  Time ││
│  │  ────────────  ──────────────  ───────  ─────  ──── ││
│  │  Dec 15 14:22  healthtian.xml  ✓ Done  4,597  12m  ││
│  │  Dec 14 09:15  backup.xml      ✕ Fail    890   3m  ││
│  │  Dec 10 16:40  export.xml      ⊘ Cancel 2,341   8m ││
│  │                                                     ││
│  │  [Clear history]                                    ││
│  └─────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────┘
```

---

## Pause/Resume/Cancel Behavior

| Action | Immediate Effect | Server Effect | Can Undo |
|--------|-----------------|---------------|----------|
| **Pause** | Polling continues, progress frozen | Job status → `paused`, cron skips this job | Resume |
| **Resume** | Polling continues, progress resumes | Job status → `processing`, cron picks up | Pause again |
| **Cancel** | Progress stops, cancel confirmation shows | Job status → `cancelled`, processed items preserved | Resume (re-creates job) |

- Pause/resume works across browser sessions — closing and reopening the page shows current paused/processing state
- Cancel requires confirmation: "This will stop the import. 2,340 items already imported will remain. You can resume this import later."

---

## WP-CLI Progress

```
$ wp wxr-importer import healthtian.xml --verbose
[INFO] Pre-scanning XML file... (17.3 MB)
[INFO] Found: 4597 posts, 320 media, 8 users, 892 comments, 45 terms
[INFO] Starting import with batch size 50
 25% [===========                        ] 0:02:34 / 0:10:15
[INFO] Processed 1150/4597 items (25%), rate: 447 items/min, ETA: 7m 41s
[WARNING] Skipped post "Old Draft": auto-draft
 50% [=======================            ] 0:05:08 / 0:10:15
...
[SUCCESS] Import complete! 4597 items imported in 10m 15s
[SUCCESS] 0 failed, 12 skipped (already existed)
```

---

## CSS and Visual Consistency

- Use WordPress core CSS variables: `--wp-admin-theme-color`, etc.
- All cards use existing `.wxr-card` class with WordPress-standard border/radius/shadow
- Progress bars use WordPress blue (`#2271b1`) and green (`#00a32a`) accent colors
- Dashicons for all type indicators (already implemented)
- Responsive: stat grid collapses to 2 columns at mobile widths (already implemented)

---

## Accessibility

- All progress information available as text, not just visual bars
- Status messages use `aria-live="polite"` regions
- Buttons have clear accessible labels
- Log entries use semantic `<table>` structure (already implemented)
- Keyboard navigation: cancel, pause, resume buttons focusable in logical order
- Screen reader announces: "Import at 50 percent. Processing batch 46 of 92. 2,300 of 4,597 items complete."

---

## Browser Compatibility

- Chrome, Firefox, Edge, Safari (latest 2 versions)
- IE11: not supported (WordPress itself dropped IE11 support)
- No reliance on bleeding-edge APIs beyond `fetch()` (EventSource is removed)
