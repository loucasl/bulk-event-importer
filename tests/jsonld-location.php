<?php
/**
 * Location helper checks (no WordPress bootstrap).
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
    echo ( $cond ? 'OK' : 'FAIL' ) . ": {$label}\n";
    if ( ! $cond ) {
        $failed++;
    }
}

$nanaimo = '{"@type":"Event","location":{"@type":"Place","name":"Vancouver Island Regional Library - Nanaimo Harbourfront Branch","address":{"streetAddress":"90 Commercial Street","addressLocality":"Nanaimo","addressRegion":"BC","postalCode":"V9R 5G4"}}}';
$from = bei_location_from_jsonld( $nanaimo );
bei_test_assert( 'JSON-LD Event place', str_contains( $from, 'Harbourfront' ) && str_contains( $from, 'Nanaimo' ) );
bei_test_assert( 'JSON-LD @graph', bei_location_from_jsonld( [
    '@graph' => [
        [ '@type' => 'WebPage' ],
        [ '@type' => 'Event', 'location' => [ 'name' => 'White Sails Brewing', 'address' => 'Nanaimo, BC' ] ],
    ],
] ) === 'White Sails Brewing, Nanaimo, BC' );
bei_test_assert( 'non-Event ignored', bei_location_from_jsonld( [ '@type' => 'Organization', 'location' => 'X' ] ) === '' );

$rss = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Open Mic</title><link>https://ex.test/a</link><pubDate>Mon, 01 Sep 2026 19:00:00 -0700</pubDate><location>White Sails Brewing</location><description>x</description></item></channel></rss>';
$parsed = bei_parse_rss_body( $rss, 'Feed' );
bei_test_assert( 'RSS location field', ( $parsed['events'][0]['location'] ?? '' ) === 'White Sails Brewing' );

bei_test_assert( 'junk ON', bei_is_junk_location( 'ON' ) );
bei_test_assert( 'not junk venue', ! bei_is_junk_location( 'Dufferin Hi-Land Bruce Trail' ) );
bei_test_assert( 'title Mulmur', bei_place_from_event_title( 'Mulmur 175 End-to-End Challenge' ) === 'Mulmur' );
bei_test_assert( 'title Orangeville', bei_place_from_event_title( 'Orangeville Neighbourhood Block Box' ) === 'Orangeville' );
bei_test_assert( 'title Music denied', bei_place_from_event_title( 'Music in the Library: Nanaimo' ) === '' );
bei_test_assert( 'title Fashion denied', bei_place_from_event_title( 'Fashion Show' ) === '' );
bei_test_assert( 'title Christmas denied', bei_place_from_event_title( 'Christmas Eve Celebration' ) === '' );
bei_test_assert( 'title Together denied', bei_place_from_event_title( 'Together for Nature Tour' ) === '' );
bei_test_assert( 'paren Ottawa', bei_parenthetical_place_from_title( 'Together for Nature Tour (Ottawa)' ) === 'Ottawa' );
bei_test_assert( 'paren from title fn', bei_place_from_event_title( 'Together For Nature Tour (Toronto)' ) === 'Toronto' );
bei_test_assert( 'no paren Fashion', bei_parenthetical_place_from_title( 'Fashion Show' ) === '' );
bei_test_assert( 'append Mulmur', bei_append_place_to_location( 'Dufferin Hi-Land Bruce Trail', 'Mulmur' ) === 'Dufferin Hi-Land Bruce Trail, Mulmur' );
bei_test_assert( 'append Orangeville+ON', bei_append_place_to_location( '', 'Orangeville', 'ON' ) === 'Orangeville, ON' );
bei_test_assert( 'no King in Kingston', ! bei_location_contains_place( 'Kingston, ON', 'King' ) );

if ( $failed > 0 ) {
    fwrite( STDERR, "Failed: {$failed}\n" );
    exit( 1 );
}
echo "OK: location checks passed.\n";
