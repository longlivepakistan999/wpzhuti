<?php
/**
 * Tutorial card component.
 *
 * @package QWE_Developer_Flavor
 */
?>

<article class="course-card" itemscope itemtype="https://schema.org/Article">
    <?php if ( has_post_thumbnail() ) : ?>
        <div class="course-card__thumbnail">
            <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
                <?php the_post_thumbnail( 'qwe-tutorial-card', array( 'itemprop' => 'image' ) ); ?>
            </a>
            <span class="course-card__badge <?php echo esc_attr( qwe_difficulty_class() ); ?>">
                <?php echo esc_html( qwe_difficulty_label() ); ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="course-card__body">
        <?php
        $categories = get_the_terms( get_the_ID(), 'tutorial_category' );
        if ( $categories && ! is_wp_error( $categories ) ) :
            $cat = $categories[0];
        ?>
            <div class="course-card__category">
                <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
            </div>
        <?php endif; ?>

        <h3 class="course-card__title" itemprop="headline">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <p class="course-card__excerpt" itemprop="description"><?php echo esc_html( get_the_excerpt() ); ?></p>

        <div class="course-card__meta">
            <span class="course-card__reading-time">
                <?php echo esc_html( qwe_reading_time() ); ?>
            </span>
            <span class="course-card__date">
                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" itemprop="datePublished">
                    <?php echo esc_html( get_the_date() ); ?>
                </time>
            </span>
        </div>
    </div>
</article>
