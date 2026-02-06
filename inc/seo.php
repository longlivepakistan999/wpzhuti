<?php
/**
 * SEO Enhancements.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output JSON-LD structured data.
 */
function qwe_output_structured_data() {
    if ( is_front_page() ) {
        $data = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url'         => home_url( '/' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ),
        );
        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

        $org = array(
            '@context' => 'https://schema.org',
            '@type'    => 'EducationalOrganization',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
            'logo'     => get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '',
        );
        echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_singular( 'tutorial' ) ) {
        $post = get_post();
        $categories = get_the_terms( $post->ID, 'tutorial_category' );
        $cat_name = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : 'AI';

        $data = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title(),
            'description'   => get_the_excerpt(),
            'datePublished' => get_the_date( 'c' ),
            'dateModified'  => get_the_modified_date( 'c' ),
            'author'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
            'publisher'     => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
            'articleSection' => $cat_name,
            'isAccessibleForFree' => true,
        );

        if ( has_post_thumbnail() ) {
            $data['image'] = get_the_post_thumbnail_url( $post->ID, 'qwe-tutorial-hero' );
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' ) ) {
        $data = array(
            '@context' => 'https://schema.org',
            '@type'    => 'CollectionPage',
            'name'     => is_tax() ? single_term_title( '', false ) : __( 'AI Tutorials', 'qwe-developer-flavor' ),
            'url'      => get_pagenum_link(),
        );
        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
add_action( 'wp_head', 'qwe_output_structured_data', 5 );

/**
 * Output Open Graph meta tags.
 */
function qwe_output_og_tags() {
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";

    if ( is_singular() ) {
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( get_the_excerpt() ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
        if ( has_post_thumbnail() ) {
            echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( null, 'qwe-tutorial-hero' ) ) . '">' . "\n";
        }
    } elseif ( is_front_page() ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( get_bloginfo( 'description' ) ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
add_action( 'wp_head', 'qwe_output_og_tags', 5 );

/**
 * Add meta description tag.
 */
function qwe_meta_description() {
    $description = '';

    if ( is_front_page() ) {
        $description = get_bloginfo( 'description' );
    } elseif ( is_singular() ) {
        $description = get_the_excerpt();
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $term = get_queried_object();
        $description = $term->description ? $term->description : sprintf(
            /* translators: %s: category name */
            __( 'Free %s tutorials - Learn AI at QWE AI Academy', 'qwe-developer-flavor' ),
            $term->name
        );
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $description = __( 'Free AI tutorials - Learn ChatGPT, AI art, AI coding, and more at QWE AI Academy', 'qwe-developer-flavor' );
    }

    if ( $description ) {
        echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_meta_description', 1 );

/**
 * Output canonical URL.
 */
function qwe_canonical_url() {
    if ( is_singular() ) {
        echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '">' . "\n";
    } elseif ( is_front_page() ) {
        echo '<link rel="canonical" href="' . esc_url( home_url( '/' ) ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_canonical_url', 1 );

/**
 * Generate breadcrumbs.
 */
function qwe_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }

    $items = array();
    $items[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'qwe-developer-flavor' ) . '</a>';

    if ( is_singular( 'tutorial' ) ) {
        $items[] = '<a href="' . esc_url( get_post_type_archive_link( 'tutorial' ) ) . '">' . esc_html__( 'Tutorials', 'qwe-developer-flavor' ) . '</a>';
        $categories = get_the_terms( get_the_ID(), 'tutorial_category' );
        if ( $categories && ! is_wp_error( $categories ) ) {
            $cat = $categories[0];
            $items[] = '<a href="' . esc_url( get_term_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
        }
        $items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $items[] = '<span aria-current="page">' . esc_html__( 'Tutorials', 'qwe-developer-flavor' ) . '</span>';
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $items[] = '<a href="' . esc_url( get_post_type_archive_link( 'tutorial' ) ) . '">' . esc_html__( 'Tutorials', 'qwe-developer-flavor' ) . '</a>';
        $items[] = '<span aria-current="page">' . esc_html( single_term_title( '', false ) ) . '</span>';
    } elseif ( is_singular( 'post' ) ) {
        $items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
    } elseif ( is_page() ) {
        $items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
    } elseif ( is_search() ) {
        $items[] = '<span aria-current="page">' . esc_html__( 'Search Results', 'qwe-developer-flavor' ) . '</span>';
    } elseif ( is_404() ) {
        $items[] = '<span aria-current="page">' . esc_html__( 'Page Not Found', 'qwe-developer-flavor' ) . '</span>';
    }

    if ( ! empty( $items ) ) {
        echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'qwe-developer-flavor' ) . '">';
        echo '<div class="container">';
        echo '<ol class="breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';
        foreach ( $items as $i => $item ) {
            echo '<li class="breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            echo $item;
            echo '<meta itemprop="position" content="' . ( $i + 1 ) . '">';
            echo '</li>';
        }
        echo '</ol>';
        echo '</div>';
        echo '</nav>';
    }
}
