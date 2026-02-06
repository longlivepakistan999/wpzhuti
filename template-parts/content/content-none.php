<?php
/**
 * Template part for displaying a message when no content is found.
 *
 * @package QWE_Developer_Flavor
 */
?>

<section class="no-results">
    <div class="no-results__content">
        <h2><?php esc_html_e( 'Nothing found', 'qwe-developer-flavor' ); ?></h2>

        <?php if ( is_search() ) : ?>
            <p><?php esc_html_e( 'Sorry, no results matched your search. Please try different keywords.', 'qwe-developer-flavor' ); ?></p>
        <?php else : ?>
            <p><?php esc_html_e( 'No content available yet. Please check back later!', 'qwe-developer-flavor' ); ?></p>
        <?php endif; ?>

        <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" class="search-form__input" placeholder="<?php esc_attr_e( 'Search tutorials...', 'qwe-developer-flavor' ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
            <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Search', 'qwe-developer-flavor' ); ?></button>
        </form>
    </div>
</section>
