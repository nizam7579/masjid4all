<?php
/*
Plugin Name: ENAIZI WA
Description: WhatsApp Cloud API + AI Assistant for Masjid4All
Version: 2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

define('NIZ_WA_VERSION', '2.0.0');
define('NIZ_WA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NIZ_WA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NIZ_WA_BOT_USER_ID', 2);

/*
|--------------------------------------------------------------------------
| Core Includes
|--------------------------------------------------------------------------
*/

require_once NIZ_WA_PLUGIN_DIR . 'includes/class-whatsapp.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-inbox.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-session.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-keywords.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-actions.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-faq.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-intent-ai.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-ai.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-intent.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-webhook.php';




/*
|--------------------------------------------------------------------------
| Admin Includes
|--------------------------------------------------------------------------
*/

require_once NIZ_WA_PLUGIN_DIR . 'includes/class-settings.php';
require_once NIZ_WA_PLUGIN_DIR . 'includes/class-faq-admin.php';

/*
|--------------------------------------------------------------------------
| Init Classes
|--------------------------------------------------------------------------
*/

new Niz_WA_Inbox();
new Niz_WA_Settings();
new Niz_WA_FAQ_Admin();

/*
|--------------------------------------------------------------------------
| Register Webhook Route
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {
    $webhook = new Niz_WA_Webhook();
    $webhook->register_routes();
});

/*
|--------------------------------------------------------------------------
| Wrapper/Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('niz_wa_send_password')) {
    function niz_wa_send_password($phone, $password) {
        return Niz_WA_WhatsApp::send_temp_password($phone, $password);
    }
}

function niz_wa_get_user_phone($user_id) {
    return get_user_meta($user_id, 'user_phone', true);
}

function niz_wa_is_window_active($user_id) {
    $last = get_user_meta($user_id, 'last_whatsapp_inbound_at', true);

    if (!$last) {
        return false;
    }

    return strtotime($last) >= strtotime('-24 hours', current_time('timestamp'));
} 



// Shortcodes

function niz_wa_inbox_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in.</p>';
    }

    $user_id = absint($_GET['user_id'] ?? 0);

    if (!$user_id) {
        return '<p>Invalid user.</p>';
    }

    wp_enqueue_script('niz-wa-inbox');
    wp_enqueue_style('niz-wa-inbox');

    wp_localize_script('niz-wa-inbox', 'niz_wa_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('niz_wa_inbox'),
        'user_id' => $user_id,
        'bot_user_id' => NIZ_WA_BOT_USER_ID
    ]);

    ob_start();
    ?>
    <div class="niz-wa-inbox-wrap">
        <div class="niz-wa-window-status" id="niz-wa-window-status">
            Checking WhatsApp window...
        </div>

        <div class="niz-wa-messages" id="niz-wa-messages">
            Loading messages...
        </div>

        <div class="niz-wa-composer">
            <textarea id="niz-wa-reply-text" placeholder="Type your message..."></textarea>
            <button id="niz-wa-send-reply">Send</button>
        </div>

        <div class="niz-wa-template-buttons">
            <button data-template="welcome">Welcome</button>
            <button data-template="membership_invite">Membership Invite</button>
            <button data-template="complete_registration">Complete Registration</button>
            <button data-template="reset_password">Reset Password</button>
            <button data-template="claim_business">Claim Business</button>
            <button data-template="advertise">Advertise</button>
            <button data-template="premium_upgrade">Premium Upgrade</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('niz_wa_inbox', 'niz_wa_inbox_shortcode');

/*
|--------------------------------------------------------------------------
| Activation Hook
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, 'niz_wa_install_tables');

function niz_wa_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    $session_table = $wpdb->prefix . 'niz_wa_sessions';
    $faq_table = $wpdb->prefix . 'niz_wa_faq';

    $sql_sessions = "CREATE TABLE $session_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        phone VARCHAR(30) NOT NULL,
        state VARCHAR(100) NULL,
        context_json LONGTEXT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY phone (phone)
    ) $charset;";

    $sql_faq = "CREATE TABLE $faq_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        category VARCHAR(100) NOT NULL,
        question TEXT NOT NULL,
        answer LONGTEXT NOT NULL,
        keywords TEXT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql_sessions);
    dbDelta($sql_faq);
}