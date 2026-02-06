</main><!-- .site-content -->

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <div class="site-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
                <p><?php echo esc_html( get_theme_mod( 'qwe_footer_about', __( 'QWE AI Academy - Your free resource for learning how to use AI tools effectively. From ChatGPT to Midjourney, master AI at your own pace.', 'qwe-developer-flavor' ) ) ); ?></p>
            </div>

            <div class="footer-widget">
                <h4><?php esc_html_e( 'Popular Topics', 'qwe-developer-flavor' ); ?></h4>
                <?php
                $categories = get_terms( array(
                    'taxonomy'   => 'tutorial_category',
                    'number'     => 6,
                    'hide_empty' => true,
                ) );
                if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
                    <ul>
                        <?php foreach ( $categories as $cat ) : ?>
                            <li><a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="footer-widget">
                <h4><?php esc_html_e( 'Quick Links', 'qwe-developer-flavor' ); ?></h4>
                <?php
                if ( has_nav_menu( 'footer' ) ) {
                    wp_nav_menu( array(
                        'theme_location' => 'footer',
                        'container'      => false,
                        'depth'          => 1,
                    ) );
                }
                ?>
            </div>

            <div class="footer-widget">
                <h4><?php esc_html_e( 'Contact', 'qwe-developer-flavor' ); ?></h4>
                <ul>
                    <?php if ( get_theme_mod( 'qwe_contact_email' ) ) : ?>
                        <li><a href="mailto:<?php echo esc_attr( get_theme_mod( 'qwe_contact_email' ) ); ?>"><?php echo esc_html( get_theme_mod( 'qwe_contact_email' ) ); ?></a></li>
                    <?php endif; ?>
                    <?php if ( get_theme_mod( 'qwe_social_twitter' ) ) : ?>
                        <li><a href="<?php echo esc_url( get_theme_mod( 'qwe_social_twitter' ) ); ?>" target="_blank" rel="noopener">Twitter / X</a></li>
                    <?php endif; ?>
                    <?php if ( get_theme_mod( 'qwe_social_github' ) ) : ?>
                        <li><a href="<?php echo esc_url( get_theme_mod( 'qwe_social_github' ) ); ?>" target="_blank" rel="noopener">GitHub</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'qwe-developer-flavor' ); ?></p>
            <p><?php esc_html_e( 'All tutorials are free and open to everyone.', 'qwe-developer-flavor' ); ?></p>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button class="back-to-top" id="back-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'qwe-developer-flavor' ); ?>">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
</button>

<?php wp_footer(); ?>
</body>
</html>
