<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Re-registration tracking: promote a member's status from 'Prospect' to
 * 'Member' when they log in.
 *
 * The whole member base was reset to 'Prospect' for a pre-launch
 * re-registration campaign; we want to know who re-engages, so any
 * successful login promotes a 'Prospect' back to 'Member'.
 *
 * Hooked on 'set_auth_cookie' rather than 'wp_login' because several of this
 * site's login paths authenticate via wp_set_auth_cookie() directly and
 * never fire the wp_login action:
 *   - WhatsApp/phone AJAX login (enaizi-user/includes/ajax-handlers.php)
 *   - Google login (enaizi-identity Google provider)
 *   - post-register auto-login (enaizi-identity class-register.php)
 * Only the email login (wp_signon) fires wp_login. wp_set_auth_cookie() is
 * the one call all of them share, so 'set_auth_cookie' catches every method.
 *
 * Safety: only ever promotes Prospect -> Member; never touches Member /
 * Premium Member / Premium Lifetime. This updates a JetEngine CCT status
 * field only - it changes no WordPress role, capability, or auth token.
 */
add_action( 'set_auth_cookie', 'mfa_member_flip_prospect_on_login', 10, 4 );
function mfa_member_flip_prospect_on_login( $auth_cookie, $expire, $expiration, $user_id ) {
	if ( empty( $user_id ) ) {
		return;
	}
	mfa_member_promote_prospect( (int) $user_id );
}

/**
 * Promote the given user's member record from Prospect to Member, if it is
 * currently a Prospect. No-op otherwise. Safe to call repeatedly.
 */
function mfa_member_promote_prospect( $user_id ) {
	global $wpdb;
	$table  = $wpdb->prefix . 'jet_cct_member';
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$table}` WHERE user_id = %d LIMIT 1", $user_id ) );
	if ( 'Prospect' === $status ) {
		$wpdb->update(
			$table,
			array( 'status' => 'Member', 'cct_modified' => current_time( 'mysql' ) ),
			array( 'user_id' => $user_id )
		);
	}
}
