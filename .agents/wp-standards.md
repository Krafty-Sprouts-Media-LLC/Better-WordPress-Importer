# Relatedly Plugin — WordPress Standards

> **Scope:** These standards apply to ALL code written for the Relatedly plugin. They supplement the general WordPress skill files in this directory with plugin-specific rules derived from the audit of v1.9.15.

---

## 1. File Headers

### 1.1 Main Plugin File (`relatedly.php`)

```php
<?php
/**
 * Plugin Name: Relatedly
 * Plugin URI: https://kraftysprouts.com/portfolio/relatedly/
 * Description: Advanced related posts engine with inline display, end-of-content lists, RSS integration, and multiple themes.
 * Version: 2.0.0
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: relatedly
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package Relatedly
 * @since 2.0.0
 */

defined('ABSPATH') || exit;
```

### 1.2 Class Files (`src/` directory)

Every class file must have a file-level docblock:

```php
<?php
/**
 * [ One-line description of what this class does ]
 *
 * [ Optional: 2-3 sentence explanation if the purpose is non-obvious ]
 *
 * @package Relatedly
 * @since 2.0.0
 */

namespace Relatedly\Core;

// If the file stands alone: no ABSPATH guard needed (autoloaded).
// If the file can be reached directly: add `defined('ABSPATH') || exit;`.

/**
 * [ Class description — what responsibility does this class have? ]
 *
 * @since 2.0.0
 */
class Options {
```

### 1.3 Template Files (`templates/` directory)

Template files do not need class-level docblocks but should identify themselves:

```php
<?php
/**
 * Template: Inline Theme 1 (Simple)
 *
 * @package Relatedly
 * @since 2.0.0
 *
 * @var string $title         Post title
 * @var string $permalink     Post URL
 * @var string $label         Randomized label text
 * @var string $excerpt_text  Trimmed excerpt
 * @var string $css_vars      CSS custom properties string
 * @var string $link_attrs    Link target/rel attributes
 * @var string $tracking_attrs Data attributes for click tracking
 * @var int    $source_post_id Current post ID
 */
```

---

## 2. Docblocks and Comments

### 2.1 PHPDoc on Public Methods

Every `public` method MUST have a PHPDoc block with:
- One-line description
- `@since 2.0.0` tag
- `@param` for each parameter (type + description)
- `@return` for the return type + description

```php
/**
 * Get related posts for a given post ID.
 *
 * Queries a pool of posts by date, shuffles in PHP, and
 * returns the requested count. Results are cached via transients.
 *
 * @since 2.0.0
 *
 * @param int    $post_id The source post ID.
 * @param int    $limit   Maximum number of related posts to return.
 * @param string $context Display context: 'inline', 'end_content', 'rss'.
 *
 * @return WP_Post[] Array of related post objects. Empty array if none found.
 */
public function getRelatedPosts(int $post_id, int $limit, string $context): array {
```

### 2.2 PHPDoc on Protected/Private Methods

Required if the method is non-trivial (>10 lines or complex logic). Simple getters/setters and obvious helpers can omit `@since` but should keep parameter documentation if types aren't clear.

```php
/**
 * Normalize a hex color value from 3-digit to 6-digit format.
 *
 * @since 2.0.0
 *
 * @param string $color Raw color value from user input.
 * @param string $default Fallback if input is invalid or empty.
 *
 * @return string Normalized 6-digit hex color, or the default.
 */
private function normalizeColor(string $color, string $default): string {
```

### 2.3 `@since` Tag Rules

| Location | Required? | Value |
|----------|-----------|-------|
| File-level docblock | Yes | `@since 2.0.0` |
| Class docblock | Yes | `@since 2.0.0` |
| Public method | Yes | `@since 2.0.0` |
| Hook registration | Yes (in docblock or inline comment) | `@since 2.0.0` |
| Protected/private non-trivial method | Yes | `@since 2.0.0` |
| Simple getter/setter | Optional | `@since 2.0.0` |
| New method added after 2.0.0 | Yes | `@since 2.1.0` (actual version) |

Never backdate `@since` to a version older than the file. Everything starts at `2.0.0` in the rebuild.

### 2.4 Inline Comments

Rule: **Explain WHY, not WHAT.**

```php
// CORRECT: Explains the non-obvious reason
// Use array_key_exists instead of isset — isset returns false for null values
// and we need to preserve explicitly saved nulls.
if (array_key_exists($key, $saved)) {

// WRONG: Restates what the code says
// Check if the key exists in the saved array
if (array_key_exists($key, $saved)) {

// WRONG: No comment needed at all for obvious code
// Get the post title
$title = get_the_title($post->ID);
```

Comments are for:
- Why a particular approach was chosen over alternatives
- Non-obvious WordPress API quirks being worked around
- Performance considerations that affect the choice of implementation
- Security considerations (why a specific escaping/sanitizing method was chosen)

Never use comments for:
- Explaining what the code does (write clearer code instead)
- Section dividers (`// ============ SETTINGS ============`)
- TODO markers (use your issue tracker; if you must, format as `// @todo Issue #123: Description`)
- Disabled/old code (delete it — git has the history)

### 2.5 PHPDoc on Properties

Class properties should be documented:

```php
/**
 * Plugin options loaded from the database.
 *
 * Populated by loadOptions() on construction. Contains the merged
 * result of saved options and schema defaults.
 *
 * @since 2.0.0
 * @var array<string, mixed>
 */
private array $options;
```

---

## 3. WordPress Coding Standards

Reference: the `wp-plugin-development` skill in this directory + `phpcs.xml.dist` in the project root.

### 3.1 Quick Rules

| Rule | Standard |
|------|----------|
| Indentation | Tabs |
| Opening braces | Same line |
| Closing braces | New line |
| File encoding | UTF-8 without BOM |
| Line endings | LF (Unix) |
| Trailing whitespace | None |
| End of file | One blank line |
| PHP closing tag | Omit `?>` in pure PHP files |

### 3.2 Naming

| Element | Style | Example |
|---------|-------|---------|
| Namespace | `Relatedly\Sub\Name` | `Relatedly\Core\Options` |
| Class | PascalCase | `QueryBuilder` |
| Method | camelCase | `getRelatedPosts()` |
| Variable | camelCase | `$relatedPosts` |
| Constant | UPPER_SNAKE | `RELATEDLY_VERSION` |
| Option key | snake_case | `inline_max_posts` |
| Hook name | `relatedly_` prefix | `relatedly_inline_labels` |
| Action name | `relatedly_` prefix | `wp_ajax_relatedly_get_stats` |
| Nonce action | `relatedly_` prefix | `relatedly_settings` |
| Transient key | `relatedly_` prefix | `relatedly_pool_123_inline` |

### 3.3 Escaping Output

| Context | Function |
|---------|----------|
| HTML text | `esc_html()`, `esc_html_e()`, `esc_html__()` |
| HTML attributes | `esc_attr()`, `esc_attr_e()`, `esc_attr__()` |
| URLs | `esc_url()` |
| Textareas | `esc_textarea()` |
| CSS in `<style>` | `wp_strip_all_tags()` then `echo` with `phpcs:ignore` |

### 3.4 Sanitizing Input

| Input type | Function |
|-----------|----------|
| Text | `sanitize_text_field(wp_unslash($value))` |
| Integer | `absint($value)` |
| Hex color | `sanitize_hex_color($value)` |
| URL (storage) | `esc_url_raw($value)` |
| Textarea | `sanitize_textarea_field(wp_unslash($value))` |
| Email | `sanitize_email($value)` |
| Array of text | `array_map('sanitize_text_field', $array)` |

**Always `wp_unslash()` before sanitize.** WordPress adds slashes to `$_POST` data.

---

## 4. Performance — Non-Negotiable Rules

These rules exist because the audit found `orderby => 'rand'` throughout v1.9.15, causing the primary performance bottleneck.

### 4.1 Query Strategy

**ABSOLUTE RULE: Never use `orderby => 'rand'` in any WP_Query.**

Approved pattern:
```php
// 1. Query pool by date (uses post_date index)
$args = ['posts_per_page' => 30, 'orderby' => 'date', 'order' => 'DESC'];
$pool = (new \WP_Query($args))->posts;

// 2. Cache the pool
set_transient($cache_key, $pool, HOUR_IN_SECONDS);

// 3. On render: shuffle in PHP, slice to needed count
$pool = get_transient($cache_key);
shuffle($pool);
$selected = array_slice($pool, 0, $limit);
```

### 4.2 Asset Loading

- Frontend assets: only on `is_single()` AND plugin enabled AND post type enabled
- Admin assets: only on `toplevel_page_relatedly-settings` or sub-pages
- Statistics JS: only on statistics tab
- Zero CDN URLs — all assets from `assets/vendor/` (built by webpack)

### 4.3 Caching

| What | Method | TTL | Key Pattern |
|------|--------|-----|-------------|
| Related post pools | Transient | 1 hour | `relatedly_pool_{post_id}_{context}` |
| Dashboard stats | Transient | 10 minutes | `relatedly_dashboard_stats_{md5}` |
| Theme previews | Transient | 1 hour | `relatedly_preview_{theme_key}` |

---

## 5. Freemium Rules

### 5.1 Feature Gating

```php
use Relatedly\Premium\FeatureGate;

// Theme registration (the primary gating point)
if (FeatureGate::isPremium()) {
    $registry->register('inline', 'theme9', new PremiumTheme());
}
```

### 5.2 Code Separation

- Premium files: `src/Premium/` — only loads when `relatedly_fs()->can_use_premium_code()` returns true
- Free code never imports premium classes
- `FeatureGate` is the only bridge class

### 5.3 Admin Upsells

- Upsells only on the plugin's own settings page
- No admin-wide notices
- No dead links — premium URLs must resolve

---

## 6. Security Checklist (per feature)

Every new feature that handles user input or output must pass:

- [ ] Nonce verification on all forms and AJAX
- [ ] `current_user_can('manage_options')` on all admin actions
- [ ] Input sanitized with appropriate WordPress function
- [ ] Output escaped with appropriate WordPress function
- [ ] `wp_safe_redirect()` + `exit` for redirects
- [ ] `$wpdb->prepare()` for all DB queries with variables
- [ ] No user input in SQL strings
- [ ] `wp_ajax_nopriv_` handlers validate nonce + input + authorization

---

## 7. Related Skill Files

This standards file supplements the reference-based skills in this directory:

| Skill | When to load |
|-------|-------------|
| `wp-plugin-development/` | Plugin architecture, Settings API, lifecycle |
| `wp-plugin-directory-guidelines/` | wp.org submission compliance |
| `wp-performance/` | Performance profiling and optimization |
| `wp-block-development/` | Gutenberg blocks (premium feature) |
| `wp-rest-api/` | REST API endpoints (if added) |
| `wp-wpcli-and-ops/` | WP-CLI commands, deployment |
