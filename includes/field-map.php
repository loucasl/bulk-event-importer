<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Field mapping.
 *
 * JetEngine generates its own meta keys per site/field-group, and different
 * sites are set up with different field structures (a single "Advanced Date"
 * field vs separate start/end date+time fields, optional long/short
 * description fields, etc). Rather than hardcode either shape, the plugin
 * reads a field map from Settings and writes only the meta keys a given site
 * has actually configured. Leave a field blank to skip it entirely.
 */
function bei_get_field_map() {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    $map     = is_array( $options['field_map'] ?? null ) ? $options['field_map'] : [];

    $defaults = [
        'date_mode'               => 'split', // 'split' or 'je_advanced_date'
        'je_date_meta'            => '',
        'start_date_meta'         => 'event-start-date',
        'start_time_meta'         => 'event-start-time',
        'end_date_meta'           => 'event-end-date',
        'end_time_meta'           => 'event-end-time',
        'location_meta'           => 'event-location',
        'description_long_meta'   => '',
        'description_short_meta'  => '',
        'external_url_meta'       => 'event-link',
        'source_meta'             => 'event-source',
        'extra_static_meta'       => [], // [ ['key'=>'button-text','value'=>'View Details'], ... ]
    ];

    return array_merge( $defaults, $map );
}

/**
 * Read back the stored start timestamp for a post, regardless of which date
 * mode the site uses. Used for day-based dedup lookups.
 */
function bei_get_stored_start_timestamp( $post_id ) {
    $field_map = bei_get_field_map();

    if ( $field_map['date_mode'] === 'je_advanced_date' && ! empty( $field_map['je_date_meta'] ) ) {
        return (int) get_post_meta( $post_id, $field_map['je_date_meta'], true );
    }

    if ( ! empty( $field_map['start_date_meta'] ) ) {
        return (int) get_post_meta( $post_id, $field_map['start_date_meta'], true );
    }

    return 0;
}

/**
 * Write the start/end date+time meta for a post according to the site's
 * configured date mode. $dt_start is required; $dt_end may be null.
 */
function bei_write_date_fields( $post_id, DateTime $dt_start, ?DateTime $dt_end, $raw_start = '', $raw_end = '' ) {
    $field_map = bei_get_field_map();
    $tz        = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

    if ( $field_map['date_mode'] === 'je_advanced_date' && ! empty( $field_map['je_date_meta'] ) ) {

        $je_key      = $field_map['je_date_meta'];
        $je_midnight = clone $dt_start;
        $je_midnight->setTimezone( $tz );
        $je_midnight->setTime( 0, 0, 0 );

        $je_ts   = (int) $je_midnight->getTimestamp();
        $je_date = $je_midnight->format( 'Y-m-d' );

        if ( (int) get_post_meta( $post_id, $je_key, true ) !== $je_ts ) {
            update_post_meta( $post_id, $je_key, $je_ts );
            update_post_meta( $post_id, $je_key . '__config', wp_json_encode( [ 'date' => $je_date ] ) );
            update_post_meta( $post_id, $je_key . '__rrule', 'DTSTART=' . gmdate( 'Ymd', $je_ts ) . 'T000000Z' );
        }

        bei_write_meta_if_changed( $post_id, $field_map['start_time_meta'], $dt_start->format( 'H:i' ) );
        if ( $dt_end ) {
            bei_write_meta_if_changed( $post_id, $field_map['end_time_meta'], $dt_end->format( 'H:i' ) );
        } else {
            bei_delete_meta_if_present( $post_id, $field_map['end_time_meta'] ?? '' );
        }

        return;
    }

    // Split mode: separate start/end date (timestamp, local midnight) + time metas.
    if ( ! empty( $field_map['start_date_meta'] ) ) {
        $start_ts = bei_local_midnight_timestamp( $dt_start );
        bei_write_meta_if_changed( $post_id, $field_map['start_date_meta'], $start_ts );
    }

    if ( ! empty( $field_map['start_time_meta'] ) ) {
        if ( strlen( (string) $raw_start ) > 8 ) {
            bei_write_meta_if_changed( $post_id, $field_map['start_time_meta'], $dt_start->format( 'H:i' ) );
        } else {
            bei_delete_meta_if_present( $post_id, $field_map['start_time_meta'] );
        }
    }

    if ( $dt_end ) {
        $start_day = $dt_start->format( 'Y-m-d' );
        $end_day   = $dt_end->format( 'Y-m-d' );

        if ( ! empty( $field_map['end_date_meta'] ) ) {
            if ( $end_day !== $start_day ) {
                bei_write_meta_if_changed( $post_id, $field_map['end_date_meta'], bei_local_midnight_timestamp( $dt_end ) );
            } else {
                bei_delete_meta_if_present( $post_id, $field_map['end_date_meta'] );
            }
        }

        if ( ! empty( $field_map['end_time_meta'] ) ) {
            if ( strlen( (string) $raw_end ) > 8 ) {
                bei_write_meta_if_changed( $post_id, $field_map['end_time_meta'], $dt_end->format( 'H:i' ) );
            } else {
                bei_delete_meta_if_present( $post_id, $field_map['end_time_meta'] );
            }
        }
    } else {
        bei_delete_meta_if_present( $post_id, $field_map['end_date_meta'] ?? '' );
        bei_delete_meta_if_present( $post_id, $field_map['end_time_meta'] ?? '' );
    }
}

function bei_write_meta_if_changed( $post_id, $meta_key, $value ) {
    if ( empty( $meta_key ) ) {
        return;
    }
    $current = get_post_meta( $post_id, $meta_key, true );
    if ( (string) $current !== (string) $value ) {
        update_post_meta( $post_id, $meta_key, $value );
    }
}

function bei_delete_meta_if_present( $post_id, $meta_key ) {
    if ( empty( $meta_key ) ) {
        return;
    }
    if ( get_post_meta( $post_id, $meta_key, true ) !== '' ) {
        delete_post_meta( $post_id, $meta_key );
    }
}
