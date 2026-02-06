<?php
/**
 * Register Custom Post Types and Taxonomies.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Tutorial post type.
 */
function qwe_register_post_types() {
    $labels = array(
        'name'                  => _x( 'Tutorials', 'Post type general name', 'qwe-developer-flavor' ),
        'singular_name'         => _x( 'Tutorial', 'Post type singular name', 'qwe-developer-flavor' ),
        'menu_name'             => _x( 'Tutorials', 'Admin Menu text', 'qwe-developer-flavor' ),
        'add_new'               => __( 'Add New', 'qwe-developer-flavor' ),
        'add_new_item'          => __( 'Add New Tutorial', 'qwe-developer-flavor' ),
        'new_item'              => __( 'New Tutorial', 'qwe-developer-flavor' ),
        'edit_item'             => __( 'Edit Tutorial', 'qwe-developer-flavor' ),
        'view_item'             => __( 'View Tutorial', 'qwe-developer-flavor' ),
        'all_items'             => __( 'All Tutorials', 'qwe-developer-flavor' ),
        'search_items'          => __( 'Search Tutorials', 'qwe-developer-flavor' ),
        'not_found'             => __( 'No tutorials found.', 'qwe-developer-flavor' ),
        'not_found_in_trash'    => __( 'No tutorials found in Trash.', 'qwe-developer-flavor' ),
        'archives'              => __( 'Tutorial Archives', 'qwe-developer-flavor' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'tutorial', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
    );

    register_post_type( 'tutorial', $args );
}
add_action( 'init', 'qwe_register_post_types' );

/**
 * Register Tutorial Category taxonomy.
 */
function qwe_register_taxonomies() {
    $labels = array(
        'name'              => _x( 'Tutorial Categories', 'taxonomy general name', 'qwe-developer-flavor' ),
        'singular_name'     => _x( 'Tutorial Category', 'taxonomy singular name', 'qwe-developer-flavor' ),
        'search_items'      => __( 'Search Categories', 'qwe-developer-flavor' ),
        'all_items'         => __( 'All Categories', 'qwe-developer-flavor' ),
        'parent_item'       => __( 'Parent Category', 'qwe-developer-flavor' ),
        'parent_item_colon' => __( 'Parent Category:', 'qwe-developer-flavor' ),
        'edit_item'         => __( 'Edit Category', 'qwe-developer-flavor' ),
        'update_item'       => __( 'Update Category', 'qwe-developer-flavor' ),
        'add_new_item'      => __( 'Add New Category', 'qwe-developer-flavor' ),
        'new_item_name'     => __( 'New Category Name', 'qwe-developer-flavor' ),
        'menu_name'         => __( 'Categories', 'qwe-developer-flavor' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'tutorial-category', 'with_front' => false ),
    );

    register_taxonomy( 'tutorial_category', array( 'tutorial' ), $args );
}
add_action( 'init', 'qwe_register_taxonomies' );

/**
 * Register tutorial meta box for difficulty and featured status.
 */
function qwe_add_tutorial_meta_boxes() {
    add_meta_box(
        'qwe_tutorial_options',
        __( 'Tutorial Options', 'qwe-developer-flavor' ),
        'qwe_tutorial_options_callback',
        'tutorial',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'qwe_add_tutorial_meta_boxes' );

/**
 * Tutorial options meta box callback.
 */
function qwe_tutorial_options_callback( $post ) {
    wp_nonce_field( 'qwe_tutorial_options', 'qwe_tutorial_options_nonce' );

    $difficulty = get_post_meta( $post->ID, '_qwe_difficulty', true );
    $featured   = get_post_meta( $post->ID, '_qwe_featured', true );
    ?>
    <p>
        <label for="qwe_difficulty"><strong><?php esc_html_e( 'Difficulty Level', 'qwe-developer-flavor' ); ?></strong></label><br>
        <select id="qwe_difficulty" name="qwe_difficulty" style="width:100%;margin-top:4px;">
            <option value="beginner" <?php selected( $difficulty, 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'qwe-developer-flavor' ); ?></option>
            <option value="intermediate" <?php selected( $difficulty, 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'qwe-developer-flavor' ); ?></option>
            <option value="advanced" <?php selected( $difficulty, 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'qwe-developer-flavor' ); ?></option>
        </select>
    </p>
    <p>
        <label>
            <input type="checkbox" name="qwe_featured" value="1" <?php checked( $featured, '1' ); ?>>
            <?php esc_html_e( 'Featured Tutorial (show on homepage)', 'qwe-developer-flavor' ); ?>
        </label>
    </p>
    <?php
}

/**
 * Save tutorial meta box data.
 */
function qwe_save_tutorial_meta( $post_id ) {
    if ( ! isset( $_POST['qwe_tutorial_options_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['qwe_tutorial_options_nonce'], 'qwe_tutorial_options' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['qwe_difficulty'] ) ) {
        $allowed = array( 'beginner', 'intermediate', 'advanced' );
        $difficulty = sanitize_text_field( $_POST['qwe_difficulty'] );
        if ( in_array( $difficulty, $allowed, true ) ) {
            update_post_meta( $post_id, '_qwe_difficulty', $difficulty );
        }
    }

    $featured = isset( $_POST['qwe_featured'] ) ? '1' : '0';
    update_post_meta( $post_id, '_qwe_featured', $featured );
}
add_action( 'save_post_tutorial', 'qwe_save_tutorial_meta' );

/**
 * Flush rewrite rules on theme activation.
 */
function qwe_rewrite_flush() {
    qwe_register_post_types();
    qwe_register_taxonomies();
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'qwe_rewrite_flush' );
