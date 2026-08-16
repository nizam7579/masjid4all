<?php
/**
 * Plugin Name:       Enaizi
 * Description:       Enaizi - Masjid4all Plugin
 * Author:            Nizam Mustapha
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('ABS_VERSION', '2.0.1');
define('ABS_NAME', plugin_basename(__FILE__));
define('ABS_DIR', __DIR__);
define('ABS_URL', plugin_dir_url(__FILE__));

// Dynamically require all PHP files in the plugin directory
foreach (glob(ABS_DIR . '/*.php') as $file) {
    if (basename($file) !== basename(__FILE__)) { // Avoid including the main plugin file
        require_once $file;
    }
}


/////////////////////////////
// CUSTOM FUNCTIONS        //
/////////////////////////////

// Extend login duration to 30 days (2592000 seconds)
add_filter('auth_cookie_expiration', 'extend_login_cookie_expiration', 99, 3);
function extend_login_cookie_expiration($length, $user_id, $remember) {
    return 30 * DAY_IN_SECONDS; // 30 days
}

// Disable Admin Bar for All Users
add_filter('show_admin_bar', '__return_false');



// Remove Unwanted Dashboard Widgets
add_action('wp_dashboard_setup', function () {
    $widgets = [
        'dashboard_plugins',
        'dashboard_primary',
        'dashboard_activity',
        'dashboard_right_now',
        'dashboard_secondary',
        'dashboard_quick_press',
        'dashboard_browser_nag',
        'dashboard_recent_drafts',
        'dashboard_incoming_links',
        'dashboard_recent_comments',
    ];
    foreach ($widgets as $widget) {
        remove_meta_box($widget, 'dashboard', 'normal');
        remove_meta_box($widget, 'dashboard', 'side');
    }
});