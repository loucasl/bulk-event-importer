<?php
/**
 * Unit checks for RSS / JSON-LD location helpers (no WordPress bootstrap).
 *
 *   php tests/jsonld-location.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $string ) {
    return trim( strip_tags( (string) $string ) );
}

function sanitize_text_field( $str ) {
    return trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $str ) );
}

function wp_kses_post( $string ) {
    return (string) $string;
}

function esc_url_raw( $url ) {
    return (string) $url;
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/parsers-rss.php';

$failed = 0;

function bei_test_assert( $label, $cond ) {
    global $failed;
    if ( $cond ) {
        echo "OK: {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
}

$nanaimo_json = <<<'JSON'
{"@context":"http://schema.org","@type":"Event","name":"Music in the Library: Nanaimo Recorder Consort","startDate":"2026-08-01","url":"https://tourismnanaimo.com/event/music-in-the-library%3a-nanaimo-recorder-consort/774/","location":{"@type":"Place","name":"Vancouver Island Regional Library - Nanaimo Harbourfront Branch","address":{"@type":"PostalAddress","addressLocality":"Nanaimo","addressRegion":"BC","postalCode":"V9R 5G4","streetAddress":"90 Commercial Street"},"geo":{"@type":"GeoCoordinates","latitude":49.16557,"longitude":-123.936446}}}
JSON;

$from_nanaimo = bei_location_from_jsonld( $nanaimo_json );
bei_test_assert(
    'Tourism Nanaimo Event JSON-LD yields harbourfront library venue',
    str_contains( $from_nanaimo, 'Nanaimo Harbourfront Branch' )
    && str_contains( $from_nanaimo, '90 Commercial Street' )
    && str_contains( $from_nanaimo, 'Nanaimo' )
);

$graph = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [ '@type' => 'WebPage', 'name' => 'Ignore me' ],
        [
            '@type'    => 'Event',
            'name'     => 'Sample',
            'location' => [
                '@type'   => 'Place',
                'name'    => 'White Sails Brewing',
                'address' => 'Nanaimo, BC',
            ],
        ],
    ],
];
bei_test_assert(
    '@graph Event location is found',
    bei_location_from_jsonld( $graph ) === 'White Sails Brewing, Nanaimo, BC'
);

bei_test_assert(
    'string location is accepted',
    bei_location_from_jsonld( [
        '@type'    => 'Event',
        'location' => 'Departure Bay Beach',
    ] ) === 'Departure Bay Beach'
);

bei_test_assert(
    'non-Event JSON-LD returns empty',
    bei_location_from_jsonld( [
        '@type'    => 'Organization',
        'location' => 'Should not use this',
    ] ) === ''
);

$rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Events</title>
    <item>
      <title>Open Mic</title>
      <link>https://example.com/events/open-mic/</link>
      <pubDate>Mon, 01 Sep 2026 19:00:00 -0700</pubDate>
      <location>White Sails Brewing</location>
      <description>Live music</description>
    </item>
    <item>
      <title>No Venue Item</title>
      <link>https://example.com/events/no-venue/</link>
      <pubDate>Tue, 02 Sep 2026 19:00:00 -0700</pubDate>
      <description>Somewhere</description>
    </item>
  </channel>
</rss>
XML;

$parsed = bei_parse_rss_body( $rss, 'Example Feed' );
bei_test_assert( 'RSS parse has two items', count( $parsed['events'] ) === 2 );
bei_test_assert(
    'RSS location child is mapped',
    ( $parsed['events'][0]['location'] ?? '' ) === 'White Sails Brewing'
);
bei_test_assert(
    'RSS item without location stays empty before enrichment',
    ( $parsed['events'][1]['location'] ?? 'missing' ) === ''
);

// Live page check (optional; skip quietly if unreachable).
$live_html = '';
if ( function_exists( 'curl_init' ) ) {
    $ch = curl_init( 'https://tourismnanaimo.com/event/music-in-the-library%3a-nanaimo-recorder-consort/774/' );
    if ( $ch ) {
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'BulkEventImporterTest/1.0',
        ] );
        $body = curl_exec( $ch );
        $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        if ( $code >= 200 && $code < 300 && is_string( $body ) ) {
            $live_html = $body;
        }
    }
} else {
    $ctx = stream_context_create( [
        'http' => [
            'timeout' => 20,
            'header'  => "User-Agent: BulkEventImporterTest/1.0\r\n",
        ],
    ] );
    $body = @file_get_contents(
        'https://tourismnanaimo.com/event/music-in-the-library%3a-nanaimo-recorder-consort/774/',
        false,
        $ctx
    );
    if ( is_string( $body ) ) {
        $live_html = $body;
    }
}

if ( $live_html !== '' && preg_match_all(
    '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
    $live_html,
    $m
) ) {
    $live = '';
    foreach ( $m[1] as $raw ) {
        $live = bei_location_from_jsonld( html_entity_decode( trim( (string) $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( $live !== '' ) {
            break;
        }
    }
    bei_test_assert(
        'live Tourism Nanaimo page JSON-LD parses to harbourfront branch',
        str_contains( $live, 'Harbourfront' )
    );
} else {
    echo "SKIP: live Tourism Nanaimo page fetch\n";
}

if ( $failed > 0 ) {
    fwrite( STDERR, "Failed assertions: {$failed}\n" );
    exit( 1 );
}

echo "OK: JSON-LD / RSS location checks passed.\n";
