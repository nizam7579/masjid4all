<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glue between niz-wa (the portable WhatsApp plugin) and mfa-core's own
 * identity system. Moved here from niz-wa/includes/site-integration.php
 * so niz-wa has zero Masjid4All-specific code — this is the ONLY place
 * that decides what a WhatsApp number means on this site.
 */

/* ---------------- User resolution ---------------- */

add_filter( 'nwa_resolve_user_id', 'niz_wa_resolve_user_id', 10, 3 );

function niz_wa_resolve_user_id( $user_id, $wa_number, $contact_name ) {
	if ( ! function_exists( 'niz_user_check' ) ) {
		return $user_id; // Identity core not loaded — fall back to niz-wa's own default resolver.
	}

	// Read-only: link to an already-existing WordPress member if this
	// WhatsApp number is recognized. Does not create anything.
	$existing = niz_user_check( $wa_number );
	if ( $existing ) {
		return $existing;
	}

	// No auto-creating new WordPress users ("prospects") for unrecognized
	// numbers anymore — niz-wa falls back to its own standalone
	// wp_nwa_contacts table instead (NWA_DB::get_or_create_contact()),
	// same as running with no site-integration hook at all.
	return $user_id;
}

/* ---------------- Action callbacks ---------------- */
/* Signature: function( $user_id, $context ): string reply. Never send messages
   directly — NWA_Router sends whatever string is returned. */

function niz_wa_action_start( $user_id, $context ) {
	return "Welcome to Masjid4All.\n\nHow can I help you?";
}

function niz_wa_action_register( $user_id, $context ) {
	if ( ! function_exists( 'niz_user_register' ) ) {
		return "Registration is temporarily unavailable. Please try again later.";
	}

	$status = get_user_meta( $user_id, 'user_status', true );

	if ( in_array( $status, array( 'member', 'premium' ), true ) ) {
		return "You're already a registered member. Log in here:\nhttps://staging.masjid4all.com/member";
	}

	$phone = get_user_meta( $user_id, 'user_phone', true );
	$user  = get_userdata( $user_id );
	$name  = $user ? trim( (string) $user->first_name ) : '';

	if ( ! $name || 0 === strpos( $name, 'Prospect ' ) ) {
		$name = 'Member';
	}

	if ( empty( $phone ) ) {
		return "Registration failed — we couldn't find your WhatsApp number on file. Please try again later.";
	}

	$result = niz_user_register( $phone, $name, false );

	if ( is_wp_error( $result ) ) {
		return 'Registration failed: ' . $result->get_error_message();
	}

	$password = function_exists( 'niz_user_password' ) ? niz_user_password( $phone ) : null;

	if ( ! $password || is_wp_error( $password ) ) {
		return "Registration successful, {$name}!\n\nUse the 'forgot password' option after visiting:\nhttps://staging.masjid4all.com/member";
	}

	return "Registration successful, {$name}!\n\nYour temporary password is: {$password}\n\nLogin here:\nhttps://staging.masjid4all.com/member\n\nPlease change your password after login.";
}

function niz_wa_action_reset_password( $user_id, $context ) {
	if ( ! function_exists( 'niz_user_password' ) ) {
		return "Password reset is temporarily unavailable. Please try again later.";
	}

	$status = get_user_meta( $user_id, 'user_status', true );
	$phone  = get_user_meta( $user_id, 'user_phone', true );

	if ( empty( $status ) || 'prospect' === $status ) {
		return "You don't have an active membership yet. Reply REGISTER to sign up as a free member first.";
	}

	if ( empty( $phone ) ) {
		return "We couldn't find your WhatsApp number on file. Please try again later.";
	}

	$password = niz_user_password( $phone );

	if ( is_wp_error( $password ) || ! $password ) {
		return "We couldn't reset your password right now. Please try again later.";
	}

	return "Your temporary password is: {$password}\n\nLogin here:\nhttps://staging.masjid4all.com/member\n\nPlease change your password after login.";
}

function niz_wa_action_claim_business( $user_id, $context ) {
	return "To claim your business listing on Masjid4All, please visit:\nhttps://staging.masjid4all.com/add-business/\n\nIt's a free service — our team will verify and activate your Halal business profile.";
}

function niz_wa_action_membership_price( $user_id, $context ) {
	return "Membership starts from RM19.90 per year.\n\nFor the latest pricing and to upgrade, visit:\nhttps://staging.masjid4all.com/member/premium/";
}

function niz_wa_action_advertise( $user_id, $context ) {
	return "Interested in advertising with Masjid4All? Learn more here:\nhttps://staging.masjid4all.com/advertise\n\nOr reply here and our team will reach out.";
}

/* ---------------- Action registry seeding ---------------- */

add_action( 'admin_init', 'niz_wa_seed_actions' );

function niz_wa_seed_actions() {
	if ( ! class_exists( 'NWA_DB' ) ) {
		return;
	}

	global $wpdb;
	$table = NWA_DB::actions_table();

	$actions = array(
		array(
			'intent_key'            => 'start',
			'keywords'              => 'start,help,menu',
			'description'           => 'User wants a welcome/help message or main menu',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_start',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'register',
			'keywords'              => 'register,signup,sign up,daftar,jadi ahli',
			'description'           => 'User wants to register as a new member or asks about signing up',
			'requires_confirmation' => true,
			'confirm_message'       => 'Would you like to register as a free Masjid4All member?',
			'callback_function'     => 'niz_wa_action_register',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'reset_password',
			'keywords'              => 'password,forgot password,reset password,request password,kata laluan,tukar password,lupa password,change password',
			'description'           => "User forgot their password or wants to reset/change their account password",
			'requires_confirmation' => true,
			'confirm_message'       => 'Do you want to reset your password?',
			'callback_function'     => 'niz_wa_action_reset_password',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'claim_business',
			'keywords'              => 'claim business,claim listing,claim bisnes,tuntut bisnes',
			'description'           => 'User wants to claim or manage their business listing',
			'requires_confirmation' => true,
			'confirm_message'       => 'Would you like to claim your business listing on Masjid4All?',
			'callback_function'     => 'niz_wa_action_claim_business',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'membership_price',
			'keywords'              => 'price,pricing,membership price,harga,yuran',
			'description'           => 'User is asking about membership pricing or fees',
			'requires_confirmation' => true,
			'confirm_message'       => 'Would you like details on our membership pricing?',
			'callback_function'     => 'niz_wa_action_membership_price',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'advertise',
			'keywords'              => 'advertise,advertising,iklan,promote',
			'description'           => 'User wants to advertise or promote their business with Masjid4All',
			'requires_confirmation' => true,
			'confirm_message'       => 'Are you interested in advertising with Masjid4All?',
			'callback_function'     => 'niz_wa_action_advertise',
			'enabled'               => true,
		),
	);

	foreach ( $actions as $action ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE intent_key = %s",
			$action['intent_key']
		) );

		if ( $exists ) {
			continue;
		}

		NWA_DB::save_action( $action );
	}
}
