<?php
/**
 * Manual REST settings round-trip check (requires WordPress + manage_options).
 *
 * wp eval-file tests/rest-settings-roundtrip.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Run via WP-CLI inside WordPress.\n" );
    exit( 1 );
}

$fixture = [
    'feed_urls'           => "Test | https://example.com/feed.ics\n",
    'default_feed_type'   => 'ics',
    'past_days'           => 7,
    'future_months'       => 18,
    'trash_after_days'    => 7,
    'default_post_status' => 'publish',
    'cron_interval'       => 'daily',
    'blocked_keywords'    => 'cancelled,private',
    'allowlist_enabled'   => '1',
    'allowed_keywords'    => 'festival',
    'geocoding_enabled'   => '1',
    'geocoding_address_metas'  => 'event-location',
    'geocoding_lat_meta'       => 'lat_meta',
    'geocoding_lng_meta'       => 'lng_meta',
    'taxonomies'          => [
        [
            'slug'         => 'event-categories',
            'label'        => 'Event Categories',
            'default_term' => '',
            'groups'       => [
                [ 'key' => 'event_community_keywords', 'term' => 'Community Events' ],
            ],
        ],
    ],
    'event_community_keywords' => 'community,town hall',
    'field_map'             => [
        'date_mode'         => 'split',
        'start_date_meta'   => 'event-start-date',
        'start_time_meta'   => 'event-start-time',
        'end_date_meta'     => 'event-end-date',
        'end_time_meta'     => 'event-end-time',
        'location_meta'     => 'event-location',
        'external_url_meta' => 'event-button-link',
        'source_meta'       => 'event-source',
        'extra_static_meta' => [],
    ],
];

$rest  = bei_settings_to_rest( $fixture );
$input = bei_settings_from_rest( $rest );
$saved = Bulk_Event_Importer::sanitize_settings( $input );

if ( ( $saved['feed_urls'] ?? '' ) !== $fixture['feed_urls'] ) {
    fwrite( STDERR, "FAIL: feed_urls mismatch\n" );
    exit( 1 );
}

if ( ( $saved['taxonomies'][0]['slug'] ?? '' ) !== 'event-categories' ) {
    fwrite( STDERR, "FAIL: taxonomy slug mismatch\n" );
    exit( 1 );
}

if ( ( $saved['event_community_keywords'] ?? '' ) !== 'community,town hall' ) {
    fwrite( STDERR, "FAIL: group keywords mismatch: " . ( $saved['event_community_keywords'] ?? '' ) . "\n" );
    exit( 1 );
}

if ( empty( $saved['allowlist_enabled'] ) || empty( $saved['geocoding_enabled'] ) ) {
    fwrite( STDERR, "FAIL: module flags not preserved\n" );
    exit( 1 );
}

echo "OK: REST settings round-trip passed.\n";
