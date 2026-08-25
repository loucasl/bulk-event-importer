<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RSS parsing + lightweight Event JSON-LD location enrichment.
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
        $location    = '';

        foreach ( $item->children() as $child_name => $child_val ) {
            $n = strtolower( (string) $child_name );
            if ( in_array( $n, [ 'start', 'startdate', 'dtstart', 'eventstart' ], true ) ) {
                $start_guess = (string) $child_val;
            }
            if ( in_array( $n, [ 'end', 'enddate', 'dtend', 'eventend' ], true ) ) {
                $end_guess = (string) $child_val;
            }
            if ( in_array( $n, [ 'location', 'venue', 'eventlocation', 'placename' ], true ) ) {
                $location = (string) $child_val;
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
            'location'     => bei_sanitize_location( $location ),
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

    $dt = new DateTime( '@' . $ts );
    $dt->setTimezone( function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' ) );
    return $dt->format( 'Y-m-d H:i:s' );
}

/**
 * Format a schema.org Place (or string/list) into one location line.
 */
function bei_format_schema_place( $place ) {
    if ( is_string( $place ) ) {
        return trim( $place );
    }
    if ( ! is_array( $place ) ) {
        return '';
    }
    if ( isset( $place[0] ) ) {
        foreach ( $place as $candidate ) {
            $formatted = bei_format_schema_place( $candidate );
            if ( $formatted !== '' ) {
                return $formatted;
            }
        }
        return '';
    }

    $parts = [];
    if ( ! empty( $place['name'] ) ) {
        $parts[] = trim( (string) $place['name'] );
    }

    $address = $place['address'] ?? null;
    if ( is_string( $address ) && $address !== '' ) {
        $parts[] = trim( $address );
    } elseif ( is_array( $address ) ) {
        if ( isset( $address[0] ) && is_array( $address[0] ) ) {
            $address = $address[0];
        }
        foreach ( [ 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode' ] as $key ) {
            if ( ! empty( $address[ $key ] ) ) {
                $parts[] = trim( (string) $address[ $key ] );
            }
        }
    }

    return implode( ', ', array_values( array_unique( array_filter( $parts ) ) ) );
}

/**
 * Pull venue/address from schema.org Event JSON-LD.
 */
function bei_location_from_jsonld( $json ) {
    if ( is_string( $json ) ) {
        $json = json_decode( trim( $json ), true );
    }
    if ( ! is_array( $json ) ) {
        return '';
    }

    $nodes = isset( $json['@graph'] ) && is_array( $json['@graph'] )
        ? $json['@graph']
        : ( isset( $json[0] ) ? $json : [ $json ] );

    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) || empty( $node['location'] ) ) {
            continue;
        }
        $types = $node['@type'] ?? '';
        $types = is_array( $types ) ? $types : [ $types ];
        foreach ( $types as $type ) {
            if ( is_string( $type ) && preg_match( '/(^|\/)Event$/i', $type ) ) {
                return bei_format_schema_place( $node['location'] );
            }
        }
    }

    return '';
}

/**
 * Fetch an event page and read location from Event JSON-LD (request-cached).
 */
function bei_extract_location_from_url( $url ) {
    static $cache = [];

    $url = trim( (string) $url );
    if ( $url === '' || ! str_starts_with( $url, 'http' ) ) {
        return '';
    }

    $key = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
    if ( $key === '' ) {
        $key = $url;
    }
    if ( array_key_exists( $key, $cache ) ) {
        return $cache[ $key ];
    }

    $response = wp_remote_get( $url, [
        'timeout'     => 15,
        'redirection' => 5,
        'headers'     => [
            'User-Agent' => 'Mozilla/5.0 (compatible; BulkEventImporter/2.2)',
            'Accept'     => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
        ],
    ] );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $cache[ $key ] = '';
    }

    $html = (string) wp_remote_retrieve_body( $response );
    $location = '';
    if ( $html !== '' && preg_match_all(
        '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
        $html,
        $matches
    ) ) {
        foreach ( $matches[1] as $raw ) {
            $location = bei_location_from_jsonld(
                html_entity_decode( trim( (string) $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
            );
            if ( $location !== '' ) {
                break;
            }
        }
    }

    return $cache[ $key ] = $location;
}
