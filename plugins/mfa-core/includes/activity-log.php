<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member activity log — real replacement for the old /admin/member/info/
 * "Activities" tab, whose JetEngine wp_jet_cct_activity data source turned
 * out to be dead (see the memory note this was resumed from): its writer
 * had zero live call sites, and the closer-sounding member_activity_update()
 * was never actually defined anywhere, only referenced in a commented-out
 * line. This is a plain custom table (dbDelta, not a JetEngine CCT) per the
 * project's standing rule that any genuinely new data-storage need goes
 * through a hand-written wp_mfa_* table, same pattern as niz-wa's wp_nwa_*
 * tables.
 */

function mfa_activity_table() {
	global $wpdb;
	return $wpdb->prefix . 'mfa_member_activity';
}

/**
 * Version-gated so this also runs correctly for a plugin that's already
 * active (register_activation_hook() only fires on a fresh activation, not
 * when new code lands under an already-running plugin — same reasoning as
 * NWA_Roles::activate() being re-run on every load in niz-wa.php). dbDelta()
 * itself is idempotent, but the option check avoids running it on every
 * single page load.
 */
define( 'MFA_ACTIVITY_TABLE_VERSION', '1.0' );

add_action( 'plugins_loaded', 'mfa_activity_maybe_create_table' );
function mfa_activity_maybe_create_table() {
	if ( get_option( 'mfa_activity_table_version' ) === MFA_ACTIVITY_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table = mfa_activity_table();
	$cc    = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		type VARCHAR(50) NOT NULL,
		description VARCHAR(255) NOT NULL,
		meta LONGTEXT DEFAULT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY created_at (created_at)
	) {$cc};" );

	update_option( 'mfa_activity_table_version', MFA_ACTIVITY_TABLE_VERSION );
}

/**
 * @param int    $user_id
 * @param string $type        Short machine key, e.g. 'login', 'points', 'registration'.
 * @param string $description Human-readable text shown in the admin Activity tab.
 * @param array  $meta        Optional extra context, stored as JSON.
 */
function mfa_log_activity( $user_id, $type, $description, $meta = array() ) {
	global $wpdb;

	$wpdb->insert(
		mfa_activity_table(),
		array(
			'user_id'     => (int) $user_id,
			'type'        => sanitize_key( $type ),
			'description' => sanitize_text_field( $description ),
			'meta'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'created_at'  => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);
}

function mfa_get_member_activity( $user_id, $limit = 100 ) {
	global $wpdb;
	$table = mfa_activity_table();

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
		$user_id,
		$limit
	), ARRAY_A );
}

/**
 * Login is the one event with no existing single choke point to hook into
 * (unlike registration/verification, which all already funnel through
 * mfa_award_points() — see the hook added there in barakah.php).
 */
add_action( 'wp_login', 'mfa_log_activity_on_login', 10, 2 );
function mfa_log_activity_on_login( $user_login, $user ) {
	mfa_log_activity( $user->ID, 'login', 'Logged in' );
}
