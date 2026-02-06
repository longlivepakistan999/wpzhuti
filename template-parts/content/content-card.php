<?php
/**
 * Blog post card component (used in index.php for regular posts).
 *
 * @package QWE_Developer_Flavor
 */
?>

<article <?php post_class( 'course-card' ); ?> itemscope itemtype="https://schema.org/Article">
    <?php if ( has_post_thumbnail() ) : ?>
        <div class="course-card__thumbnail">
            <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
                <?php the_post_thumbnail( 'qwe-tutorial-card', array( 'itemprop' => 'image' ) ); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="course-card__body">
        <?php
        $cat = get_the_category();
        if ( ! empty( $cat ) ) :
        ?>
            <div class="course-card__category">
                <a href="<?php echo esc_url( get_category_link( $cat[0]->term_id ) ); ?>"><?php echo esc_html( $cat[0]->name ); ?></a>
            </div>
        <?php endif; ?>

        <h3 class="course-card__title" itemprop="headline">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <p class="course-card__excerpt" itemprop="description"><?php echo esc_html( get_the_excerpt() ); ?></p>

        <div class="course-card__meta">
            <span class="course-card__reading-time"><?php echo esc_html( qwe_reading_time() ); ?></span>
            <span class="course-card__date">
                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" itemprop="datePublished">
                    <?php echo esc_html( get_the_date() ); ?>
                </time>
            </span>
        </div>
    </div>
</article>
