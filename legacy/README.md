# Legacy code (v3.0.x experimental)

This folder holds the pre-1.0 import engine. It is **not loaded** by `plugin.php` on the `main` branch.

Use this code only as a reference while building the 1.0 rebuild. The frozen v3.0.8 layout at the plugin root is preserved on the `legacy-v3` git branch.

## Contents

| Path | Description |
|------|-------------|
| `class-wxr-*.php` | v3 job-based / SSE import engine |
| `class-command.php` | WP-CLI `wxr-importer` command |
| `class-logger*.php` | Logger implementations |
| `install.php` | v3 table installer (`wxr_import_*`) |
| `templates/` | v3 admin templates |
| `assets/` | v3 admin JavaScript and CSS |
| `tests/` | PHPUnit tests for the v3 engine |
| `bin/` | v3 smoke / import scripts |
| `reference/` | Upstream and third-party reference copies |

## Git branches

| Branch | Purpose |
|--------|---------|
| `main` | 1.0.0 rebuild (active development) |
| `legacy-v3` | Frozen snapshot of v3.0.8 with files at plugin root |

Per `docs/IMPLEMENTATION.md` Phase F, this folder will be quarantined or removed once the 1.0 engine reaches feature parity.
