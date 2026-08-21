<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Only the logout handler remains here.
 *
 * Four unauthenticated (`nopriv`) endpoints were removed on 2026-08-21:
 * niz_user_check, niz_user_register, niz_user_login and niz_user_reset.
 * None verified a nonce. Their only caller was the phone login/register form
 * in assets/js/niz-user.js, which activates inside `.niz-user-box` - markup
 * emitted solely by the [niz_user_login] shortcode. That shortcode renders on
 * no published page on staging or production: every occurrence is a draft, a
 * revision, a kadence_element (the post type is no longer registered since
 * Kadence was deactivated), or a reusable block whose only parents are drafts.
 * So removing these breaks no user journey.
 *
 * The live login is [niz_login] (enaizi-identity, ajax action `niz_login`) -
 * a different action entirely, easily confused with `niz_user_login`.
 *
 * What they exposed while reachable:
 *
 *   niz_user_reset    The serious one. Called niz_user_reset_password(),
 *                     which runs wp_set_password() unconditionally on
 *                     whatever phone number is posted - no nonce, no auth,
 *                     no ownership check. Anyone could reset any member's
 *                     password, which also destroys all their sessions. And
 *                     because niz_wa_template() exists in no active plugin
 *                     (it lived in the retired enaizi_wa), the replacement
 *                     password was never delivered to anyone - it only hit
 *                     error_log. A repeatable account lockout against any
 *                     member, with no way back in. It had no caller at all:
 *                     the JS "forgot password" flow opens a wa.me link.
 *
 *   niz_user_check    Answered "is this phone number registered?" for any
 *                     anonymous caller - an enumeration oracle across
 *                     109,437 accounts, and exactly how somebody would find
 *                     targets for the above.
 *
 *   niz_user_register Created Member-status accounts plus a jet_cct_member
 *                     row from nothing but a phone number and a name. It was
 *                     also already broken: it requests a WhatsApp password
 *                     template via niz_wa_send_password(), which likewise no
 *                     longer exists, so the account's generated password was
 *                     never delivered either.
 *
 *   niz_user_login    Unauthenticated phone+password login calling
 *                     wp_set_auth_cookie(), with no throttling.
 *
 * The PHP functions behind them are deliberately NOT removed. niz_user_check()
 * in particular resolves every inbound WhatsApp number through mfa-core's
 * niz_wa_resolve_user_id() filter, and niz_user_register() is still referenced
 * by founding-member.php. Only the public HTTP entry points are gone.
 *
 * Logout stays: [niz_user_logout] (mfa-core/includes/widgets/user-logout.php)
 * renders the header logout button for every logged-in user, and its click
 * handler in niz-user.js posts to this action. Deleting this file wholesale
 * would break logout sitewide.
 */

add_action('wp_ajax_niz_user_logout', 'niz_user_ajax_logout');
function niz_user_ajax_logout() {
    wp_logout();
    wp_send_json_success();
}
