<?php
/**
 * Barakah points - single, correct implementation replacing three near-duplicate
 * functions found across enaizi-user and enaizi-mfa (niz_user_add_points,
 * niz_member_add_points, mfa_member_add_points), which had inconsistent bugs:
 * - niz_user_add_points()'s dedup check ignored user_id entirely (`WHERE
 *   description = %s`, no `AND user_id = %d`), so once ANY user had a row
 *   with a given description (e.g. "Welcome Bonus"), no other user could
 *   ever receive that same-named award again.
 * - niz_member_add_points() had no dedup check at all, so calling it twice
 *   for the same milestone (e.g. a shortcode re-rendering) double-awarded.
 * - All three wrote the just-inserted delta into the single-value
 *   jet_cct_member.points field instead of the recomputed running total,
 *   so that field silently drifted from the real ledger sum after the
 *   first award.
 *
 * The ledger itself (wp_jet_cct_barakah, summed per user) was already
 * correct and live - see [niz_member_barakah_points] on /member/barakah/ -
 * only the award side needed fixing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mfa_award_points( $user_id, $description, $points ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_barakah';

	// 80 awards worth 3,470 points were sitting against user_id 0 - awarded
	// to nobody and unreachable. Whatever calls this with a bad id has a bug
	// of its own, but the ledger should not absorb it silently.
	$user_id = (int) $user_id;
	if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
		error_log( 'mfa_award_points: refused award "' . $description . '" for user_id ' . $user_id );
		return array(
			'success' => false,
			'message' => 'Unknown user',
		);
	}

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT _ID FROM {$table} WHERE user_id = %d AND description = %s LIMIT 1",
			$user_id,
			$description
		)
	);
	if ( $exists ) {
		return array(
			'success' => false,
			'message' => 'Already awarded',
		);
	}

	$result = $wpdb->insert(
		$table,
		array(
			'user_id'     => (int) $user_id,
			'description' => sanitize_text_field( $description ),
			'points'      => (int) $points,
			'cct_created' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%d', '%s' )
	);

	if ( $result === false ) {
		return array(
			'success' => false,
			'message' => $wpdb->last_error,
		);
	}

	$total = mfa_get_barakah_points( $user_id );
	$rank  = mfa_get_barakah_rank( $total );

	if ( function_exists( 'mfa_log_activity' ) ) {
		mfa_log_activity( $user_id, 'points', $description . ' (+' . (int) $points . ' pts)' );
	}

	// Prospects earn points too (decision 2026-08-19) - the balance is a
	// conversion lever: "you've earned 10 Barakah points, register to keep
	// them". But they need somewhere to live. A contact Sofia created has no
	// jet_cct_member row, so the sync below silently updated nothing and the
	// points existed only in the ledger, invisible everywhere in the UI. The
	// Iqra wa Rattel prospect earned 10 points that way and could never see
	// them.
	if ( function_exists( 'niz_user_member_cct' ) ) {
		$had_row = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT _ID FROM {$wpdb->prefix}jet_cct_member WHERE user_id = %d LIMIT 1",
			$user_id
		) );

		if ( ! $had_row ) {
			niz_user_member_cct( $user_id );

			// Set the status explicitly - an empty one is worse than a wrong
			// one here, because /admin/ filters match exact values and blank
			// rows drop out of every filtered view (same trap noted in
			// niz_user_complete_registration).
			$status = get_user_meta( $user_id, 'user_status', true );
			if ( function_exists( 'niz_user_update_field' ) ) {
				niz_user_update_field(
					$user_id,
					'status',
					in_array( $status, array( 'member', 'premium' ), true ) ? 'Member' : 'Prospect'
				);
			}
		}
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'points', $total );
		niz_user_update_field( $user_id, 'rank', $rank['rank'] );
	} elseif ( function_exists( 'mfa_member_update_field' ) ) {
		mfa_member_update_field( $user_id, 'points', $total );
		mfa_member_update_field( $user_id, 'rank', $rank['rank'] );
	}

	return array(
		'success'   => true,
		'insert_id' => (int) $wpdb->insert_id,
		'total'     => $total,
	);
}

function mfa_get_barakah_points( $user_id ) {
	global $wpdb;
	$table  = $wpdb->prefix . 'jet_cct_barakah';
	$points = $wpdb->get_var(
		$wpdb->prepare( "SELECT SUM(points) FROM {$table} WHERE user_id = %d", $user_id )
	);
	return $points ? (int) $points : 0;
}

function mfa_get_barakah_rank( $total_points ) {
	if ( $total_points >= 10000 ) {
		return array(
			'rank'           => 'Diamond',
			'next_rank'      => null,
			'next_at'        => null,
			'points_to_next' => 0,
		);
	}
	if ( $total_points >= 5000 ) {
		return array(
			'rank'           => 'Platinum',
			'next_rank'      => 'Diamond',
			'next_at'        => 10000,
			'points_to_next' => 10000 - $total_points,
		);
	}
	if ( $total_points >= 2000 ) {
		return array(
			'rank'           => 'Gold',
			'next_rank'      => 'Platinum',
			'next_at'        => 5000,
			'points_to_next' => 5000 - $total_points,
		);
	}
	if ( $total_points >= 500 ) {
		return array(
			'rank'           => 'Silver',
			'next_rank'      => 'Gold',
			'next_at'        => 2000,
			'points_to_next' => 2000 - $total_points,
		);
	}
	return array(
		'rank'           => 'Bronze',
		'next_rank'      => 'Silver',
		'next_at'        => 500,
		'points_to_next' => 500 - $total_points,
	);
}

/**
 * Whether $user_id already has a ledger row for $description - lets the
 * member dashboard checklist (includes/widgets/member-dashboard.php) show
 * earned/pending state without a second, separate "is this done" data
 * source that could drift from what was actually awarded.
 */
function mfa_has_barakah_award( $user_id, $description ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_barakah';
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT _ID FROM {$table} WHERE user_id = %d AND description = %s LIMIT 1",
			$user_id,
			$description
		)
	);
	return (bool) $exists;
}

// "Complete your profile" funnel step points are now awarded directly from
// mfa_ajax_update_profile() in includes/widgets/member-account-modals.php,
// which replaced the dashboard's Fluent Form 17 embed 2026-08-08 (avoiding
// a 3rd-party form-plugin dependency). No fluentform/before_insert_submission
// hook needed here anymore.
