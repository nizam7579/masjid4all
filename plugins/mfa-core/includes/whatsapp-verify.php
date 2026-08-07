<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links a WhatsApp number to an existing WP user account (registered via
 * email/Google, with no verified phone yet) instead of letting niz-wa's
 * resolver auto-create a disconnected 'prospect' account for that number.
 *
 * Flow: a logged-in user calls niz_wa_generate_verify_link() (from a
 * future 'Verify WhatsApp' UI — not built yet) to get a wa.me deep link
 * with a pre-filled 'VERIFY-XXXX' code. Sending it is caught here via
 * niz-wa's nwa_route_message_override hook, before normal keyword/AI
 * routing ever sees it.
 */

/* ---------------- Code generation ---------------- */

/**
 * Generates a 15-minute verification code for $user_id and returns a
 * wa.me deep link with it pre-filled. Call this from a 'Verify WhatsApp'
 * button/shortcode on the member profile (not yet built).
 *
 * @return string|WP_Error wa.me URL, or WP_Error if the business number
 *                          isn't resolvable (bad credentials, API down).
 */
function niz_wa_generate_verify_link( $user_id ) {
	$business_number = niz_wa_get_business_display_number();

	if ( empty( $business_number ) ) {
		return new WP_Error( 'no_business_number', 'Could not determine the WhatsApp business number.' );
	}

	$code = niz_wa_generate_readable_code();

	update_user_meta( $user_id, 'niz_wa_verify_code', $code );
	update_user_meta( $user_id, 'niz_wa_verify_code_expires', gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS ) );

	$text = rawurlencode( 'VERIFY-' . $code );

	return "https://wa.me/{$business_number}?text={$text}";
}

/**
 * 6-char alphanumeric code, uppercase, with visually ambiguous characters
 * (0/O, 1/I/L) stripped so it's easy to read and re-type if needed.
 */
function niz_wa_generate_readable_code() {
	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	$code = '';
	for ( $i = 0; $i < 6; $i++ ) {
		$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
	}
	return $code;
}

/**
 * niz-wa only stores Meta's opaque phone_number_id, not the actual
 * dialable number needed for a wa.me link — fetched live from the Graph
 * API and cached for a day.
 */
function niz_wa_get_business_display_number() {
	$cached = get_transient( 'niz_wa_business_display_number' );
	if ( $cached ) {
		return $cached;
	}

	if ( ! class_exists( 'NWA_Config' ) ) {
		return '';
	}

	$phone_number_id = NWA_Config::get( 'phone_number_id' );
	$access_token    = NWA_Config::get( 'access_token' );
	$api_version     = NWA_Config::get( 'api_version' ) ?: 'v21.0';

	if ( empty( $phone_number_id ) || empty( $access_token ) ) {
		return '';
	}

	$response = wp_remote_get( "https://graph.facebook.com/{$api_version}/{$phone_number_id}?fields=display_phone_number", array(
		'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		error_log( 'mfa-core: failed to fetch WhatsApp display number — ' . $response->get_error_message() );
		return '';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$display_number = $data['display_phone_number'] ?? '';
	$clean_number = preg_replace( '/\D/', '', $display_number );

	if ( empty( $clean_number ) ) {
		error_log( 'mfa-core: WhatsApp display number lookup returned no number: ' . wp_remote_retrieve_body( $response ) );
		return '';
	}

	set_transient( 'niz_wa_business_display_number', $clean_number, DAY_IN_SECONDS );

	return $clean_number;
}

/* ---------------- Inbound verify handling ---------------- */

add_filter( 'nwa_route_message_override', 'niz_wa_handle_verify_override', 10, 5 );

function niz_wa_handle_verify_override( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override; // another handler already claimed this message
	}

	if ( ! preg_match( '/^VERIFY-([A-Z0-9]{4,10})$/i', trim( (string) $message_text ), $matches ) ) {
		return null;
	}

	$code = strtoupper( $matches[1] );

	$users = get_users( array(
		'meta_key'   => 'niz_wa_verify_code',
		'meta_value' => $code,
		'number'     => 1,
		'fields'     => 'ID',
	) );

	if ( empty( $users ) ) {
		return "That verification code isn't valid. Please generate a new one from your account page.";
	}

	$verified_user_id = (int) $users[0];
	$expires = get_user_meta( $verified_user_id, 'niz_wa_verify_code_expires', true );

	if ( empty( $expires ) || strtotime( $expires ) < time() ) {
		delete_user_meta( $verified_user_id, 'niz_wa_verify_code' );
		delete_user_meta( $verified_user_id, 'niz_wa_verify_code_expires' );
		return "That verification code has expired. Please generate a new one from your account page.";
	}

	// If this WhatsApp number already resolved to a different (likely
	// auto-created prospect) account, fold its conversation history into
	// the verified account and remove the orphaned duplicate.
	niz_wa_merge_prospect_into_verified_user( (int) $user_id, $verified_user_id );

	update_user_meta( $verified_user_id, 'user_phone', $wa_number );
	update_user_meta( $verified_user_id, 'niz_whatsapp_verified', 'Yes' );
	delete_user_meta( $verified_user_id, 'niz_wa_verify_code' );
	delete_user_meta( $verified_user_id, 'niz_wa_verify_code_expires' );

	if ( function_exists( 'mfa_award_points' ) ) {
		mfa_award_points( $verified_user_id, 'Verify WhatsApp', 25 );
	}

	return "Your WhatsApp number has been successfully verified and linked to your account!";
}

/* ---------------- Prospect merge ---------------- */

/**
 * Moves niz-wa's conversation/message/profile rows from $prospect_user_id
 * onto $verified_user_id, then deletes the prospect WP account. Safe to
 * call with $prospect_user_id === $verified_user_id (no-op).
 */
function niz_wa_merge_prospect_into_verified_user( $prospect_user_id, $verified_user_id ) {
	if ( $prospect_user_id === $verified_user_id ) {
		return;
	}

	if ( ! class_exists( 'NWA_DB' ) ) {
		return;
	}

	global $wpdb;
	$conversations_table = NWA_DB::conversations_table();
	$messages_table      = NWA_DB::messages_table();
	$profiles_table      = NWA_DB::profiles_table();

	$verified_conversation_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$conversations_table} WHERE user_id = %d", $verified_user_id ) );
	$prospect_conversation_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$conversations_table} WHERE user_id = %d", $prospect_user_id ) );

	if ( $verified_conversation_id ) {
		// Verified account already has a conversation (edge case) — move the
		// prospect's messages onto the existing one instead of clashing on
		// the conversations table's UNIQUE KEY on user_id.
		if ( $prospect_conversation_id ) {
			$wpdb->update(
				$messages_table,
				array( 'user_id' => $verified_user_id, 'conversation_id' => $verified_conversation_id ),
				array( 'conversation_id' => $prospect_conversation_id )
			);
			$wpdb->delete( $conversations_table, array( 'id' => $prospect_conversation_id ) );
		}
	} else {
		$wpdb->update( $conversations_table, array( 'user_id' => $verified_user_id ), array( 'user_id' => $prospect_user_id ) );
		$wpdb->update( $messages_table, array( 'user_id' => $verified_user_id ), array( 'user_id' => $prospect_user_id ) );
	}

	$verified_has_profile = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$profiles_table} WHERE user_id = %d", $verified_user_id ) );
	if ( $verified_has_profile ) {
		$wpdb->delete( $profiles_table, array( 'user_id' => $prospect_user_id ) );
	} else {
		$wpdb->update( $profiles_table, array( 'user_id' => $verified_user_id ), array( 'user_id' => $prospect_user_id ) );
	}

	$prospect_user = get_userdata( $prospect_user_id );
	if ( $prospect_user && 'prospect' === get_user_meta( $prospect_user_id, 'user_status', true ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $prospect_user_id );
	}
}
