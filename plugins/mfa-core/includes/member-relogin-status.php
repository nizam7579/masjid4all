<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What happens the first time someone logs in.
 *
 * Everyone starts a prospect - imported contacts, and accounts created the
 * first time an unknown number messages Sofia. Logging in is the moment they
 * demonstrably own the account, so that is where the prospect becomes a
 * member (decision 2026-08-21).
 *
 * Hooked on 'set_auth_cookie' rather than 'wp_login' because most of this
 * site's login paths authenticate via wp_set_auth_cookie() directly and never
 * fire the wp_login action:
 *   - WhatsApp/phone AJAX login (enaizi-user/includes/ajax-handlers.php)
 *   - Google login (enaizi-identity Google provider)
 *   - post-register auto-login (enaizi-identity class-register.php)
 *   - the WhatsApp magic link (niz_wa_magic_login_handler())
 * Only the email login (wp_signon) fires wp_login. wp_set_auth_cookie() is
 * the one call all of them share, so 'set_auth_cookie' catches every method.
 *
 * AUTH-SENSITIVE, but only in where it runs, not in what it does: this
 * changes no WordPress role, capability, password or auth token. It sets a
 * member status and fills two blank profile fields.
 */
add_action( 'set_auth_cookie', 'mfa_member_flip_prospect_on_login', 10, 4 );
function mfa_member_flip_prospect_on_login( $auth_cookie, $expire, $expiration, $user_id ) {
	if ( empty( $user_id ) ) {
		return;
	}

	mfa_member_on_login( (int) $user_id );
}

/**
 * True when this account has not yet been activated as a member.
 *
 * Missing and empty both count as "prospect", and the test is
 * case-insensitive. Both matter against real data: 74,811 accounts carry no
 * user_status meta at all, and the 34,597 that do carry it spell it
 * 'Prospect' with a capital P. A strict === 'prospect' test would have
 * promoted neither group - that is, nobody.
 *
 * The inverse of the rule stated in CLAUDE.md: a status GATE must demand an
 * explicit 'prospect' so a missing value can't lock somebody out. This is a
 * promotion, not a gate, so a missing value is treated as promotable for the
 * same reason - it must not strand them.
 */
function mfa_member_is_unactivated( $user_id ) {
	$status = strtolower( trim( (string) get_user_meta( (int) $user_id, 'user_status', true ) ) );

	return ( '' === $status || 'prospect' === $status );
}

/**
 * Run the standard registration for a prospect who just logged in, and keep
 * country current for everyone else.
 *
 * Promotion goes through niz_user_complete_registration() rather than setting
 * a status directly, so a member who arrives this way is indistinguishable
 * from one who registered on the web: same jet_cct_member row, same Welcome
 * Bonus, same referrer award, same user_registered reset to the activation
 * moment, same mfa_user_activated hook. That is the whole point of having a
 * single chokepoint - a second way to become a member is how the two paths
 * drifted apart last time.
 */
function mfa_member_on_login( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}

	// Re-entry guard. This runs inside wp_set_auth_cookie(), and the
	// mfa_user_activated hook below is open to listeners - one of them
	// setting an auth cookie would otherwise recurse.
	static $running = array();
	if ( isset( $running[ $user_id ] ) ) {
		return;
	}
	$running[ $user_id ] = true;

	if ( mfa_member_is_unactivated( $user_id ) && function_exists( 'niz_user_complete_registration' ) ) {
		// route 'login' distinguishes these from web/google/whatsapp signups
		// on the /admin/ dashboard: they did not fill in a registration form,
		// they proved ownership of an account we already held.
		niz_user_complete_registration( $user_id, array( 'route' => 'login' ) );
	} else {
		// Already a member. Still repair a CCT row left at Prospect or blank -
		// the two status stores drifted apart for years and this is the cheap
		// place to reconcile one.
		mfa_member_promote_prospect( $user_id );
	}

	// Every login, not just the first: the country cookie is written by the
	// geolocation widget and may not have existed yet at activation time (a
	// WhatsApp magic-link arrival often has no country cookie on the very
	// first request). Only ever fills a blank.
	if ( function_exists( 'mfa_member_backfill_country' ) ) {
		mfa_member_backfill_country( $user_id );
	}

	unset( $running[ $user_id ] );
}

/**
 * Promote the given user's member record from Prospect to Member, if it is
 * currently a Prospect. No-op otherwise. Safe to call repeatedly.
 *
 * CCT-only, and now a fallback rather than the main path - a real promotion
 * goes through mfa_member_on_login() so both status stores and the whole
 * registration side-effect set stay in step.
 */
function mfa_member_promote_prospect( $user_id ) {
	global $wpdb;
	$table  = $wpdb->prefix . 'jet_cct_member';
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$table}` WHERE user_id = %d LIMIT 1", $user_id ) );
	// '' is treated as promotable alongside 'Prospect': rows created through
	// niz_user_member_cct() before the fix in identity-registration.php carry
	// no status at all, and without this they would stay invisible to every
	// status filter forever. Still never touches a row with a real status.
	if ( 'Prospect' === $status || '' === (string) $status ) {
		$wpdb->update(
			$table,
			array( 'status' => 'Member', 'cct_modified' => current_time( 'mysql' ) ),
			array( 'user_id' => $user_id )
		);
	}
}
