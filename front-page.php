<?php
/**
 * Front Page Template
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<?php get_template_part( 'template-parts/hero/hero', 'front' ); ?>

<!-- Featured Tutorials Section -->
<section class="section section--alt">
    <div class="container">
        <div class="section__header">
            <h2><?php esc_html_e( 'Featured Tutorials', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Start your AI journey with our most popular free tutorials.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $featured = new WP_Query( array(
            'post_type'      => 'tutorial',
            'posts_per_page' => 6,
            'meta_key'       => '_qwe_featured',
            'meta_value'     => '1',
        ) );

        if ( $featured->have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
                    <?php get_template_part( 'template-parts/course/course', 'card' ); ?>
                <?php endwhile; ?>
            </div>
        <?php endif;
        wp_reset_postdata();
        ?>

        <div style="text-align: center; margin-top: var(--space-8);">
            <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-secondary">
                <?php esc_html_e( 'View All Tutorials', 'qwe-developer-flavor' ); ?>
            </a>
        </div>
    </div>
</section>

<!-- Learning Paths Section -->
<section class="section">
    <div class="container">
        <div class="section__header">
            <h2><?php esc_html_e( 'Learning Paths', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Choose a topic and follow our structured learning path from beginner to advanced.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $categories = get_terms( array(
            'taxonomy'   => 'tutorial_category',
            'hide_empty' => true,
            'number'     => 8,
        ) );

        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
            <div class="learning-paths">
                <?php foreach ( $categories as $cat ) :
                    $icon = get_term_meta( $cat->term_id, '_qwe_category_icon', true );
                    if ( ! $icon ) {
                        $icon = '&#x1F4DA;';
                    }
                    ?>
                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="path-card">
                        <div class="path-card__icon"><?php echo wp_kses_post( $icon ); ?></div>
                        <h3 class="path-card__title"><?php echo esc_html( $cat->name ); ?></h3>
                        <p class="path-card__count">
                            <?php
                            printf(
                                /* translators: %d: number of tutorials */
                                esc_html( _n( '%d tutorial', '%d tutorials', $cat->count, 'qwe-developer-flavor' ) ),
                                $cat->count
                            );
                            ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Latest Articles Section -->
<section class="section section--alt">
    <div class="container">
        <div class="section__header">
            <h2><?php esc_html_e( 'Latest Articles', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Stay up to date with the latest AI tips, news, and tutorials.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $latest = new WP_Query( array(
            'post_type'      => 'post',
            'posts_per_page' => 3,
        ) );

        if ( $latest->have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( $latest->have_posts() ) : $latest->the_post(); ?>
                    <?php get_template_part( 'template-parts/content/content', 'card' ); ?>
                <?php endwhile; ?>
            </div>
        <?php endif;
        wp_reset_postdata();
        ?>
    </div>
</section>

<!-- CTA Section -->
<section class="section">
    <div class="container">
        <div class="cta-banner">
            <div class="cta-banner__content">
                <h2><?php echo esc_html( get_theme_mod( 'qwe_cta_title', __( 'Start Learning AI Today', 'qwe-developer-flavor' ) ) ); ?></h2>
                <p><?php echo esc_html( get_theme_mod( 'qwe_cta_text', __( 'All our tutorials are completely free. No registration required. Just pick a topic and start learning!', 'qwe-developer-flavor' ) ) ); ?></p>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-primary btn-lg">
                    <?php esc_html_e( 'Browse Tutorials', 'qwe-developer-flavor' ); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
