<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple helper functions used across the importer.
 */

function bei_sanitize_location( $location ) : string {

    $location = (string) $location;

    if ( $location === '' ) {
        return '';
    }

    // Decode entities first so &amp; etc become readable.
    $location = html_entity_decode( $location, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

    // Preserve separation where HTML would otherwise collapse text.
    $location = preg_replace( '~</\s*(li|p|div|tr|td|h[1-6])\s*>~i', ' ', $location );
    $location = preg_replace( '~<\s*br\s*/?\s*>~i', ' ', $location );

    // Strip any remaining tags.
    $location = wp_strip_all_tags( $location );

    // Normalize whitespace.
    $location = preg_replace( '/[\r\n\t]+/', ' ', $location );
    $location = preg_replace( '/\s{2,}/', ' ', $location );

    // Trim stray punctuation and sanitize.
    $location = trim( $location, " \t\n\r\0\x0B,;|" );

    return sanitize_text_field( $location );
}

function bei_province_codes() : array {
    return [ 'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT' ];
}

/** Province-only / postal-only / tiny values are too thin to keep. */
function bei_is_junk_location( $location ) : bool {
    $location = trim( (string) $location );
    if ( $location === '' ) {
        return false;
    }
    $upper = strtoupper( $location );
    if ( in_array( $upper, bei_province_codes(), true ) ) {
        return true;
    }
    if ( in_array( $upper, [ 'ONTARIO', 'BRITISH COLUMBIA', 'QUEBEC', 'ALBERTA', 'MANITOBA', 'SASKATCHEWAN', 'CANADA' ], true ) ) {
        return true;
    }
    if ( strlen( $location ) <= 2 ) {
        return true;
    }
    return (bool) preg_match( '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z]\s?\d[ABCEGHJ-NPRSTV-Z]\d$/i', $location );
}

function bei_province_code_from_location( $location ) : string {
    $upper = strtoupper( trim( (string) $location ) );
    return in_array( $upper, bei_province_codes(), true ) ? $upper : '';
}

/** True when a short title fragment looks like a place name (not a year/phrase). */
function bei_looks_like_place_name( $place ) : bool {
    $place = trim( (string) $place );
    if ( $place === '' || strlen( $place ) > 40 || preg_match( '/^\d{4}$/', $place ) ) {
        return false;
    }
    if ( ! preg_match( '/^[\p{L}][\p{L}\p{N}\'’.\-]*(?: [\p{L}][\p{L}\p{N}\'’.\-]*){0,3}$/u', $place ) ) {
        return false;
    }
    $deny = 'the|and|for|free|live|open|virtual|online|annual|music|concert|festival|workshop|market|fair|show|series|join|beyond|special|summer|winter|spring|fall|community|canada|national|together|fashion|christmas|dress|celebration|rehearsal|hangout|tour|nature|eve|party|gala';
    $first = preg_split( '/\s+/', $place )[0] ?? '';
    return $first !== '' && ! preg_match( '/^(?:' . $deny . ')$/i', $first );
}

/**
 * Place hint from an event title.
 * Prefer trailing "(City)"; else a leading capitalized token (append-only use).
 */
function bei_place_from_event_title( $title ) : string {
    $title = html_entity_decode( trim( wp_strip_all_tags( (string) $title ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    if ( $title === '' ) {
        return '';
    }
    if ( preg_match( '/\(([^)]+)\)\s*$/u', $title, $m ) ) {
        $inner = bei_sanitize_location( $m[1] );
        if ( bei_looks_like_place_name( $inner ) ) {
            return $inner;
        }
    }
    if ( ! preg_match( '/^([A-Z][\p{L}\'’-]{2,})\b/u', $title, $m ) ) {
        return '';
    }
    return bei_looks_like_place_name( $m[1] ) ? $m[1] : '';
}

/** Trailing "(City)" only — safe to use as a standalone location. */
function bei_parenthetical_place_from_title( $title ) : string {
    $title = html_entity_decode( trim( wp_strip_all_tags( (string) $title ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    if ( ! preg_match( '/\(([^)]+)\)\s*$/u', $title, $m ) ) {
        return '';
    }
    $inner = bei_sanitize_location( $m[1] );
    return bei_looks_like_place_name( $inner ) ? $inner : '';
}

function bei_location_contains_place( $location, $place ) : bool {
    $norm = static function ( $value ) {
        $value = strtolower( wp_strip_all_tags( (string) $value ) );
        $value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
        return trim( preg_replace( '/\s+/', ' ', (string) $value ) );
    };
    $place = $norm( $place );
    return $place !== '' && strpos( ' ' . $norm( $location ) . ' ', ' ' . $place . ' ' ) !== false;
}

function bei_append_place_to_location( $location, $place, $province = '' ) : string {
    $out      = bei_sanitize_location( $location );
    $place    = bei_sanitize_location( $place );
    $province = strtoupper( trim( (string) $province ) );

    if ( $place !== '' && ! bei_location_contains_place( $out, $place ) ) {
        $out = ( $out === '' ) ? $place : ( $out . ', ' . $place );
    }
    if (
        $province !== ''
        && in_array( $province, bei_province_codes(), true )
        && ! bei_location_contains_place( $out, $province )
    ) {
        $out = ( $out === '' ) ? $province : ( $out . ', ' . $province );
    }

    return bei_sanitize_location( $out );
}

function bei_matches_keywords( $text, $keywords_raw ) {

    if ( empty( $keywords_raw ) ) {
        return false;
    }

    $keywords = array_filter(
        array_map(
            'trim',
            explode( ',', strtolower( $keywords_raw ) )
        )
    );

    if ( empty( $keywords ) ) {
        return false;
    }

    $escaped = array_map( 'preg_quote', $keywords );
    $pattern = '/\b(' . implode( '|', $escaped ) . ')(s|es)?\b/i';

    return (bool) preg_match( $pattern, $text );
}

/**
 * Normalize a comma-separated keyword list: trim, lowercase, dedupe.
 */
function bei_normalize_keyword_csv( $raw ) {
    $keywords = array_filter(
        array_map( 'trim', explode( ',', (string) $raw ) )
    );
    $keywords = array_map( 'strtolower', $keywords );
    $keywords = array_unique( $keywords );
    return implode( ',', $keywords );
}

function bei_get_source_name_from_url( $url ) {
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) {
        return '';
    }

    $host = strtolower( $host );
    $host = preg_replace( '/^www\./', '', $host );

    $mappings = [
        'facebook.com'     => 'Facebook',
        'eventbrite.com'   => 'Eventbrite',
        'eventbrite.ca'    => 'Eventbrite',
        'instagram.com'    => 'Instagram',
        'youtube.com'      => 'YouTube',
        'meetup.com'       => 'Meetup',
        'ticketmaster.com' => 'Ticketmaster',
    ];

    if ( isset( $mappings[ $host ] ) ) {
        return $mappings[ $host ];
    }

    if ( preg_match( '/(?:^|\.)([^.]+)\.(?:com|org|net|edu|gov|mil|ca|co|uk|us|info|biz|tv|me|io)(?:\.|$)/i', $host, $matches ) ) {
        $name = $matches[1];
    } else {
        $parts = explode( '.', $host );
        $name  = $parts[0];
    }

    $name = str_replace( [ '-', '_' ], ' ', $name );
    return ucwords( $name );
}

/**
 * Unix timestamp for local midnight of the given DateTime's calendar day.
 */
function bei_local_midnight_timestamp( DateTime $dt ) : int {
    $midnight = clone $dt;
    $midnight->setTime( 0, 0, 0 );
    return (int) $midnight->getTimestamp();
}

/**
 * Find an existing event post by title + source + calendar day.
 * Used to re-link posts created under an older, datetime-based hash so a
 * timezone correction updates the existing post instead of duplicating it.
 *
 * @return WP_Post[] Zero or one post in an array (same shape as get_posts).
 */
function bei_find_existing_event_by_day( $title, $source, $start_day ) {
    global $wpdb;

    $title     = (string) $title;
    $source    = (string) $source;
    $start_day = (string) $start_day;

    if ( $title === '' || $start_day === '' ) {
        return [];
    }

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
               AND post_title = %s
               AND post_status IN ('publish','draft','pending','private')
             ORDER BY ID ASC
             LIMIT 50",
            Bulk_Event_Importer::POST_TYPE,
            $title
        )
    );

    if ( empty( $ids ) ) {
        return [];
    }

    $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
    $field_map = bei_get_field_map();
    $source_meta = $field_map['source_meta'] ?? '';

    foreach ( $ids as $id ) {
        $id = (int) $id;
        if ( $source !== '' && $source_meta !== '' ) {
            $post_source = (string) get_post_meta( $id, $source_meta, true );
            if ( $post_source !== $source ) {
                continue;
            }
        }

        $ts = (int) bei_get_stored_start_timestamp( $id );
        if ( ! $ts ) {
            continue;
        }

        try {
            $dt = new DateTime( '@' . $ts );
            $dt->setTimezone( $tz );
            if ( $dt->format( 'Y-m-d' ) === $start_day ) {
                $post = get_post( $id );
                return $post ? [ $post ] : [];
            }
        } catch ( Exception $e ) {
            continue;
        }
    }

    return [];
}
