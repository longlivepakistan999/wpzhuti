</main><!-- .site-content -->

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <?php
        $has_friend_links = false;
        if ( is_front_page() ) {
            $has_friend_links = (bool) get_posts( array(
                'post_type'      => 'friend_link',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ) );
        }
        ?>
        <div class="footer-grid<?php echo $has_friend_links ? ' footer-grid--5col' : ''; ?>">
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
                } else {
                    // Fallback links when no footer menu is assigned.
                    echo '<ul>';
                    echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'qwe-developer-flavor' ) . '</a></li>';
                    $archive_link = get_post_type_archive_link( 'tutorial' );
                    if ( $archive_link ) {
                        echo '<li><a href="' . esc_url( $archive_link ) . '">' . esc_html__( 'All Tutorials', 'qwe-developer-flavor' ) . '</a></li>';
                    }
                    $footer_pages = get_pages( array( 'number' => 4, 'sort_column' => 'menu_order' ) );
                    if ( $footer_pages ) {
                        foreach ( $footer_pages as $fp ) {
                            echo '<li><a href="' . esc_url( get_permalink( $fp ) ) . '">' . esc_html( $fp->post_title ) . '</a></li>';
                        }
                    }
                    echo '</ul>';
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

            <?php if ( is_front_page() ) :
                $friend_links = get_posts( array(
                    'post_type'      => 'friend_link',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'meta_key'       => '_qwe_friend_order',
                    'orderby'        => 'meta_value_num',
                    'order'          => 'ASC',
                ) );
                if ( ! empty( $friend_links ) ) : ?>
                    <div class="footer-widget">
                        <h4><?php esc_html_e( 'Friend Links', 'qwe-developer-flavor' ); ?></h4>
                        <ul>
                            <?php foreach ( $friend_links as $link ) :
                                $url = get_post_meta( $link->ID, '_qwe_friend_url', true );
                                if ( $url ) : ?>
                                    <li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( $link->post_title ); ?></a></li>
                                <?php endif;
                            endforeach; ?>
                        </ul>
                    </div>
                <?php endif;
            endif; ?>
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
