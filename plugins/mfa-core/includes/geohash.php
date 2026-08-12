<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geohash coverage grid (wp_mfa_geohash) - the crawl queue + coverage index
 * for the Serper-based mosque / halal-business directory build.
 *
 * One row per precision-6 geohash cell (~1 km), seeded from wp_jet_cct_cities
 * and folded with the counts of mosques / businesses we already hold in that
 * cell. `status` (New -> Pending -> Done) is the crawl-queue authority:
 *   - New     : seeded, not yet queued.
 *   - Pending : queued for crawl (a country rollout, or a visitor whose
 *               location fell on this cell).
 *   - Done    : crawled - `mosque` / `business` hold the found counts
 *               (0 = crawled but none found, so it is never re-crawled).
 * The cron crawler drains status='Pending', runs one Serper Maps search from
 * the cell centre (latitude/longitude), upserts each place by place_id, writes
 * the counts and flips the cell to Done. Locations outside the seed countries
 * are inserted on demand (seed_source='visitor', status='Pending') the first
 * time a visitor from that area opens the app.
 *
 * Plain custom table (dbDelta, not a JetEngine CCT) per the project's standing
 * rule that new storage is a hand-written wp_mfa_* table - same pattern as
 * mfa_member_activity (see activity-log.php) and niz-wa's wp_nwa_* tables.
 */

function mfa_geohash_table() {
	global $wpdb;
	return $wpdb->prefix . 'mfa_geohash';
}

/**
 * Version-gated so this runs for an already-active plugin when new code lands
 * (register_activation_hook only fires on a fresh activation). dbDelta() is
 * idempotent; the option check just avoids running it on every page load.
 */
define( 'MFA_GEOHASH_TABLE_VERSION', '1.0' );

add_action( 'plugins_loaded', 'mfa_geohash_maybe_create_table' );
function mfa_geohash_maybe_create_table() {
	if ( get_option( 'mfa_geohash_table_version' ) === MFA_GEOHASH_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table = mfa_geohash_table();
	$cc    = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		geohash VARCHAR(12) NOT NULL,
		latitude DECIMAL(9,6) NOT NULL,
		longitude DECIMAL(9,6) NOT NULL,
		country_code CHAR(2) DEFAULT NULL,
		country VARCHAR(64) DEFAULT NULL,
		status VARCHAR(10) NOT NULL DEFAULT 'New',
		mosque INT DEFAULT NULL,
		business INT DEFAULT NULL,
		mosque_crawled_at DATETIME DEFAULT NULL,
		business_crawled_at DATETIME DEFAULT NULL,
		seed_source VARCHAR(16) DEFAULT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY uq_geohash (geohash),
		KEY idx_status (status),
		KEY idx_country_status (country_code, status)
	) {$cc};" );

	update_option( 'mfa_geohash_table_version', MFA_GEOHASH_TABLE_VERSION );
}

/**
 * Standard geohash (base32) encode - lat/lng -> geohash string. Used to place
 * a crawled mosque/business into its cell (precision 9) and to resolve a
 * visitor's coordinates to a coverage cell (precision 6). Kept dependency-free
 * (no library) - this is the same algorithm the cities/mosque geohashes were
 * generated with, verified round-trip against the US seed.
 */
function mfa_geohash_encode( $lat, $lng, $precision = 6 ) {
	$base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
	$lat_i  = array( -90.0, 90.0 );
	$lng_i  = array( -180.0, 180.0 );
	$bits   = array( 16, 8, 4, 2, 1 );
	$hash   = '';
	$bit    = 0;
	$ch     = 0;
	$even   = true;

	while ( strlen( $hash ) < $precision ) {
		if ( $even ) {
			$mid = ( $lng_i[0] + $lng_i[1] ) / 2;
			if ( $lng >= $mid ) { $ch |= $bits[ $bit ]; $lng_i[0] = $mid; } else { $lng_i[1] = $mid; }
		} else {
			$mid = ( $lat_i[0] + $lat_i[1] ) / 2;
			if ( $lat >= $mid ) { $ch |= $bits[ $bit ]; $lat_i[0] = $mid; } else { $lat_i[1] = $mid; }
		}
		$even = ! $even;
		if ( $bit < 4 ) {
			$bit++;
		} else {
			$hash .= $base32[ $ch ];
			$bit = 0;
			$ch  = 0;
		}
	}
	return $hash;
}

/**
 * Geohash decode -> cell centre array( 'lat' => float, 'lng' => float ).
 */
function mfa_geohash_decode( $hash ) {
	$base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
	$lat_i  = array( -90.0, 90.0 );
	$lng_i  = array( -180.0, 180.0 );
	$even   = true;
	$len    = strlen( $hash );

	for ( $i = 0; $i < $len; $i++ ) {
		$cd = strpos( $base32, $hash[ $i ] );
		for ( $z = 4; $z >= 0; $z-- ) {
			$bit = ( $cd >> $z ) & 1;
			if ( $even ) {
				$mid = ( $lng_i[0] + $lng_i[1] ) / 2;
				if ( $bit ) { $lng_i[0] = $mid; } else { $lng_i[1] = $mid; }
			} else {
				$mid = ( $lat_i[0] + $lat_i[1] ) / 2;
				if ( $bit ) { $lat_i[0] = $mid; } else { $lat_i[1] = $mid; }
			}
			$even = ! $even;
		}
	}
	return array(
		'lat' => ( $lat_i[0] + $lat_i[1] ) / 2,
		'lng' => ( $lng_i[0] + $lng_i[1] ) / 2,
	);
}
