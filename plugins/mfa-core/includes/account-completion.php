<?php
/**
 * Account completion state.
 *
 * All three registration routes are meant to converge on the same place:
 * a member with a verified email, a verified WhatsApp number, and a
 * password they chose. Each route arrives with a different piece missing:
 *
 *   Google  - email verified, no WhatsApp, no password
 *   Sofia   - WhatsApp verified, email asked for, no password
 *   Web form- password set, both still to verify
 *
 * Two of the three flags already existed as user meta (niz_email_verified,
 * niz_whatsapp_verified). The third could not be derived at all: WordPress
 * always stores a password hash, so there is no way to tell "chose a
 * password" from "was given a random one they have never seen". This file
 * adds that flag and reads the three together.
 *
 * Keying follow-ups off what is MISSING - rather than off which route
 * someone came through - is what lets one set of nudges serve all three.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Has this user chosen a password they know?
 *
 * A missing flag is treated as "no". That is deliberately the safe
 * direction: it can only ever over-invite someone to set a password,
 * which is harmless, whereas assuming "yes" would leave Google and Sofia
 * members permanently unable to log in by email without ever being asked.
 * The flag self-corrects the moment anyone sets or resets one.
 */
function mfa_user_has_password( $user_id ) {
	return 'yes' === get_user_meta( (int) $user_id, 'mfa_password_set', true );
}

/** The three completion flags, plus what is still outstanding. */
function mfa_user_completion( $user_id ) {
	$user_id = (int) $user_id;
	$user    = get_userdata( $user_id );

	$email_ok = ( 'Yes' === get_user_meta( $user_id, 'niz_email_verified', true ) );

	// A <phone>@mfa.com placeholder is not a real address, so it cannot
	// count as a verified email no matter what the flag says.
	if ( $user && preg_match( '/@mfa\.com$/i', $user->user_email ) ) {
		$email_ok = false;
	}

	$state = array(
		'email'    => $email_ok,
		'whatsapp' => ( 'Yes' === get_user_meta( $user_id, 'niz_whatsapp_verified', true ) ),
		'password' => mfa_user_has_password( $user_id ),
	);

	$state['missing']  = array_keys( array_filter( $state, function ( $v ) {
		return false === $v;
	} ) );
	$state['complete'] = empty( $state['missing'] );

	return $state;
}

/**
 * Record whether the route gave them a password.
 *
 * Only the web form asks for one. Google and Sofia both create the account
 * with a random password the member never sees, so they start as 'no' and
 * get nudged to set one.
 */
add_action( 'mfa_user_activated', 'mfa_completion_on_activation', 10, 2 );

function mfa_completion_on_activation( $user_id, $args ) {
	$route = ! empty( $args['route'] ) ? $args['route'] : '';

	update_user_meta( $user_id, 'mfa_password_set', ( 'web' === $route ) ? 'yes' : 'no' );
}

/* A completed reset means they now have one they chose - true whichever
   route they originally came through, so this self-heals the flag for
   members who predate it. */
add_action( 'after_password_reset', 'mfa_completion_after_password_reset', 10, 2 );

function mfa_completion_after_password_reset( $user, $new_pass ) {
	if ( $user instanceof WP_User ) {
		update_user_meta( $user->ID, 'mfa_password_set', 'yes' );
	}
}
