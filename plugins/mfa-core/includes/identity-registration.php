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
		niz_user_member_cct( $user_id );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'name', $name );
		if ( $email ) {
			niz_user_update_field( $user_id, 'email', $email );
		}
	}

	update_user_meta( $user_id, 'user_status', 'member' );

	if ( function_exists( 'mfa_award_points' ) ) {
		mfa_award_points( $user_id, 'Welcome Bonus', 50 );
	}

	return true;
}
