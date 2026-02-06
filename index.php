<?php
/**
 * The main template file.
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<div class="section">
    <div class="container">
        <?php if ( is_home() && ! is_front_page() ) : ?>
            <header class="section__header">
                <h1><?php single_post_title(); ?></h1>
            </header>
        <?php endif; ?>

        <?php if ( have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php get_template_part( 'template-parts/content/content', 'card' ); ?>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination( array(
                'mid_size'           => 2,
                'prev_text'          => '&larr; ' . esc_html__( 'Previous', 'qwe-developer-flavor' ),
                'next_text'          => esc_html__( 'Next', 'qwe-developer-flavor' ) . ' &rarr;',
                'screen_reader_text' => esc_html__( 'Posts navigation', 'qwe-developer-flavor' ),
            ) ); ?>

        <?php else : ?>
            <?php get_template_part( 'template-parts/content/content', 'none' ); ?>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
