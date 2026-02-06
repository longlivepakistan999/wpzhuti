<?php
/**
 * Template Name: About Page
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<?php qwe_breadcrumbs(); ?>

<div class="section">
    <div class="container">
        <header class="section__header">
            <h1><?php the_title(); ?></h1>
        </header>

        <!-- Mission -->
        <div class="about-content">
            <?php if ( have_posts() ) : the_post(); ?>
                <div class="tutorial-content">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Features -->
        <div class="about-features">
            <div class="about-features__grid">
                <div class="about-feature-card">
                    <div class="about-feature-card__icon">&#x1F4B0;</div>
                    <h3><?php esc_html_e( 'Completely Free', 'qwe-developer-flavor' ); ?></h3>
                    <p><?php esc_html_e( 'All tutorials are 100% free. No hidden charges, no premium tiers.', 'qwe-developer-flavor' ); ?></p>
                </div>
                <div class="about-feature-card">
                    <div class="about-feature-card__icon">&#x1F476;</div>
                    <h3><?php esc_html_e( 'Beginner Friendly', 'qwe-developer-flavor' ); ?></h3>
                    <p><?php esc_html_e( 'Step-by-step guides designed for absolute beginners. No prior experience needed.', 'qwe-developer-flavor' ); ?></p>
                </div>
                <div class="about-feature-card">
                    <div class="about-feature-card__icon">&#x1F504;</div>
                    <h3><?php esc_html_e( 'Always Updated', 'qwe-developer-flavor' ); ?></h3>
                    <p><?php esc_html_e( 'AI moves fast. Our content is regularly updated to reflect the latest tools and features.', 'qwe-developer-flavor' ); ?></p>
                </div>
                <div class="about-feature-card">
                    <div class="about-feature-card__icon">&#x1F3AF;</div>
                    <h3><?php esc_html_e( 'Practical Focus', 'qwe-developer-flavor' ); ?></h3>
                    <p><?php esc_html_e( 'Learn by doing. Every tutorial focuses on real-world applications you can use today.', 'qwe-developer-flavor' ); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
