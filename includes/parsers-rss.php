<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RSS parsing + lightweight Event JSON-LD enrichment (location + dates).
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
 * True when @type is schema.org Event or a subtype (MusicEvent, SportsEvent, …).
 */
function bei_is_schema_event_type( $types ) {
    $types = is_array( $types ) ? $types : [ $types ];
    foreach ( $types as $type ) {
        // Event, MusicEvent, https://schema.org/SportsEvent, etc.
        if ( is_string( $type ) && preg_match( '/(^|\/)[A-Za-z0-9]*Event$/i', $type ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Normalize a schema.org date value to a plain string.
 */
function bei_schema_date_string( $value ) {
    if ( is_array( $value ) ) {
        $value = reset( $value );
    }
    return is_string( $value ) || is_numeric( $value ) ? trim( (string) $value ) : '';
}

/**
 * Pull location + start/end from schema.org Event JSON-LD.
 *
 * @return array{location:string,start:string,end:string,raw_start:string,raw_end:string}
 */
function bei_event_fields_from_jsonld( $json ) {
    $empty = [
        'location'  => '',
        'start'     => '',
        'end'       => '',
        'raw_start' => '',
        'raw_end'   => '',
    ];

    if ( is_string( $json ) ) {
        $json = json_decode( trim( $json ), true );
    }
    if ( ! is_array( $json ) ) {
        return $empty;
    }

    $nodes = isset( $json['@graph'] ) && is_array( $json['@graph'] )
        ? $json['@graph']
        : ( isset( $json[0] ) ? $json : [ $json ] );

    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) || ! bei_is_schema_event_type( $node['@type'] ?? '' ) ) {
            continue;
        }

        $location  = ! empty( $node['location'] ) ? bei_format_schema_place( $node['location'] ) : '';
        $raw_start = bei_schema_date_string( $node['startDate'] ?? '' );
        $raw_end   = bei_schema_date_string( $node['endDate'] ?? '' );

        if ( $location === '' && $raw_start === '' && $raw_end === '' ) {
            continue;
        }

        return [
            'location'  => $location,
            'start'     => bei_rss_time_to_mysql( $raw_start ),
            'end'       => bei_rss_time_to_mysql( $raw_end ),
            'raw_start' => $raw_start,
            'raw_end'   => $raw_end,
        ];
    }

    return $empty;
}

/**
 * Pull venue/address from schema.org Event JSON-LD.
 */
function bei_location_from_jsonld( $json ) {
    return bei_event_fields_from_jsonld( $json )['location'];
}

/**
 * Fetch an event page and read Event JSON-LD fields (request-cached).
 *
 * @return array{location:string,start:string,end:string,raw_start:string,raw_end:string}
 */
function bei_extract_event_fields_from_url( $url ) {
    static $cache = [];

    $empty = [
        'location'  => '',
        'start'     => '',
        'end'       => '',
        'raw_start' => '',
        'raw_end'   => '',
    ];

    $url = trim( (string) $url );
    if ( $url === '' || ! str_starts_with( $url, 'http' ) ) {
        return $empty;
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
            'User-Agent' => 'Mozilla/5.0 (compatible; BulkEventImporter/2.3)',
            'Accept'     => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
        ],
    ] );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $cache[ $key ] = $empty;
    }

    $html   = (string) wp_remote_retrieve_body( $response );
    $fields = $empty;
    if ( $html !== '' && preg_match_all(
        '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
        $html,
        $matches
    ) ) {
        foreach ( $matches[1] as $raw ) {
            $candidate = bei_event_fields_from_jsonld(
                html_entity_decode( trim( (string) $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
            );
            if ( $candidate['location'] !== '' || $candidate['start'] !== '' ) {
                $fields = $candidate;
                break;
            }
        }
    }

    return $cache[ $key ] = $fields;
}

/**
 * Fetch an event page and read location from Event JSON-LD (request-cached).
 */
function bei_extract_location_from_url( $url ) {
    return bei_extract_event_fields_from_url( $url )['location'];
}
