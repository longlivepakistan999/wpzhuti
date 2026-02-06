<?php
/**
 * Single Post Template (for regular blog posts).
 *
 * @package QWE_Developer_Flavor
 */

get_header();

if ( have_posts() ) : the_post();
?>

<?php qwe_breadcrumbs(); ?>

<article <?php post_class( 'single-tutorial' ); ?> itemscope itemtype="https://schema.org/Article">

    <header class="tutorial-header">
        <div class="container">
            <?php
            $cat = get_the_category();
            if ( ! empty( $cat ) ) :
            ?>
                <div class="tutorial-categories">
                    <a href="<?php echo esc_url( get_category_link( $cat[0]->term_id ) ); ?>" class="tutorial-category-tag"><?php echo esc_html( $cat[0]->name ); ?></a>
                </div>
            <?php endif; ?>

            <h1 class="tutorial-header__title" itemprop="headline"><?php echo esc_html( get_the_title() ); ?></h1>

            <div class="tutorial-meta">
                <span class="tutorial-meta__date">
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" itemprop="datePublished"><?php echo esc_html( get_the_date() ); ?></time>
                </span>
                <span class="tutorial-meta__reading-time"><?php echo esc_html( qwe_reading_time() ); ?></span>
            </div>
        </div>
    </header>

    <div class="tutorial-layout">
        <div class="container">
            <div class="tutorial-layout__grid tutorial-layout__grid--no-toc">
                <div class="tutorial-content" itemprop="articleBody">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <figure class="tutorial-content__hero-image">
                            <?php the_post_thumbnail( 'qwe-tutorial-hero', array( 'itemprop' => 'image' ) ); ?>
                        </figure>
                    <?php endif; ?>

                    <?php the_content(); ?>
                </div>
            </div>
        </div>
    </div>

    <?php qwe_post_navigation(); ?>

</article>

<?php
endif;

get_footer();
