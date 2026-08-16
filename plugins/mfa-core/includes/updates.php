<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for [run-update] (widgets/run-update.php) - the standing "run any
 * pending DB schema change or one-off data fix" page for administrators.
 * Each entry is a self-contained unit:
 *
 *   'key'       => a unique slug, used to route the AJAX batch call.
 *   'label'     => what's shown to the admin.
 *   'is_done'   => callable, no args, returns bool - is there nothing left
 *                  to do? Checked on page load to decide whether to show
 *                  "No Update" or the Start button.
 *   'run_batch' => callable, no args, does ONE small chunk of work and
 *                  returns array( 'done' => bool, 'progress' => string ).
 *
 * The shortcode's JS calls run_batch() repeatedly - one AJAX round trip per
 * call - until it reports done. That single mechanism covers an instant
 * one-shot fix (done on the very first call) and a slow, rate-limited job
 * (many calls, each one small enough to stay inside any PHP execution time
 * limit) without needing two different code paths: a fast fix's run_batch()
 * just does everything and returns done immediately. A slow job's state
 * lives entirely in the DB (e.g. a WHERE ... state IS NULL count), not in
 * the browser, so closing the tab mid-run and coming back later just
 * resumes from wherever the data actually is.
 *
 * Add a new entry here whenever a future DB/process change needs a human to
 * trigger it, instead of writing a one-off script or asking for a manual
 * WP-CLI run.
 */
function mfa_updates_registry() {
	return array(
		array(
			'key'       => 'geohash_state_backfill_api',
			'label'     => 'Backfill listing state for remaining countries (reverse-geocode via Nominatim)',
			'is_done'   => 'mfa_update_state_backfill_is_done',
			'run_batch' => 'mfa_update_state_backfill_run_batch',
		),
	);
}

/**
 * Remaining rows needing mfa_geohash_backfill_state_api()'s reverse-geocode
 * fallback (see geohash-crawl.php) - almost entirely non-Malaysia countries,
 * since Malaysia itself is already fully backfilled (see
 * [[project_places_hub]] Phase 3). This was ~136K rows when last measured;
 * genuinely slow at Nominatim's ~1 req/sec pace, meant to be run in
 * sessions over time via this page, not necessarily finished in one sitting.
 */
function mfa_update_state_backfill_remaining() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_mosque WHERE ( state IS NULL OR state = '' ) AND latitude != 0 AND longitude != 0" )
		+ (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_business WHERE ( state IS NULL OR state = '' ) AND latitude != 0 AND longitude != 0" );
}

function mfa_update_state_backfill_is_done() {
	return 0 === mfa_update_state_backfill_remaining();
}

/**
 * One click-driven AJAX round trip's worth of work. Kept small (5 rows)
 * so a single batch - including Nominatim's sleep(1)-per-row pacing plus
 * network latency - stays well inside any PHP execution time limit,
 * roughly 5-8s per call.
 */
function mfa_update_state_backfill_run_batch() {
	if ( ! function_exists( 'mfa_geohash_backfill_state_api' ) ) {
		return array( 'done' => true, 'progress' => '' );
	}

	mfa_geohash_backfill_state_api( true, 5 );
	$remaining = mfa_update_state_backfill_remaining();

	return array(
		'done'     => 0 === $remaining,
		'progress' => 0 === $remaining ? '' : number_format_i18n( $remaining ) . ' rows remaining',
	);
}
