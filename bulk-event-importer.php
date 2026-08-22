<?php
/**
 * Plugin Name: Bulk Event Importer
 * Description: Fetches external calendar feeds (RSS/ICS), normalizes them, and creates/updates Event posts. Taxonomies, keyword rules, JetEngine field mapping, geocoding, and the allowlist filter are all configured per site from Settings, so one codebase runs on every site.
 * Author: Red Dragon Creative
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Polyfills for PHP < 8.
if ( ! function_exists( 'str_contains' ) ) {
    function str_contains( $haystack, $needle ) {
        return $needle !== '' && strpos( (string) $haystack, (string) $needle ) !== false;
    }
}
if ( ! function_exists( 'str_starts_with' ) ) {
    function str_starts_with( $haystack, $needle ) {
        $needle = (string) $needle;
        if ( $needle === '' ) {
            return true;
        }
        return strncmp( (string) $haystack, $needle, strlen( $needle ) ) === 0;
    }
}
if ( ! function_exists( 'str_ends_with' ) ) {
    function str_ends_with( $haystack, $needle ) {
        $needle = (string) $needle;
        if ( $needle === '' ) {
            return true;
        }
        $haystack = (string) $haystack;
        return substr( $haystack, -strlen( $needle ) ) === $needle;
    }
}

define( 'BEI_PLUGIN_FILE', __FILE__ );
define( 'BEI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BEI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BEI_VERSION', '2.0.0' );

class Bulk_Event_Importer {

    const OPTION_SETTINGS = 'bulk_event_importer_settings';
    const POST_TYPE        = 'events';
    const META_HASH_KEY    = 'event_hash';

    /**
     * Get Feeds from Settings.
     * Format per line: Label | URL | type (type is optional: ics or rss).
     * Lines starting with # // or STANDBY are treated as comments and skipped.
     */
    public static function get_feeds() {
        $options = get_option( self::OPTION_SETTINGS, [] );
        $urls    = array_filter(
            array_map( 'trim', explode( "\n", $options['feed_urls'] ?? '' ) )
        );

        $default_type = in_array( $options['default_feed_type'] ?? 'ics', [ 'ics', 'rss' ], true )
            ? $options['default_feed_type']
            : 'ics';

        $feeds = [];

        foreach ( $urls as $line ) {

            $check_line = strtoupper( $line );
            if ( str_starts_with( $check_line, 'STANDBY' ) || str_starts_with( $check_line, '#' ) || str_starts_with( $check_line, '//' ) ) {
                continue;
            }

            $parts = explode( '|', $line );
            if ( count( $parts ) < 2 ) {
                continue;
            }

            $source = trim( $parts[0] );
            $url    = trim( $parts[1] );

            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) && ! str_starts_with( strtolower( $url ), 'webcal://' ) ) {
                continue; // skip malformed lines
            }

            // wp_remote_get cannot fetch the webcal:// scheme; it's https underneath.
            if ( str_starts_with( strtolower( $url ), 'webcal://' ) ) {
                $url = 'https://' . substr( $url, strlen( 'webcal://' ) );
            }

            $type = '';
            if ( count( $parts ) >= 3 ) {
                $maybe = strtolower( trim( $parts[2] ) );
                if ( in_array( $maybe, [ 'ics', 'rss' ], true ) ) {
                    $type = $maybe;
                }
            }

            if ( ! $type ) {
                $u = strtolower( $url );

                $looks_ics =
                    str_contains( $u, '.ics' ) ||
                    str_starts_with( $u, 'webcal://' ) ||
                    str_contains( $u, 'widget-subscription/' ) ||
                    str_contains( $u, 'format=ics' ) ||
                    str_contains( $u, 'format=ical' ) ||
                    str_contains( $u, 'ical=1' );

                $looks_rss =
                    str_contains( $u, '.rss' ) ||
                    str_contains( $u, '.xml' ) ||
                    str_contains( $u, 'format=rss' ) ||
                    str_contains( $u, 'feed=rss' );

                if ( $looks_ics ) {
                    $type = 'ics';
                } elseif ( $looks_rss ) {
                    $type = 'rss';
                } else {
                    $type = $default_type;
                }
            }

            $feeds[] = [
                'source' => $source,
                'url'    => $url,
                'type'   => $type,
            ];
        }

        return $feeds;
    }

    /**
     * Register the taxonomies defined in Settings against the Events post type.
     * Each site defines its own taxonomy/keyword-group structure, so this plugin
     * carries no hardcoded categories.
     */
    public static function register_dynamic_taxonomies() {
        $taxonomies = bei_get_taxonomy_config();

        foreach ( $taxonomies as $tax ) {
            $slug = sanitize_key( $tax['slug'] ?? '' );
            if ( $slug === '' ) {
                continue;
            }

            $label = $tax['label'] ?? ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

            register_taxonomy(
                $slug,
                self::POST_TYPE,
                [
                    'label'             => $label,
                    'hierarchical'      => true,
                    'public'            => true,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'show_in_rest'      => true,
                    'rewrite'           => [ 'slug' => $slug ],
                ]
            );
        }
    }

    public static function remove_blocked_events() {
        return bei_remove_blocked_events();
    }

    public static function trash_old_events( $days_old = 7 ) {
        return bei_trash_old_events( $days_old );
    }

    public static function run_import() {
        bei_run_import();
    }

    public static function fetch_remote( $url, &$error = null, &$http_code = null, &$debug = null ) {
        return bei_fetch_remote( $url, $error, $http_code, $debug );
    }

    public static function parse_ics_feed( $url, $source_name ) {
        return bei_parse_ics_feed( $url, $source_name );
    }

    public static function parse_ics_body( $body, $source_name, $feed_url = '' ) {
        return bei_parse_ics_body( $body, $source_name, $feed_url );
    }

    public static function parse_rss_feed( $url, $source_name ) {
        return bei_parse_rss_feed( $url, $source_name );
    }

    public static function parse_rss_body( $body, $source_name ) {
        return bei_parse_rss_body( $body, $source_name );
    }

    public static function upsert_event_post( $event ) {
        return bei_upsert_event_post( $event );
    }

    public static function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'Importer Settings',
            'Importer Settings',
            'manage_options',
            'bulk-event-importer-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_settings_page() {
        require BEI_PLUGIN_DIR . 'includes/admin-page-template.php';
    }

    public static function register_settings() {
        register_setting(
            'bulk_event_importer',
            self::OPTION_SETTINGS,
            [ 'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ] ]
        );
    }

    /**
     * Sanitize settings. Keyword group values are stored flat under their own
     * group key (e.g. option['cat_music_keywords']), same as legacy installs,
     * so existing keyword data survives the upgrade with no migration step.
     */
    public static function sanitize_settings( $input ) {
        $output = get_option( self::OPTION_SETTINGS, [] );

        // --- Feeds ---
        if ( isset( $input['feed_urls'] ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", (string) $input['feed_urls'] ) ) );
            $output['feed_urls'] = implode( "\n", $lines );
        }
        if ( isset( $input['default_feed_type'] ) ) {
            $val = strtolower( sanitize_text_field( $input['default_feed_type'] ) );
            $output['default_feed_type'] = in_array( $val, [ 'ics', 'rss' ], true ) ? $val : 'ics';
        }

        // --- Import window / status ---
        $status = isset( $input['default_post_status'] ) ? sanitize_text_field( $input['default_post_status'] ) : ( $output['default_post_status'] ?? 'publish' );
        $output['default_post_status'] = in_array( $status, [ 'publish', 'pending', 'draft' ], true ) ? $status : 'publish';

        $output['past_days']      = isset( $input['past_days'] ) ? max( 0, (int) $input['past_days'] ) : ( $output['past_days'] ?? 7 );
        $output['future_months']  = isset( $input['future_months'] ) ? max( 0, (int) $input['future_months'] ) : ( $output['future_months'] ?? 18 );
        $output['trash_after_days'] = isset( $input['trash_after_days'] ) ? max( 0, (int) $input['trash_after_days'] ) : ( $output['trash_after_days'] ?? 7 );

        $cron = isset( $input['cron_interval'] ) ? sanitize_text_field( $input['cron_interval'] ) : ( $output['cron_interval'] ?? 'hourly' );
        $output['cron_interval'] = in_array( $cron, [ 'hourly', 'twicedaily', 'daily' ], true ) ? $cron : 'hourly';

        // --- Blocklist / allowlist ---
        if ( isset( $input['blocked_keywords'] ) ) {
            $output['blocked_keywords'] = bei_normalize_keyword_csv( (string) $input['blocked_keywords'] );
        }
        $output['allowlist_enabled'] = ! empty( $input['allowlist_enabled'] ) ? '1' : '';
        if ( isset( $input['allowed_keywords'] ) ) {
            $output['allowed_keywords'] = bei_normalize_keyword_csv( (string) $input['allowed_keywords'] );
        }

        // --- Dynamic taxonomy structure (slug/label/groups), keywords stored separately below ---
        if ( isset( $input['taxonomies_json'] ) ) {
            // The Settings API normally hands us already-unslashed data; fall
            // back to wp_unslash only if a first decode fails.
            $decoded = json_decode( (string) $input['taxonomies_json'], true );
            if ( ! is_array( $decoded ) ) {
                $decoded = json_decode( wp_unslash( (string) $input['taxonomies_json'] ), true );
            }
            if ( is_array( $decoded ) ) {
                $clean_tax = [];
                foreach ( $decoded as $tax ) {
                    $slug  = sanitize_key( $tax['slug'] ?? '' );
                    $label = sanitize_text_field( $tax['label'] ?? '' );
                    if ( $slug === '' || $label === '' ) {
                        continue;
                    }
                    $groups = [];
                    foreach ( (array) ( $tax['groups'] ?? [] ) as $group ) {
                        $key  = sanitize_key( $group['key'] ?? '' );
                        $term = sanitize_text_field( $group['term'] ?? '' );
                        if ( $key === '' || $term === '' ) {
                            continue;
                        }
                        $groups[] = [ 'key' => $key, 'term' => $term ];

                        // Persist this group's keyword CSV using its own flat option key.
                        $kw_field = 'group_kw__' . $key;
                        if ( isset( $input[ $kw_field ] ) ) {
                            $output[ $key ] = bei_normalize_keyword_csv( (string) $input[ $kw_field ] );
                        }
                    }
                    $clean_tax[] = [
                        'slug'         => $slug,
                        'label'        => $label,
                        'default_term' => sanitize_text_field( $tax['default_term'] ?? '' ),
                        'groups'       => $groups,
                    ];
                }
                $output['taxonomies'] = $clean_tax;
            }
        }

        // --- Field mapping ---
        $field_keys = [
            'date_mode', 'je_date_meta',
            'start_date_meta', 'start_time_meta', 'end_date_meta', 'end_time_meta',
            'location_meta', 'description_long_meta', 'description_short_meta',
            'external_url_meta', 'source_meta',
        ];
        $field_map = $output['field_map'] ?? [];
        foreach ( $field_keys as $fk ) {
            if ( isset( $input[ 'fm_' . $fk ] ) ) {
                $field_map[ $fk ] = sanitize_text_field( $input[ 'fm_' . $fk ] );
            }
        }
        if ( ! in_array( $field_map['date_mode'] ?? 'split', [ 'split', 'je_advanced_date' ], true ) ) {
            $field_map['date_mode'] = 'split';
        }

        // Static extra meta (key/value pairs always written on create/update).
        if ( isset( $input['fm_extra_keys'] ) && isset( $input['fm_extra_values'] ) ) {
            $keys   = (array) $input['fm_extra_keys'];
            $values = (array) $input['fm_extra_values'];
            $extra  = [];
            foreach ( $keys as $i => $k ) {
                $k = sanitize_key( $k );
                $v = isset( $values[ $i ] ) ? sanitize_text_field( $values[ $i ] ) : '';
                if ( $k !== '' ) {
                    $extra[] = [ 'key' => $k, 'value' => $v ];
                }
            }
            $field_map['extra_static_meta'] = $extra;
        }
        $output['field_map'] = $field_map;

        // --- Geocoding module ---
        $output['geocoding_enabled'] = ! empty( $input['geocoding_enabled'] ) ? '1' : '';
        foreach ( [ 'geocoding_address_metas', 'geocoding_lat_meta', 'geocoding_lng_meta', 'geocoding_hash_meta', 'geocoding_country_suffix' ] as $gk ) {
            if ( isset( $input[ $gk ] ) ) {
                $output[ $gk ] = sanitize_text_field( $input[ $gk ] );
            }
        }

        return $output;
    }
}

/**
 * Load included files.
 */
require_once BEI_PLUGIN_DIR . 'includes/helpers.php';
require_once BEI_PLUGIN_DIR . 'includes/taxonomy.php';
require_once BEI_PLUGIN_DIR . 'includes/field-map.php';
require_once BEI_PLUGIN_DIR . 'includes/post-upsert.php';
require_once BEI_PLUGIN_DIR . 'includes/importer.php';
require_once BEI_PLUGIN_DIR . 'includes/parsers-ics.php';
require_once BEI_PLUGIN_DIR . 'includes/parsers-rss.php';
require_once BEI_PLUGIN_DIR . 'includes/images.php';
require_once BEI_PLUGIN_DIR . 'includes/geocode.php';
require_once BEI_PLUGIN_DIR . 'includes/admin.php';
require_once BEI_PLUGIN_DIR . 'includes/ajax.php';
require_once BEI_PLUGIN_DIR . 'includes/cron.php';

add_action( 'init', [ 'Bulk_Event_Importer', 'register_dynamic_taxonomies' ] );

/**
 * Cron setup. Interval is configurable per site (Settings > Importer Settings);
 * default matches the historical "hourly" behavior.
 */
register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'bulk_event_import_cron' ) ) {
        $options  = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
        $interval = in_array( $options['cron_interval'] ?? 'hourly', [ 'hourly', 'twicedaily', 'daily' ], true )
            ? $options['cron_interval']
            : 'hourly';
        wp_schedule_event( time(), $interval, 'bulk_event_import_cron' );
    }
} );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'bulk_event_import_cron' );
} );

/**
 * If the cron interval setting changes, reschedule to match.
 */
add_action( 'update_option_' . Bulk_Event_Importer::OPTION_SETTINGS, function( $old_value, $new_value ) {
    $old_interval = $old_value['cron_interval'] ?? 'hourly';
    $new_interval = $new_value['cron_interval'] ?? 'hourly';

    if ( $old_interval !== $new_interval ) {
        wp_clear_scheduled_hook( 'bulk_event_import_cron' );
        wp_schedule_event( time(), $new_interval, 'bulk_event_import_cron' );
    }
}, 10, 2 );
