<?php
/*
Plugin Name: What's My Views
Plugin URI: https://github.com/benahmetcelik/wmv
Description: This plugin shows the number of views of the post.
Version: 0.1
Author: Ahmet ÇELİK
Author URI: https://github.com/benahmetcelik
*/
// Ensure the `get_plugin_data` function is available
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Get plugin version
$plugin_data = get_plugin_data(__FILE__);
$plugin_version = $plugin_data['Version'];
// Add menu
add_action('admin_menu', 'wmv_add_menu');
function wmv_add_menu()
{

    add_menu_page(
        'What\'s My Views ?',
        'What\'s My Views ?',
        'administrator',
        'wmv',
        'wmv_admin_menu_hook',
        'dashicons-code-standards'
    );


}

function wmv_admin_menu_hook()
{
    $background_color = get_option('wmv_bg_color');
    if (!$background_color) {
        $background_color = '#f1f1f1';
    }

    $text_color = get_option('wmv_text_color');
    if (!$text_color) {
        $text_color = '#000';
    }
    // translators: %s is the number of views
    $text = __('%s Views', 'wmv_counter_text');


    ?>
    <div class="wrap">
        <h2>What's My Views</h2>
        <form method="post" action="options.php">
            <?php settings_fields('wmv_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Background Color</th>
                    <td><input type="color" name="wmv_bg_color" value="<?php echo esc_attr($background_color); ?>"/></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Text</th>
                    <td>
                        <input type="text" name="wmv_counter_text" value="<?php echo esc_attr($text); ?>"/>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Text Color</th>
                    <td>
                        <input type="color" name="wmv_text_color" value="<?php echo esc_attr($text_color); ?>"/>
                    </td>
                </tr>

            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php

}


add_action('admin_init', 'wmv_settings_init');
function wmv_settings_init()
{
    register_setting('wmv_settings_group', 'wmv_bg_color');
    register_setting('wmv_settings_group', 'wmv_counter_text');
    register_setting('wmv_settings_group', 'wmv_text_color');
}

add_action('admin_enqueue_scripts', 'wmv_admin_enqueue_scripts');

function wmv_admin_enqueue_scripts()
{
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
}



function enqueue_wmv_styles() {
    global $plugin_version;
    wp_enqueue_style('wmv-style', plugin_dir_url(__FILE__) . 'style.css', array(), $plugin_version);
}




function wmv_counter($content)
{
    $postView = 0;
    if (is_single() && !is_preview()) {
        $postId = get_the_ID();
        $postView = (int)get_post_meta($postId, 'wmv_count', true) + 1;
        update_post_meta(
            $postId,
            'wmv_count',
            $postView
        );

        remove_filter('the_content', 'wmv_counter');
    }

    return getViewHtml($content,$postView);
}


add_filter('the_content', 'wmv_counter');


add_action('manage_posts_custom_column', function ($column, $post_id) {
    if ($column === 'wmv_count') {
        $count = get_post_meta($post_id, 'wmv_count', true);
        $text = "<h4>".esc_attr($count)."</h4>";
    }
}, 10, 2);


add_filter('manage_posts_columns', function ($columns) {
    return array_merge($columns, ['wmv_count' => 'Views']);
});


add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget(
        'wmv_dashboard_widget',
        'Most Viewed Posts',
        'wmv_dashboard_widget'
    );
});

function getViewHtml($content,$view)
{
    $background_color = get_option('wmv_bg_color');
    if (!$background_color) {
        $background_color = '#f1f1f1';
    }
    $text_color = get_option('wmv_text_color');
    if (!$text_color) {
        $text_color = '#000';
    }
    // translators: %s is the number of views
    $text = __('%s Views', 'wmv_counter_text');
    $text = str_replace('%s', $view, $text);
    $template = wp_remote_get(__DIR__ . '/template.html');

    $template = str_replace('%s', $text_color, $template);
    $template = str_replace('%d', $background_color, $template);
    $template = str_replace('%f', $text, $template);
    $content .= $template;
    enqueue_wmv_styles();
    return $content;
}

function wmv_dashboard_widget()
{
    if ( false === ( $results = get_transient( 'wmv_top_posts' ) ) ) {
        $args = [
            'post_type' => 'post',
            'meta_key' => 'wmv_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'posts_per_page' => 5,
        ];
        $query = new WP_Query($args);
        $results = $query->get_posts();
        set_transient( 'wmv_top_posts', $results, HOUR_IN_SECONDS );
    }

    enqueue_wmv_styles();

    // Output the results
    echo '<ul>';
    foreach ($results as $result) {
        echo '<li>';
        echo '<a href="' . esc_url(get_permalink($result->ID)) . '">';
        echo esc_html($result->post_title);
        echo '</a>';
        echo ' - ';
        echo esc_html(get_post_meta($result->ID, 'wmv_count', true)).' Views';
        echo '</li>';
    }
    echo '</ul>';
}


function wmv_clear_transient() {
    delete_transient('wmv_top_posts');
}

if (!wp_next_scheduled('wmv_daily_event')) {
    wp_schedule_event(time(), 'daily', 'wmv_daily_event');
}

add_action('wmv_daily_event', 'wmv_clear_transient');

