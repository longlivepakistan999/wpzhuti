<?php
/**
 * Theme hooks and filters.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add preconnect for Google Fonts.
 */
function qwe_resource_hints( $urls, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $urls[] = array(
            'href'        => 'https://fonts.googleapis.com',
            'crossorigin' => true,
        );
        $urls[] = array(
            'href'        => 'https://fonts.gstatic.com',
            'crossorigin' => true,
        );
    }
    return $urls;
}
add_filter( 'wp_resource_hints', 'qwe_resource_hints', 10, 2 );

/**
 * Customize the document title separator.
 */
function qwe_document_title_separator( $sep ) {
    return '|';
}
add_filter( 'document_title_separator', 'qwe_document_title_separator' );

/**
 * Remove unnecessary WordPress head elements for cleaner HTML.
 */
function qwe_cleanup_head() {
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'qwe_cleanup_head' );

/**
 * Add tutorial count to taxonomy archive titles.
 */
function qwe_archive_title( $title ) {
    if ( is_post_type_archive( 'tutorial' ) ) {
        $title = __( 'AI Tutorials', 'qwe-developer-flavor' );
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $title = single_term_title( '', false );
    }
    return $title;
}
add_filter( 'get_the_archive_title', 'qwe_archive_title' );

/**
 * Custom pingback header.
 */
function qwe_pingback_header() {
    if ( is_singular() && pings_open() ) {
        printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
    }
}
add_action( 'wp_head', 'qwe_pingback_header' );
