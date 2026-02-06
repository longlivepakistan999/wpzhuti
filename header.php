<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php if ( ! has_site_icon() ) : ?>
        <link rel="icon" href="<?php echo esc_url( get_template_directory_uri() . '/assets/images/icon.svg' ); ?>" type="image/svg+xml">
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="screen-reader-text" href="#main-content">
    <?php esc_html_e( 'Skip to content', 'qwe-developer-flavor' ); ?>
</a>

<header class="site-header" role="banner">
    <div class="container">
        <div class="site-branding">
            <?php if ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else :
                // Use h1 only on front page; p on inner pages to avoid multiple h1 tags.
                $title_tag = ( is_front_page() && ! is_paged() ) ? 'h1' : 'p';
            ?>
                <<?php echo $title_tag; // phpcs:ignore ?> class="site-title">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <span class="site-logo-icon" aria-hidden="true">&#x1F916;</span>
                        <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
                    </a>
                </<?php echo $title_tag; // phpcs:ignore ?>>
            <?php endif; ?>
        </div>

        <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle menu', 'qwe-developer-flavor' ); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <nav class="main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Primary Menu', 'qwe-developer-flavor' ); ?>">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'menu_id'        => 'primary-menu',
                'container'      => false,
                'depth'          => 2,
                'fallback_cb'    => 'qwe_fallback_menu',
            ) );
            ?>
        </nav>

        <div class="header-actions">
            <form class="header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <label class="screen-reader-text" for="header-search-input">
                    <?php esc_html_e( 'Search', 'qwe-developer-flavor' ); ?>
                </label>
                <input type="search" id="header-search-input" class="header-search__input" placeholder="<?php esc_attr_e( 'Search tutorials...', 'qwe-developer-flavor' ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
            </form>
        </div>
    </div>
</header>

<main id="main-content" class="site-content">
