<?php
/**
 * Front Page Template — AI University Style
 *
 * Sections:
 *  1. Hero — University Welcome Banner
 *  2. Trust Bar — Credibility Indicators
 *  3. Departments — AI Learning Categories
 *  4. Featured Courses — Highlighted Tutorials
 *  5. Why Us — University Advantages
 *  6. Latest — Recently Published
 *  7. CTA — Enrollment Banner
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<!-- 1. HERO -->
<?php get_template_part( 'template-parts/hero/hero', 'front' ); ?>

<!-- 2. TRUST BAR -->
<section class="trust-bar">
    <div class="container">
        <div class="trust-bar__grid">
            <div class="trust-bar__item">
                <span class="trust-bar__icon" aria-hidden="true">&#x1F393;</span>
                <div>
                    <strong><?php esc_html_e( '100% Free', 'qwe-developer-flavor' ); ?></strong>
                    <span><?php esc_html_e( 'No hidden fees', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
            <div class="trust-bar__item">
                <span class="trust-bar__icon" aria-hidden="true">&#x1F4DD;</span>
                <div>
                    <strong><?php esc_html_e( 'No Registration', 'qwe-developer-flavor' ); ?></strong>
                    <span><?php esc_html_e( 'Start learning instantly', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
            <div class="trust-bar__item">
                <span class="trust-bar__icon" aria-hidden="true">&#x1F504;</span>
                <div>
                    <strong><?php esc_html_e( 'Always Updated', 'qwe-developer-flavor' ); ?></strong>
                    <span><?php esc_html_e( 'Latest AI tools covered', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
            <div class="trust-bar__item">
                <span class="trust-bar__icon" aria-hidden="true">&#x1F310;</span>
                <div>
                    <strong><?php esc_html_e( 'Open Access', 'qwe-developer-flavor' ); ?></strong>
                    <span><?php esc_html_e( 'Learn from anywhere', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. DEPARTMENTS -->
<section class="section" id="learning-paths">
    <div class="container">
        <div class="section__header">
            <span class="section__badge"><?php esc_html_e( 'Departments', 'qwe-developer-flavor' ); ?></span>
            <h2><?php esc_html_e( 'Explore Our AI Departments', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Choose your field of study. Each department offers structured tutorials from beginner to advanced.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $categories = get_terms( array(
            'taxonomy'   => 'tutorial_category',
            'hide_empty' => true,
            'number'     => 8,
        ) );

        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
            <div class="dept-grid">
                <?php
                $dept_icons = array(
                    '&#x1F4AC;',
                    '&#x1F3A8;',
                    '&#x1F4BB;',
                    '&#x1F4CA;',
                    '&#x1F4DD;',
                    '&#x1F3AC;',
                    '&#x1F4BC;',
                    '&#x1F9E0;',
                );
                $i = 0;
                foreach ( $categories as $cat ) :
                    $icon = get_term_meta( $cat->term_id, '_qwe_category_icon', true );
                    if ( ! $icon ) {
                        $icon = isset( $dept_icons[ $i ] ) ? $dept_icons[ $i ] : '&#x1F4DA;';
                    }
                    ?>
                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="dept-card">
                        <div class="dept-card__icon"><?php echo wp_kses_post( $icon ); ?></div>
                        <div class="dept-card__body">
                            <h3 class="dept-card__title"><?php echo esc_html( $cat->name ); ?></h3>
                            <?php if ( $cat->description ) : ?>
                                <p class="dept-card__desc"><?php echo esc_html( wp_trim_words( $cat->description, 12 ) ); ?></p>
                            <?php endif; ?>
                            <span class="dept-card__count">
                                <?php
                                printf(
                                    esc_html( _n( '%d tutorial', '%d tutorials', $cat->count, 'qwe-developer-flavor' ) ),
                                    $cat->count
                                );
                                ?>
                            </span>
                        </div>
                        <span class="dept-card__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php
                $i++;
                endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- 4. FEATURED COURSES -->
<section class="section section--alt">
    <div class="container">
        <div class="section__header">
            <span class="section__badge"><?php esc_html_e( 'Featured Courses', 'qwe-developer-flavor' ); ?></span>
            <h2><?php esc_html_e( 'Most Popular Tutorials', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Hand-picked tutorials our readers love. Start your AI journey here.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $featured = new WP_Query( array(
            'post_type'      => 'tutorial',
            'posts_per_page' => 6,
            'meta_query'     => array(
                array(
                    'key'   => '_qwe_featured',
                    'value' => '1',
                ),
            ),
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

        <div class="section__footer">
            <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-secondary btn-lg">
                <?php esc_html_e( 'View All Tutorials', 'qwe-developer-flavor' ); ?> &rarr;
            </a>
        </div>
    </div>
</section>

<!-- 5. WHY US -->
<section class="section">
    <div class="container">
        <div class="section__header">
            <span class="section__badge"><?php esc_html_e( 'Why QWE', 'qwe-developer-flavor' ); ?></span>
            <h2><?php esc_html_e( 'Why Study at QWE AI Academy?', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'We make learning AI accessible, practical, and enjoyable for everyone.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <div class="advantages-grid">
            <div class="advantage-card">
                <div class="advantage-card__number">01</div>
                <h3><?php esc_html_e( 'Zero to Hero Curriculum', 'qwe-developer-flavor' ); ?></h3>
                <p><?php esc_html_e( 'Structured learning paths take you from complete beginner to confident AI user. Every tutorial builds on the last.', 'qwe-developer-flavor' ); ?></p>
            </div>
            <div class="advantage-card">
                <div class="advantage-card__number">02</div>
                <h3><?php esc_html_e( 'Hands-On Practice', 'qwe-developer-flavor' ); ?></h3>
                <p><?php esc_html_e( 'Every tutorial includes real examples and step-by-step instructions you can follow along. Learn by doing, not just reading.', 'qwe-developer-flavor' ); ?></p>
            </div>
            <div class="advantage-card">
                <div class="advantage-card__number">03</div>
                <h3><?php esc_html_e( 'Cutting-Edge Content', 'qwe-developer-flavor' ); ?></h3>
                <p><?php esc_html_e( 'AI evolves fast. Our tutorials cover the latest tools — ChatGPT, Claude, Midjourney, Stable Diffusion, Copilot, and more.', 'qwe-developer-flavor' ); ?></p>
            </div>
            <div class="advantage-card">
                <div class="advantage-card__number">04</div>
                <h3><?php esc_html_e( 'Difficulty Levels', 'qwe-developer-flavor' ); ?></h3>
                <p><?php esc_html_e( 'Every tutorial is marked with a clear difficulty level — Beginner, Intermediate, or Advanced — so you always know what to expect.', 'qwe-developer-flavor' ); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- 6. LATEST -->
<section class="section section--alt">
    <div class="container">
        <div class="section__header">
            <span class="section__badge"><?php esc_html_e( 'Latest', 'qwe-developer-flavor' ); ?></span>
            <h2><?php esc_html_e( 'Recently Published', 'qwe-developer-flavor' ); ?></h2>
            <p><?php esc_html_e( 'Fresh tutorials and articles from our editorial team.', 'qwe-developer-flavor' ); ?></p>
        </div>

        <?php
        $latest = new WP_Query( array(
            'post_type'      => array( 'tutorial', 'post' ),
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( $latest->have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( $latest->have_posts() ) : $latest->the_post(); ?>
                    <?php
                    if ( 'tutorial' === get_post_type() ) {
                        get_template_part( 'template-parts/course/course', 'card' );
                    } else {
                        get_template_part( 'template-parts/content/content', 'card' );
                    }
                    ?>
                <?php endwhile; ?>
            </div>
        <?php endif;
        wp_reset_postdata();
        ?>
    </div>
</section>

<!-- 7. CTA -->
<section class="section">
    <div class="container">
        <div class="cta-banner">
            <div class="cta-banner__content">
                <span class="cta-banner__badge"><?php esc_html_e( 'Open Enrollment', 'qwe-developer-flavor' ); ?></span>
                <h2><?php echo esc_html( get_theme_mod( 'qwe_cta_title', __( 'Start Your AI Education Today', 'qwe-developer-flavor' ) ) ); ?></h2>
                <p><?php echo esc_html( get_theme_mod( 'qwe_cta_text', __( 'All courses are completely free. No registration needed. Pick a department and start your first lesson now!', 'qwe-developer-flavor' ) ) ); ?></p>
                <div class="cta-banner__actions">
                    <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-accent btn-lg">
                        <?php esc_html_e( 'Browse All Tutorials', 'qwe-developer-flavor' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
