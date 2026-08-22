<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Importer core helpers (kept as simple functions for readability).
 * The class methods call these, so hook/AJAX code does not need to change.
 */

/**
 * Delete already-imported posts that now fail the blocklist (title match)
 * or, when the allowlist module is enabled, that fail to match any allowed
 * keyword across title/location/external URL.
 */
function bei_remove_blocked_events() {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    $field_map = bei_get_field_map();

    $blocked_terms = array_filter( array_map( 'trim', explode( ',', strtolower( $options['blocked_keywords'] ?? '' ) ) ) );
    $blocked_terms = array_unique( $blocked_terms );

    $allowlist_enabled = ! empty( $options['allowlist_enabled'] );
    $allowed_terms = $allowlist_enabled
        ? array_filter( array_map( 'trim', explode( ',', strtolower( $options['allowed_keywords'] ?? '' ) ) ) )
        : [];

    if ( empty( $blocked_terms ) && empty( $allowed_terms ) ) {
        return 0;
    }

    $deleted_count = 0;

    $query = new WP_Query( [
        'post_type'      => Bulk_Event_Importer::POST_TYPE,
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'pending', 'draft' ],
        'meta_query'     => [
            [
                'key'     => Bulk_Event_Importer::META_HASH_KEY,
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    foreach ( $query->posts as $post ) {

        $should_delete = false;

        // Blocklist: title-only match (matches import-time behavior).
        if ( ! empty( $blocked_terms ) ) {
            $title_haystack = strtolower( (string) $post->post_title );
            foreach ( $blocked_terms as $term ) {
                if ( $term !== '' && str_contains( $title_haystack, $term ) ) {
                    $should_delete = true;
                    break;
                }
            }
        }

        // Allowlist: broader match across title/location/link, only when enabled.
        if ( ! $should_delete && ! empty( $allowed_terms ) ) {
            $location = ! empty( $field_map['location_meta'] ) ? get_post_meta( $post->ID, $field_map['location_meta'], true ) : '';
            $link     = ! empty( $field_map['external_url_meta'] ) ? get_post_meta( $post->ID, $field_map['external_url_meta'], true ) : '';
            $combined = $post->post_title . ' ' . $location . ' ' . $link;
            $clean    = preg_replace( '/[[:punct:]]/', ' ', $combined );
            $haystack = strtolower( $clean );

            $has_allow_match = false;
            foreach ( $allowed_terms as $term ) {
                $escaped = preg_quote( $term, '/' );
                if ( $term !== '' && preg_match( '/\b' . $escaped . '(s|es)?\b/i', $haystack ) ) {
                    $has_allow_match = true;
                    break;
                }
            }
            if ( ! $has_allow_match ) {
                $should_delete = true;
            }
        }

        if ( $should_delete ) {
            wp_delete_post( $post->ID, false );
            $deleted_count++;
        }
    }

    return $deleted_count;
}

/**
 * Trash events whose start date is older than $days_old.
 */
function bei_trash_old_events( $days_old = 7 ) {
    $field_map = bei_get_field_map();
    $date_meta = ( $field_map['date_mode'] === 'je_advanced_date' && ! empty( $field_map['je_date_meta'] ) )
        ? $field_map['je_date_meta']
        : $field_map['start_date_meta'];

    if ( empty( $date_meta ) ) {
        return 0;
    }

    $days_old = max( 1, absint( $days_old ) );
    $cutoff_timestamp = current_time( 'timestamp' ) - ( $days_old * DAY_IN_SECONDS );

    $old_event_ids = get_posts( [
        'post_type'              => Bulk_Event_Importer::POST_TYPE,
        'post_status'            => [ 'publish', 'future', 'draft', 'pending', 'private' ],
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'              => [
            [
                'key'     => $date_meta,
                'value'   => $cutoff_timestamp,
                'compare' => '<',
                'type'    => 'NUMERIC',
            ],
        ],
    ] );

    $trashed_count = 0;

    foreach ( $old_event_ids as $post_id ) {
        if ( wp_trash_post( $post_id ) ) {
            $trashed_count++;
        }
    }

    return $trashed_count;
}

/**
 * Fallback full import run (used by WP-Cron; the admin page uses the
 * batched AJAX importer in ajax.php instead so large feed sets don't time out).
 */
function bei_run_import() {
    $feeds = Bulk_Event_Importer::get_feeds();

    foreach ( $feeds as $feed ) {
        $items = [];

        if ( $feed['type'] === 'ics' ) {
            $items = Bulk_Event_Importer::parse_ics_feed( $feed['url'], $feed['source'] );
        } elseif ( $feed['type'] === 'rss' ) {
            $items = Bulk_Event_Importer::parse_rss_feed( $feed['url'], $feed['source'] );
        }

        foreach ( $items as $event ) {
            Bulk_Event_Importer::upsert_event_post( $event );
        }
    }

    bei_remove_blocked_events();

    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    bei_trash_old_events( (int) ( $options['trash_after_days'] ?? 7 ) );
}

/**
 * Build a Referer that looks like a normal browser visit to the remote host.
 */
function bei_fetch_remote_referer_for_url( $url ) {
    $parts = wp_parse_url( $url );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
        return '';
    }
    $scheme = strtolower( (string) $parts['scheme'] );
    $host   = (string) $parts['host'];
    if ( $scheme !== 'http' && $scheme !== 'https' ) {
        return '';
    }
    return $scheme . '://' . $host . '/';
}

/**
 * Whether an HTTP error message looks like a transient TLS/connection issue worth retrying.
 */
function bei_fetch_remote_is_transient_curl_error( $message ) {
    return (bool) preg_match(
        '/(cURL error 56|unexpected eof while reading|SSL_read|Recv failure|Connection reset by peer)/i',
        (string) $message
    );
}

/**
 * Apply curl options for feed fetches: force HTTP/1.1; optionally force IPv4.
 */
function bei_fetch_remote_apply_curl_options( $handle, $force_ipv4 ) {
    if ( ! function_exists( 'curl_setopt' ) ) {
        return;
    }
    if ( defined( 'CURL_HTTP_VERSION_1_1' ) ) {
        curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
    }
    if ( $force_ipv4 && defined( 'CURL_IPRESOLVE_V4' ) ) {
        curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    }
}

/**
 * Fetch a remote feed with retry/backoff for transient TLS errors, an IPv4
 * fallback for hosts that break on IPv6, an sslverify=false last resort, and
 * a 403 retry with feed-flavored Accept headers for CDNs that block generic
 * browser requests on /rss/ style paths.
 */
function bei_fetch_remote( $url, &$error = null, &$http_code = null, &$debug = null ) {

    $error = '';
    $http_code = 0;
    $t0 = microtime( true );

    if ( is_array( $debug ) ) {
        $debug = [];
    }

    $debug_state = [
        'attempts_total'       => 0,
        'attempts'             => [],
        'last_error'           => '',
        'http_code'            => 0,
        'used_sslverify_false' => false,
        'used_ipv4'            => false,
        'timing_ms'            => 0,
        'final_url'            => '',
        'original_url'         => (string) $url,
    ];

    $referer = bei_fetch_remote_referer_for_url( $url );
    if ( $referer === '' ) {
        $referer = home_url( '/' );
    }

    $base_headers = [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Accept'          => 'text/calendar, application/ics, application/rss+xml, application/atom+xml, application/xml, text/xml, text/html;q=0.9, */*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Referer'         => $referer,
        'Connection'      => 'close',
    ];

    $base_args = [
        'timeout'     => 25,
        'redirection' => 5,
        'httpversion' => '1.1',
        'sslverify'   => true,
        'headers'     => $base_headers,
    ];

    $do_request = static function ( array $args, $force_ipv4, $sleep_before_sec = 0, $attempt_no = 1 ) use ( $url, &$debug_state ) {
        if ( $sleep_before_sec > 0 ) {
            sleep( (int) $sleep_before_sec );
        }
        $cb = static function ( $handle ) use ( $force_ipv4 ) {
            bei_fetch_remote_apply_curl_options( $handle, $force_ipv4 );
        };
        add_action( 'http_api_curl', $cb, 10, 1 );
        $response = wp_remote_get( $url, $args );
        remove_action( 'http_api_curl', $cb, 10, 1 );

        $debug_state['attempts_total']++;
        $attempt = [
            'attempt'          => (int) $attempt_no,
            'force_ipv4'       => (bool) $force_ipv4,
            'sslverify'        => (bool) ( $args['sslverify'] ?? true ),
            'sleep_before_sec' => (int) $sleep_before_sec,
            'error_message'    => '',
        ];
        if ( is_wp_error( $response ) ) {
            $attempt['error_message'] = (string) $response->get_error_message();
            $debug_state['last_error'] = $attempt['error_message'];
        }
        $debug_state['attempts'][] = $attempt;

        if ( $force_ipv4 ) {
            $debug_state['used_ipv4'] = true;
        }
        if ( empty( $args['sslverify'] ) ) {
            $debug_state['used_sslverify_false'] = true;
        }

        return $response;
    };

    $finish = function ( $val ) use ( &$debug, &$debug_state, $t0 ) {
        $debug_state['timing_ms'] = (int) round( ( microtime( true ) - $t0 ) * 1000 );
        if ( is_array( $debug ) ) {
            $debug = $debug_state;
        }
        return $val;
    };

    $response = $do_request( $base_args, false, 0, 1 );

    if ( is_wp_error( $response ) ) {
        $err_msg = $response->get_error_message();
        error_log( 'Bulk Event Importer fetch error (attempt 1): ' . $err_msg . ' | URL: ' . $url );

        if ( ! bei_fetch_remote_is_transient_curl_error( $err_msg ) ) {
            $error = $err_msg;
            $debug_state['last_error'] = (string) $error;
            return $finish( false );
        }

        foreach ( [ 1, 2, 4 ] as $idx => $sleep_sec ) {
            $response = $do_request( $base_args, true, $sleep_sec, $idx + 2 );
            if ( ! is_wp_error( $response ) ) {
                break;
            }
            $err_msg = $response->get_error_message();
            error_log( 'Bulk Event Importer fetch error: ' . $err_msg . ' | URL: ' . $url );
            if ( ! bei_fetch_remote_is_transient_curl_error( $err_msg ) ) {
                $error = $err_msg;
                $debug_state['last_error'] = (string) $error;
                return $finish( false );
            }
        }

        if ( is_wp_error( $response ) ) {
            $fallback = $base_args;
            $fallback['sslverify'] = false;
            $response = $do_request( $fallback, true, 0, (int) ( $debug_state['attempts_total'] + 1 ) );
            if ( is_wp_error( $response ) ) {
                $error = $response->get_error_message();
                error_log( 'Bulk Event Importer fetch error (retry no-verify): ' . $error . ' | URL: ' . $url );
                $debug_state['last_error'] = (string) $error;
                return $finish( false );
            }
        }
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $debug_state['http_code'] = (int) $http_code;

    // 403: retry with a feed-first Accept header (some CDNs block generic browser Accept on /rss/ paths).
    if ( $http_code === 403 ) {
        $retry403 = $base_args;
        $retry403['headers']['Accept'] = 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8';
        $retry403['headers']['Cache-Control'] = 'no-cache';
        $response = $do_request( $retry403, false, 0, (int) ( $debug_state['attempts_total'] + 1 ) );
        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            error_log( 'Bulk Event Importer fetch 403 retry error: ' . $error . ' | URL: ' . $url );
            return $finish( false );
        }
        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $debug_state['http_code'] = (int) $http_code;
    }

    $location = wp_remote_retrieve_header( $response, 'location' );
    if ( is_string( $location ) && $location !== '' ) {
        $debug_state['final_url'] = $location;
    }

    if ( $http_code < 200 || $http_code >= 300 ) {
        $error = 'Bad HTTP code: ' . $http_code;
        error_log( 'Bulk Event Importer bad HTTP code ' . $http_code . ' for ' . $url );
        $debug_state['last_error'] = (string) $error;
        return $finish( false );
    }

    $body = wp_remote_retrieve_body( $response );

    if ( $body === '' || $body === null ) {
        $error = 'Empty response body';
        $debug_state['last_error'] = (string) $error;
        return $finish( false );
    }

    return $finish( $body );
}
