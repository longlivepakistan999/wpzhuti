<?php
/**
 * Template Name: Contact Page
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
            <p><?php esc_html_e( 'Have questions, suggestions, or want to contribute? Get in touch with us.', 'qwe-developer-flavor' ); ?></p>
        </header>

        <div class="contact-layout">
            <div class="contact-layout__form">
                <?php if ( have_posts() ) : the_post(); ?>
                    <div class="tutorial-content">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="contact-layout__info">
                <div class="contact-info-card">
                    <h3><?php esc_html_e( 'Other Ways to Reach Us', 'qwe-developer-flavor' ); ?></h3>

                    <?php if ( get_theme_mod( 'qwe_contact_email' ) ) : ?>
                        <div class="contact-info-item">
                            <strong><?php esc_html_e( 'Email', 'qwe-developer-flavor' ); ?></strong>
                            <a href="mailto:<?php echo esc_attr( get_theme_mod( 'qwe_contact_email' ) ); ?>">
                                <?php echo esc_html( get_theme_mod( 'qwe_contact_email' ) ); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="contact-info-item">
                        <strong><?php esc_html_e( 'Social Media', 'qwe-developer-flavor' ); ?></strong>
                        <div class="contact-social-links">
                            <?php if ( get_theme_mod( 'qwe_social_twitter' ) ) : ?>
                                <a href="<?php echo esc_url( get_theme_mod( 'qwe_social_twitter' ) ); ?>" target="_blank" rel="noopener">Twitter / X</a>
                            <?php endif; ?>
                            <?php if ( get_theme_mod( 'qwe_social_github' ) ) : ?>
                                <a href="<?php echo esc_url( get_theme_mod( 'qwe_social_github' ) ); ?>" target="_blank" rel="noopener">GitHub</a>
                            <?php endif; ?>
                            <?php if ( get_theme_mod( 'qwe_social_youtube' ) ) : ?>
                                <a href="<?php echo esc_url( get_theme_mod( 'qwe_social_youtube' ) ); ?>" target="_blank" rel="noopener">YouTube</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
