<?php
if (!defined('ABSPATH')) exit;

/**
 * [niz_user_logout]
 *
 * Rendered by site-header.php (mobile account row) and
 * member-header-footer.php (member shell). Not used in any post content -
 * both callers are PHP.
 *
 * 2026-08-21: this was a <button> whose click handler lived in
 * enaizi-user/assets/js/niz-user.js and POSTed to a wp_ajax_niz_user_logout
 * endpoint in that same legacy plugin. So mfa-core owned the markup while a
 * plugin being retired owned the behaviour, and deleting that plugin's ajax
 * file - the obvious cleanup - would have broken logout sitewide.
 *
 * It is now a plain link to wp_logout_url(), which:
 *   - removes the dependency on enaizi-user entirely (no JS, no endpoint),
 *   - is nonce-protected by WordPress itself, closing the CSRF-logout hole
 *     the old handler had (it verified no nonce at all), and
 *   - works with JavaScript disabled.
 *
 * The three shells style .niz-user-logout-btn themselves, scoped
 * (site-chrome-v1.css, member-shell-v1.css, admin-shell-v3.css), so the class
 * name is kept and no styling moves with this change.
 */
add_shortcode('niz_user_logout', 'niz_user_logout_shortcode');
function niz_user_logout_shortcode() {
    if (!is_user_logged_in()) {
        return '';
    }

    return '<a href="' . esc_url(wp_logout_url(home_url('/'))) . '"'
        . ' class="niz-user-logout-btn" rel="nofollow" aria-label="Log out">&#10148;]</a>';
}
