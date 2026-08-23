=== Bulk Event Importer ===
Contributors: reddragoncreative
Tags: events, import, ics, rss, jetengine
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later

Fetches external calendar feeds (RSS/ICS), normalizes them, and creates/updates
Event posts. Taxonomies, keyword rules, JetEngine field mapping, geocoding,
and the allowlist filter are all configured per site from Settings, so one
codebase runs on every site.

== Description ==

This plugin is shared, unmodified, across every site that uses it. All
per-site behavior lives in Settings > Importer Settings:

* Feed URLs and type detection
* Blocked-keyword filter (always available) and allowlist filter (optional)
* Import date window, default post status, cron interval
* Categories & Taxonomies: define any number of taxonomies and keyword
  groups; no category structure is hardcoded
* JetEngine field mapping: point the plugin at this site's actual meta keys
* Geocoding module (optional, requires a Google Geocoding API key)

See MIGRATION.md in this repository for the settings needed to bring an
existing install (running an older, site-forked version of this plugin) up
to parity with zero behavior change.

Two extensibility filters/actions are available for anything too
site-specific to belong in the shared codebase:

* `bei_event_source_name` (filter) — rewrite or blank a parsed source name.
* `bei_after_upsert_event_post` (action) — fires after every create/update,
  args: $post_id, $event, $status. Useful for hooking a site-specific
  integration (e.g. auto-linking to a related custom post type).

== Changelog ==

= 2.2.0 =
* Reworked Importer Settings copy and layout for non-technical editors
  (clearer section labels, grouped JetEngine mapping, more spacing).
* Fixed a bug where editing blocked keywords could turn off Geocoding or the
  keyword-match filter.
* Optional modules: Fixed event fields can be toggled off; map field names
  are hidden behind an advanced disclosure.
* Remove actions now use outlined buttons and ask for confirmation.

= 2.1.0 =
* Rebuilt Importer Settings as a React admin page using WordPress DataForm and
  DataViews (modern admin design language). Settings are loaded and saved via
  REST; the same option array and sanitize logic are unchanged, so existing
  saved settings are preserved with no migration step.
* Added npm build toolchain (@wordpress/scripts). Prebuilt assets ship in
  build/; run npm install && npm run build after changing src/admin/settings/.
* Removed legacy PHP form template admin CSS/JS for the settings page.

= 2.0.1 =
* Importer Settings: optional modules (Allowlist Filter, Geocoding) appear in a
  card grid with enable toggles that show or hide each module's settings
  section; the group sits below Static meta, with the grid directly above those
  module sections.
* Moved "Run Import Now" onto the same row as the page title (right-aligned),
  with progress shown full-width underneath when an import is running, and
  removed the shared-code / per-site configuration intro paragraph.
* Admin CSS/JS now cache-bust by file modification time so layout tweaks show up
  after deploy without a plugin version bump.

= 2.0.0 =
* Unified codebase merging two previously-forked, site-specific versions.
* Added dynamic, admin-configurable taxonomy/keyword-group system.
* Added configurable JetEngine field mapping (supports both a single
  "Advanced Date" field and separate start/end date+time fields).
* Added optional, toggleable geocoding module with configurable meta keys.
* Added optional, toggleable allowlist keyword filter.
* Fixed a timezone bug in RSS and ICS date parsing that could shift events
  by several hours depending on server timezone.
* Switched event de-duplication to a calendar-day-based hash with automatic
  migration from the older datetime-based hash, so the timezone fix does
  not create duplicate posts.
* Much more resilient feed fetching: retry with backoff, IPv4 fallback,
  sslverify=false last resort, and a 403 retry with feed-flavored headers.
* Added automatic trashing of old events (configurable retention window).
* Added per-feed skip-reason reporting in the AJAX import UI.
* Configurable cron interval (hourly/twice-daily/daily).
