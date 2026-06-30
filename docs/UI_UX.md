# UI/UX Plan V2: Better WordPress Importer

> **Note:** Export functionality is planned for a follow-up release. The MVP (1.0.0) focuses on import reliability.

## Design Principles

This is an operational administration tool. The UI must:
- **Report truthfully** — never conflate scanned, queued, imported, skipped, failed, or partial
- **Survive interruption** — browser close, reload, timeout, server restart must not lose progress
- **Show sub-step detail** — users should see "importing meta 120/200" not "importing post #543"
- **Make failures actionable** — failed items need retry/skip/inspect, not just a count
- **Keep MVP focused on import** — exporter screens appear in Phase 2, not 1.0.0
- **Be WordPress-native** — core components, dashicons, admin color scheme

---

## Navigation

### Top-Level Menu

```
Tools
  ├── Import                   (WordPress core)
  │   └── WordPress (v2)      (launches Better WordPress Importer)
  └── Better Importer          (standalone menu — full access)
      ├── Import
      ├── History
      └── Settings
```

The plugin registers in TWO places:
1. **Tools → Import** as "WordPress (v2)" — familiarity, backward compatibility
2. **Tools → Better Importer** — own menu with import, history, and settings

Export is a Phase 2 feature and must not appear in the MVP navigation.

---

## Screen 1: Import — File Selection

### Layout

```
┌─────────────────────────────────────────────────────────┐
│  Better WordPress Importer                              │
│                                                         │
│  [Import] [History] [Settings]                          │
│                                                         │
│  ┌─ Upload WXR File ──────────────────────────────────┐ │
│  │                                                    │ │
│  │  ┌─────────────────────────────────────────────┐   │ │
│  │  │          Drop .xml file here                │   │ │
│  │  │              or click to browse             │   │ │
│  │  └─────────────────────────────────────────────┘   │ │
│  │                                                    │ │
│  │  Upload progress: ████████████████ 87% (14/16 MB) │ │
│  │                                                    │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ── or ──                                              │
│                                                         │
│  ┌─ Import from Server ───────────────────────────────┐ │
│  │  Path: [ /path/to/wp-content/uploads/import.xml  ] │ │
│  │  [Validate XML]  [Start Import]                    │ │
│  │                                                    │ │
│  │  Uploads directory: /var/www/wp-content/uploads/   │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ── or ──                                              │
│                                                         │
│  ┌─ Import from Media Library ────────────────────────┐ │
│  │  [Browse Media Library]                            │ │
│  │  Selected: healthtian.xml (17.3 MB, uploaded 2h ago)│ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Previous Imports ─────────────────────────────────┐ │
│  │  Dec 15 14:22  healthtian.xml  ✓ Complete  4,597   │ │
│  │  Dec 14 09:15  backup.xml      ✕ Failed     890   │ │
│  │  [View all import history]                         │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### States

**File selected (before upload):**
- Show filename + size estimate
- "Validate XML" button available immediately
- Validation result: "Looks good — 4,597 posts, 6,460 media, 599 terms, 17 users, ~12 min estimated"

**Upload in progress:**
- Progress bar with size (not percentage-only)
- Cancel upload button

**Upload failed:**
- Specific error: "HTTP 500 — file too large for server. Try the 'Import from Server' option below."
- "Try Again" button

---

## Screen 2: Import — Settings

### Layout

```
┌─────────────────────────────────────────────────────────┐
│  Import Settings                                        │
│                                                         │
│  ┌─ Summary ──────────────────────────────────────────┐ │
│  │  File:    healthtian.xml (17.3 MB)                  │ │
│  │  Source:  https://healthtian.com (WXR v1.2)         │ │
│  │  Content: 4,597 posts · 6,460 media · 599 terms     │ │
│  │           17 users · ~12,000 comments               │ │
│  │  Est. time: ~12 minutes (content) + attachment time │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Author Mapping ───────────────────────────────────┐ │
│  │  [Assign all to: (current user) ▼]                 │ │
│  │  [Create new users for unmatched]                  │ │
│  │                                                    │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │ admin (admin)                                │  │ │
│  │  │ ○ Create new user: [admin         ]          │  │ │
│  │  │ ● Assign to: [(current user) ▼]             │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │ editor (Editor Name)                         │  │ │
│  │  │ ● Create new user: [editor        ]          │  │ │
│  │  │ ○ Assign to: [(select user) ▼]              │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  │  ... (17 authors)                                  │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Options ──────────────────────────────────────────┐ │
│  │  ☐ Download and import file attachments            │ │
│  │    (6,460 media files — may take significant time) │ │
│  │                                                    │ │
│  │  ☐ Update attachment GUIDs to new URLs             │ │
│  │    (compatibility with older plugins)              │ │
│  │                                                    │ │
│  │  Label: [healthtian-import______________] (optional)│ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Warnings ─────────────────────────────────────────┐ │
│  │  ⚠ 3 custom post types not registered on this site │ │
│  │  ⚠ 12 authors need mapping (defaults to you)       │ │
│  │  ⚠ Media fetching is enabled for 6,460 files       │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  [Back]                    [Start Import in Background] │
└─────────────────────────────────────────────────────────┘
```

---

## Screen 3: Import — Progress (Honest)

### Processing State

```
┌─────────────────────────────────────────────────────────┐
│  ⬡  Importing healthtian.xml              [Pause] [✕]  │
│                                                         │
│  ┌─ Status ───────────────────────────────────────────┐ │
│  │  ● Processing: 12% · Elapsed: 1m 23s · Rate: ~340/min│
│  │  Processing continues if you close this page.       │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Overall ──────────────────────────────────────────┐ │
│  │  ██████░░░░░░░░░░░░░░░░░░░░░░░░  12%  (1,402/11,673)│
│  │  Phase: Importing posts · Batch 47 of ~460         │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ By Type ──────────────────────────────────────────┐ │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐           │ │
│  │  │  📄 Posts │ │  🏷 Terms │ │  👤 Users │           │ │
│  │  │  2,450   │ │    599   │ │     17   │           │ │
│  │  │  remain  │ │   done ✓ │ │   done ✓ │           │ │
│  │  └──────────┘ └──────────┘ └──────────┘           │ │
│  │  ┌──────────┐ ┌──────────┐                        │ │
│  │  │  🖼 Media │ │  💬 Comm.│                        │ │
│  │  │    0     │ │      0   │                        │ │
│  │  │ skipped  │ │  pending │                        │ │
│  │  └──────────┘ └──────────┘                        │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Current Entity ───────────────────────────────────┐ │
│  │  "How to Stay Healthy in 2020" (post #1543)        │ │
│  │  Step: Importing meta · 120 of 200 rows complete   │ │
│  │  ████████████████████░░░░░░░░░░░  60%              │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Activity Log ─────────────────────────────────────┐ │
│  │  [All ▼] [Warnings (3)] [Errors (0)]              │ │
│  │                                                    │ │
│  │  11:23:45  ⚠ Skipped "Old Draft": auto-draft      │ │
│  │  11:23:44  ℹ Imported "Sample Page" (page #12)     │ │
│  │  11:23:43  ✓ "Hello World" complete (187 meta, 5c) │ │
│  │  11:23:40  ⚠ Meta key "bad_plugin_data" needs retry│ │
│  │  ...                                               │ │
│  │                                                    │ │
│  │  [Download Full Log]                               │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Paused State

```
  ● Paused · 2,450 of 4,597 posts imported
  [Resume] [Cancel Import]
```

### Complete State

```
  ✓ Import complete · 4,597 posts in 12m 34s
  
  ┌─ Summary ───────────────────────────────────────────┐
  │  ✓ 4,597  Posts imported                            │
  │  ○ 6,460  Media skipped (fetching disabled)         │
  │  ✓   599  Terms imported                            │
  │  ✓    17  Users imported                            │
  │  ✓ 8,230  Comments imported                         │
  │  ✕     3  Failed (2 timeout, 1 invalid type)        │
  │  ⚠    12  Warnings                                  │
  └─────────────────────────────────────────────────────┘
  
  [View Full Report] [Import Another File]
```

### Failed Items Section (expandable in complete state)

```
  ┌─ 3 Failed Items ────────────────────────────────────┐ │
  │  Post #2341 "Huge Post"       Timeout (meta 200MB)  │ │
  │    Step: import_meta · Cursor: 45/200 · Attempts: 3 │ │
  │    [Retry with limits] [Skip] [Inspect XML]         │ │
  │                                                     │ │
  │  Post #4521 "Problem Post"    Invalid post type     │ │
  │    Error: post_type "bad_type" not registered       │ │
  │    [Skip]                                           │ │
  └─────────────────────────────────────────────────────┘
```

---

---

## Screen 5: History

```
┌─────────────────────────────────────────────────────────┐
│  History                                                │
│                                                         │
│  [Imports] [Exports]                                   │
│                                                         │
│  ┌─ Import History ───────────────────────────────────┐ │
│  │  Date          File              Status    Items   │ │
│  │  ────────────  ────────────────  ────────  ──────  │ │
│  │  Dec 15 14:22  healthtian.xml    ✓ Done   4,597   │ │
│  │  Dec 14 09:15  backup.xml        ✕ Failed   890   │ │
│  │  Dec 10 16:40  export.xml        ⊘ Cancel  2,341  │ │
│  │                                                    │ │
│  │  Click a row to view full report                   │ │
│  │  [Clear History]                                   │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

---

## Screen 6: Settings

```
┌─────────────────────────────────────────────────────────┐
│  Settings                                               │
│                                                         │
│  ┌─ Import Defaults ──────────────────────────────────┐ │
│  │  Batch time limit:      [25] seconds               │ │
│  │  Entity timeout:         [10] seconds              │ │
│  │  Meta chunk size:        [25] rows                 │ │
│  │  Max retries per item:   [3]                       │ │
│  │  Cache flush interval:   [200] posts               │ │
│  │  Default author:         [(current user) ▼]        │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Maintenance ──────────────────────────────────────┐ │
│  │  [Clean up temporary files]                        │ │
│  │  [Remove legacy import metadata]                   │ │
│  │  [Delete all import/export history]                │ │
│  └────────────────────────────────────────────────────┘ │
│                                                         │
│  [Save Settings]                                       │
└─────────────────────────────────────────────────────────┘
```

---

## Pause / Resume / Cancel Behavior

| Action | Browser Effect | Server Effect | Data Safety |
|--------|---------------|---------------|-------------|
| **Pause** | Progress freezes, "Paused" shown | Job status → PAUSED, cron skips, lock released | All processed items preserved |
| **Resume** | Progress resumes from checkpoint | Job status → PROCESSING, cron resumes | Continues from exact sub-step |
| **Cancel** | Confirmation dialog, then redirect to history | Job status → CANCELLED, cleanup runs | Imported items preserved, partial items tagged |
| **Close browser** | No effect on server | Processing continues via cron or next AJAX poll | Progress persists in job table |
| **Reopen browser** | Polls status, shows current progress | Responds with current job state | Accurate from last checkpoint |

---

## Accessibility

- All progress values available as text in `aria-live="polite"` regions
- Screen reader announcements: "Import at 45 percent. Processing post Hello World, meta row 120 of 200."
- All buttons have accessible labels
- Color is never the sole indicator of state (icons + text)
- Keyboard navigation: logical tab order through all controls
- Reduced motion: progress bars update without animation when `prefers-reduced-motion` is active
