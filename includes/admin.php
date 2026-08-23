<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin menu + settings init.
 */
add_action( 'admin_menu', [ 'Bulk_Event_Importer', 'register_admin_menu' ] );
add_action( 'admin_init', [ 'Bulk_Event_Importer', 'register_settings' ] );

/**
 * Reasons the React settings UI cannot load (empty messages = OK to render).
 *
 * @return string[]
 */
function bei_settings_page_blockers() {
    $blockers = [];

    if ( ! file_exists( BEI_PLUGIN_DIR . 'build/settings.js' ) ) {
        $blockers[] = 'Built admin assets are missing (build/settings.js). Reinstall or redeploy the plugin from the develop branch, or run npm install && npm run build in the plugin directory.';
    }

    global $wp_version;
    if ( version_compare( $wp_version, '6.9', '<' ) ) {
        $blockers[] = sprintf(
            'Bulk Event Importer 2.1.0 requires WordPress 6.9 or newer (this site is running %s). Upgrade WordPress or deploy the main branch (2.0.x) until you can upgrade.',
            $wp_version
        );
    }

    return $blockers;
}

/**
 * Admin assets for the React settings page.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'events_page_bulk-event-importer-settings' ) {
        return;
    }

    if ( bei_settings_page_blockers() ) {
        return;
    }

    $script_path = BEI_PLUGIN_DIR . 'build/settings.js';
    $style_path  = BEI_PLUGIN_DIR . 'build/style-index.css';
    $asset_path  = BEI_PLUGIN_DIR . 'build/settings.asset.php';

    $asset = file_exists( $asset_path )
        ? include $asset_path
        : [
            'dependencies' => [
                'wp-element',
                'wp-components',
                'wp-i18n',
                'wp-api-fetch',
            ],
            'version'      => BEI_VERSION,
        ];

    $script_ver = file_exists( $script_path )
        ? (string) filemtime( $script_path )
        : $asset['version'];

    wp_enqueue_script(
        'bulk-event-importer-settings',
        BEI_PLUGIN_URL . 'build/settings.js',
        $asset['dependencies'],
        $script_ver,
        true
    );

    if ( file_exists( $style_path ) ) {
        wp_enqueue_style(
            'bulk-event-importer-settings',
            BEI_PLUGIN_URL . 'build/style-index.css',
            [ 'wp-components' ],
            (string) filemtime( $style_path )
        );
    }

    wp_localize_script(
        'bulk-event-importer-settings',
        'bulkEventImporter',
        [
            'nonce'   => wp_create_nonce( 'bulk_event_import' ),
            'restUrl' => esc_url_raw( rest_url( 'bulk-event-importer/v1/settings' ) ),
        ]
    );

    wp_add_inline_script(
        'bulk-event-importer-settings',
        'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( ' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ' ) );',
        'before'
    );
} );

/**
 * Add "Run Import" button to the Events admin list table.
 */
add_action( 'admin_head-edit.php', function() {
    $screen = get_current_screen();

    if ( empty( $screen->post_type ) || $screen->post_type !== Bulk_Event_Importer::POST_TYPE ) {
        return;
    }

    $import_url = admin_url( 'edit.php?post_type=' . Bulk_Event_Importer::POST_TYPE . '&page=bulk-event-importer-settings#import-progress' );
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const addNew = document.querySelector('.wrap a.page-title-action');
        if (!addNew) return;

        const btn = document.createElement('a');
        btn.setAttribute('href', <?php echo wp_json_encode( $import_url ); ?>);
        btn.className = 'page-title-action';
        btn.textContent = 'Run Event Import';

        addNew.insertAdjacentElement('afterend', btn);
    });
    </script>
    <?php
} );

/**
 * Admin-post endpoint to run a full import immediately outside the AJAX UI
 * (useful for a manual link or an external uptime-style trigger).
 */
add_action( 'admin_post_bulk_event_import_now', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    Bulk_Event_Importer::run_import();

    wp_redirect(
        admin_url( 'edit.php?post_type=' . Bulk_Event_Importer::POST_TYPE . '&page=bulk-event-importer-settings#import-progress' )
    );
    exit;
} );
