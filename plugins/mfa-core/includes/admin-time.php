<?php
/**
 * Which date columns are GMT, which are site-local, and how to compare
 * either one against a day the staff picked.
 *
 * This exists because /admin/ reads from tables that disagree about the
 * clock, and the disagreement is invisible until a count is wrong:
 *
 *   GMT    wp_users.user_registered   (wp_insert_user writes GMT)
 *          wp_nwa_messages.*          (the 24h window depends on it)
 *          wp_nwa_conversations.*
 *   LOCAL  jet_cct_*.cct_created / cct_modified / registered
 *          the activity log
 *          jet_cct_contact_us
 *          wp_posts.post_date
 *
 * The site runs Asia/Kuala_Lumpur, UTC+8, so a GMT column compared against
 * a locally-chosen date puts the day boundary at 8am instead of midnight.
 * "Today" then means 08:00 today to 08:00 tomorrow, and for the eight hours
 * after midnight it means YESTERDAY - which is exactly when an overnight
 * signup would go missing from the dashboard.
 *
 * Note the offset is applied to the COLUMN, not subtracted from the date
 * the user typed. Both fix a range, but only shifting the column also fixes
 * GROUP BY DATE_FORMAT(...) bucketing, and the reports chart groups by
 * month. Doing it one way here keeps the range and the buckets agreeing.
 *
 * Neither helper is a general-purpose display converter - use
 * get_date_from_gmt() for that. These are for SQL only.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps a GMT column in the offset that turns it into site-local time, for
 * use inside a WHERE or GROUP BY.
 *
 * Returns the column untouched on a UTC site, so the SQL stays readable
 * where there is nothing to correct.
 *
 * Uses DATE_ADD with a MINUTE interval rather than CONVERT_TZ because
 * CONVERT_TZ returns NULL when MySQL's named-timezone tables have not been
 * loaded - a silent failure that would empty every count rather than
 * shifting it. Minutes rather than hours because gmt_offset is fractional
 * in some zones (5.5, 5.75), and this helper should not be the reason a
 * future deployment is half an hour out.
 *
 * The interval is cast to int and interpolated directly: it is a SQL
 * fragment, so it cannot be passed through $wpdb->prepare() as a value.
 *
 * @param string $column Bare column reference, e.g. 'u.user_registered'.
 * @return string SQL expression evaluating to site-local time.
 */
function mfa_admin_local_sql( $column ) {
	$minutes = (int) round( (float) get_option( 'gmt_offset', 0 ) * 60 );

	if ( 0 === $minutes ) {
		return $column;
	}

	return sprintf( 'DATE_ADD(%s, INTERVAL %d MINUTE)', $column, $minutes );
}

/**
 * Today's date as the staff reading the screen would write it.
 *
 * gmdate('Y-m-d') is the trap this replaces: it is correct on a UTC site
 * and silently one day behind here every night between midnight and 8am.
 *
 * @return string Y-m-d in site time.
 */
function mfa_admin_today_local() {
	return current_time( 'Y-m-d' );
}
