<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bei_settings_blockers = bei_settings_page_blockers();
?>
<div class="wrap bulk-event-importer-settings">
    <?php if ( $bei_settings_blockers ) : ?>
        <?php foreach ( $bei_settings_blockers as $bei_blocker_message ) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $bei_blocker_message ); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div id="bei-settings-root"></div>
        <noscript>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'JavaScript is required to manage importer settings.', 'bulk-event-importer' ); ?></p>
            </div>
        </noscript>
        <script>
        document.addEventListener( 'DOMContentLoaded', function () {
            window.setTimeout( function () {
                var root = document.getElementById( 'bei-settings-root' );
                if ( ! root || root.innerHTML.trim() ) {
                    return;
                }
                root.innerHTML = <?php echo wp_json_encode(
                    '<div class="notice notice-error"><p>'
                    . esc_html__(
                        'The settings UI failed to load. Open the browser developer console for errors, confirm this site is on WordPress 6.9+, and redeploy the plugin so build/settings.js is present.',
                        'bulk-event-importer'
                    )
                    . '</p></div>'
                ); ?>;
            }, 4000 );
        } );
        </script>
    <?php endif; ?>
</div>
