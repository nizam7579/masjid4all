<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email-based check/register, ported from enaizi-identity's Niz_Login /
 * Niz_Register classes and fixed along the way:
 *  - enaizi-identity's phone-login path queried usermeta key
 *    'niz_phone_number', but its own register() only ever wrote
 *    'user_phone' — the two never agreed, so phone login there never
 *    actually worked. Standardized here on 'user_phone', the key every
 *    other plugin (enaizi-user, enaizi-mfa, niz-wa) already uses.
 *  - enaizi-identity's register() left debug echo statements in and
 *    didn't stop on wp_insert_user() failure, so a failed insert would
 *    fall through to wp_set_current_user()/wp_set_auth_cookie() with a
 *    WP_Error object instead of a user ID. Fixed to return WP_Error
 *    immediately on failure.
 *
 * Not wired to any AJAX action or shortcode yet — enaizi-identity's
 * own [niz_register]/[niz_login] forms remain the live front-end path
 * until a later phase explicitly cuts over.
 */

/**
 * Look up a WP user by email. Mirrors niz_user_check()'s contract
 * (null if not found) for consistency once both live in mfa-core.
 *
 * @return int|null
 */
function niz_user_check_email( $email ) {
	$email = sanitize_email( $email );

	if ( empty( $email ) || ! is_email( $email ) ) {
		return null;
	}

	$user = get_user_by( 'email', $email );

	return $user ? $user->ID : null;
}

/**
 * Registers a new WP user by email+password. If a phone number is
 * given, it's checked against niz_user_check() (when available) to
 * avoid creating a second account for a phone that's already
 * registered via the phone-based flow.
 *
 * @return int|WP_Error User ID on success, WP_Error on failure.
 */
function niz_user_register_email( $email, $name, $password, $phone = '' ) {
	$email = sanitize_email( $email );
	$name  = sanitize_text_field( $name );
	$phone = sanitize_text_field( $phone );

	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', 'Please enter a valid email.' );
	}

	if ( empty( $name ) || empty( $password ) ) {
		return new WP_Error( 'missing_fields', 'Please complete all required fields.' );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'email_exists', 'Email already registered.' );
	}

	if ( ! empty( $phone ) && function_exists( 'niz_user_check' ) ) {
		$existing_phone_user = niz_user_check( $phone );
		if ( $existing_phone_user ) {
			return new WP_Error( 'phone_exists', 'This phone number is already registered.' );
		}
	}

	$username = sanitize_user( current( explode( '@', $email ) ) );
	if ( username_exists( $username ) ) {
		$username .= wp_rand( 100, 999 );
	}

	$user_id = wp_insert_user( array(
		'user_login'   => $username,
		'user_pass'    => $password,
		'user_email'   => $email,
		'first_name'   => $name,
		'display_name' => $name,
		'role'         => 'subscriber',
	) );

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, 'niz_email_verified', 'No' );
	update_user_meta( $user_id, 'niz_google_connected', 'No' );
	update_user_meta( $user_id, 'niz_whatsapp_verified', 'No' );

	if ( ! empty( $phone ) ) {
		update_user_meta( $user_id, 'user_phone', $phone );
	}

	// NOTE: enaizi-identity's original flow also generated an email
	// verification token and sent it via Niz_Email_Verification, which
	// still lives in enaizi-identity. Deliberately not called from here
	// to avoid mfa-core depending back on the plugin it's replacing —
	// wire this up again once email verification itself moves to mfa-core.

	return $user_id;
}
