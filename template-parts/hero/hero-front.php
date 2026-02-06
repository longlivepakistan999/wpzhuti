<?php
/**
 * Hero section for the front page — University style.
 *
 * @package QWE_Developer_Flavor
 */
?>

<section class="hero-section">
    <div class="container">
        <div class="hero-layout">
            <div class="hero-content">
                <span class="hero-content__badge"><?php esc_html_e( 'QWE AI Academy', 'qwe-developer-flavor' ); ?></span>
                <h1><?php echo esc_html( get_theme_mod( 'qwe_hero_title', __( 'Learn AI for Free — From Zero to Pro', 'qwe-developer-flavor' ) ) ); ?></h1>
                <p><?php echo esc_html( get_theme_mod( 'qwe_hero_subtitle', __( 'Master ChatGPT, AI art, AI coding, and more with our step-by-step tutorials. Completely free, no registration required.', 'qwe-developer-flavor' ) ) ); ?></p>
                <div class="hero-actions">
                    <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-primary btn-lg">
                        <?php esc_html_e( 'Start Learning', 'qwe-developer-flavor' ); ?>
                    </a>
                    <a href="#learning-paths" class="btn btn-lg hero-btn-outline">
                        <?php esc_html_e( 'Browse Departments', 'qwe-developer-flavor' ); ?>
                    </a>
                </div>
            </div>

            <div class="hero-stats-card">
                <div class="hero-stat">
                    <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_tutorials', '200+' ) ); ?></span>
                    <span class="hero-stat__label"><?php esc_html_e( 'Free Tutorials', 'qwe-developer-flavor' ); ?></span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_categories', '7' ) ); ?></span>
                    <span class="hero-stat__label"><?php esc_html_e( 'AI Departments', 'qwe-developer-flavor' ); ?></span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_readers', '50K+' ) ); ?></span>
                    <span class="hero-stat__label"><?php esc_html_e( 'Monthly Readers', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
