<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Turn a normalized event array into a WordPress Event post.
 * Returns: 'created', 'updated', or 'skipped'
 */
function bei_upsert_event_post( $event ) {

    // Used by the AJAX importer to show why events were skipped (not persisted).
    $GLOBALS['bei_last_skip_reason']  = '';
    $GLOBALS['bei_last_skip_keyword'] = '';

    $defaults = [
        'title'        => '',
        'description'  => '',
        'start'        => '',
        'raw_start'    => '',
        'end'          => '',
        'raw_end'      => '',
        'location'     => '',
        'source'       => '',
        'external_url' => '',
    ];
    $event = array_merge( $defaults, $event );

    $options   = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    $field_map = bei_get_field_map();

    // Date validation.
    if ( empty( $event['start'] ) ) {
        $GLOBALS['bei_last_skip_reason'] = 'missing_start_date';
        return 'skipped';
    }

    // If location is missing, fall back to the feed/source label so RSS
    // items without a location field don't get skipped downstream.
    $incoming_location = bei_sanitize_location( $event['location'] ?? '' );
    if ( $incoming_location === '' ) {
        $incoming_location = bei_sanitize_location( $event['source'] ?? '' );
    }
    $event['location'] = $incoming_location;

    $now = new DateTime( 'now', wp_timezone() );

    try {
        $event_start = new DateTime( $event['start'], wp_timezone() );
    } catch ( Exception $e ) {
        $GLOBALS['bei_last_skip_reason'] = 'invalid_start_date';
        return 'skipped';
    }

    $past_days     = isset( $options['past_days'] ) && $options['past_days'] !== '' ? (int) $options['past_days'] : 7;
    $future_months = isset( $options['future_months'] ) && $options['future_months'] !== '' ? (int) $options['future_months'] : 18;

    $past_cutoff   = ( clone $now )->modify( "-{$past_days} days" );
    $future_cutoff = ( clone $now )->modify( "+{$future_months} months" );

    if ( $event_start < $past_cutoff || $event_start > $future_cutoff ) {
        $GLOBALS['bei_last_skip_reason'] = 'date_out_of_import_window';
        return 'skipped';
    }

    // Blocked keywords (title-only).
    $blocked_terms_raw = $options['blocked_keywords'] ?? '';
    $blocked_terms = array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( $blocked_terms_raw ) ) ) ) );

    $title_haystack = strtolower( (string) $event['title'] );
    foreach ( $blocked_terms as $word ) {
        if ( $word !== '' && str_contains( $title_haystack, $word ) ) {
            $GLOBALS['bei_last_skip_reason']  = 'blocked_keyword_match';
            $GLOBALS['bei_last_skip_keyword'] = (string) $word;
            return 'skipped';
        }
    }

    // Allowlist (optional module): only import events that match at least
    // one allowed keyword across title/location/link.
    if ( ! empty( $options['allowlist_enabled'] ) ) {
        $allowed_terms_raw = $options['allowed_keywords'] ?? '';
        $allowed_terms = array_filter( array_map( 'trim', explode( ',', strtolower( $allowed_terms_raw ) ) ) );

        if ( ! empty( $allowed_terms ) ) {
            $combined = $event['title'] . ' ' . $event['location'] . ' ' . $event['external_url'];
            $haystack = strtolower( preg_replace( '/[[:punct:]]/', ' ', $combined ) );

            $has_allow_match = false;
            foreach ( $allowed_terms as $word ) {
                $escaped = preg_quote( $word, '/' );
                if ( $word !== '' && preg_match( '/\b' . $escaped . '(s|es)?\b/i', $haystack ) ) {
                    $has_allow_match = true;
                    break;
                }
            }
            if ( ! $has_allow_match ) {
                $GLOBALS['bei_last_skip_reason'] = 'allowlist_no_match';
                return 'skipped';
            }
        }
    }

    // Identity uses calendar day (not clock time) so a timezone correction
    // updates an existing post instead of creating a duplicate.
    $start_day   = $event_start->format( 'Y-m-d' );
    $hash_source = $event['title'] . '|' . $start_day . '|' . $event['source'];

    if ( ! empty( $event['end'] ) ) {
        try {
            $event_end_for_hash = new DateTime( $event['end'], wp_timezone() );
            $end_day_for_hash   = $event_end_for_hash->format( 'Y-m-d' );
            if ( $end_day_for_hash !== $start_day ) {
                $hash_source .= '|' . $end_day_for_hash;
            }
        } catch ( Exception $e ) {
            // Ignore end for hash if unparseable.
        }
    }

    $hash = md5( $hash_source );

    $existing = get_posts( [
        'post_type'      => Bulk_Event_Importer::POST_TYPE,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'meta_query'     => [
            [ 'key' => Bulk_Event_Importer::META_HASH_KEY, 'value' => $hash ],
        ],
    ] );

    // Fallback: older hashes (or a different site's prior hash scheme) may
    // have included full datetime strings. Re-link by title + source +
    // calendar day so a timezone fix updates the existing post in place.
    if ( ! $existing ) {
        $existing = bei_find_existing_event_by_day( (string) $event['title'], (string) $event['source'], $start_day );
    }

    $raw_description = trim( wp_strip_all_tags( $event['description'] ) );
    $post_content     = wpautop( wp_kses_post( $event['description'] ) );

    if ( $existing ) {

        $post_id = $existing[0]->ID ?? $existing[0];

        $old_hash = (string) get_post_meta( $post_id, Bulk_Event_Importer::META_HASH_KEY, true );
        if ( $old_hash !== $hash ) {
            update_post_meta( $post_id, Bulk_Event_Importer::META_HASH_KEY, $hash );
        }

        $update_data = [ 'ID' => $post_id ];

        $current_title = (string) get_post_field( 'post_title', $post_id );
        if ( $current_title !== (string) $event['title'] ) {
            $update_data['post_title'] = $event['title'];
        }

        if ( strlen( $raw_description ) > 20 && ! filter_var( $raw_description, FILTER_VALIDATE_URL ) ) {
            $current_content = (string) get_post_field( 'post_content', $post_id );
            if ( $current_content !== $post_content ) {
                $update_data['post_content'] = $post_content;
            }
        }

        if ( count( $update_data ) > 1 ) {
            wp_update_post( $update_data );
        }

        $status = 'updated';

    } else {

        $import_status = $options['default_post_status'] ?? 'publish';

        $post_id = wp_insert_post( [
            'post_type'    => Bulk_Event_Importer::POST_TYPE,
            'post_status'  => $import_status,
            'post_author'  => 0,
            'post_title'   => $event['title'],
            'post_content' => $post_content,
        ] );

        if ( is_wp_error( $post_id ) ) {
            error_log( 'Bulk Event Importer: post insert error ' . $post_id->get_error_message() );
            return 'skipped';
        }

        update_post_meta( $post_id, Bulk_Event_Importer::META_HASH_KEY, $hash );

        $status = 'created';
    }

    $GLOBALS['bei_last_skip_reason']  = '';
    $GLOBALS['bei_last_skip_keyword'] = '';

    // Date/time fields, per the site's configured field map.
    try {
        $dt_start = new DateTime( $event['start'], wp_timezone() );

        $raw_start = trim( (string) ( $event['raw_start'] ?? '' ) );
        $raw_end   = trim( (string) ( $event['raw_end'] ?? '' ) );

        $dt_end = ( $raw_end !== '' && $raw_end !== $raw_start && ! empty( $event['end'] ) )
            ? new DateTime( $event['end'], wp_timezone() )
            : null;

    } catch ( Exception $e ) {
        return 'skipped';
    }

    bei_write_date_fields( $post_id, $dt_start, $dt_end, $raw_start, $raw_end );

    // Location (+ trigger geocoding when the module is enabled and the value changed).
    if ( ! empty( $field_map['location_meta'] ) ) {
        $old_location = (string) get_post_meta( $post_id, $field_map['location_meta'], true );
        $new_location = $event['location'];

        if ( $new_location === '' ) {
            bei_delete_meta_if_present( $post_id, $field_map['location_meta'] );
        } elseif ( $old_location !== $new_location ) {
            update_post_meta( $post_id, $field_map['location_meta'], $new_location );
            bei_geocode_and_save_for_post( (int) $post_id );
        }
    }

    // Long / short description (optional fields; skipped if not mapped).
    if ( ! empty( $field_map['description_long_meta'] ) ) {
        bei_write_meta_if_changed( $post_id, $field_map['description_long_meta'], $event['description'] );
    }
    if ( ! empty( $field_map['description_short_meta'] ) ) {
        bei_write_meta_if_changed( $post_id, $field_map['description_short_meta'], wp_trim_words( $event['description'], 25 ) );
    }

    // External URL.
    if ( ! empty( $field_map['external_url_meta'] ) && ! empty( $event['external_url'] ) ) {
        bei_write_meta_if_changed( $post_id, $field_map['external_url_meta'], esc_url_raw( $event['external_url'] ) );
    }

    // Source. A filter is provided so a site can blank/rewrite specific
    // source names (e.g. a mislabeled calendar feed) without a code fork.
    if ( ! empty( $field_map['source_meta'] ) ) {
        $new_source = ! empty( $event['source'] ) ? (string) $event['source'] : '';
        $new_source = (string) apply_filters( 'bei_event_source_name', $new_source, $event );
        if ( $new_source === '' ) {
            bei_delete_meta_if_present( $post_id, $field_map['source_meta'] );
        } else {
            bei_write_meta_if_changed( $post_id, $field_map['source_meta'], $new_source );
        }
    }

    // Any static meta the site wants set on every create/update (e.g. a
    // "button-text" or "ticketed" flag field JetEngine expects).
    $static_meta_enabled = array_key_exists( 'static_meta_enabled', $options )
        ? ! empty( $options['static_meta_enabled'] )
        : ! empty( $field_map['extra_static_meta'] );
    if ( $static_meta_enabled ) {
        foreach ( (array) ( $field_map['extra_static_meta'] ?? [] ) as $extra ) {
            if ( ! empty( $extra['key'] ) ) {
                bei_write_meta_if_changed( $post_id, $extra['key'], $extra['value'] ?? '' );
            }
        }
    }

    // Featured image.
    bei_maybe_import_featured_image( $post_id, $event );

    // Dynamic taxonomy assignment, per each site's configured keyword groups.
    $blended_text = strtolower( $event['title'] . ' ' . $event['description'] );
    bei_apply_dynamic_taxonomies( $post_id, $blended_text );

    /**
     * Fires after an event post has been fully upserted, for site-specific
     * integrations (e.g. auto-linking to a related "Community" post type)
     * that shouldn't live in the shared codebase.
     */
    do_action( 'bei_after_upsert_event_post', $post_id, $event, $status );

    return $status;
}
