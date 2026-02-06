<?php
/**
 * Tutorial Archive Template
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<div class="section">
    <div class="container">
        <header class="section__header">
            <h1><?php esc_html_e( 'AI Tutorials', 'qwe-developer-flavor' ); ?></h1>
            <p><?php esc_html_e( 'Free tutorials to help you master AI tools and techniques.', 'qwe-developer-flavor' ); ?></p>
        </header>

        <!-- Category Filters -->
        <?php
        $categories = get_terms( array(
            'taxonomy'   => 'tutorial_category',
            'hide_empty' => true,
        ) );
        $current_cat = get_queried_object();
        ?>

        <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
            <div class="course-filters">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>"
                   class="filter-btn <?php echo is_post_type_archive( 'tutorial' ) ? 'active' : ''; ?>">
                    <?php esc_html_e( 'All', 'qwe-developer-flavor' ); ?>
                </a>
                <?php foreach ( $categories as $cat ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
                       class="filter-btn <?php echo ( is_tax( 'tutorial_category' ) && $current_cat->term_id === $cat->term_id ) ? 'active' : ''; ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php get_template_part( 'template-parts/course/course', 'card' ); ?>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination( array(
                'mid_size'           => 2,
                'prev_text'          => '&larr; ' . esc_html__( 'Previous', 'qwe-developer-flavor' ),
                'next_text'          => esc_html__( 'Next', 'qwe-developer-flavor' ) . ' &rarr;',
                'screen_reader_text' => esc_html__( 'Tutorials navigation', 'qwe-developer-flavor' ),
            ) ); ?>
        <?php else : ?>
            <?php get_template_part( 'template-parts/content/content', 'none' ); ?>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
