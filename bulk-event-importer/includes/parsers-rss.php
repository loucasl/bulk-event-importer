<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RSS parsing functions.
 */

function bei_parse_rss_feed( $url, $source_name ) {
    $body = Bulk_Event_Importer::fetch_remote( $url );
    if ( ! $body ) {
        return [];
    }
    $result = bei_parse_rss_body( $body, $source_name );
    return $result['events'];
}

function bei_parse_rss_body( $body, $source_name ) {

    $xml = simplexml_load_string( $body );

    if ( ! $xml ) {
        return [ 'events' => [], 'error' => 'RSS parse failed (invalid XML)' ];
    }

    $events = [];

    foreach ( $xml->channel->item as $item ) {
        $title       = (string) $item->title;
        $link        = (string) $item->link;
        $description = (string) ( $item->children( 'content', true )->encoded ?? $item->description );

        $start_guess = '';
        $end_guess   = '';

        foreach ( $item->children() as $child_name => $child_val ) {
            $n = strtolower( (string) $child_name );
            if ( in_array( $n, [ 'start', 'startdate', 'dtstart', 'eventstart' ], true ) ) {
                $start_guess = (string) $child_val;
            }
            if ( in_array( $n, [ 'end', 'enddate', 'dtend', 'eventend' ], true ) ) {
                $end_guess = (string) $child_val;
            }
        }

        if ( ! $start_guess && isset( $item->pubDate ) ) {
            $start_guess = (string) $item->pubDate;
        }

        $events[] = [
            'title'        => wp_strip_all_tags( $title ),
            'description'  => wp_kses_post( $description ),
            'start'        => bei_rss_time_to_mysql( $start_guess ),
            'raw_start'    => $start_guess,
            'end'          => bei_rss_time_to_mysql( $end_guess ),
            'raw_end'      => $end_guess,
            'location'     => '',
            'source'       => $source_name,
            'external_url' => esc_url_raw( $link ),
        ];
    }

    return [ 'events' => $events, 'error' => '' ];
}

function bei_rss_time_to_mysql( $time_string ) {
    $time_string = trim( (string) $time_string );
    if ( $time_string === '' ) {
        return '';
    }

    $ts = strtotime( $time_string );
    if ( ! $ts ) {
        return '';
    }

    // strtotime() yields a real instant; format as the WordPress-local wall
    // clock (not PHP's UTC date(), which would otherwise store UTC digits
    // as if they were already local time).
    $dt = new DateTime( '@' . $ts );
    $dt->setTimezone( function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' ) );
    return $dt->format( 'Y-m-d H:i:s' );
}
