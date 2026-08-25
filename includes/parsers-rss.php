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

        $start_guess    = '';
        $end_guess      = '';
        $location_guess = '';

        foreach ( $item->children() as $child_name => $child_val ) {
            $n = strtolower( (string) $child_name );
            if ( in_array( $n, [ 'start', 'startdate', 'dtstart', 'eventstart' ], true ) ) {
                $start_guess = (string) $child_val;
            }
            if ( in_array( $n, [ 'end', 'enddate', 'dtend', 'eventend' ], true ) ) {
                $end_guess = (string) $child_val;
            }
            if ( in_array( $n, [ 'location', 'venue', 'eventlocation', 'placename' ], true ) ) {
                $location_guess = (string) $child_val;
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
            'location'     => bei_sanitize_location( $location_guess ),
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

/**
 * Whether a schema.org node is (or includes) the given type name.
 */
function bei_schema_is_type( $node, $type ) {
    if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
        return false;
    }

    $type  = (string) $type;
    $types = $node['@type'];
    if ( is_string( $types ) ) {
        $types = [ $types ];
    }
    if ( ! is_array( $types ) ) {
        return false;
    }

    foreach ( $types as $one ) {
        if ( ! is_string( $one ) || $one === '' ) {
            continue;
        }
        if ( strcasecmp( $one, $type ) === 0 ) {
            return true;
        }
        // Accept full URLs like https://schema.org/Event.
        if ( preg_match( '~/(?:' . preg_quote( $type, '~' ) . ')$~i', $one ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Format a schema.org Place (or plain string / list) into a single location line.
 */
function bei_format_schema_place( $place ) {
    if ( is_string( $place ) ) {
        return trim( $place );
    }

    if ( ! is_array( $place ) ) {
        return '';
    }

    // location can be a list of places; use the first usable one.
    if ( isset( $place[0] ) && ( is_array( $place[0] ) || is_string( $place[0] ) ) ) {
        foreach ( $place as $candidate ) {
            $formatted = bei_format_schema_place( $candidate );
            if ( $formatted !== '' ) {
                return $formatted;
            }
        }
        return '';
    }

    $parts = [];

    $name = isset( $place['name'] ) ? trim( (string) $place['name'] ) : '';
    if ( $name !== '' ) {
        $parts[] = $name;
    }

    $address = $place['address'] ?? null;
    if ( is_string( $address ) ) {
        $address = trim( $address );
        if ( $address !== '' ) {
            $parts[] = $address;
        }
    } elseif ( is_array( $address ) ) {
        // address can itself be a list.
        if ( isset( $address[0] ) && is_array( $address[0] ) ) {
            $address = $address[0];
        }
        foreach ( [ 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode' ] as $key ) {
            if ( ! empty( $address[ $key ] ) ) {
                $parts[] = trim( (string) $address[ $key ] );
            }
        }
    }

    $parts = array_values( array_unique( array_filter( $parts, static function ( $p ) {
        return is_string( $p ) && $p !== '';
    } ) ) );

    return implode( ', ', $parts );
}

/**
 * Pull a venue/address string from schema.org Event JSON-LD.
 */
function bei_location_from_jsonld( $json ) {
    if ( is_string( $json ) ) {
        $json = trim( $json );
        if ( $json === '' ) {
            return '';
        }
        $json = json_decode( $json, true );
    }

    if ( ! is_array( $json ) ) {
        return '';
    }

    if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
        foreach ( $json['@graph'] as $node ) {
            $found = bei_location_from_jsonld( $node );
            if ( $found !== '' ) {
                return $found;
            }
        }
    }

    // Top-level list of nodes (common for multiple JSON-LD objects).
    if ( isset( $json[0] ) ) {
        foreach ( $json as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $found = bei_location_from_jsonld( $node );
            if ( $found !== '' ) {
                return $found;
            }
        }
        return '';
    }

    if ( bei_schema_is_type( $json, 'Event' ) && isset( $json['location'] ) ) {
        return bei_format_schema_place( $json['location'] );
    }

    return '';
}

/**
 * Fetch an event detail page and read location from Event JSON-LD.
 * Results are cached per URL for the current request (duplicate items / batches).
 */
function bei_extract_location_from_url( $url ) {
    static $cache = [];

    $url = trim( (string) $url );
    if ( $url === '' || ! str_starts_with( $url, 'http' ) ) {
        return '';
    }

    // Normalize cache key the same way we store external_url.
    $cache_key = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
    if ( $cache_key === '' ) {
        $cache_key = $url;
    }

    if ( array_key_exists( $cache_key, $cache ) ) {
        return $cache[ $cache_key ];
    }

    $response = wp_remote_get( $url, [
        'timeout'     => 15,
        'redirection' => 5,
        'headers'     => [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer'         => 'https://www.google.com/',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        $cache[ $cache_key ] = '';
        return '';
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        $cache[ $cache_key ] = '';
        return '';
    }

    $html = (string) wp_remote_retrieve_body( $response );
    if ( $html === '' ) {
        $cache[ $cache_key ] = '';
        return '';
    }

    $location = '';

    if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $loaded = @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
        if ( $loaded ) {
            $xpath = new DOMXPath( $dom );
            $nodes = $xpath->query( "//script[@type='application/ld+json']" );
            if ( $nodes && $nodes->length ) {
                foreach ( $nodes as $n ) {
                    $found = bei_location_from_jsonld( trim( (string) $n->textContent ) );
                    if ( $found !== '' ) {
                        $location = $found;
                        break;
                    }
                }
            }
        }
        libxml_clear_errors();
    }

    // Regex fallback when DOM extension is unavailable or HTML is messy.
    if ( $location === '' && preg_match_all(
        '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
        $html,
        $matches
    ) ) {
        foreach ( $matches[1] as $raw ) {
            $found = bei_location_from_jsonld( html_entity_decode( trim( (string) $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            if ( $found !== '' ) {
                $location = $found;
                break;
            }
        }
    }

    $cache[ $cache_key ] = $location;
    return $location;
}
