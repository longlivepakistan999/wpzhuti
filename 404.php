<?php
/**
 * 404 Page Template.
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<div class="section">
    <div class="container">
        <div class="error-404">
            <div class="error-404__icon" aria-hidden="true">404</div>
            <h1><?php esc_html_e( 'Page Not Found', 'qwe-developer-flavor' ); ?></h1>
            <p><?php esc_html_e( 'The page you are looking for does not exist or has been moved. Try searching for what you need.', 'qwe-developer-flavor' ); ?></p>

            <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <input type="search" class="search-form__input" placeholder="<?php esc_attr_e( 'Search tutorials...', 'qwe-developer-flavor' ); ?>" name="s">
                <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Search', 'qwe-developer-flavor' ); ?></button>
            </form>

            <div class="error-404__links">
                <h3><?php esc_html_e( 'Popular Tutorials', 'qwe-developer-flavor' ); ?></h3>
                <?php
                $popular = new WP_Query( array(
                    'post_type'      => 'tutorial',
                    'posts_per_page' => 5,
                    'meta_key'       => '_qwe_featured',
                    'meta_value'     => '1',
                ) );

                if ( $popular->have_posts() ) : ?>
                    <ul>
                        <?php while ( $popular->have_posts() ) : $popular->the_post(); ?>
                            <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif;
                wp_reset_postdata();
                ?>

                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-secondary" style="margin-top: var(--space-6);">
                    <?php esc_html_e( 'Back to Homepage', 'qwe-developer-flavor' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
