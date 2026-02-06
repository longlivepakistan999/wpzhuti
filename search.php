<?php
/**
 * Search Results Template.
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<?php qwe_breadcrumbs(); ?>

<div class="section">
    <div class="container">
        <header class="section__header">
            <h1>
                <?php
                printf(
                    /* translators: %s: search query */
                    esc_html__( 'Search Results for: %s', 'qwe-developer-flavor' ),
                    '<span class="search-query">' . esc_html( get_search_query() ) . '</span>'
                );
                ?>
            </h1>
            <?php if ( $wp_query->found_posts ) : ?>
                <p>
                    <?php
                    printf(
                        /* translators: %d: number of results */
                        esc_html( _n( '%d result found', '%d results found', $wp_query->found_posts, 'qwe-developer-flavor' ) ),
                        $wp_query->found_posts
                    );
                    ?>
                </p>
            <?php endif; ?>
        </header>

        <?php if ( have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php
                    if ( 'tutorial' === get_post_type() ) {
                        get_template_part( 'template-parts/course/course', 'card' );
                    } else {
                        get_template_part( 'template-parts/content/content', 'card' );
                    }
                    ?>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination( array(
                'mid_size'           => 2,
                'prev_text'          => '&larr; ' . esc_html__( 'Previous', 'qwe-developer-flavor' ),
                'next_text'          => esc_html__( 'Next', 'qwe-developer-flavor' ) . ' &rarr;',
                'screen_reader_text' => esc_html__( 'Search results navigation', 'qwe-developer-flavor' ),
            ) ); ?>
        <?php else : ?>
            <?php get_template_part( 'template-parts/content/content', 'none' ); ?>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
