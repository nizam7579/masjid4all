<?php
/**
 * The "what has happened with this member" summary the helpline reads
 * before deciding what to do next.
 *
 * Everything here is DERIVED from the tables that actually record the
 * event, never from the convenience columns on jet_cct_member. Those look
 * inviting - last_contact, chk_share, business_owner - but nothing writes
 * them: last_contact is empty for all 29 members on production, chk_share
 * and business_owner for every one. A field that is blank because nobody
 * populates it is worse than no field, because it reads as "this never
 * happened".
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When we last had any contact with this member, and through which channel.
 *
 * Looks across every channel that records a timestamp, and takes the most
 * recent: WhatsApp in either direction, an admin-initiated send from the
 * activity log, and any inquiry they submitted.
 *
 * Note the timezones differ by design and are normalised here: wp_nwa_*
 * is GMT (see CLAUDE.md), while the activity log and jet_cct_contact_us
 * store site-local time. Comparing them raw would put a WhatsApp message
 * eight hours off and pick the wrong "latest".
 *
 * @return array|null array( 'at' => local mysql datetime, 'channel' => label )
 */
function mfa_member_last_contact( $user_id ) {
	global $wpdb;

	$user_id    = (int) $user_id;
	$candidates = array();

	// --- WhatsApp (GMT -> local)
	$wa = $wpdb->prefix . 'nwa_messages';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wa ) ) ) {
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT created_at, direction FROM {$wa} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );
		if ( $row ) {
			$candidates[] = array(
				'at'      => get_date_from_gmt( $row->created_at ),
				'channel' => 'inbound' === $row->direction ? 'WhatsApp (they messaged)' : 'WhatsApp (we messaged)',
			);
		}
	}

	// --- Admin-initiated sends, recorded when staff use the buttons on this page.
	if ( function_exists( 'mfa_activity_table' ) ) {
		$act = mfa_activity_table();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT created_at, type FROM {$act}
			 WHERE user_id = %d AND type IN ('admin_email','admin_whatsapp','admin_template')
			 ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );
		if ( $row ) {
			$labels       = array(
				'admin_email'    => 'Email (we sent)',
				'admin_whatsapp' => 'WhatsApp (we sent)',
				'admin_template' => 'Template (we sent)',
			);
			$candidates[] = array(
				'at'      => $row->created_at,
				'channel' => isset( $labels[ $row->type ] ) ? $labels[ $row->type ] : 'Admin message',
			);
		}
	}

	// --- Inquiries they submitted.
	$inq = $wpdb->prefix . 'jet_cct_contact_us';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inq ) ) ) {
		$at = $wpdb->get_var( $wpdb->prepare(
			"SELECT cct_created FROM {$inq} WHERE cct_author_id = %d ORDER BY cct_created DESC LIMIT 1",
			$user_id
		) );
		if ( $at ) {
			$candidates[] = array( 'at' => $at, 'channel' => 'Inquiry' );
		}
	}

	if ( ! $candidates ) {
		return null;
	}

	usort( $candidates, function ( $a, $b ) {
		return strtotime( $b['at'] ) <=> strtotime( $a['at'] );
	} );

	return $candidates[0];
}

/** "3 days ago" / "today" for a local mysql datetime. */
function mfa_member_time_ago( $datetime ) {
	$ts = strtotime( $datetime );
	if ( ! $ts ) {
		return '';
	}

	$now  = (int) current_time( 'timestamp' );
	$diff = $now - $ts;

	if ( $diff < 0 ) {
		return 'in the future';
	}
	if ( $diff < DAY_IN_SECONDS ) {
		return 'today';
	}

	$days = (int) floor( $diff / DAY_IN_SECONDS );

	return sprintf( '%d %s ago', $days, 1 === $days ? 'day' : 'days' );
}

/** How many members this member referred. */
function mfa_member_downline_count( $user_id ) {
	global $wpdb;

	// 14270 is the "no real referrer" sentinel used across the codebase, so
	// it must be excluded or every unreferred member counts as its downline.
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_member WHERE referrer_id = %d AND referrer_id <> 14270",
		(int) $user_id
	) );
}

/**
 * The milestone checklist: what this member has and has not done.
 *
 * Read from whichever table records the thing itself, so a tick means the
 * event genuinely happened rather than that a flag column was remembered.
 *
 * @return array label => bool, in the order the helpline reads them.
 */
function mfa_member_milestones( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	$cct     = $wpdb->prefix . 'jet_cct_member';

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT namecard, affiliate, update_info FROM {$cct} WHERE user_id = %d",
		$user_id
	), ARRAY_A );

	$filled = function ( $value ) {
		return null !== $value && '' !== trim( (string) $value ) && '0' !== (string) $value;
	};

	$count = function ( $table, $column ) use ( $wpdb, $user_id ) {
		$full = $wpdb->prefix . $table;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$full} WHERE {$column} = %d",
			$user_id
		) );
	};

	$claims = $count( 'jet_cct_listing_owner', 'user_id' );

	return array(
		'Email verified'    => ( 'Yes' === get_user_meta( $user_id, 'niz_email_verified', true ) ),
		'WhatsApp verified' => ( 'Yes' === get_user_meta( $user_id, 'niz_whatsapp_verified', true ) ),
		'Password set'      => function_exists( 'mfa_user_has_password' ) ? mfa_user_has_password( $user_id ) : false,
		'Profile completed' => $filled( $row['update_info'] ?? '' ),
		'Namecard created'  => $filled( $row['namecard'] ?? '' ),
		'Affiliate joined'  => $filled( $row['affiliate'] ?? '' ),
		'Mosque added'      => $count( 'jet_cct_mosque', 'cct_author_id' ) > 0,
		'Business added'    => $count( 'jet_cct_business', 'cct_author_id' ) > 0,
		'Website added'     => $count( 'jet_cct_web', 'cct_author_id' ) > 0,
		'Community joined'  => $count( 'jet_cct_community', 'user_id' ) > 0,
		'Listing claimed'   => $claims > 0,
	);
}

/**
 * A wa.me link that starts the email-capture conversation.
 *
 * Sofia cannot message anyone who has never written to her, and none of the
 * 18 members missing an address ever has - so there is no window to reply
 * into and no approved template to open one. The way round it is the same
 * one WhatsApp verification uses: a link the MEMBER taps, which sends
 * "EMAIL" from their phone. That both opens the 24-hour window and matches
 * the update_email intent, so Sofia asks for the address on arrival.
 *
 * Staff send this link by whatever means they already have - their own
 * WhatsApp, SMS - because the platform itself cannot reach these people.
 *
 * @return string Empty when there is no number to send to.
 */
function mfa_member_email_capture_link( $user_id ) {
	$phone = trim( (string) get_user_meta( (int) $user_id, 'user_phone', true ) );

	if ( '' === $phone ) {
		global $wpdb;
		$phone = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT phone FROM {$wpdb->prefix}jet_cct_member WHERE user_id = %d",
			(int) $user_id
		) );
	}

	$phone = preg_replace( '/[^0-9]/', '', $phone );
	if ( '' === $phone ) {
		return '';
	}

	return 'https://wa.me/' . $phone . '?text=' . rawurlencode( 'EMAIL' );
}

/**
 * What FluentCRM knows about this member - so staff can see which
 * automation someone is already in before sending them anything by hand.
 *
 * There are three genuinely different answers, and collapsing them would
 * mislead. On production today: 18 of 29 members carry a placeholder
 * address and therefore CANNOT have a CRM record at all, because FluentCRM
 * is keyed on email; 4 have a real address but no record yet; 7 are in it.
 * Showing "no tags" for all three reads as "no automation is reaching
 * them", which is only actionable for the middle group.
 *
 * The API surface here was checked against the live install before being
 * relied on: getContact() returns a Subscriber or null, and ->tags /
 * ->lists are relations. Note getTags() does NOT exist - guarding on a
 * method that isn't there is how a silent no-op gets shipped.
 *
 * @return array state => none_possible|not_in_crm|found, plus tags/lists.
 */
function mfa_member_crm_profile( $user_id ) {
	$out = array(
		'state'  => 'unavailable',
		'reason' => '',
		'tags'   => array(),
		'lists'  => array(),
		'status' => '',
		'type'   => '',
		'id'     => 0,
	);

	if ( ! function_exists( 'FluentCrmApi' ) ) {
		$out['reason'] = 'FluentCRM is not active.';
		return $out;
	}

	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		$out['reason'] = 'No such user.';
		return $out;
	}

	// FluentCRM is keyed on email, so a placeholder address is not "missing
	// from the CRM" - it can never be in it until a real address is captured.
	if ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $user->user_email ) ) {
		$out['state']  = 'none_possible';
		$out['reason'] = 'No real email address, so no CRM record is possible.';
		return $out;
	}

	try {
		$contact = FluentCrmApi( 'contacts' )->getContact( $user->user_email );
	} catch ( \Throwable $e ) {
		$out['reason'] = 'CRM lookup failed: ' . $e->getMessage();
		return $out;
	}

	if ( ! $contact ) {
		$out['state']  = 'not_in_crm';
		$out['reason'] = 'Not in FluentCRM yet.';
		return $out;
	}

	$out['state']  = 'found';
	$out['id']     = (int) $contact->id;
	$out['status'] = (string) $contact->status;
	$out['type']   = (string) ( $contact->contact_type ?? '' );

	// Iterated directly, NOT cast with (array). These are Laravel
	// collections, and casting one yields its internal properties rather
	// than its items - which produced "Attempt to read property title on
	// array" and a list of empty strings. The element check covers both a
	// model and a plain array, since the shape has differed between
	// FluentCRM versions.
	$title = function ( $item ) {
		if ( is_object( $item ) ) {
			return isset( $item->title ) ? (string) $item->title : '';
		}
		if ( is_array( $item ) ) {
			return isset( $item['title'] ) ? (string) $item['title'] : '';
		}

		return '';
	};

	foreach ( $contact->tags as $tag ) {
		$name = $title( $tag );
		if ( '' !== $name ) {
			$out['tags'][] = $name;
		}
	}

	foreach ( $contact->lists as $list ) {
		$name = $title( $list );
		if ( '' !== $name ) {
			$out['lists'][] = $name;
		}
	}

	return $out;
}
