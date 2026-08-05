<?php
if (!defined('ABSPATH')) exit;

/**
 * [niz_user_logout]
 */
add_shortcode('niz_user_logout', 'niz_user_logout_shortcode');
function niz_user_logout_shortcode() {
    if (!is_user_logged_in()) {
        return '';
    }
    return '<button id="niz_user_logout_btn" class="niz-user-logout-btn">➜]</button>';
}