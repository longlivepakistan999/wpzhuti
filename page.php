<?php
/**
 * Default Page Template.
 *
 * @package QWE_Developer_Flavor
 */

get_header();

if ( have_posts() ) : the_post();
?>

<?php qwe_breadcrumbs(); ?>

<div class="section">
    <div class="container">
        <article <?php post_class( 'page-content' ); ?>>
            <header class="page-content__header">
                <h1><?php the_title(); ?></h1>
            </header>

            <div class="page-content__body tutorial-content">
                <?php the_content(); ?>
            </div>
        </article>
    </div>
</div>

<?php
endif;

get_footer();
