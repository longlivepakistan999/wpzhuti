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
            'inLanguage'  => 'en',
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
        );
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $logo_url ) {
                $org['logo'] = $logo_url;
            }
        }
        echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_singular( 'tutorial' ) || is_singular( 'post' ) ) {
        $post = get_post();

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
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink(),
            ),
            'isAccessibleForFree' => true,
            'inLanguage'          => 'en',
        );

        if ( 'tutorial' === get_post_type() ) {
            $categories = get_the_terms( $post->ID, 'tutorial_category' );
            $cat_name = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : 'AI';
            $data['articleSection'] = $cat_name;
        } else {
            $cat = get_the_category();
            if ( ! empty( $cat ) ) {
                $data['articleSection'] = $cat[0]->name;
            }
        }

        if ( has_post_thumbnail() ) {
            $data['image'] = get_the_post_thumbnail_url( $post->ID, 'qwe-tutorial-hero' );
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' ) ) {
        $data = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'CollectionPage',
            'name'       => is_tax() ? single_term_title( '', false ) : __( 'AI Tutorials', 'qwe-developer-flavor' ),
            'url'        => get_pagenum_link(),
            'inLanguage' => 'en',
        );

        if ( is_tax() ) {
            $term = get_queried_object();
            if ( $term->description ) {
                $data['description'] = $term->description;
            }
        }

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
        echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
        if ( has_post_thumbnail() ) {
            $img_id  = get_post_thumbnail_id();
            $img_url = get_the_post_thumbnail_url( null, 'qwe-tutorial-hero' );
            $img_meta = wp_get_attachment_metadata( $img_id );
            echo '<meta property="og:image" content="' . esc_url( $img_url ) . '">' . "\n";
            if ( $img_meta && isset( $img_meta['width'], $img_meta['height'] ) ) {
                echo '<meta property="og:image:width" content="' . esc_attr( $img_meta['width'] ) . '">' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $img_meta['height'] ) . '">' . "\n";
            }
        }
    } elseif ( is_front_page() ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( get_bloginfo( 'description' ) ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr__( 'AI Tutorials', 'qwe-developer-flavor' ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_post_type_archive_link( 'tutorial' ) ) . '">' . "\n";
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $term = get_queried_object();
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $term->name ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_term_link( $term ) ) . '">' . "\n";
        if ( $term->description ) {
            echo '<meta property="og:description" content="' . esc_attr( $term->description ) . '">' . "\n";
        }
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
    } elseif ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' ) ) {
        echo '<link rel="canonical" href="' . esc_url( get_pagenum_link() ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_canonical_url', 1 );

/**
 * Add noindex for search results and 404 pages.
 */
function qwe_robots_meta() {
    if ( is_search() || is_404() ) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_robots_meta', 1 );

/**
 * Add hreflang tag for English content.
 */
function qwe_hreflang_tag() {
    if ( is_singular() ) {
        $url = get_permalink();
    } elseif ( is_front_page() ) {
        $url = home_url( '/' );
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $url = get_post_type_archive_link( 'tutorial' );
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $url = get_term_link( get_queried_object() );
    } else {
        return;
    }

    if ( $url && ! is_wp_error( $url ) ) {
        echo '<link rel="alternate" hreflang="en" href="' . esc_url( $url ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_hreflang_tag', 1 );

/**
 * Generate breadcrumbs with proper Schema.org markup.
 */
function qwe_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }

    $items = array();

    $items[] = array(
        'url'  => home_url( '/' ),
        'name' => __( 'Home', 'qwe-developer-flavor' ),
    );

    if ( is_singular( 'tutorial' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'tutorial' ),
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
        $categories = get_the_terms( get_the_ID(), 'tutorial_category' );
        if ( $categories && ! is_wp_error( $categories ) ) {
            $cat = $categories[0];
            $items[] = array(
                'url'  => get_term_link( $cat ),
                'name' => $cat->name,
            );
        }
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $items[] = array(
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'tutorial' ),
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
        $items[] = array(
            'name' => single_term_title( '', false ),
        );
    } elseif ( is_singular( 'post' ) ) {
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_page() ) {
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_search() ) {
        $items[] = array(
            'name' => __( 'Search Results', 'qwe-developer-flavor' ),
        );
    } elseif ( is_404() ) {
        $items[] = array(
            'name' => __( 'Page Not Found', 'qwe-developer-flavor' ),
        );
    }

    if ( ! empty( $items ) ) {
        echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'qwe-developer-flavor' ) . '">';
        echo '<div class="container">';
        echo '<ol class="breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';
        foreach ( $items as $i => $item ) {
            $is_last = ( $i === count( $items ) - 1 );
            echo '<li class="breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if ( ! $is_last && isset( $item['url'] ) ) {
                echo '<a itemprop="item" href="' . esc_url( $item['url'] ) . '"><span itemprop="name">' . esc_html( $item['name'] ) . '</span></a>';
            } else {
                echo '<span aria-current="page" itemprop="name">' . esc_html( $item['name'] ) . '</span>';
            }
            echo '<meta itemprop="position" content="' . ( $i + 1 ) . '">';
            echo '</li>';
        }
        echo '</ol>';
        echo '</div>';
        echo '</nav>';
    }
}
