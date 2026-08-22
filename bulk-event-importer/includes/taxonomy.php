<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dynamic taxonomy system.
 *
 * Each site defines its own taxonomies and keyword groups from the settings
 * page (Settings > Importer Settings > Categories & Taxonomies). Nothing here
 * is hardcoded, so DAC's two-taxonomy scheme and GS's single-taxonomy scheme
 * (or any future site's scheme) live in the same codebase as configuration.
 *
 * Stored shape (option['taxonomies']):
 * [
 *   [
 *     'slug'         => 'event-category',
 *     'label'        => 'Event Category',
 *     'default_term' => 'General',   // optional, applied if nothing matched
 *     'groups'       => [
 *        [ 'key' => 'cat_music_keywords', 'term' => 'Music' ],
 *        ...
 *     ],
 *   ],
 *   ...
 * ]
 *
 * Each group's keyword CSV is stored flat at option[$group_key], exactly
 * like the legacy per-site plugins stored their keyword fields, so upgrading
 * an existing site does not require moving any keyword data.
 */
function bei_get_taxonomy_config() {
    $options = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    $taxonomies = $options['taxonomies'] ?? [];
    return is_array( $taxonomies ) ? $taxonomies : [];
}

/**
 * Given blended text (title + description), work out which term(s) apply
 * for every configured taxonomy and assign them to the post.
 */
function bei_apply_dynamic_taxonomies( $post_id, $blended_text ) {
    $options    = get_option( Bulk_Event_Importer::OPTION_SETTINGS, [] );
    $taxonomies = bei_get_taxonomy_config();

    foreach ( $taxonomies as $tax ) {
        $slug = sanitize_key( $tax['slug'] ?? '' );
        if ( $slug === '' || ! taxonomy_exists( $slug ) ) {
            continue;
        }

        $terms = [];
        foreach ( (array) ( $tax['groups'] ?? [] ) as $group ) {
            $key  = $group['key']  ?? '';
            $term = $group['term'] ?? '';
            if ( $key === '' || $term === '' ) {
                continue;
            }
            if ( bei_matches_keywords( $blended_text, $options[ $key ] ?? '' ) ) {
                $terms[] = $term;
            }
        }

        if ( empty( $terms ) && ! empty( $tax['default_term'] ) ) {
            $terms[] = $tax['default_term'];
        }

        if ( ! empty( $terms ) ) {
            wp_set_object_terms( $post_id, array_values( array_unique( $terms ) ), $slug, false );
        }
    }
}
