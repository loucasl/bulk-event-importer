<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_bulk_event_ajax_import_start', function() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    check_ajax_referer( 'bulk_event_import' );

    $job_id = wp_generate_uuid4();

    $state = [
        'job_id' => $job_id,
        'feeds'  => Bulk_Event_Importer::get_feeds(),
        'feed_i' => 0,
        'offset' => 0,
        'batch'  => 25,

        'totals' => [
            'created'     => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'deleted'     => 0,
            'old_trashed' => 0,
        ],

        'per_feed' => [],
    ];

    set_transient( 'bei_job_' . $job_id, $state, HOUR_IN_SECONDS );

    wp_send_json_success( [
        'job_id'     => $job_id,
        'feed_count' => count( $state['feeds'] ),
        'batch_size' => $state['batch'],
    ] );
} );

add_action( 'wp_ajax_bulk_event_ajax_import_cancel', function() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    check_ajax_referer( 'bulk_event_import' );

    $job_id = sanitize_text_field( $_POST['job_id'] ?? '' );
    if ( ! $job_id ) {
        wp_send_json_error( [ 'message' => 'Missing job_id' ] );
    }

    $key   = 'bei_job_' . $job_id;
    $state = get_transient( $key );

    if ( is_array( $state ) ) {
        $state['cancelled'] = true;
        set_transient( $key, $state, HOUR_IN_SECONDS );
    }

    wp_send_json_success( [ 'cancelled' => true ] );
} );

add_action( 'wp_ajax_bulk_event_ajax_import_step', function() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }

    check_ajax_referer( 'bulk_event_import' );

    $job_id = sanitize_text_field( $_POST['job_id'] ?? '' );
    if ( ! $job_id ) {
        wp_send_json_error( [ 'message' => 'Missing job_id' ] );
    }

    ob_start();

    try {

        $key   = 'bei_job_' . $job_id;
        $state = get_transient( $key );

        if ( ! is_array( $state ) ) {
            ob_end_clean();
            wp_send_json_error( [ 'message' => 'Job expired. Please run import again.' ] );
        }

        if ( ! empty( $state['cancelled'] ) ) {
            set_transient( $key, $state, HOUR_IN_SECONDS );
            ob_end_clean();
            wp_send_json_success( [
                'done'      => true,
                'cancelled' => true,
                'totals'    => $state['totals'] ?? [],
                'per_feed'  => $state['per_feed'] ?? [],
            ] );
        }

        $feeds      = $state['feeds'];
        $feed_count = count( $feeds );

        if ( $state['feed_i'] >= $feed_count ) {

            $deleted = Bulk_Event_Importer::remove_blocked_events();
            $state['totals']['deleted'] = (int) $deleted;

            $options     = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
            $old_trashed = Bulk_Event_Importer::trash_old_events( (int) ( $options['trash_after_days'] ?? 7 ) );
            $state['totals']['old_trashed'] = (int) $old_trashed;

            set_transient( $key, $state, HOUR_IN_SECONDS );

            ob_end_clean();
            wp_send_json_success( [
                'done'     => true,
                'totals'   => $state['totals'],
                'per_feed' => $state['per_feed'],
            ] );
        }

        $feed_i     = (int) $state['feed_i'];
        $feed       = $feeds[ $feed_i ];
        $events_key = 'bei_job_' . $job_id . '_events_' . $feed_i;

        if ( empty( $state['per_feed'][ $feed_i ] ) ) {
            $state['per_feed'][ $feed_i ] = [
                'source'                 => $feed['source'],
                'url'                    => $feed['url'],
                'total'                  => 0,
                'done'                   => 0,
                'created'                => 0,
                'updated'                => 0,
                'skipped'                => 0,
                'skip_reasons'           => [],
                'blocked_keyword_counts' => [],
                'errors'                 => [],
            ];
        }

        $events = get_transient( $events_key );

        if ( ! is_array( $events ) ) {

            $err = '';
            $code = 0;
            $fetch_debug = [];
            $body = Bulk_Event_Importer::fetch_remote( $feed['url'], $err, $code, $fetch_debug );

            if ( ! $body ) {
                $state['per_feed'][ $feed_i ]['errors'][] = trim( $feed['source'] . ': ' . ( $err ?: 'Fetch failed' ) );
                if ( is_array( $fetch_debug ) && ! empty( $fetch_debug ) ) {
                    $state['per_feed'][ $feed_i ]['debug'] = [ 'type' => 'fetch', 'fetch' => $fetch_debug ];
                }
                $state['feed_i']++;
                $state['offset'] = 0;

                set_transient( $key, $state, HOUR_IN_SECONDS );

                ob_end_clean();
                wp_send_json_success( [
                    'done'       => false,
                    'feed_i'     => $feed_i,
                    'feed_count' => $feed_count,
                    'feed'       => $state['per_feed'][ $feed_i ],
                    'message'    => 'Skipped feed due to fetch error',
                ] );
            }

            if ( $feed['type'] === 'ics' ) {
                $events = Bulk_Event_Importer::parse_ics_body(
                    $body,
                    $feed['source'],
                    $feed['url'],
                    ! empty( $feed['aggregate'] )
                );
            } else {
                $rss    = Bulk_Event_Importer::parse_rss_body( $body, $feed['source'] );
                $events = $rss['events'];
                if ( ! empty( $rss['error'] ) ) {
                    $state['per_feed'][ $feed_i ]['errors'][] = $rss['error'];
                    $state['per_feed'][ $feed_i ]['debug'] = [
                        'type'        => 'parse',
                        'parse_error' => (string) $rss['error'],
                        'fetch'       => ( is_array( $fetch_debug ) && ! empty( $fetch_debug ) ) ? $fetch_debug : null,
                    ];
                }
            }

            if ( ! is_array( $events ) ) {
                $events = [];
            }

            $state['per_feed'][ $feed_i ]['total'] = count( $events );

            set_transient( $events_key, $events, HOUR_IN_SECONDS );
            $state['offset'] = 0;
        }

        $offset = (int) $state['offset'];
        $batch  = (int) $state['batch'];

        $slice = array_slice( $events, $offset, $batch );

        foreach ( $slice as $event ) {
            try {
                $result = Bulk_Event_Importer::upsert_event_post( $event );
            } catch ( Throwable $e ) {
                $result = 'skipped';
                $GLOBALS['bei_last_skip_reason']  = 'event_exception';
                $GLOBALS['bei_last_skip_keyword'] = '';
            }

            if ( $result === 'created' ) {
                $state['totals']['created']++;
                $state['per_feed'][ $feed_i ]['created']++;
            } elseif ( $result === 'updated' ) {
                $state['totals']['updated']++;
                $state['per_feed'][ $feed_i ]['updated']++;
            } else {
                $state['totals']['skipped']++;
                $state['per_feed'][ $feed_i ]['skipped']++;

                $reason       = $GLOBALS['bei_last_skip_reason'] ?? '';
                $skip_keyword = $GLOBALS['bei_last_skip_keyword'] ?? '';
                if ( is_string( $reason ) && $reason !== '' ) {
                    if ( ! isset( $state['per_feed'][ $feed_i ]['skip_reasons'][ $reason ] ) ) {
                        $state['per_feed'][ $feed_i ]['skip_reasons'][ $reason ] = 0;
                    }
                    $state['per_feed'][ $feed_i ]['skip_reasons'][ $reason ]++;

                    if ( $reason === 'blocked_keyword_match' && is_string( $skip_keyword ) && $skip_keyword !== '' ) {
                        if ( ! isset( $state['per_feed'][ $feed_i ]['blocked_keyword_counts'][ $skip_keyword ] ) ) {
                            $state['per_feed'][ $feed_i ]['blocked_keyword_counts'][ $skip_keyword ] = 0;
                        }
                        $state['per_feed'][ $feed_i ]['blocked_keyword_counts'][ $skip_keyword ]++;
                    }
                }
            }

            $state['per_feed'][ $feed_i ]['done']++;
        }

        $offset += count( $slice );
        $state['offset'] = $offset;

        $feed_total = (int) $state['per_feed'][ $feed_i ]['total'];

        if ( $offset >= $feed_total ) {
            delete_transient( $events_key );
            $state['feed_i']++;
            $state['offset'] = 0;
        }

        set_transient( $key, $state, HOUR_IN_SECONDS );

        ob_end_clean();

        wp_send_json_success( [
            'done'       => false,
            'feed_i'     => $feed_i,
            'feed_count' => $feed_count,
            'feed'       => $state['per_feed'][ $feed_i ],
            'totals'     => $state['totals'],
        ] );

    } catch ( Throwable $e ) {
        ob_end_clean();
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
} );
