<?php
/*
Plugin Name: User Tags
Description: A plugin to categorize users with custom taxonomies and filter based on custom fields.
Version: 1.0
Author: Dhaval Parikh
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Register the custom taxonomy for users
function ut_register_user_taxonomy() {
    $labels = array(
        'name' => _x('User Tags', 'taxonomy general name'),
        'singular_name' => _x('User Tag', 'taxonomy singular name'),
        'search_items' => __('Search User Tags'),
        'all_items' => __('All User Tags'),
        'edit_item' => __('Edit User Tag'),
        'update_item' => __('Update User Tag'),
        'add_new_item' => __('Add New User Tag'),
        'new_item_name' => __('New User Tag Name'),
        'menu_name' => __('User Tags'),
    );

    $args = array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'user-tag'),
    );

    register_taxonomy('user_tag', 'user', $args);
}
add_action('init', 'ut_register_user_taxonomy');

// Add User Tags to user profiles
function ut_add_user_taxonomy_to_profile($user) {
    if (!current_user_can('edit_user', $user->ID)) {
        return;
    }

    $terms = get_terms(array(
        'taxonomy' => 'user_tag',
        'hide_empty' => false,
    ));

    $user_tags = wp_get_object_terms($user->ID, 'user_tag', array('fields' => 'ids'));

    ?>
    <h3><?php _e('User Tags'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="user_tags"><?php _e('Select User Tags'); ?></label></th>
            <td>
                <select name="user_tags[]" id="user_tags" multiple="multiple">
                    <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term->term_id; ?>" <?php selected(in_array($term->term_id, $user_tags)); ?>><?php echo $term->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'ut_add_user_taxonomy_to_profile');
add_action('edit_user_profile', 'ut_add_user_taxonomy_to_profile');

// Save User Tags when user profile is updated
function ut_save_user_taxonomy($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
        return;
    }

    if (isset($_POST['user_tags'])) {
        $user_tags = array_map('intval', $_POST['user_tags']);
        wp_set_object_terms($user_id, $user_tags, 'user_tag', false);

        // Update custom field in user meta
        $primary_tag = !empty($user_tags) ? $user_tags[0] : ''; // Use the first tag as the primary tag
        update_user_meta($user_id, 'primary_user_tag', $primary_tag);
    }
}
add_action('personal_options_update', 'ut_save_user_taxonomy');
add_action('edit_user_profile_update', 'ut_save_user_taxonomy');

// Add User Tags admin page under Users menu
function ut_add_user_tags_admin_page() {
    add_users_page(
        __('User Tags'), // Page title
        __('User Tags'), // Menu title
        'manage_options', // Capability required
        'edit-tags.php?taxonomy=user_tag' // Menu slug
    );
}
add_action('admin_menu', 'ut_add_user_tags_admin_page');

// Add independent User Tag filter above the Users table
function ut_add_independent_user_tag_filter() {
    $screen = get_current_screen();
    if ($screen->id != 'users') {
        return;
    }

    $terms = get_terms(array(
        'taxonomy' => 'user_tag',
        'hide_empty' => false,
    ));

    if (!empty($terms)) {
        ?>
        <div class="alignleft actions">
            <select name="user_tag_filter" id="user_tag_filter">
                <option value=""><?php _e('Filter by User Tag'); ?></option>
                <?php foreach ($terms as $term): ?>
                    <option value="<?php echo $term->term_id; ?>"><?php echo $term->name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}
add_action('restrict_manage_users', 'ut_add_independent_user_tag_filter');

// Enqueue Select2 and custom scripts
function ut_enqueue_scripts() {
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');

    wp_enqueue_script('user-tags-ajax', plugin_dir_url(__FILE__) . 'user-tags-ajax.js', array('jquery'), '1.0', true);
    wp_localize_script('user-tags-ajax', 'userTagsAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('user_tags_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'ut_enqueue_scripts');

// AJAX handler to fetch users by custom field (primary_user_tag)
function ut_ajax_filter_users_by_custom_field() {
    check_ajax_referer('user_tags_nonce', 'nonce');

    if (!isset($_POST['tag_id'])) {
        wp_send_json_error('Invalid request');
    }

    $tag_id = intval($_POST['tag_id']);

    // Fetch users with the selected custom field value
    $users = get_users(array(
        'meta_key' => 'primary_user_tag',
        'meta_value' => $tag_id,
        'meta_compare' => '=',
    ));

    $user_list = array();
    foreach ($users as $user) {
        $user_list[] = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
            'posts' => count_user_posts($user->ID), // Get the number of posts by the user
        );
    }

    wp_send_json_success($user_list);
}
add_action('wp_ajax_filter_users_by_custom_field', 'ut_ajax_filter_users_by_custom_field');
