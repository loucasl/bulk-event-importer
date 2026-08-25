# Bulk Event Importer

A WordPress plugin that fetches external calendar feeds (RSS/ICS), normalizes them, and creates or updates Event posts. Taxonomies, keyword rules, JetEngine field mapping, geocoding, and the allowlist filter are all configured per site from **Settings → Importer Settings**, so one codebase runs on every site.

| | |
|---|---|
| **Version** | 2.2.1 |
| **Requires WordPress** | 6.9+ |
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
3. Open **Events → Importer Settings** and configure feeds, taxonomies, field mapping, and other options for your site.

## Development

The settings page is built with `@wordpress/scripts`. Precompiled assets are committed under `build/` so sites can deploy without Node.js.

To change the settings UI:

```bash
npm install
npm run build
```

Source lives in `src/admin/settings/`. Settings are exposed at `GET/PUT /wp-json/bulk-event-importer/v1/settings` and still persist to the `bulk_event_importer_settings` option via the existing sanitizer.

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

### 2.2.1

- RSS imports: read `location` / `venue` item fields when present
- When RSS has no venue, enrich from schema.org Event JSON-LD on the event detail page (fixes Tourism Nanaimo and similar SimpleView feeds that only publish venue on the HTML page)
- Re-import updates existing posts when the resolved location changes (and re-geocodes if that module is on)

### 2.2.0

- Reworked Importer Settings copy and layout for non-technical editors
- Fixed a bug where editing blocked keywords could turn off Geocoding or the keyword-match filter
- Optional modules: Fixed event fields can be toggled off; map field names sit behind an advanced disclosure
- Remove actions now use outlined buttons and ask for confirmation

### 2.1.0

- Rebuilt Importer Settings with WordPress DataForm and DataViews (React admin UI)
- REST settings endpoint; same option storage and sanitize logic (no settings migration)
- npm / `@wordpress/scripts` build; prebuilt assets in `build/`
- Requires WordPress 6.9+

### 2.0.1

- Importer Settings: optional modules (Allowlist Filter, Geocoding) appear in a card grid with enable toggles that show or hide each module's settings section; the group sits below Static meta, with the grid directly above those module sections
- Moved "Run Import Now" onto the same row as the page title (right-aligned), with progress shown full-width underneath when an import is running, and removed the shared-code / per-site configuration intro paragraph
- Admin CSS/JS now cache-bust by file modification time so layout tweaks show up after deploy without a plugin version bump

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
