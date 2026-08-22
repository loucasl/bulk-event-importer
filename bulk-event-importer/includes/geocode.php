<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Optional geocoding module. Off by default; enable per site in
 * Settings > Importer Settings > Geocoding.
 *
 * Requires a server-side Google Geocoding key in wp-config.php:
 *   define('LL_GOOGLE_GEOCODE_KEY', 'YOUR_SERVER_GEOCODING_KEY');
 *
 * The lat/lng/hash meta keys are configurable (not hardcoded) because
 * JetEngine's map-field meta keys are generated per field group per site
 * and will differ between installs.
 */

function bei_geocoding_enabled() {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    return ! empty( $options['geocoding_enabled'] )
        && defined( 'LL_GOOGLE_GEOCODE_KEY' )
        && trim( (string) LL_GOOGLE_GEOCODE_KEY ) !== '';
}

function bei_geocode_config() {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );

    $address_metas = array_filter( array_map( 'trim', explode( ',', $options['geocoding_address_metas'] ?? '' ) ) );

    return [
        'address_meta_keys' => $address_metas,
        'lat_key'           => $options['geocoding_lat_meta'] ?? '',
        'lng_key'           => $options['geocoding_lng_meta'] ?? '',
        'hash_key'          => $options['geocoding_hash_meta'] ?? '',
        'country_suffix'    => $options['geocoding_country_suffix'] ?? '',
    ];
}

function bei_geocode_build_address_for_post( $post_id, array $cfg ) {
    $parts = [];

    foreach ( (array) $cfg['address_meta_keys'] as $meta_key ) {
        $val = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
        if ( $val !== '' ) {
            $parts[] = $val;
        }
    }

    if ( empty( $parts ) ) {
        return '';
    }

    $address = implode( ', ', $parts );

    $suffix = trim( (string) ( $cfg['country_suffix'] ?? '' ) );
    if ( $suffix !== '' && stripos( $address, $suffix ) === false ) {
        $address .= ', ' . $suffix;
    }

    return $address;
}

function bei_geocode_google( $address ) {
    $key = defined( 'LL_GOOGLE_GEOCODE_KEY' ) ? trim( (string) LL_GOOGLE_GEOCODE_KEY ) : '';
    if ( $key === '' ) {
        return new WP_Error( 'no_key', 'LL_GOOGLE_GEOCODE_KEY is not defined in wp-config.php' );
    }

    $url = add_query_arg(
        [ 'key' => $key, 'language' => 'en', 'address' => $address ],
        'https://maps.googleapis.com/maps/api/geocode/json'
    );

    $res = wp_remote_get( $url, [ 'timeout' => 15 ] );
    if ( is_wp_error( $res ) ) {
        return $res;
    }

    $code = (int) wp_remote_retrieve_response_code( $res );
    $body = (string) wp_remote_retrieve_body( $res );

    if ( $code !== 200 ) {
        return new WP_Error( 'http_' . $code, 'Google geocode HTTP status: ' . $code );
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'bad_json', 'Google geocode returned invalid JSON' );
    }

    $status = $data['status'] ?? '';
    if ( $status !== 'OK' || empty( $data['results'][0]['geometry']['location'] ) ) {
        $msg = $data['error_message'] ?? '';
        return new WP_Error( 'google_status', 'Google status: ' . $status . ( $msg ? ' | ' . $msg : '' ) );
    }

    $loc = $data['results'][0]['geometry']['location'];

    return [
        'lat' => (string) ( $loc['lat'] ?? '' ),
        'lng' => (string) ( $loc['lng'] ?? '' ),
    ];
}

function bei_geocode_and_save_for_post( $post_id ) {

    if ( ! bei_geocoding_enabled() ) {
        return;
    }

    $post_id = (int) $post_id;
    if ( ! $post_id ) {
        return;
    }

    $cfg = bei_geocode_config();

    if ( empty( $cfg['address_meta_keys'] ) || empty( $cfg['lat_key'] ) || empty( $cfg['lng_key'] ) ) {
        return; // module enabled but not fully configured yet
    }

    $address = bei_geocode_build_address_for_post( $post_id, $cfg );

    if ( $address === '' ) {
        update_post_meta( $post_id, '_bei_geocode_error', 'Address is empty (missing meta fields)' );
        return;
    }

    $result = bei_geocode_google( $address );
    if ( is_wp_error( $result ) ) {
        update_post_meta( $post_id, '_bei_geocode_error', $result->get_error_message() );
        return;
    }

    $lat = $result['lat'];
    $lng = $result['lng'];

    update_post_meta( $post_id, $cfg['lat_key'], $lat );
    update_post_meta( $post_id, $cfg['lng_key'], $lng );

    if ( ! empty( $cfg['hash_key'] ) ) {
        update_post_meta( $post_id, $cfg['hash_key'], md5( $lat . ',' . $lng ) );
    }

    delete_post_meta( $post_id, '_bei_geocode_error' );
    update_post_meta( $post_id, '_bei_geocode_last_success', current_time( 'mysql' ) );
}
