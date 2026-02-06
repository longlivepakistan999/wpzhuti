<?php
/**
 * Template Name: Privacy Policy Page
 *
 * @package QWE_Developer_Flavor
 */

get_header();
?>

<?php qwe_breadcrumbs(); ?>

<div class="section">
    <div class="container">
        <article class="page-content privacy-page">
            <header class="page-content__header">
                <h1><?php echo esc_html( get_the_title() ); ?></h1>
                <p class="privacy-page__updated">
                    <?php
                    printf(
                        /* translators: %s: date */
                        esc_html__( 'Last updated: %s', 'qwe-developer-flavor' ),
                        esc_html( get_the_modified_date() )
                    );
                    ?>
                </p>
            </header>

            <div class="page-content__body tutorial-content">
                <?php if ( have_posts() ) : the_post(); ?>
                    <?php the_content(); ?>
                <?php endif; ?>
            </div>
        </article>
    </div>
</div>

<?php
get_footer();
