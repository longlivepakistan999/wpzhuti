<?php
/**
 * Hero section for the front page — Immersive AI Academy.
 *
 * @package QWE_Developer_Flavor
 */
?>

<section class="hero-section">
    <!-- Floating decoration elements -->
    <div class="hero-particles" aria-hidden="true">
        <div class="hero-particle hero-particle--1"></div>
        <div class="hero-particle hero-particle--2"></div>
        <div class="hero-particle hero-particle--3"></div>
        <div class="hero-particle hero-particle--4"></div>
        <div class="hero-particle hero-particle--5"></div>
    </div>

    <div class="container">
        <div class="hero-layout">
            <div class="hero-content">
                <span class="hero-content__badge">
                    <span class="hero-content__badge-dot" aria-hidden="true"></span>
                    <?php esc_html_e( 'QWE AI Academy', 'qwe-developer-flavor' ); ?>
                </span>
                <h1><?php echo esc_html( get_theme_mod( 'qwe_hero_title', __( 'Learn AI for Free — From Zero to Pro', 'qwe-developer-flavor' ) ) ); ?></h1>
                <p class="hero-content__desc"><?php echo esc_html( get_theme_mod( 'qwe_hero_subtitle', __( 'Master ChatGPT, AI art, AI coding, and more with our step-by-step tutorials. Completely free, no registration required.', 'qwe-developer-flavor' ) ) ); ?></p>
                <div class="hero-actions">
                    <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ); ?>" class="btn btn-primary btn-lg btn-glow">
                        <?php esc_html_e( 'Start Learning', 'qwe-developer-flavor' ); ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <a href="#learning-paths" class="btn btn-lg hero-btn-outline">
                        <?php esc_html_e( 'Browse Departments', 'qwe-developer-flavor' ); ?>
                    </a>
                </div>

                <!-- Compact trust indicators inline -->
                <div class="hero-trust">
                    <div class="hero-trust__item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e( '100% Free', 'qwe-developer-flavor' ); ?>
                    </div>
                    <div class="hero-trust__item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e( 'No Registration', 'qwe-developer-flavor' ); ?>
                    </div>
                    <div class="hero-trust__item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e( 'Always Updated', 'qwe-developer-flavor' ); ?>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-stats-card">
                    <div class="hero-stats-card__glow" aria-hidden="true"></div>
                    <div class="hero-stat">
                        <span class="hero-stat__number" data-count="200"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_tutorials', '200+' ) ); ?></span>
                        <span class="hero-stat__label"><?php esc_html_e( 'Free Tutorials', 'qwe-developer-flavor' ); ?></span>
                    </div>
                    <div class="hero-stat__divider" aria-hidden="true"></div>
                    <div class="hero-stat">
                        <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_categories', '7' ) ); ?></span>
                        <span class="hero-stat__label"><?php esc_html_e( 'AI Departments', 'qwe-developer-flavor' ); ?></span>
                    </div>
                    <div class="hero-stat__divider" aria-hidden="true"></div>
                    <div class="hero-stat">
                        <span class="hero-stat__number"><?php echo esc_html( get_theme_mod( 'qwe_hero_stats_readers', '50K+' ) ); ?></span>
                        <span class="hero-stat__label"><?php esc_html_e( 'Monthly Readers', 'qwe-developer-flavor' ); ?></span>
                    </div>
                </div>

                <!-- Floating feature cards around the stats -->
                <div class="hero-float-card hero-float-card--1" aria-hidden="true">
                    <span class="hero-float-card__icon">&#x1F916;</span>
                    <span><?php esc_html_e( 'ChatGPT', 'qwe-developer-flavor' ); ?></span>
                </div>
                <div class="hero-float-card hero-float-card--2" aria-hidden="true">
                    <span class="hero-float-card__icon">&#x1F3A8;</span>
                    <span><?php esc_html_e( 'AI Art', 'qwe-developer-flavor' ); ?></span>
                </div>
                <div class="hero-float-card hero-float-card--3" aria-hidden="true">
                    <span class="hero-float-card__icon">&#x1F4BB;</span>
                    <span><?php esc_html_e( 'AI Coding', 'qwe-developer-flavor' ); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
