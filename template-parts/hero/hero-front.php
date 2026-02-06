<?php
/**
 * Hero section for the front page.
 *
 * @package QWE_Developer_Flavor
 */
?>

<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1><?php echo esc_html( get_theme_mod( 'qwe_hero_title', __( 'Learn AI for Free — From Zero to Pro', 'qwe-developer-flavor' ) ) ); ?></h1>
            <p><?php echo esc_html( get_theme_mod( 'qwe_hero_subtitle', __( 'Master ChatGPT, AI art, AI coding, and more with our step-by-step tutorials. Completely free, no registration required.', 'qwe-developer-flavor' ) ) ); ?></p>
            <div class="hero-actions">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-accent btn-lg">
                    <?php esc_html_e( 'Start Learning', 'qwe-developer-flavor' ); ?>
                </a>
                <a href="#learning-paths" class="btn btn-secondary btn-lg" style="border-color: rgba(255,255,255,0.5); color: #fff;">
                    <?php esc_html_e( 'Browse Topics', 'qwe-developer-flavor' ); ?>
                </a>
            </div>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_tutorials', '200+' ) ); ?></span>
                <span class="hero-stat__label"><?php esc_html_e( 'Free Tutorials', 'qwe-developer-flavor' ); ?></span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_categories', '7' ) ); ?></span>
                <span class="hero-stat__label"><?php esc_html_e( 'AI Topics', 'qwe-developer-flavor' ); ?></span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_readers', '50K+' ) ); ?></span>
                <span class="hero-stat__label"><?php esc_html_e( 'Monthly Readers', 'qwe-developer-flavor' ); ?></span>
            </div>
        </div>
    </div>
</section>
