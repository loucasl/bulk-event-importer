<?php
/**
 * Feed flag + aggregate source checks (no WordPress bootstrap).
 *
 *   php tests/feed-aggregate-flags.php
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
function wp_parse_url( $url, $component = -1 ) {
    $parts = parse_url( $url );
    if ( $component === -1 ) {
        return $parts;
    }
    $map = [
        PHP_URL_SCHEME   => 'scheme',
        PHP_URL_HOST     => 'host',
        PHP_URL_PORT     => 'port',
        PHP_URL_USER     => 'user',
        PHP_URL_PASS     => 'pass',
        PHP_URL_PATH     => 'path',
        PHP_URL_QUERY    => 'query',
        PHP_URL_FRAGMENT => 'fragment',
    ];
    $key = $map[ $component ] ?? null;
    return ( $key && isset( $parts[ $key ] ) ) ? $parts[ $key ] : null;
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/parsers-ics.php';

$failed = 0;
function bei_test_assert( $label, $cond ) {
    global $failed;
    echo ( $cond ? 'OK' : 'FAIL' ) . ": {$label}\n";
    if ( ! $cond ) {
        $failed++;
    }
}

$flags = bei_parse_feed_flags( [] );
bei_test_assert( 'empty flags', $flags['type'] === '' && $flags['aggregate'] === false );

$flags = bei_parse_feed_flags( [ 'ics' ] );
bei_test_assert( 'type only', $flags['type'] === 'ics' && $flags['aggregate'] === false );

$flags = bei_parse_feed_flags( [ 'aggregate' ] );
bei_test_assert( 'aggregate only', $flags['type'] === '' && $flags['aggregate'] === true );

$flags = bei_parse_feed_flags( [ 'ics', 'aggregate' ] );
bei_test_assert( 'ics then aggregate', $flags['type'] === 'ics' && $flags['aggregate'] === true );

$flags = bei_parse_feed_flags( [ 'aggregate', 'rss' ] );
bei_test_assert( 'aggregate then rss', $flags['type'] === 'rss' && $flags['aggregate'] === true );

$ics = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SUMMARY:Bootlegging History Tour
DTSTART:20261009T190000
DTEND:20261009T210000
URL:https://www.gatheringuelph.com/events/bootlegging
DESCRIPTION:Tour details https://www.gatheringuelph.com/events/bootlegging
END:VEVENT
END:VCALENDAR
ICS;

$without = bei_parse_ics_body( $ics, 'Gather in Guelph', 'https://calendar.google.com/calendar/ical/x/public/basic.ics', false );
bei_test_assert( 'default keeps feed label', ( $without[0]['source'] ?? '' ) === 'Gather in Guelph' );

$with = bei_parse_ics_body( $ics, 'Gather in Guelph', 'https://calendar.google.com/calendar/ical/x/public/basic.ics', true );
bei_test_assert( 'aggregate uses host slug', ( $with[0]['source'] ?? '' ) === 'Gatheringuelph' );

$platform = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SUMMARY:Some Show
DTSTART:20261009T190000
URL:https://www.eventbrite.ca/e/some-show-123
DESCRIPTION:Tickets https://www.eventbrite.ca/e/some-show-123
END:VEVENT
END:VCALENDAR
ICS;

$agg_platform = bei_parse_ics_body( $platform, "Lisa's TGS Calendar", 'https://calendar.google.com/calendar/ical/x/public/basic.ics', true );
bei_test_assert( 'aggregate maps Eventbrite', ( $agg_platform[0]['source'] ?? '' ) === 'Eventbrite' );

if ( $failed > 0 ) {
    fwrite( STDERR, "\n{$failed} assertion(s) failed\n" );
    exit( 1 );
}

echo "\nAll feed-aggregate checks passed.\n";
