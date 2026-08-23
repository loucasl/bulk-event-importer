<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options    = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
$field_map  = bei_get_field_map();
$taxonomies = bei_get_taxonomy_config();
$OPT        = Bulk_Event_Importer::OPTION_SETTINGS;

if ( ! function_exists( 'bei_admin_chip_field' ) ) {
function bei_admin_chip_field( $name, $raw_value, $label = '', $description = '' ) {
    $list = array_filter( array_map( 'trim', explode( ',', (string) $raw_value ) ) );
    ?>
    <div class="bei-chip-field">
        <?php if ( $label ) : ?><label class="bei-field-label"><?php echo esc_html( $label ); ?></label><?php endif; ?>
        <div class="chip-wrapper">
            <div class="chips">
                <?php foreach ( $list as $item ) : ?>
                    <span class="keyword-chip"><?php echo esc_html( $item ); ?><button type="button" class="remove-chip">&times;</button></span>
                <?php endforeach; ?>
            </div>
            <input type="text" class="chip-input" placeholder="Type keyword and press Enter">
            <input type="hidden" class="chip-hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ',', $list ) ); ?>">
        </div>
        <?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
    </div>
    <?php
}
}
?>
<div class="wrap bulk-event-importer-settings">

    <div class="bei-page-header">
        <h1>Bulk Event Importer Settings</h1>
        <div class="bei-import-now">
            <div class="bei-import-actions">
                <button type="button" id="run-import-ajax" class="button button-primary">Run Import Now</button>
                <button type="button" id="cancel-import-ajax" class="button" style="display:none;">Cancel Import</button>
            </div>
            <p class="description bei-import-hint">Imports all configured feeds in real time. Progress appears below.</p>
            <div id="import-progress" class="bei-import-progress" aria-labelledby="import-progress-heading">
                <h2 id="import-progress-heading" class="screen-reader-text">Import progress</h2>
                <div class="bei-progress-outer">
                    <div id="progress-bar" class="bei-progress-bar"></div>
                </div>
                <div id="import-status" class="bei-import-status"></div>
            </div>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'bulk_event_importer' ); ?>

        <!-- Feed URLs -->
        <section class="bei-section">
            <h2>Feed URLs</h2>
            <p class="description">One feed per line: <code>Label | URL</code> or <code>Label | URL | type</code> (type is <code>ics</code> or <code>rss</code>, optional). Lines starting with <code>#</code> are ignored.</p>
            <textarea name="<?php echo esc_attr( $OPT ); ?>[feed_urls]" rows="8"><?php echo esc_textarea( $options['feed_urls'] ?? '' ); ?></textarea>

            <p class="bei-inline-field">
                <label for="bei-default-feed-type">Default type when not specified:</label>
                <select id="bei-default-feed-type" name="<?php echo esc_attr( $OPT ); ?>[default_feed_type]">
                    <option value="ics" <?php selected( $options['default_feed_type'] ?? 'ics', 'ics' ); ?>>ICS</option>
                    <option value="rss" <?php selected( $options['default_feed_type'] ?? 'ics', 'rss' ); ?>>RSS</option>
                </select>
            </p>
        </section>

        <!-- Import window / status -->
        <section class="bei-section">
            <h2>Import Window &amp; Status</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="bei-past-days">Import events starting up to (days in the past)</label></th>
                    <td><input type="number" min="0" id="bei-past-days" name="<?php echo esc_attr( $OPT ); ?>[past_days]" value="<?php echo esc_attr( $options['past_days'] ?? 7 ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bei-future-months">Import events starting up to (months in the future)</label></th>
                    <td><input type="number" min="0" id="bei-future-months" name="<?php echo esc_attr( $OPT ); ?>[future_months]" value="<?php echo esc_attr( $options['future_months'] ?? 18 ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bei-trash-days">Move past events to trash after (days)</label></th>
                    <td><input type="number" min="0" id="bei-trash-days" name="<?php echo esc_attr( $OPT ); ?>[trash_after_days]" value="<?php echo esc_attr( $options['trash_after_days'] ?? 7 ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bei-default-status">New event post status</label></th>
                    <td>
                        <select id="bei-default-status" name="<?php echo esc_attr( $OPT ); ?>[default_post_status]">
                            <?php foreach ( [ 'publish' => 'Publish', 'pending' => 'Pending review', 'draft' => 'Draft' ] as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $options['default_post_status'] ?? 'publish', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bei-cron-interval">Automatic import runs</label></th>
                    <td>
                        <select id="bei-cron-interval" name="<?php echo esc_attr( $OPT ); ?>[cron_interval]">
                            <?php foreach ( [ 'hourly' => 'Every hour', 'twicedaily' => 'Twice a day', 'daily' => 'Once a day' ] as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $options['cron_interval'] ?? 'hourly', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </section>

        <!-- Blocked / Allowed keywords -->
        <section class="bei-section">
            <h2>Blocked Keywords</h2>
            <p class="description">Events whose title contains any of these are skipped on import and removed if already published.</p>
            <?php bei_admin_chip_field( $OPT . '[blocked_keywords]', $options['blocked_keywords'] ?? '' ); ?>
        </section>

        <!-- Optional modules overview + settings -->
        <section class="bei-section">
            <h2>Optional Modules</h2>
            <p class="description">Enable a module to show its settings below. Disabled modules are left out of import behaviour.</p>
            <div class="bei-modules-grid">
                <article class="bei-module-card">
                    <div class="bei-module-card-body">
                        <h3 class="bei-module-card-title">Allowlist Filter</h3>
                        <p class="bei-module-card-desc">Only import events that match at least one allowed keyword (title, location, or link).</p>
                    </div>
                    <label class="bei-module-switch">
                        <input type="checkbox" class="bei-module-toggle" name="<?php echo esc_attr( $OPT ); ?>[allowlist_enabled]" value="1" data-bei-module="allowlist" <?php checked( ! empty( $options['allowlist_enabled'] ) ); ?>>
                        <span class="bei-module-switch-ui" aria-hidden="true"></span>
                        <span class="screen-reader-text">Enable Allowlist Filter</span>
                    </label>
                </article>
                <article class="bei-module-card">
                    <div class="bei-module-card-body">
                        <h3 class="bei-module-card-title">Geocoding</h3>
                        <p class="bei-module-card-desc">Look up coordinates for event locations. Requires <code>LL_GOOGLE_GEOCODE_KEY</code> in wp-config.php.</p>
                    </div>
                    <label class="bei-module-switch">
                        <input type="checkbox" class="bei-module-toggle" name="<?php echo esc_attr( $OPT ); ?>[geocoding_enabled]" value="1" data-bei-module="geocoding" <?php checked( ! empty( $options['geocoding_enabled'] ) ); ?>>
                        <span class="bei-module-switch-ui" aria-hidden="true"></span>
                        <span class="screen-reader-text">Enable Geocoding</span>
                    </label>
                </article>
            </div>
        </section>

        <section class="bei-section bei-module-panel" data-bei-module-panel="allowlist"<?php echo empty( $options['allowlist_enabled'] ) ? ' hidden' : ''; ?>>
            <h2>Allowlist Filter <span class="bei-badge">optional module</span></h2>
            <p class="description">Only import events that match at least one of these keywords (checked against title, location, and link).</p>
            <?php bei_admin_chip_field( $OPT . '[allowed_keywords]', $options['allowed_keywords'] ?? '' ); ?>
        </section>

        <section class="bei-section bei-module-panel" data-bei-module-panel="geocoding"<?php echo empty( $options['geocoding_enabled'] ) ? ' hidden' : ''; ?>>
            <h2>Geocoding <span class="bei-badge">optional module</span></h2>
            <p class="description">Requires <code>LL_GOOGLE_GEOCODE_KEY</code> defined in wp-config.php. Meta keys must match this site's JetEngine map field.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Address source meta key(s)</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[geocoding_address_metas]" value="<?php echo esc_attr( $options['geocoding_address_metas'] ?? '' ); ?>" class="regular-text" placeholder="event-location"><p class="description">Comma-separated if the address is built from more than one field.</p></td>
                </tr>
                <tr>
                    <th scope="row">Latitude meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[geocoding_lat_meta]" value="<?php echo esc_attr( $options['geocoding_lat_meta'] ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Longitude meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[geocoding_lng_meta]" value="<?php echo esc_attr( $options['geocoding_lng_meta'] ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Hash meta key <span class="bei-optional">(optional)</span></th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[geocoding_hash_meta]" value="<?php echo esc_attr( $options['geocoding_hash_meta'] ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Country suffix <span class="bei-optional">(optional)</span></th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[geocoding_country_suffix]" value="<?php echo esc_attr( $options['geocoding_country_suffix'] ?? '' ); ?>" class="regular-text" placeholder="Canada"></td>
                </tr>
            </table>
        </section>

        <!-- Dynamic taxonomies -->
        <section class="bei-section">
            <h2>Categories &amp; Taxonomies</h2>
            <p class="description">Define the taxonomies this site uses and the keyword groups that auto-assign each term. Structure is entirely per-site; nothing here is hardcoded in the plugin.</p>

            <div id="bei-taxonomy-list">
                <?php foreach ( $taxonomies as $tax_i => $tax ) : ?>
                    <div class="bei-taxonomy-block" data-tax-index="<?php echo (int) $tax_i; ?>">
                        <div class="bei-taxonomy-header">
                            <label>Taxonomy slug
                                <input type="text" class="bei-tax-slug" value="<?php echo esc_attr( $tax['slug'] ?? '' ); ?>" placeholder="event-category">
                            </label>
                            <label>Display label
                                <input type="text" class="bei-tax-label" value="<?php echo esc_attr( $tax['label'] ?? '' ); ?>" placeholder="Event Category">
                            </label>
                            <label>Default term (optional, applied when nothing matches)
                                <input type="text" class="bei-tax-default" value="<?php echo esc_attr( $tax['default_term'] ?? '' ); ?>" placeholder="General">
                            </label>
                            <button type="button" class="button bei-remove-taxonomy">Remove taxonomy</button>
                        </div>

                        <table class="widefat bei-group-table">
                            <thead><tr><th style="width:22%">Term name</th><th>Keywords</th><th style="width:40px"></th></tr></thead>
                            <tbody class="bei-group-rows">
                                <?php foreach ( (array) ( $tax['groups'] ?? [] ) as $group ) :
                                    $gkey = $group['key'] ?? '';
                                    ?>
                                    <tr class="bei-group-row" data-group-key="<?php echo esc_attr( $gkey ); ?>">
                                        <td><input type="text" class="bei-group-term" value="<?php echo esc_attr( $group['term'] ?? '' ); ?>" placeholder="Music"></td>
                                        <td><?php bei_admin_chip_field( $OPT . '[group_kw__' . $gkey . ']', $options[ $gkey ] ?? '' ); ?></td>
                                        <td><button type="button" class="button-link bei-remove-group" aria-label="Remove group">&times;</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button bei-add-group">Add category</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <p><button type="button" class="button button-secondary" id="bei-add-taxonomy">Add taxonomy</button></p>

            <input type="hidden" name="<?php echo esc_attr( $OPT ); ?>[taxonomies_json]" id="bei-taxonomies-json" value="">

            <!-- Template for a new, empty group row (cloned by JS) -->
            <template id="bei-group-row-template">
                <tr class="bei-group-row" data-group-key="">
                    <td><input type="text" class="bei-group-term" placeholder="Music"></td>
                    <td>
                        <div class="bei-chip-field">
                            <div class="chip-wrapper">
                                <div class="chips"></div>
                                <input type="text" class="chip-input" placeholder="Type keyword and press Enter">
                                <input type="hidden" class="chip-hidden" name="" value="">
                            </div>
                        </div>
                    </td>
                    <td><button type="button" class="button-link bei-remove-group" aria-label="Remove group">&times;</button></td>
                </tr>
            </template>

            <!-- Template for a new, empty taxonomy block (cloned by JS) -->
            <template id="bei-taxonomy-block-template">
                <div class="bei-taxonomy-block" data-tax-index="">
                    <div class="bei-taxonomy-header">
                        <label>Taxonomy slug<input type="text" class="bei-tax-slug" placeholder="event-category"></label>
                        <label>Display label<input type="text" class="bei-tax-label" placeholder="Event Category"></label>
                        <label>Default term (optional)<input type="text" class="bei-tax-default" placeholder="General"></label>
                        <button type="button" class="button bei-remove-taxonomy">Remove taxonomy</button>
                    </div>
                    <table class="widefat bei-group-table">
                        <thead><tr><th style="width:22%">Term name</th><th>Keywords</th><th style="width:40px"></th></tr></thead>
                        <tbody class="bei-group-rows"></tbody>
                    </table>
                    <button type="button" class="button bei-add-group">Add category</button>
                </div>
            </template>
        </section>

        <!-- Field mapping -->
        <section class="bei-section">
            <h2>JetEngine Field Mapping</h2>
            <p class="description">Map internal event data to this site's actual JetEngine meta keys. Leave a field blank to skip writing it. Check a field's meta key under Custom Fields in JetEngine's field group editor.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Date field type</th>
                    <td>
                        <select id="bei-date-mode" name="<?php echo esc_attr( $OPT ); ?>[fm_date_mode]">
                            <option value="split" <?php selected( $field_map['date_mode'], 'split' ); ?>>Separate start/end date + time fields</option>
                            <option value="je_advanced_date" <?php selected( $field_map['date_mode'], 'je_advanced_date' ); ?>>JetEngine "Advanced Date" field</option>
                        </select>
                    </td>
                </tr>
                <tr class="bei-fm-split">
                    <th scope="row">Start date meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_start_date_meta]" value="<?php echo esc_attr( $field_map['start_date_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr class="bei-fm-split">
                    <th scope="row">End date meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_end_date_meta]" value="<?php echo esc_attr( $field_map['end_date_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr class="bei-fm-je">
                    <th scope="row">Advanced Date meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_je_date_meta]" value="<?php echo esc_attr( $field_map['je_date_meta'] ); ?>" class="regular-text" placeholder="event-date"></td>
                </tr>
                <tr>
                    <th scope="row">Start time meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_start_time_meta]" value="<?php echo esc_attr( $field_map['start_time_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">End time meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_end_time_meta]" value="<?php echo esc_attr( $field_map['end_time_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Location meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_location_meta]" value="<?php echo esc_attr( $field_map['location_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Long description meta key <span class="bei-optional">(optional)</span></th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_description_long_meta]" value="<?php echo esc_attr( $field_map['description_long_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Short description meta key <span class="bei-optional">(optional)</span></th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_description_short_meta]" value="<?php echo esc_attr( $field_map['description_short_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">External URL meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_external_url_meta]" value="<?php echo esc_attr( $field_map['external_url_meta'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Source meta key</th>
                    <td><input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_source_meta]" value="<?php echo esc_attr( $field_map['source_meta'] ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h3>Static meta <span class="bei-optional">(optional, written on every create/update)</span></h3>
            <div id="bei-extra-meta-rows">
                <?php foreach ( (array) ( $field_map['extra_static_meta'] ?? [] ) as $extra ) : ?>
                    <p class="bei-extra-row">
                        <input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_extra_keys][]" value="<?php echo esc_attr( $extra['key'] ?? '' ); ?>" placeholder="meta key">
                        <input type="text" name="<?php echo esc_attr( $OPT ); ?>[fm_extra_values][]" value="<?php echo esc_attr( $extra['value'] ?? '' ); ?>" placeholder="value">
                        <button type="button" class="button-link bei-remove-extra">&times;</button>
                    </p>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="bei-add-extra-meta">Add static meta field</button>
        </section>

        <?php submit_button( 'Save Settings' ); ?>
    </form>

</div>
