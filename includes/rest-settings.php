<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API for Importer Settings (React admin UI).
 */
add_action( 'rest_api_init', function() {
    register_rest_route(
        'bulk-event-importer/v1',
        '/settings',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'bei_rest_get_settings',
                'permission_callback' => 'bei_rest_settings_permission',
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => 'bei_rest_update_settings',
                'permission_callback' => 'bei_rest_settings_permission',
            ],
        ]
    );
} );

function bei_rest_settings_permission() {
    return current_user_can( 'manage_options' );
}

/**
 * DB option → UI-shaped object for React.
 *
 * @param array $options Raw option array.
 * @return array
 */
function bei_settings_to_rest( array $options ) {
    $field_map = array_merge(
        [
            'date_mode'              => 'split',
            'je_date_meta'           => '',
            'start_date_meta'        => 'event-start-date',
            'start_time_meta'        => 'event-start-time',
            'end_date_meta'          => 'event-end-date',
            'end_time_meta'          => 'event-end-time',
            'location_meta'          => 'event-location',
            'description_long_meta'  => '',
            'description_short_meta' => '',
            'external_url_meta'      => 'event-link',
            'source_meta'            => 'event-source',
            'extra_static_meta'      => [],
        ],
        is_array( $options['field_map'] ?? null ) ? $options['field_map'] : []
    );

    $taxonomies = [];
    foreach ( (array) ( $options['taxonomies'] ?? [] ) as $tax ) {
        $groups = [];
        foreach ( (array) ( $tax['groups'] ?? [] ) as $group ) {
            $key = $group['key'] ?? '';
            if ( $key === '' ) {
                continue;
            }
            $raw_kw = $options[ $key ] ?? '';
            $keywords = array_values(
                array_filter(
                    array_map( 'trim', explode( ',', (string) $raw_kw ) )
                )
            );
            $groups[] = [
                'key'      => $key,
                'term'     => (string) ( $group['term'] ?? '' ),
                'keywords' => $keywords,
            ];
        }
        $taxonomies[] = [
            'slug'         => (string) ( $tax['slug'] ?? '' ),
            'label'        => (string) ( $tax['label'] ?? '' ),
            'default_term' => (string) ( $tax['default_term'] ?? '' ),
            'groups'       => $groups,
        ];
    }

    return [
        'feed_urls'            => (string) ( $options['feed_urls'] ?? '' ),
        'default_feed_type'    => in_array( $options['default_feed_type'] ?? 'ics', [ 'ics', 'rss' ], true )
            ? $options['default_feed_type']
            : 'ics',
        'past_days'            => (int) ( $options['past_days'] ?? 7 ),
        'future_months'        => (int) ( $options['future_months'] ?? 18 ),
        'trash_after_days'     => (int) ( $options['trash_after_days'] ?? 7 ),
        'default_post_status'  => (string) ( $options['default_post_status'] ?? 'publish' ),
        'cron_interval'        => (string) ( $options['cron_interval'] ?? 'hourly' ),
        'blocked_keywords'     => bei_rest_csv_to_array( $options['blocked_keywords'] ?? '' ),
        'allowlist_enabled'    => ! empty( $options['allowlist_enabled'] ),
        'allowed_keywords'     => bei_rest_csv_to_array( $options['allowed_keywords'] ?? '' ),
        'geocoding_enabled'    => ! empty( $options['geocoding_enabled'] ),
        'static_meta_enabled'  => array_key_exists( 'static_meta_enabled', $options )
            ? ! empty( $options['static_meta_enabled'] )
            : ! empty( $field_map['extra_static_meta'] ),
        'geocoding_address_metas'   => (string) ( $options['geocoding_address_metas'] ?? '' ),
        'geocoding_lat_meta'        => (string) ( $options['geocoding_lat_meta'] ?? '' ),
        'geocoding_lng_meta'        => (string) ( $options['geocoding_lng_meta'] ?? '' ),
        'geocoding_hash_meta'       => (string) ( $options['geocoding_hash_meta'] ?? '' ),
        'geocoding_country_suffix'  => (string) ( $options['geocoding_country_suffix'] ?? '' ),
        'taxonomies'           => $taxonomies,
        'field_map'            => $field_map,
    ];
}

/**
 * UI object → flat input for sanitize_settings().
 *
 * @param array $data REST payload.
 * @return array
 */
function bei_settings_from_rest( array $data ) {
    $field_map = is_array( $data['field_map'] ?? null ) ? $data['field_map'] : [];

    $taxonomies_for_json = [];
    $input = [
        'feed_urls'           => (string) ( $data['feed_urls'] ?? '' ),
        'default_feed_type'   => (string) ( $data['default_feed_type'] ?? 'ics' ),
        'past_days'           => (int) ( $data['past_days'] ?? 7 ),
        'future_months'       => (int) ( $data['future_months'] ?? 18 ),
        'trash_after_days'    => (int) ( $data['trash_after_days'] ?? 7 ),
        'default_post_status' => (string) ( $data['default_post_status'] ?? 'publish' ),
        'cron_interval'       => (string) ( $data['cron_interval'] ?? 'hourly' ),
        'blocked_keywords'    => bei_rest_array_to_csv( $data['blocked_keywords'] ?? [] ),
        'allowlist_enabled'   => ! empty( $data['allowlist_enabled'] ) ? '1' : '',
        'allowed_keywords'    => bei_rest_array_to_csv( $data['allowed_keywords'] ?? [] ),
        'geocoding_enabled'   => ! empty( $data['geocoding_enabled'] ) ? '1' : '',
        'static_meta_enabled' => ! empty( $data['static_meta_enabled'] ) ? '1' : '',
        'geocoding_address_metas'  => (string) ( $data['geocoding_address_metas'] ?? '' ),
        'geocoding_lat_meta'       => (string) ( $data['geocoding_lat_meta'] ?? '' ),
        'geocoding_lng_meta'       => (string) ( $data['geocoding_lng_meta'] ?? '' ),
        'geocoding_hash_meta'      => (string) ( $data['geocoding_hash_meta'] ?? '' ),
        'geocoding_country_suffix' => (string) ( $data['geocoding_country_suffix'] ?? '' ),
    ];

    foreach ( (array) ( $data['taxonomies'] ?? [] ) as $tax ) {
        $groups = [];
        foreach ( (array) ( $tax['groups'] ?? [] ) as $group ) {
            $key  = sanitize_key( $group['key'] ?? '' );
            $term = (string) ( $group['term'] ?? '' );
            if ( $key === '' || $term === '' ) {
                continue;
            }
            $groups[] = [
                'key'  => $key,
                'term' => $term,
            ];
            $input[ 'group_kw__' . $key ] = bei_rest_array_to_csv( $group['keywords'] ?? [] );
        }
        $slug  = sanitize_key( $tax['slug'] ?? '' );
        $label = (string) ( $tax['label'] ?? '' );
        if ( $slug === '' || $label === '' ) {
            continue;
        }
        $taxonomies_for_json[] = [
            'slug'         => $slug,
            'label'        => $label,
            'default_term' => (string) ( $tax['default_term'] ?? '' ),
            'groups'       => $groups,
        ];
    }

    $input['taxonomies_json'] = wp_json_encode( $taxonomies_for_json );

    $fm_keys = [
        'date_mode'              => 'fm_date_mode',
        'je_date_meta'           => 'fm_je_date_meta',
        'start_date_meta'        => 'fm_start_date_meta',
        'start_time_meta'        => 'fm_start_time_meta',
        'end_date_meta'          => 'fm_end_date_meta',
        'end_time_meta'          => 'fm_end_time_meta',
        'location_meta'          => 'fm_location_meta',
        'description_long_meta'  => 'fm_description_long_meta',
        'description_short_meta' => 'fm_description_short_meta',
        'external_url_meta'      => 'fm_external_url_meta',
        'source_meta'            => 'fm_source_meta',
    ];
    foreach ( $fm_keys as $fk => $input_key ) {
        if ( isset( $field_map[ $fk ] ) ) {
            $input[ $input_key ] = (string) $field_map[ $fk ];
        }
    }

    $extra = (array) ( $field_map['extra_static_meta'] ?? [] );
    $input['fm_extra_keys']   = array_map(
        function( $row ) {
            return (string) ( $row['key'] ?? '' );
        },
        $extra
    );
    $input['fm_extra_values'] = array_map(
        function( $row ) {
            return (string) ( $row['value'] ?? '' );
        },
        $extra
    );

    return $input;
}

function bei_rest_csv_to_array( $raw ) {
    if ( is_array( $raw ) ) {
        return array_values(
            array_filter(
                array_map( 'trim', $raw )
            )
        );
    }
    return array_values(
        array_filter(
            array_map( 'trim', explode( ',', (string) $raw ) )
        )
    );
}

function bei_rest_array_to_csv( $arr ) {
    if ( ! is_array( $arr ) ) {
        return '';
    }
    return implode(
        ',',
        array_filter(
            array_map( 'trim', $arr )
        )
    );
}

function bei_rest_get_settings( WP_REST_Request $request ) {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    if ( ! is_array( $options ) ) {
        $options = [];
    }
    return rest_ensure_response( bei_settings_to_rest( $options ) );
}

function bei_rest_update_settings( WP_REST_Request $request ) {
    $data  = $request->get_json_params();
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'bei_invalid_settings', 'Invalid settings payload.', [ 'status' => 400 ] );
    }

    $input  = bei_settings_from_rest( $data );
    $output = Bulk_Event_Importer::sanitize_settings( $input );
    update_option( Bulk_Event_Importer::OPTION_SETTINGS, $output );

    return rest_ensure_response( bei_settings_to_rest( $output ) );
}
