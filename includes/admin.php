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
 * Admin assets (CSS/JS) for the plugin settings page only.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'events_page_bulk-event-importer-settings' ) {
        return;
    }

    // Bust browser cache on file change so Push-to-Deploy / manual updates
    // pick up CSS/JS without requiring a plugin version bump.
    $css_path = BEI_PLUGIN_DIR . 'assets/admin.css';
    $js_path  = BEI_PLUGIN_DIR . 'assets/admin.js';
    $css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : BEI_VERSION;
    $js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : BEI_VERSION;

    wp_enqueue_style( 'bulk-event-importer-admin', BEI_PLUGIN_URL . 'assets/admin.css', [], $css_ver );
    wp_enqueue_script( 'bulk-event-importer-admin', BEI_PLUGIN_URL . 'assets/admin.js', [], $js_ver, true );

    wp_localize_script(
        'bulk-event-importer-admin',
        'bulkEventImporter',
        [
            'nonce'      => wp_create_nonce( 'bulk_event_import' ),
            'optionName' => Bulk_Event_Importer::OPTION_SETTINGS,
        ]
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
