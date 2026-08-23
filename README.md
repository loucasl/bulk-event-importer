# Bulk Event Importer

A WordPress plugin that fetches external calendar feeds (RSS/ICS), normalizes them, and creates or updates Event posts. Taxonomies, keyword rules, JetEngine field mapping, geocoding, and the allowlist filter are all configured per site from **Settings → Importer Settings**, so one codebase runs on every site.

| | |
|---|---|
| **Version** | 2.0.1 |
| **Requires WordPress** | 6.0+ |
| **Tested up to** | 6.7 |
| **Requires PHP** | 7.4+ |
| **License** | [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) |

## Features

- **Feed import** — RSS and ICS URLs with automatic type detection
- **Keyword filters** — blocked-keyword filter (always on) and optional allowlist filter
- **Scheduling** — configurable import date window, default post status, and cron interval (hourly / twice-daily / daily)
- **Categories & taxonomies** — define any number of taxonomies and keyword groups; nothing is hardcoded
- **JetEngine mapping** — point the plugin at your site's meta keys for dates, location, descriptions, and more
- **Geocoding** — optional module (requires a Google Geocoding API key)
- **Resilient fetching** — retry with backoff, IPv4 fallback, and 403 retry with feed-flavored headers
- **Housekeeping** — automatic trashing of old events with a configurable retention window

## Installation

1. Copy the plugin folder into `wp-content/plugins/` (or install from this repository).
2. Activate **Bulk Event Importer** in the WordPress admin.
3. Open **Settings → Importer Settings** and configure feeds, taxonomies, field mapping, and other options for your site.

## Configuration

All per-site behavior is managed from **Settings → Importer Settings**:

- Feed URLs and type detection
- Blocked-keyword filter (always available) and allowlist filter (optional)
- Import date window, default post status, cron interval
- Categories & taxonomies — define any number of taxonomies and keyword groups
- JetEngine field mapping — map to this site's actual meta keys
- Geocoding module (optional)

### Migrating from a site-forked version

If you are upgrading from an older, site-specific fork of this plugin, see [MIGRATION.md](MIGRATION.md) for settings snippets that bring an existing install up to parity with zero behavior change.

## Extensibility

Two hooks are available for site-specific behavior that does not belong in the shared codebase:

| Hook | Type | Purpose |
|---|---|---|
| `bei_event_source_name` | Filter | Rewrite or blank a parsed source name |
| `bei_after_upsert_event_post` | Action | Fires after every create/update; args: `$post_id`, `$event`, `$status`. Useful for linking to a related custom post type |

## Changelog

### 2.0.1

- Importer Settings: optional modules (Allowlist Filter, Geocoding) appear in a card grid with enable toggles that show or hide each module's settings section; the grid sits at the bottom of the settings page
- Moved "Run Import Now" to the top right below the page title, and removed the shared-code / per-site configuration intro paragraph

### 2.0.0

- Unified codebase merging two previously forked, site-specific versions
- Added dynamic, admin-configurable taxonomy/keyword-group system
- Added configurable JetEngine field mapping (supports both a single "Advanced Date" field and separate start/end date+time fields)
- Added optional, toggleable geocoding module with configurable meta keys
- Added optional, toggleable allowlist keyword filter
- Fixed a timezone bug in RSS and ICS date parsing that could shift events by several hours depending on server timezone
- Switched event de-duplication to a calendar-day-based hash with automatic migration from the older datetime-based hash, so the timezone fix does not create duplicate posts
- Much more resilient feed fetching: retry with backoff, IPv4 fallback, `sslverify=false` last resort, and a 403 retry with feed-flavored headers
- Added automatic trashing of old events (configurable retention window)
- Added per-feed skip-reason reporting in the AJAX import UI
- Configurable cron interval (hourly / twice-daily / daily)

## WordPress.org readme

The [readme.txt](readme.txt) file uses WordPress.org plugin directory format and is kept separately for distribution on wordpress.org.
