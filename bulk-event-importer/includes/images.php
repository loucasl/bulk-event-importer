<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Image helpers for the importer.
 */

/**
 * Make a URL absolute against a base URL (protocol-relative, root-relative,
 * and path-relative all handled).
 */
function bei_make_absolute_url( $maybe_url, $base_url ) {
    $maybe_url = trim( (string) $maybe_url );
    if ( $maybe_url === '' ) {
        return '';
    }

    if ( preg_match( '#^https?://#i', $maybe_url ) ) {
        return $maybe_url;
    }

    $base   = wp_parse_url( $base_url );
    $scheme = $base['scheme'] ?? 'https';
    $host   = $base['host']   ?? '';

    if ( ! $host ) {
        return '';
    }

    if ( str_starts_with( $maybe_url, '//' ) ) {
        return $scheme . ':' . $maybe_url;
    }

    if ( str_starts_with( $maybe_url, '/' ) ) {
        return $scheme . '://' . $host . $maybe_url;
    }

    $base_path = $base['path'] ?? '/';
    $dir = rtrim( str_replace( '\\', '/', dirname( $base_path ) ), '/' );

    return $scheme . '://' . $host . $dir . '/' . ltrim( $maybe_url, '/' );
}

/**
 * Clean up an extracted image URL: fixes the "https:///path" triple-slash
 * bug some site templates produce, then resolves relative URLs.
 */
function bei_clean_extracted_image_url( $image_url, $page_url ) {
    $image_url = trim( (string) $image_url );
    if ( $image_url === '' ) {
        return '';
    }

    if ( preg_match( '#^(https?):///(.*)$#i', $image_url, $matches ) ) {
        $scheme = $matches[1];
        $path   = $matches[2];
        $host   = wp_parse_url( $page_url, PHP_URL_HOST );
        if ( $host ) {
            $image_url = $scheme . '://' . $host . '/' . $path;
        }
    }

    return bei_make_absolute_url( $image_url, $page_url );
}

/**
 * Extract an image URL from JSON-LD (very common on event pages).
 */
function bei_first_image_from_jsonld( $json ) {
    if ( is_string( $json ) ) {
        $json = trim( $json );
        if ( $json === '' ) {
            return '';
        }
        $json = json_decode( $json, true );
    }

    if ( ! is_array( $json ) ) {
        return '';
    }

    if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
        foreach ( $json['@graph'] as $node ) {
            $found = bei_first_image_from_jsonld( $node );
            if ( $found ) {
                return $found;
            }
        }
    }

    foreach ( [ 'image', 'thumbnailUrl', 'logo' ] as $k ) {
        if ( ! isset( $json[ $k ] ) ) {
            continue;
        }

        $v = $json[ $k ];

        if ( is_string( $v ) && $v !== '' ) {
            return $v;
        }

        if ( is_array( $v ) ) {
            if ( isset( $v['url'] ) && is_string( $v['url'] ) && $v['url'] !== '' ) {
                return $v['url'];
            }
            if ( isset( $v[0] ) ) {
                if ( is_string( $v[0] ) && $v[0] !== '' ) {
                    return $v[0];
                }
                if ( is_array( $v[0] ) && isset( $v[0]['url'] ) && is_string( $v[0]['url'] ) && $v[0]['url'] !== '' ) {
                    return $v[0]['url'];
                }
            }
        }
    }

    foreach ( $json as $v ) {
        if ( is_array( $v ) ) {
            $found = bei_first_image_from_jsonld( $v );
            if ( $found ) {
                return $found;
            }
        }
    }

    return '';
}

/**
 * Scrape an event page and try to find a representative image.
 * Returns a direct image URL or false. $debug, if provided as an array,
 * is populated with diagnostics so failures can be inspected from the admin.
 */
function bei_extract_image_from_url( $url, &$debug = null ) {

    $debug = is_array( $debug ) ? $debug : [];

    if ( empty( $url ) || ! str_starts_with( $url, 'http' ) ) {
        $debug['reason'] = 'invalid_url';
        return false;
    }

    $response = wp_remote_get( $url, [
        'timeout'     => 15,
        'redirection' => 5,
        'headers'     => [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer'         => 'https://www.google.com/',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        $debug['reason'] = 'wp_error';
        $debug['error']  = $response->get_error_message();
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $debug['http_code'] = $code;

    if ( $code < 200 || $code >= 300 ) {
        $debug['reason'] = 'non_2xx';
        return false;
    }

    $type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
    $debug['content_type'] = $type;

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) {
        $debug['reason'] = 'empty_body';
        return false;
    }

    $looks_like_html =
        ( stripos( $html, '<html' ) !== false ) ||
        ( stripos( $html, '<!doctype' ) !== false ) ||
        ( stripos( $html, '<head' ) !== false ) ||
        ( stripos( $html, '<meta' ) !== false ) ||
        ( stripos( $html, '<body' ) !== false );

    if ( $type && ! str_contains( $type, 'text/html' ) && ! $looks_like_html ) {
        $debug['reason'] = 'not_html';
        return false;
    }

    if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
        $debug['reason'] = 'dom_missing';
        return false;
    }

    libxml_use_internal_errors( true );

    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
    if ( ! $loaded ) {
        $debug['reason'] = 'loadhtml_failed';
        return false;
    }

    $xpath = new DOMXPath( $dom );

    $meta_queries = [
        [ "//meta[@property='og:image']", 'content' ],
        [ "//meta[@property='og:image:url']", 'content' ],
        [ "//meta[@name='twitter:image']", 'content' ],
        [ "//meta[@name='twitter:image:src']", 'content' ],
        [ "//meta[@itemprop='image']", 'content' ],
        [ "//link[@rel='image_src']", 'href' ],
    ];

    foreach ( $meta_queries as $mq ) {
        $nodes = $xpath->query( $mq[0] );
        if ( $nodes && $nodes->length ) {
            $val = trim( (string) $nodes->item( 0 )->getAttribute( $mq[1] ) );
            $abs = bei_clean_extracted_image_url( $val, $url );
            if ( $abs ) {
                $debug['found'] = 'meta';
                $debug['image_url'] = $abs;
                return esc_url_raw( $abs );
            }
        }
    }

    $jsonld_nodes = $xpath->query( "//script[@type='application/ld+json']" );
    if ( $jsonld_nodes && $jsonld_nodes->length ) {
        foreach ( $jsonld_nodes as $n ) {
            $raw = trim( (string) $n->textContent );
            $img = bei_first_image_from_jsonld( $raw );
            $abs = bei_clean_extracted_image_url( $img, $url );
            if ( $abs ) {
                $debug['found'] = 'jsonld';
                $debug['image_url'] = $abs;
                return esc_url_raw( $abs );
            }
        }
    }

    foreach ( $xpath->query( "//img" ) as $img ) {
        $src = $img->getAttribute( 'src' );
        if ( ! $src ) { $src = $img->getAttribute( 'data-src' ); }
        if ( ! $src ) { $src = $img->getAttribute( 'data-lazy-src' ); }
        if ( ! $src ) { $src = $img->getAttribute( 'data-original' ); }

        $src = trim( (string) $src );
        if ( ! $src || str_starts_with( $src, 'data:' ) ) {
            continue;
        }

        $abs = bei_clean_extracted_image_url( $src, $url );
        if ( $abs ) {
            $debug['found'] = 'img';
            $debug['image_url'] = $abs;
            return esc_url_raw( $abs );
        }
    }

    if ( preg_match_all( '/<img[^>]+(?:src|data-src|data-lazy-src)=[\'"]([^\'"]+)[\'"]/i', $html, $img_matches ) ) {
        foreach ( $img_matches[1] as $img_url ) {
            if ( ! preg_match( '/(icon|logo|avatar|pixel|1x1|header|footer)/i', $img_url ) ) {
                $abs = bei_clean_extracted_image_url( $img_url, $url );
                if ( $abs ) {
                    $debug['found'] = 'img_regex';
                    $debug['image_url'] = $abs;
                    return esc_url_raw( $abs );
                }
            }
        }
    }

    if ( preg_match( '/background-image\s*:\s*url\((["\']?)([^"\')]+)\1\)/i', $html, $m ) ) {
        $abs = bei_clean_extracted_image_url( $m[2], $url );
        if ( $abs ) {
            $debug['found'] = 'css';
            $debug['image_url'] = $abs;
            return esc_url_raw( $abs );
        }
    }

    if ( preg_match( '/https?:\/\/[^"\s<]+\.(?:png|jpe?g|webp)(?:\?[^"\s<]*)?/i', $html, $m ) ) {
        $debug['found'] = 'regex';
        $debug['image_url'] = $m[0];
        return esc_url_raw( $m[0] );
    }

    $debug['reason'] = 'no_image_found';
    return false;
}

/**
 * Sideload an image by binary signature (more reliable than trusting
 * Content-Type headers, which some hosts get wrong or omit entirely).
 * Retries once with sslverify off for misconfigured certificates.
 */
function bei_custom_sideload_image( $url, $post_id, $desc ) {

    $args = [
        'timeout'   => 25,
        'sslverify' => true,
        'headers'   => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        ],
    ];

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        $args['sslverify'] = false;
        $response = wp_remote_get( $url, $args );
    }

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return new WP_Error( 'empty_body', 'Empty response body' );
    }

    $ext = '';
    if ( str_starts_with( $body, "\x89PNG\r\n\x1a\n" ) ) {
        $ext = 'png';
    } elseif ( str_starts_with( $body, "\xff\xd8\xff" ) ) {
        $ext = 'jpg';
    } elseif ( str_starts_with( $body, 'GIF87a' ) || str_starts_with( $body, 'GIF89a' ) ) {
        $ext = 'gif';
    } elseif ( str_starts_with( $body, 'RIFF' ) && substr( $body, 8, 4 ) === 'WEBP' ) {
        $ext = 'webp';
    }

    if ( ! $ext ) {
        $type = wp_remote_retrieve_header( $response, 'content-type' );
        $map  = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        foreach ( $map as $ct => $mapped_ext ) {
            if ( str_contains( (string) $type, $ct ) ) {
                $ext = $mapped_ext;
                break;
            }
        }
    }

    if ( ! $ext ) {
        return new WP_Error( 'invalid_image', 'Response is not a valid image' );
    }

    $tmp_filename = wp_tempnam( 'bei_img_' );
    if ( ! $tmp_filename ) {
        return new WP_Error( 'temp_file_error', 'Could not create temporary file' );
    }

    $tmp_file_with_ext = $tmp_filename . '.' . $ext;
    if ( ! @file_put_contents( $tmp_file_with_ext, $body ) ) {
        @unlink( $tmp_filename );
        return new WP_Error( 'write_error', 'Could not write temporary file' );
    }
    @unlink( $tmp_filename );

    $file_array = [
        'name'     => sanitize_file_name( basename( strtok( $url, '?' ) ) ) ?: 'image.' . $ext,
        'tmp_name' => $tmp_file_with_ext,
    ];
    if ( ! str_ends_with( strtolower( $file_array['name'] ), '.' . $ext ) ) {
        $file_array['name'] .= '.' . $ext;
    }

    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/media.php';
    include_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );

    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $tmp_file_with_ext );
        return $attachment_id;
    }

    return $attachment_id;
}

/**
 * Attempt to import and assign a featured image for the event post.
 */
function bei_maybe_import_featured_image( $post_id, $event ) {

    $post_id = (int) $post_id;

    if ( $post_id <= 0 || has_post_thumbnail( $post_id ) || empty( $event['external_url'] ) ) {
        return;
    }

    $debug = [];
    $image_url = bei_extract_image_from_url( $event['external_url'], $debug );

    update_post_meta( $post_id, '_bei_image_scrape_url', esc_url_raw( $event['external_url'] ) );
    update_post_meta( $post_id, '_bei_image_debug', wp_json_encode( $debug ) );

    if ( ! $image_url ) {
        return;
    }

    $download_url = $image_url;
    $hash_url     = $image_url;

    $parsed = wp_parse_url( $image_url );
    if ( ! empty( $parsed['query'] ) ) {
        parse_str( $parsed['query'], $qs );
        $only_busters = ! array_diff_key( $qs, array_flip( [ 'ver', 'v', 't', 'cache', 'cb' ] ) );
        if ( $only_busters ) {
            $hash_url = strtok( $image_url, '?' );
        }
    }

    $hash = md5( $hash_url );

    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/media.php';
    include_once ABSPATH . 'wp-admin/includes/image.php';

    // 1) Existing attachment by hash.
    $existing_by_hash = new WP_Query( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [ 'key' => '_import_image_hash', 'value' => $hash ],
        ],
        'post_mime_type' => 'image',
    ] );

    if ( ! empty( $existing_by_hash->posts ) ) {
        set_post_thumbnail( $post_id, $existing_by_hash->posts[0] );
        return;
    }

    // 2) Fallback for older imports without hash meta.
    $filename  = wp_basename( parse_url( $image_url, PHP_URL_PATH ) );
    $file_slug = sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) );

    if ( ! empty( $file_slug ) ) {
        $existing_by_name = get_posts( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'name'           => $file_slug,
            'numberposts'    => 1,
            'fields'         => 'ids',
            'post_mime_type' => 'image',
        ] );

        if ( ! empty( $existing_by_name ) ) {
            update_post_meta( $existing_by_name[0], '_import_image_hash', $hash );
            set_post_thumbnail( $post_id, $existing_by_name[0] );
            return;
        }
    }

    // 3) Download. Try WordPress's own sideloader first, then our
    // binary-signature-based fallback for servers that respond oddly.
    $attachment_id = media_sideload_image( $download_url, $post_id, $event['title'] ?? '', 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        $attachment_id = bei_custom_sideload_image( $download_url, $post_id, $event['title'] ?? '' );
    }

    if ( is_wp_error( $attachment_id ) ) {
        update_post_meta( $post_id, '_bei_image_download_error', $attachment_id->get_error_message() );
        return;
    }

    update_post_meta( $attachment_id, '_import_image_hash', $hash );
    update_post_meta( $attachment_id, '_import_image_source_url', $download_url );

    set_post_thumbnail( $post_id, $attachment_id );
}
