<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cron runner: when WordPress triggers the scheduled event, run the importer.
 * The schedule interval itself is configurable in Settings > Importer Settings
 * (see bulk-event-importer.php for the activation/reschedule logic).
 */
add_action(
    'bulk_event_import_cron',
    function() {
        Bulk_Event_Importer::run_import();
    }
);
