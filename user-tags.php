<?php
/*
Plugin Name: User Tags
Description: A plugin to categorize users with custom taxonomies.
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

    if (isset($_POST['user_tags'])) {
        $user_tags = array_map('intval', $_POST['user_tags']);
        wp_set_object_terms($user_id, $user_tags, 'user_tag', false);
    }
}
add_action('personal_options_update', 'ut_save_user_taxonomy');
add_action('edit_user_profile_update', 'ut_save_user_taxonomy');

// Add User Tags admin page under Users menu
function ut_add_user_tags_admin_page() {
    add_users_page(__('User Tags'), __('User Tags'), 'manage_options', 'edit-tags.php?taxonomy=user_tag');
}
add_action('admin_menu', 'ut_add_user_tags_admin_page');

// Add User Tag filter dropdown to the Users table
function ut_add_user_tag_filter() {
    $screen = get_current_screen();
    if ($screen->id != 'users') {
        return;
    }

    $terms = get_terms(array(
        'taxonomy' => 'user_tag',
        'hide_empty' => false,
    ));

    if (!empty($terms)) {
        echo '<select name="user_tag_filter" id="user_tag_filter">';
        echo '<option value="">' . __('Filter by User Tag') . '</option>';
        foreach ($terms as $term) {
            $selected = (isset($_GET['user_tag_filter']) && $_GET['user_tag_filter'] == $term->term_id) ? 'selected="selected"' : '';
            echo '<option value="' . $term->term_id . '" ' . $selected . '>' . $term->name . '</option>';
        }
        echo '</select>';
    }
}
add_action('restrict_manage_users', 'ut_add_user_tag_filter');

// Filter users by selected User Tag
function ut_filter_users_by_tag($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if (isset($_GET['user_tag_filter'])) {
        $tag_id = intval($_GET['user_tag_filter']);
        if ($tag_id) {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'user_tag',
                    'field' => 'term_id',
                    'terms' => $tag_id,
                ),
            ));
        }
    }
}
add_action('pre_get_users', 'ut_filter_users_by_tag');

// Enqueue Select2 for dynamic User Tag search
function ut_enqueue_select2() {
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
}
add_action('admin_enqueue_scripts', 'ut_enqueue_select2');

// AJAX handler for User Tag search
function ut_ajax_user_tag_search() {
    $search = sanitize_text_field($_GET['q']);
    $terms = get_terms(array(
        'taxonomy' => 'user_tag',
        'hide_empty' => false,
        'search' => $search,
    ));

    $results = array();
    foreach ($terms as $term) {
        $results[] = array(
            'id' => $term->term_id,
            'text' => $term->name,
        );
    }

    wp_send_json($results);
}
add_action('wp_ajax_user_tag_search', 'ut_ajax_user_tag_search');

// Add dynamic User Tag search to the user profile page
function ut_add_dynamic_user_tag_search() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#user_tags').select2({
                ajax: {
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            action: 'user_tag_search'
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'ut_add_dynamic_user_tag_search');
