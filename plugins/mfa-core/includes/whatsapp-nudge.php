<?php
/**
 * "We have their email but not their WhatsApp" nudge plumbing.
 *
 * The gap this closes: someone who registers with Google gives us an email
 * and nothing else. Sofia cannot reach them, so every WhatsApp-based
 * feature - the travel planner, the lead flows, follow-ups - is closed to
 * them, and we cannot tell they are the same person as the prospect row
 * their phone number may already have.
 *
 * The end state we want is one account holding BOTH. Sofia already knows
 * how to get there: niz_wa_account_link_to() merges the prospect into the
 * verified user, writes user_phone and sets niz_whatsapp_verified. All the
 * member has to do is message her once and give the email they signed up
 * with.
 *
 * So this file does three things and deliberately no more:
 *   1. Identifies members in that state.
 *   2. Keeps them in FluentCRM under a tag, so an email sequence can be
 *      built against it in the FluentCRM UI where the copy is written.
 *   3. Removes the tag the moment the number is linked, which is what
 *      stops the sequence.
 *
 * It sends nothing itself. Nothing here emails or messages anyone.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tag used to drive the sequence. */
function mfa_whatsapp_nudge_tag() {
	return array( 'slug' => 'needs-whatsapp', 'title' => 'Needs WhatsApp' );
}

/**
 * Does this member have an email but no verified WhatsApp number?
 *
 * Requires member/premium status - a prospect has not registered, so there
 * is nothing to nudge them toward yet - and a real email, since the
 * <phone>@mfa.com placeholder means we have the opposite problem.
 */
function mfa_needs_whatsapp_link( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	$status = get_user_meta( $user_id, 'user_status', true );
	if ( ! in_array( $status, array( 'member', 'premium' ), true ) ) {
		return false;
	}

	if ( ! is_email( $user->user_email ) || preg_match( '/@mfa\.com$/i', $user->user_email ) ) {
		return false;
	}

	if ( 'Yes' === get_user_meta( $user_id, 'niz_whatsapp_verified', true ) ) {
		return false;
	}

	$phone = trim( (string) get_user_meta( $user_id, 'user_phone', true ) );

	return ( '' === $phone );
}

/**
 * The link the nudge emails should point at.
 *
 * Pre-fills the message so the member lands in Sofia's account flow
 * (niz_wa_account_start) rather than having to work out what to type. She
 * then asks for their email, sends a code, and links the two.
 */
function mfa_whatsapp_nudge_link( $user_id = 0 ) {
	return 'https://wa.me/60189897579?text=' . rawurlencode( 'link my account' );
}

/** Every member currently in that state. */
function mfa_whatsapp_nudge_find( $limit = 200 ) {
	global $wpdb;

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT u.ID
		 FROM {$wpdb->users} u
		 INNER JOIN {$wpdb->usermeta} s ON s.user_id = u.ID AND s.meta_key = 'user_status'
		        AND s.meta_value IN ('member','premium')
		 LEFT JOIN {$wpdb->usermeta} p ON p.user_id = u.ID AND p.meta_key = 'user_phone'
		 LEFT JOIN {$wpdb->usermeta} v ON v.user_id = u.ID AND v.meta_key = 'niz_whatsapp_verified'
		 WHERE u.user_email NOT LIKE %s
		   AND (p.meta_value IS NULL OR p.meta_value = '')
		   AND (v.meta_value IS NULL OR v.meta_value <> 'Yes')
		 ORDER BY u.ID DESC
		 LIMIT %d",
		'%@mfa.com',
		(int) $limit
	) );

	return array_map( 'intval', $ids );
}

/* ---------------- FluentCRM sync ---------------- */

/**
 * Put a member under the nudge tag (or take them out of it).
 *
 * Adding the tag is what starts the sequence: FluentCRM's
 * fluentcrm_contact_added_to_tags trigger fires on it. Removing it is what
 * stops the sequence, which is why the removal path matters as much as the
 * addition and runs automatically below.
 *
 * @return string|false What happened, or false if FluentCRM is unavailable.
 */
function mfa_whatsapp_nudge_sync( $user_id, $force_remove = false ) {
	if ( ! function_exists( 'FluentCrmApi' ) || ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}

	$needs = ! $force_remove && mfa_needs_whatsapp_link( $user_id );
	$tag   = mfa_whatsapp_nudge_tag();

	try {
		$tag_row = \FluentCrm\App\Models\Tag::firstOrCreate(
			array( 'slug' => $tag['slug'] ),
			array( 'title' => $tag['title'] )
		);
		if ( ! $tag_row ) {
			return false;
		}

		$contact = FluentCrmApi( 'contacts' )->getContact( $user->user_email );

		if ( ! $needs ) {
			// Linked (or no longer eligible) - detaching the tag is the
			// signal the sequence checks before each send.
			if ( $contact ) {
				$contact->detachTags( array( $tag_row->id ) );
				return 'untagged';
			}
			return 'not_in_crm';
		}

		$parts = preg_split( '/\s+/', trim( (string) $user->display_name ), 2 );

		$contact = FluentCrmApi( 'contacts' )->createOrUpdate( array(
			'email'      => $user->user_email,
			'first_name' => isset( $parts[0] ) ? $parts[0] : '',
			'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
			// They registered on the site, so this is a real opt-in.
			'status'     => 'subscribed',
			'source'     => 'mfa-needs-whatsapp',
			'user_id'    => (int) $user_id,
			'tags'       => array( $tag_row->id ),
		) );

		return $contact ? 'tagged' : false;
	} catch ( \Throwable $e ) {
		error_log( 'mfa_whatsapp_nudge_sync: ' . $e->getMessage() );
		return false;
	}
}

/** Sync everyone currently in the state. Returns a per-outcome tally. */
function mfa_whatsapp_nudge_sync_all( $limit = 200 ) {
	$tally = array();

	foreach ( mfa_whatsapp_nudge_find( $limit ) as $user_id ) {
		$result = mfa_whatsapp_nudge_sync( $user_id );
		$key    = ( false === $result ) ? 'failed' : $result;
		$tally[ $key ] = isset( $tally[ $key ] ) ? $tally[ $key ] + 1 : 1;
	}

	return $tally;
}

/* ---------------- Automatic exit ----------------
   The sequence must stop the moment the number is linked. Sofia's
   niz_wa_account_link_to() writes both of these keys, so watching them
   covers the linking path without needing a hook inside that function. */

add_action( 'added_user_meta', 'mfa_whatsapp_nudge_meta_watch', 10, 4 );
add_action( 'updated_user_meta', 'mfa_whatsapp_nudge_meta_watch', 10, 4 );

function mfa_whatsapp_nudge_meta_watch( $meta_id, $user_id, $meta_key, $meta_value ) {
	if ( 'niz_whatsapp_verified' !== $meta_key && 'user_phone' !== $meta_key ) {
		return;
	}

	// Only react to a number actually arriving, not to it being cleared.
	if ( '' === trim( (string) $meta_value ) ) {
		return;
	}

	// Guard against recursion: the CRM write below can touch user meta.
	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;
	mfa_whatsapp_nudge_sync( (int) $user_id, true );
	$running = false;
}

/* Newly activated members get tagged straight away, so the sequence starts
   from their registration rather than from the next manual sync. */
add_action( 'mfa_user_activated', 'mfa_whatsapp_nudge_on_activation', 10, 2 );

function mfa_whatsapp_nudge_on_activation( $user_id, $args ) {
	if ( mfa_needs_whatsapp_link( $user_id ) ) {
		mfa_whatsapp_nudge_sync( $user_id );
	}
}
