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

/**
 * Canadian province / territory codes used for junk-location checks.
 */
function bei_province_codes() : array {
    return [ 'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT' ];
}

/**
 * Whether a location value is too thin to keep (province-only, postal-only, etc.).
 * Empty string is not junk — callers treat empty separately.
 */
function bei_is_junk_location( $location ) : bool {
    $location = trim( (string) $location );
    if ( $location === '' ) {
        return false;
    }

    $upper = strtoupper( $location );
    if ( in_array( $upper, bei_province_codes(), true ) ) {
        return true;
    }

    // Full province names with no city.
    $names = [
        'ALBERTA', 'BRITISH COLUMBIA', 'MANITOBA', 'NEW BRUNSWICK',
        'NEWFOUNDLAND', 'NEWFOUNDLAND AND LABRADOR', 'NOVA SCOTIA',
        'NORTHWEST TERRITORIES', 'NUNAVUT', 'ONTARIO',
        'PRINCE EDWARD ISLAND', 'QUEBEC', 'SASKATCHEWAN', 'YUKON',
        'CANADA',
    ];
    if ( in_array( $upper, $names, true ) ) {
        return true;
    }

    // Canadian postal code alone (with or without space).
    if ( preg_match( '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z]\s?\d[ABCEGHJ-NPRSTV-Z]\d$/i', $location ) ) {
        return true;
    }

    // Extremely short tokens (e.g. "A", "NB" already caught; "UK").
    if ( function_exists( 'mb_strlen' ) ) {
        return mb_strlen( $location ) <= 2;
    }
    return strlen( $location ) <= 2;
}

/**
 * If $location is only a province code, return that code; otherwise ''.
 */
function bei_province_code_from_location( $location ) : string {
    $upper = strtoupper( trim( (string) $location ) );
    return in_array( $upper, bei_province_codes(), true ) ? $upper : '';
}

/**
 * Leading place-like token from an event title (e.g. Mulmur, Orangeville).
 * Conservative: first capitalized word, skipping common non-place starters.
 */
function bei_place_from_event_title( $title ) : string {
    $title = trim( wp_strip_all_tags( (string) $title ) );
    if ( $title === '' ) {
        return '';
    }

    // Decode entities so “Mulmur” / curly quotes don't break the match.
    $title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

    if ( ! preg_match( '/^([A-Z][\p{L}\'’-]{2,})\b/u', $title, $m ) ) {
        return '';
    }

    $place = $m[1];
    $deny  = [
        'the', 'and', 'for', 'free', 'live', 'open', 'virtual', 'online', 'annual',
        'music', 'concert', 'festival', 'workshop', 'market', 'fair', 'show', 'series',
        'join', 'celebrate', 'kids', 'family', 'beyond', 'introducing', 'presents',
        'special', 'summer', 'winter', 'spring', 'autumn', 'fall', 'holiday',
        'christmas', 'halloween', 'easter', 'canada', 'canadian', 'national',
        'international', 'community', 'neighbourhood', 'neighborhood', 'block',
        'challenge', 'end', 'trail', 'park', 'library', 'gallery', 'museum',
        'theatre', 'theater', 'tonight', 'today', 'this', 'from', 'with',
    ];

    if ( in_array( strtolower( $place ), $deny, true ) ) {
        return '';
    }

    return $place;
}

/**
 * Whether $location already contains $place as a whole word (case-insensitive).
 */
function bei_location_contains_place( $location, $place ) : bool {
    $location = trim( (string) $location );
    $place    = trim( (string) $place );
    if ( $location === '' || $place === '' ) {
        return false;
    }

    $norm = static function ( $value ) {
        $value = strtolower( wp_strip_all_tags( (string) $value ) );
        $value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
        return trim( preg_replace( '/\s+/', ' ', $value ) );
    };

    $hay = ' ' . $norm( $location ) . ' ';
    $needle = ' ' . $norm( $place ) . ' ';
    return $needle !== '  ' && strpos( $hay, $needle ) !== false;
}

/**
 * Append a place (and optional province) to a location when missing.
 */
function bei_append_place_to_location( $location, $place, $province = '' ) : string {
    $location = bei_sanitize_location( $location );
    $place    = bei_sanitize_location( $place );
    $province = strtoupper( trim( (string) $province ) );

    $out = $location;

    if ( $place !== '' ) {
        if ( $out === '' ) {
            $out = $place;
        } elseif ( ! bei_location_contains_place( $out, $place ) ) {
            $out .= ', ' . $place;
        }
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
