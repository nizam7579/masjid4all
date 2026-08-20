<?php
/**
 * The owner-driven half of the listing_status lifecycle.
 *
 * listing_status is one lifecycle:
 *
 *   New -> (content generated) Pending | Approved | Rejected | Error
 *       -> Deleted, by an admin reviewing a Rejected or Error record
 *
 * plus states nobody had ever written: Verified (a business/website whose
 * owner claimed it) and Active (a mosque whose community reached its
 * activation threshold). The public directories have listed those states
 * in their visibility filters since they were built - mosque.php accepts
 * 'Active', business.php and web.php accept 'Verified' and 'Premium', and
 * business.php even sorts Premium first and Verified second - so the front
 * end was ready and only the writer was missing. Until now a claimed
 * business stayed "Approved" forever.
 *
 * ## Two rules that are not obvious
 *
 * 1. **Only ever promote from 'Approved'.** The content generators select
 *    `listing_status IN ('New','Pending')` to find work
 *    (admin-website-generate-start.php). Promoting a not-yet-generated
 *    listing to Verified would quietly drop it out of that queue and it
 *    would never get its content written. So a claim on a New listing is
 *    recorded in the ownership table and simply doesn't change the status
 *    yet; the generators call back here once they set Approved.
 *
 * 2. **Never touch 'Premium'.** It is a paid state. An unclaim demotes
 *    Verified back to Approved but leaves Premium alone - cancelling a
 *    subscription is a billing decision, not a side effect of a checkbox.
 *
 * Everything here recomputes from the ownership table rather than toggling
 * a flag, so it is idempotent and handles unclaim with the same code path
 * as claim. Safe to call more than once.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** CCT table for a claimable listing type. */
function mfa_listing_table( $post_type ) {
	global $wpdb;

	return ( 'business' === $post_type )
		? $wpdb->prefix . 'jet_cct_business'
		: $wpdb->prefix . 'jet_cct_web';
}

/** Does anyone own this listing? Returns the user id, or 0. */
function mfa_listing_owner_id( $post_id ) {
	global $wpdb;

	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 0;
	}

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$wpdb->prefix}jet_cct_listing_owner WHERE post_id = %d LIMIT 1",
		$post_id
	) );
}

/**
 * Brings one listing's status in line with whether it is claimed.
 *
 * @param int    $post_id   WP post id (jet_cct_*.cct_single_post_id).
 * @param string $post_type business | web.
 * @return string What happened: verified | unverified | unchanged | missing.
 */
function mfa_listing_sync_verified( $post_id, $post_type = 'business' ) {
	global $wpdb;

	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 'missing';
	}

	$post_type = ( 'business' === $post_type ) ? 'business' : 'web';
	$table     = mfa_listing_table( $post_type );

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT _ID, listing_status FROM {$table} WHERE cct_single_post_id = %d LIMIT 1",
		$post_id
	), ARRAY_A );

	if ( ! $row ) {
		return 'missing';
	}

	$status = (string) $row['listing_status'];
	$owned  = mfa_listing_owner_id( $post_id ) > 0;

	if ( $owned && 'Approved' === $status ) {
		$new = 'Verified';
	} elseif ( ! $owned && 'Verified' === $status ) {
		$new = 'Approved';
	} else {
		// New/Pending still owe content generation; Premium is paid;
		// Rejected/Error/Deleted are admin decisions. None are ours to move.
		return 'unchanged';
	}

	$wpdb->update(
		$table,
		array( 'listing_status' => $new, 'cct_modified' => current_time( 'mysql' ) ),
		array( '_ID' => (int) $row['_ID'] ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	/**
	 * Fires after a listing is promoted or demoted by ownership.
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 * @param string $new       New listing_status.
	 * @param string $status    Previous listing_status.
	 */
	do_action( 'mfa_listing_status_synced', $post_id, $post_type, $new, $status );

	return ( 'Verified' === $new ) ? 'verified' : 'unverified';
}

/**
 * Brings one mosque's status in line with whether its community is active.
 *
 * The activation threshold is NOT decided here. community.php already owns
 * it - it recomputes member_count on every join and writes community_status
 * as 'active' (10 or more members), 'pending' (1 to 9) or 'not_created'.
 * Reading that column rather than counting members again means the two can
 * never disagree, and moving the threshold stays a one-place change.
 *
 * @param int $cct_mosque_id jet_cct_mosque._ID.
 * @return string active | inactive | unchanged | missing
 */
function mfa_mosque_sync_active( $cct_mosque_id ) {
	global $wpdb;

	$cct_mosque_id = (int) $cct_mosque_id;
	if ( $cct_mosque_id <= 0 ) {
		return 'missing';
	}

	$table = $wpdb->prefix . 'jet_cct_mosque';

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT _ID, listing_status, community_status FROM {$table} WHERE _ID = %d LIMIT 1",
		$cct_mosque_id
	), ARRAY_A );

	if ( ! $row ) {
		return 'missing';
	}

	$status    = (string) $row['listing_status'];
	$is_active = ( 'active' === strtolower( (string) $row['community_status'] ) );

	if ( $is_active && 'Approved' === $status ) {
		$new = 'Active';
	} elseif ( ! $is_active && 'Active' === $status ) {
		$new = 'Approved';
	} else {
		return 'unchanged';
	}

	$wpdb->update(
		$table,
		array( 'listing_status' => $new, 'cct_modified' => current_time( 'mysql' ) ),
		array( '_ID' => $cct_mosque_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	do_action( 'mfa_mosque_status_synced', $cct_mosque_id, $new, $status );

	return ( 'Active' === $new ) ? 'active' : 'inactive';
}

/**
 * Reconciles every claimed listing and every mosque community in one pass.
 *
 * Needed because claims and community joins predate these writers - six
 * listings were already claimed on production and none was marked
 * Verified. Also the catch-up for a listing claimed while it was still
 * New: it is promoted the first time this runs after generation approves
 * it, if the generator's own call was missed.
 *
 * @param bool $dry_run Report what would change without writing.
 */
function mfa_listing_backfill_status( $dry_run = false ) {
	global $wpdb;

	$report = array(
		'verified' => 0, 'unverified' => 0,
		'active'   => 0, 'inactive'   => 0,
		'skipped'  => 0, 'missing'    => 0,
		'dry_run'  => (bool) $dry_run,
	);

	$owned = $wpdb->get_results(
		"SELECT DISTINCT post_id, post_type FROM {$wpdb->prefix}jet_cct_listing_owner WHERE post_id > 0",
		ARRAY_A
	);

	foreach ( $owned as $o ) {
		$type = ( 'business' === $o['post_type'] ) ? 'business' : 'web';

		if ( $dry_run ) {
			$table = mfa_listing_table( $type );
			$st    = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT listing_status FROM {$table} WHERE cct_single_post_id = %d LIMIT 1",
				(int) $o['post_id']
			) );

			if ( '' === $st ) {
				$report['missing']++;
			} elseif ( 'Approved' === $st ) {
				$report['verified']++;
			} else {
				$report['skipped']++;
			}
			continue;
		}

		$result = mfa_listing_sync_verified( (int) $o['post_id'], $type );
		$key    = isset( $report[ $result ] ) ? $result : 'skipped';
		$report[ $key ]++;
	}

	// Mosques whose community_status and listing_status disagree.
	$mosques = $wpdb->get_results(
		"SELECT _ID, listing_status, community_status FROM {$wpdb->prefix}jet_cct_mosque
		 WHERE LOWER(community_status) = 'active' OR listing_status = 'Active'",
		ARRAY_A
	);

	foreach ( $mosques as $m ) {
		if ( $dry_run ) {
			$is_active = ( 'active' === strtolower( (string) $m['community_status'] ) );
			if ( $is_active && 'Approved' === $m['listing_status'] ) {
				$report['active']++;
			} elseif ( ! $is_active && 'Active' === $m['listing_status'] ) {
				$report['inactive']++;
			} else {
				$report['skipped']++;
			}
			continue;
		}

		$result = mfa_mosque_sync_active( (int) $m['_ID'] );
		$key    = isset( $report[ $result ] ) ? $result : 'skipped';
		$report[ $key ]++;
	}

	return $report;
}
