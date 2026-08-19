<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single shared "finish registering this user" step, called identically
 * from all three entry points (2026-08-08 decision): email/password
 * register (enaizi-identity Niz_Register::register()), Google sign-up
 * (enaizi-identity Niz_Google_Provider::handle_callback()), and the
 * WhatsApp REGISTER reply (niz_wa_action_register() in
 * niz-wa-integration.php). Before this, only the email/password path
 * awarded the Welcome Bonus or created the jet_cct_member row - Google
 * sign-ups silently got neither.
 */

/**
 * @param int   $user_id
 * @param array $args { 'name' => string, 'email' => string (optional) }
 * @return true|WP_Error
 */
function niz_user_complete_registration( $user_id, $args = array() ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return new WP_Error( 'invalid_user', 'Invalid user ID.' );
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'user_not_found', 'User not found.' );
	}

	// Idempotent by design (niz_user_member_cct()/mfa_award_points() are
	// already safe to call twice), but skip re-running once a user is
	// already a member so a stray re-call (e.g. a retried WhatsApp webhook)
	// can't clobber a profile they've since edited themselves.
	if ( 'member' === get_user_meta( $user_id, 'user_status', true ) ) {
		return true;
	}

	$name  = ! empty( $args['name'] ) ? sanitize_text_field( $args['name'] ) : $user->display_name;
	$email = ! empty( $args['email'] ) ? sanitize_email( $args['email'] ) : $user->user_email;

	if ( $name && $name !== $user->display_name ) {
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => $name,
		) );
	}

	if ( function_exists( 'niz_user_member_cct' ) ) {
		// Creates and links the jet_cct_member row if one doesn't exist yet
		// (reads country/referrer/partner from cookies at creation time).
		$member = niz_user_member_cct( $user_id );
		niz_user_award_referrer_points( $user_id, $name, $member );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'name', $name );
		if ( $email ) {
			niz_user_update_field( $user_id, 'email', $email );
		}

		// Mark them a Member on the CCT row too. niz_user_member_cct()
		// inserts without a status, so anyone arriving through this shared
		// step - WhatsApp, email/password or Google - previously landed with
		// an empty status, while the older niz_sync_cct_member_record() path
		// set 'Member'. Empty is worse than wrong here: /admin/member/'s
		// status filter matches exact values, so those rows dropped out of
		// every filtered view, and mfa_member_promote_prospect() only ever
		// promoted 'Prospect', never '' - so they could not self-correct.
		// This also promotes an existing Prospect row when a WhatsApp contact
		// finally registers, which is exactly the transition being tracked.
		niz_user_update_field( $user_id, 'status', 'Member' );
	}

	// The activation moment is the only registration date that carries any
	// meaning. A prospect row may have been created weeks earlier by a
	// WhatsApp message, or carry one of the synthetic dates the imports
	// wrote in bulk. Resetting it here - the single chokepoint every route
	// (WhatsApp, Google, web form) passes through - is what makes
	// "members by registration date" a real signal instead of noise.
	//
	// GMT, not current_time('mysql'): wp_insert_user() writes user_registered
	// in GMT, and the site runs at UTC+8, so local time would place every
	// new member eight hours in the future.
	//
	// The original is preserved in usermeta rather than discarded - it is
	// still the record of when we first heard from them, which the WhatsApp
	// follow-up flows may want.
	global $wpdb;

	if ( '' === get_user_meta( $user_id, 'mfa_original_registered', true ) ) {
		update_user_meta( $user_id, 'mfa_original_registered', $user->user_registered );
	}

	$wpdb->update(
		$wpdb->users,
		array( 'user_registered' => current_time( 'mysql', true ) ),
		array( 'ID' => $user_id ),
		array( '%s' ),
		array( '%d' )
	);
	clean_user_cache( $user_id );

	// How they arrived, recorded once. The dashboard splits new members by
	// this, and it is what lets the follow-up flows differ by starting point.
	$route = ! empty( $args['route'] ) ? sanitize_key( $args['route'] ) : 'unknown';
	update_user_meta( $user_id, 'mfa_registration_route', $route );

	update_user_meta( $user_id, 'user_status', 'member' );

	if ( function_exists( 'mfa_award_points' ) ) {
		mfa_award_points( $user_id, 'Welcome Bonus', 50 );
	}

	return true;
}

/**
 * Awards the referrer Barakah points when someone they referred completes
 * registration (2026-08-09 - start of the mosque-community growth loop:
 * referral capture itself already worked sitewide via the affiliateid
 * cookie set in enaizi-mfa's niz_mfa_location_init(), read here off the
 * jet_cct_member row niz_user_member_cct() just created/fetched - only the
 * reward side was missing). Commission/payout on paid conversions is a
 * separate, later phase once Founding Member + a payment gateway exist -
 * not built here.
 *
 * @param int        $new_user_id The person who just finished registering.
 * @param string     $new_user_name
 * @param array|null $member      Return value of niz_user_member_cct().
 */
function niz_user_award_referrer_points( $new_user_id, $new_user_name, $member ) {
	if ( ! is_array( $member ) || empty( $member['referrer_id'] ) ) {
		return;
	}

	$referrer_id = (int) $member['referrer_id'];

	// 14270 is the hardcoded "no real referrer" sentinel used across the
	// codebase (identity-core.php, enaizi-user, enaizi-mfa) whenever no
	// affiliateid cookie was present at registration - confirmed 2026-08-09
	// it isn't even a real WordPress user, so it must never receive points.
	if ( ! $referrer_id || 14270 === $referrer_id || $referrer_id === (int) $new_user_id ) {
		return;
	}

	if ( ! get_userdata( $referrer_id ) ) {
		return;
	}

	if ( function_exists( 'mfa_award_points' ) ) {
		// Description includes the new member's ID (not just name) so a
		// referrer's second, third, etc. successful referral each get their
		// own ledger row - mfa_award_points() dedupes on the exact
		// (user_id, description) pair, so a generic description would
		// silently drop every referral after the first for the same referrer.
		mfa_award_points( $referrer_id, 'Referral - ' . $new_user_name . ' joined (#' . $new_user_id . ')', 25 );
	}
}
