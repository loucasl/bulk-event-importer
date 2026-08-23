# Migrating Dufferin Arts Council and Generall Store to the unified plugin

Both sites' existing keyword CSVs (feed URLs, blocked keywords, and every
`cat_*_keywords` / `event_*_keywords` field) already live in each site's
**currently-installed** plugin, under the option name it has always used:
`municipal_event_importer_settings`. This plugin is a rename (folder, class,
function prefix, and its own settings option) to `bulk_event_importer_settings`,
so nothing carries over automatically just by activating it. Each snippet
below reads the old option, adds the new *structure* settings that used to
be hardcoded in code (which taxonomy each keyword group belongs to, which
JetEngine meta keys to write to), and saves the result under the new option
name this plugin actually reads.

## How to apply a snippet

Easiest: install the "Code Snippets" plugin, paste the block for your site
into a new PHP snippet, run it once ("Run Once" mode if available, or just
deactivate/delete the snippet after saving), then confirm the values appear
correctly on Settings > Importer Settings before removing the snippet.

Alternative: `wp eval-file migration.php` via WP-CLI with the snippet body
saved to a file (drop the `<?php` opening tag requirement as WP-CLI expects).

Either way, deactivate the OLD (site-forked) plugin first, install this
unified plugin, run the snippet, then check the settings page. Deactivating
(or even deleting) the old plugin does not delete its stored settings, since
neither old plugin ships an uninstall routine, so `municipal_event_importer_settings`
stays in the database and readable until you're ready to remove it yourself.

---

## Dufferin Arts Council

```php
<?php
$options = get_option( 'municipal_event_importer_settings', [] ); // read the OLD live plugin's data

$options['default_feed_type'] = 'rss'; // matches the old heuristic's fallback

$options['taxonomies'] = [
    [
        'slug'         => 'event-audience',
        'label'        => 'Event Audience',
        'default_term' => 'All',
        'groups' => [
            [ 'key' => 'aud_all_keywords',        'term' => 'All' ],
            [ 'key' => 'aud_kids_youth_keywords',  'term' => 'Kids and Youth' ],
        ],
    ],
    [
        'slug'         => 'event-category',
        'label'        => 'Event Category',
        'default_term' => '',
        'groups' => [
            [ 'key' => 'cat_crafts_keywords',      'term' => 'Crafts' ],
            [ 'key' => 'cat_dance_keywords',        'term' => 'Dance' ],
            [ 'key' => 'cat_festivals_keywords',    'term' => 'Festivals' ],
            [ 'key' => 'cat_film_keywords',         'term' => 'Film' ],
            [ 'key' => 'cat_literary_keywords',     'term' => 'Literary Arts' ],
            [ 'key' => 'cat_music_keywords',        'term' => 'Music' ],
            [ 'key' => 'cat_theatre_keywords',      'term' => 'Theatre' ],
            [ 'key' => 'cat_visual_arts_keywords',  'term' => 'Visual Arts' ],
        ],
    ],
];

$options['field_map'] = [
    'date_mode'              => 'je_advanced_date',
    'je_date_meta'           => 'event-date',
    'start_time_meta'        => 'event-start-time',
    'end_time_meta'          => 'event-end-time',
    'location_meta'          => 'event-location',
    'description_long_meta'  => 'event-long-description',
    'description_short_meta' => 'event-short-description',
    'external_url_meta'      => 'event-link',
    'source_meta'            => 'event-source',
    'extra_static_meta' => [
        [ 'key' => 'button-text', 'value' => 'View Details' ],
        [ 'key' => 'ticketed',    'value' => 'false' ],
    ],
];

// DAC had the allowlist filter available in code. If `allowed_keywords`
// already has a value in your existing options, flip this on; otherwise
// leave it off (the plugin behaves the same either way when the list is empty).
$options['allowlist_enabled'] = ! empty( $options['allowed_keywords'] ) ? '1' : '';

$options['cron_interval'] = 'hourly';

update_option( 'bulk_event_importer_settings', $options );
echo 'Dufferin Arts Council settings migrated.';
```

---

## Generall Store

```php
<?php
$options = get_option( 'municipal_event_importer_settings', [] ); // read the OLD live plugin's data

$options['default_feed_type'] = 'ics'; // matches the old default

$options['taxonomies'] = [
    [
        'slug'         => 'event-categories',
        'label'        => 'Event Categories',
        'default_term' => '',
        'groups' => [
            [ 'key' => 'event_community_keywords', 'term' => 'Community Events' ],
            [ 'key' => 'event_market_keywords',     'term' => 'Markets & Pop-Ups' ],
            [ 'key' => 'event_workshop_keywords',   'term' => 'Workshops & Programs' ],
        ],
    ],
];

$options['field_map'] = [
    'date_mode'         => 'split',
    'start_date_meta'   => 'event-start-date',
    'start_time_meta'   => 'event-start-time',
    'end_date_meta'     => 'event-end-date',
    'end_time_meta'     => 'event-end-time',
    'location_meta'     => 'event-location',
    'external_url_meta' => 'event-button-link',
    'source_meta'       => 'event-source',
    'extra_static_meta' => [],
];

$options['allowlist_enabled'] = ''; // GS never used this feature

$options['geocoding_enabled']          = '1';
$options['geocoding_address_metas']    = 'event-location';
// Replace these with the ACTUAL JetEngine map-field meta keys on this site
// (Custom Fields > your map field > "Meta Key" in JetEngine's field editor).
// They were previously hardcoded to the field-group hash below; keeping the
// same values here preserves existing geocoded lat/lng data.
$options['geocoding_lat_meta']         = '72fefea9e06fb11eac7b77f95ce384e1_lat';
$options['geocoding_lng_meta']         = '72fefea9e06fb11eac7b77f95ce384e1_lng';
$options['geocoding_hash_meta']        = '72fefea9e06fb11eac7b77f95ce384e1_hash';
$options['geocoding_country_suffix']   = 'Canada';

$options['cron_interval'] = 'daily';

update_option( 'bulk_event_importer_settings', $options );
echo 'Generall Store settings migrated.';
```

**Also add this small site-specific mu-plugin** (or a Code Snippets entry,
set to always run) to restore two behaviors that were previously hardcoded
directly into Generall Store's copy of the plugin, and are now handled via
the shared plugin's extensibility hooks instead of being baked into the
shared codebase:

```php
<?php
/**
 * Generall Store-specific event importer integrations.
 * Not part of the shared plugin on purpose — keep this in a small
 * site-specific mu-plugin or snippet instead.
 */

// Blank out a specific mislabeled calendar's source name (was previously
// hardcoded as a regex inside post-upsert.php).
add_filter( 'bei_event_source_name', function( $source, $event ) {
    if ( preg_match( "/^Lisa['’‘`´]s TGS Calendar$/i", trim( $source ) ) ) {
        return '';
    }
    return $source;
}, 10, 2 );

// Auto-link imported events to a related "Community" post type, if that
// integration function still exists on this site.
add_action( 'bei_after_upsert_event_post', function( $post_id, $event, $status ) {
    if ( function_exists( 'rdc_autolink_community_for_post' ) ) {
        rdc_autolink_community_for_post( (int) $post_id );
    }
}, 10, 3 );
```

---

## After migrating either site

1. Open Settings > Importer Settings and visually confirm the feed list,
   keyword chips, taxonomy blocks, and field mapping look right.
2. Click "Run Import Now" and check a handful of updated posts to confirm
   dates, location, description, and categories are landing correctly.
3. Only once you're satisfied, remove the migration snippet (it's a
   one-time seed, not something that needs to keep running).

## Setting up GitHub-based updates

Once both sites are confirmed working, point each site at this repository
using either:

* **WP Pusher** (simplest: connect each site to this GitHub repo; push to
  deploy), or
* **[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)**
  wired into `bulk-event-importer.php`, so WordPress shows a native
  "Update Available" notice sourced from GitHub releases/tags.

Bump `Version:` in the plugin header (and add a changelog entry to
`readme.txt`) on every release; that's what triggers the update prompt.

### WP Pusher checklist (this repository)

Verified facts for `loucasl/bulk-event-importer`:

| Field | Expected value |
| --- | --- |
| Repository | `loucasl/bulk-event-importer` (public — leave “private repo” **unchecked**) |
| Branch | `develop` for staging; `main` (or a release tag) for production once ready |
| Subdirectory | **empty** — plugin files live at the repo root (`bulk-event-importer.php`) |
| Package / folder | Must resolve to `bulk-event-importer` (not blank) |

On WP Pusher → Edit plugin, confirm those values, then:

1. Reconnect / re-authorize GitHub if the connection looks stale.
2. Enable logging under WP Pusher → Log while testing.
3. Click **Update plugin**. Success here must work before Push-to-Deploy can work.
4. If Update fails with **“An error occurred: Invalid data provided.”**, that string is WordPress core `WP_Upgrader` `bad_request` — usually an empty package/source. Typical causes: empty package slug in WP Pusher, wrong/empty subdirectory after the repo flatten, private-checkbox mismatch on a public repo, or a broken GitHub token. Fix the Edit-plugin fields (or remove + re-install the package from GitHub) before chasing webhooks.
5. Copy the Push-to-Deploy URL and confirm it includes a non-empty `&package=…` (e.g. `&package=bulk-event-importer`). An empty `&package=` means the package was never registered correctly.

### Push-to-Deploy and SiteGround Anti-Bot AI

Staging (`staging2.thegenerall.store`) sits behind SiteGround’s **Anti-Bot AI / SG-Captcha**. Automated clients (GitHub webhooks, `curl`, scanners) often never reach WordPress. Signature of the block:

* Response header: `sg-captcha: challenge`
* Status: often `202` (sometimes surfaces as a browser “400 / This page isn’t working”)
* Body: HTML meta-refresh to `/.well-known/sgcaptcha/…`
* Generic `server: nginx` only — no WordPress cookies or HTML

Security Optimizer XSS toggles and the Site Tools IP block list do **not** control this layer. There is no self-serve allowlist by URL pattern. Open a SiteGround ticket and ask them to either:

* whitelist GitHub’s webhook CIDRs for the staging hostname in Anti-Bot AI / SG-Captcha, **or**
* disable `protect_captcha_auto` for that hostname while testing deploy.

Current GitHub Hooks ranges (confirm via `https://api.github.com/meta` → `hooks` before filing):

```
192.30.252.0/22
185.199.108.0/22
140.82.112.0/20
143.55.64.0/20
2a0a:a440::/29
2606:50c0::/32
```

After SiteGround exempts the hooks, GitHub → Settings → Webhooks → Recent Deliveries should show `2xx` with a WordPress/WP Pusher body, not an empty nginx-only 400/202. Then a merge to `develop` will auto-update staging.
