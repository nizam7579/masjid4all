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
