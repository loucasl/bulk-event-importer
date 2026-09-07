<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ICS parsing functions. Class wrappers call these so hook code doesn't change.
 */

function bei_parse_ics_feed( $url, $source_name, $aggregate = false ) {
    $body = Bulk_Event_Importer::fetch_remote( $url );
    if ( ! $body ) {
        return [];
    }
    return bei_parse_ics_body( $body, $source_name, $url, $aggregate );
}

function bei_parse_ics_body( $body, $source_name, $feed_url = '', $aggregate = false ) {

    // Handle folded lines (lines beginning with a space are continuations per RFC 5545).
    $body   = preg_replace( "/\r\n[ \t]/", '', $body );
    $lines  = preg_split( '/\r\n|\r|\n/', $body );
    $events = [];
    $current  = [];
    $in_event = false;

    foreach ( $lines as $line ) {
        $line = trim( $line );

        if ( strtoupper( $line ) === 'BEGIN:VEVENT' ) {
            $in_event = true;
            $current  = [];
            continue;
        }

        if ( strtoupper( $line ) === 'END:VEVENT' ) {
            $in_event = false;

            $desc = $current['DESCRIPTION'] ?? '';
            $alt  = $current['X-ALT-DESC'] ?? '';

            // Prefer X-ALT-DESC when it is present and more meaningful.
            if ( strlen( trim( wp_strip_all_tags( $alt ) ) ) > strlen( trim( wp_strip_all_tags( $desc ) ) ) ) {
                $desc = $alt;
            }

            $external_url = bei_normalize_external_url(
                $current['URL'] ?? '',
                $feed_url,
                $desc
            );

            $desc = bei_clean_ics_description( $desc, $external_url );

            // Default: keep the feed label. Opt-in "aggregate" feeds (e.g. a
            // personal Google Calendar that remixes many origins) may replace
            // the label with a name derived from the event URL host.
            $event_source = $source_name;
            if ( $aggregate && ! empty( $external_url ) ) {
                $extracted_source = bei_get_source_name_from_url( $external_url );
                if ( ! empty( $extracted_source ) ) {
                    $event_source = $extracted_source;
                }
            }

            $events[] = bei_ics_build_event_from_vevent( $current, $desc, $event_source, $external_url );

            $current = [];
            continue;
        }

        if ( $in_event ) {
            bei_ics_parse_vevent_line( $line, $current );
        }
    }

    return $events;
}

/**
 * Strip boilerplate that some ICS exporters (e.g. EventCalendarApp-style
 * feeds) inject into DESCRIPTION, such as a "Latest event details:" prefix,
 * a divider line, and a repeat of the event's own URL. Safe to run on any
 * feed; it only removes text that matches exactly.
 */
function bei_clean_ics_description( $desc, $external_url = '' ) {
    $needles = [ 'Latest event details:', '---' ];
    if ( ! empty( $external_url ) ) {
        $needles[] = $external_url;
    }
    $desc = str_replace( $needles, '', $desc );
    return trim( $desc, " \n\r\t\v\0-" );
}

/**
 * Parse one ICS content line into $current (mutates by reference).
 * Preserves TZID for DTSTART/DTEND so times can be converted correctly, and
 * reconstructs basic list markup from bullet/dash lines.
 */
function bei_ics_parse_vevent_line( $line, array &$current ) {
    $parts = explode( ':', $line, 2 );
    if ( count( $parts ) !== 2 ) {
        return;
    }

    $key_full = trim( $parts[0] );
    $value    = trim( $parts[1] );

    $value = preg_replace( '/\\\\n+/', "\n", $value );
    $value = str_replace( [ '\\,', '\\;' ], [ ',', ';' ], $value );
    $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

    if ( strpos( $value, '•' ) !== false || preg_match( '/(^|\n)[\-\*]\s/', $value ) ) {
        $lines_for_html = preg_split( '/\n+/', trim( $value ) );
        $html_lines     = [];

        foreach ( $lines_for_html as $l ) {
            $l = trim( $l );
            if ( $l === '' ) {
                continue;
            }

            if ( preg_match( '/^(•|[\-\*])\s*/', $l ) ) {
                $l = preg_replace( '/^(•|[\-\*])\s*/', '', $l );
                $html_lines[] = '<li>' . $l . '</li>';
            } else {
                $html_lines[] = '<p>' . $l . '</p>';
            }
        }

        if ( preg_grep( '/^<li>/', $html_lines ) ) {
            $value = '<ul>' . implode( '', $html_lines ) . '</ul>';
        } else {
            $value = implode( '', $html_lines );
        }
    }

    // KEY may look like "DTSTART;TZID=America/Toronto".
    $key_bits = explode( ';', $key_full );
    $key      = strtoupper( trim( $key_bits[0] ) );

    $tzid = '';
    foreach ( array_slice( $key_bits, 1 ) as $param ) {
        if ( stripos( $param, 'TZID=' ) === 0 ) {
            $tzid = trim( substr( $param, 5 ), " \t\"" );
            break;
        }
    }

    $current[ $key ] = $value;
    if ( $tzid !== '' && ( $key === 'DTSTART' || $key === 'DTEND' ) ) {
        $current[ $key . '_TZID' ] = $tzid;
    }
}

/**
 * Build the normalized event array from a finished VEVENT property map.
 */
function bei_ics_build_event_from_vevent( array $current, $description, $event_source, $external_url ) {
    return [
        'title'        => $current['SUMMARY'] ?? '',
        'description'  => $description,
        'start'        => isset( $current['DTSTART'] )
            ? bei_ics_datetime_to_mysql( $current['DTSTART'], $current['DTSTART_TZID'] ?? '' )
            : '',
        'raw_start'    => $current['DTSTART'] ?? '',
        'end'          => isset( $current['DTEND'] )
            ? bei_ics_datetime_to_mysql( $current['DTEND'], $current['DTEND_TZID'] ?? '' )
            : '',
        'raw_end'      => $current['DTEND'] ?? '',
        'location'     => $current['LOCATION'] ?? '',
        'source'       => $event_source,
        'external_url' => $external_url,
    ];
}

/**
 * Convert an ICS date/datetime value into a MySQL datetime string in the
 * WordPress site timezone (wall clock).
 *
 * @param string $ics_dt Raw value e.g. 20260821T100000, ...Z, or ...+0200
 * @param string $tzid   Optional TZID from DTSTART;TZID=America/Toronto
 */
function bei_ics_datetime_to_mysql( $ics_dt, $tzid = '' ) {
    $ics_dt = trim( (string) $ics_dt );
    $tzid   = trim( (string) $tzid, " \t\"" );

    if ( $ics_dt === '' ) {
        return '';
    }

    $wp_tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

    // Date only: YYYYMMDD
    if ( preg_match( '/^\d{8}$/', $ics_dt ) ) {
        $y = substr( $ics_dt, 0, 4 );
        $m = substr( $ics_dt, 4, 2 );
        $d = substr( $ics_dt, 6, 2 );
        return $y . '-' . $m . '-' . $d . ' 00:00:00';
    }

    // Datetime: YYYYMMDDTHHMMSS[Z|+/-HHMM|+/-HH:MM]
    if ( ! preg_match( '/^(\d{8})T(\d{6})(Z|[+-]\d{2}:?\d{2})?$/i', $ics_dt, $m ) ) {
        return '';
    }

    $date   = $m[1];
    $time   = $m[2];
    $suffix = isset( $m[3] ) ? strtoupper( $m[3] ) : '';

    $local_str = sprintf(
        '%s-%s-%s %s:%s:%s',
        substr( $date, 0, 4 ),
        substr( $date, 4, 2 ),
        substr( $date, 6, 2 ),
        substr( $time, 0, 2 ),
        substr( $time, 2, 2 ),
        substr( $time, 4, 2 )
    );

    try {
        if ( $suffix === 'Z' ) {
            $dt = new DateTime( $local_str, new DateTimeZone( 'UTC' ) );
            $dt->setTimezone( $wp_tz );
            return $dt->format( 'Y-m-d H:i:s' );
        }

        if ( $suffix !== '' && preg_match( '/^([+-])(\d{2}):?(\d{2})$/', $suffix, $om ) ) {
            $offset = $om[1] . $om[2] . ':' . $om[3];
            $dt     = new DateTime( $local_str, new DateTimeZone( $offset ) );
            $dt->setTimezone( $wp_tz );
            return $dt->format( 'Y-m-d H:i:s' );
        }

        if ( $tzid !== '' ) {
            try {
                $src_tz = new DateTimeZone( $tzid );
            } catch ( Exception $e ) {
                $src_tz = $wp_tz;
            }
            $dt = new DateTime( $local_str, $src_tz );
            $dt->setTimezone( $wp_tz );
            return $dt->format( 'Y-m-d H:i:s' );
        }

        // Floating local time: already a site wall clock.
        return $local_str;
    } catch ( Exception $e ) {
        return $local_str;
    }
}

/**
 * Resolve a possibly-relative event URL against the feed URL, ignoring
 * Google Calendar internal event links, with a fallback that extracts the
 * first usable URL out of the description text.
 */
function bei_normalize_external_url( $maybe_relative, $feed_url, $description = '' ) {
    $maybe_relative = trim( (string) $maybe_relative );
    $feed_url       = trim( (string) $feed_url );

    if ( $maybe_relative !== '' ) {
        if ( preg_match( '#google\.com/calendar#i', $maybe_relative ) ||
             preg_match( '#calendar\.google\.com#i', $maybe_relative ) ) {
            $maybe_relative = '';
        } elseif ( preg_match( '#^https?://#i', $maybe_relative ) ) {
            return esc_url_raw( $maybe_relative );
        } else {
            $base = wp_parse_url( $feed_url );
            if ( ! empty( $base['scheme'] ) && ! empty( $base['host'] ) ) {
                $scheme = $base['scheme'];
                $host   = $base['host'];

                $path = $maybe_relative;
                if ( str_starts_with( $path, '//' ) ) {
                    return esc_url_raw( $scheme . ':' . $path );
                }

                if ( ! str_starts_with( $path, '/' ) ) {
                    $path = '/' . $path;
                }

                return esc_url_raw( $scheme . '://' . $host . $path );
            }

            return esc_url_raw( $maybe_relative );
        }
    }

    $description = (string) $description;
    if ( $description !== '' ) {
        if ( preg_match_all( '#https?://[^\s"\'<>\(\)]+#i', $description, $matches ) ) {
            foreach ( $matches[0] as $url_candidate ) {
                if ( ! preg_match( '#google\.com/calendar#i', $url_candidate ) &&
                     ! preg_match( '#calendar\.google\.com#i', $url_candidate ) ) {
                    return esc_url_raw( $url_candidate );
                }
            }
        }
    }

    return '';
}
