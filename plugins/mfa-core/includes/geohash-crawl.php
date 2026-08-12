<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serper-based crawl engine for the geohash coverage grid (wp_mfa_geohash,
 * see geohash.php). Drains status='Pending' cells, runs a Serper Maps search
 * from each cell centre for mosques (q=mosque) and halal businesses (q=halal),
 * upserts each returned place by place_id into wp_jet_cct_mosque /
 * wp_jet_cct_business (+ its CPT), writes the found counts back onto the cell
 * and flips it to Done. New records land as listing_status='New' so they enter
 * the existing lazy "Click to Update" AI-generation flow (they are NOT
 * AI-generated here - crawling is cheap, generation is on-demand + a small
 * daily batch, per project decision).
 *
 * Key handling: mfa_serper_key() reads the MFA_SERPER_API_KEY wp-config
 * constant first, then a mfa_serper_api_key option - never hardcoded (the old
 * enaizi/xbot.php had the key inline; not carried over).
 *
 * Hosting note: this host (LiteSpeed) kills fire-and-forget loopback requests,
 * so each cell is processed synchronously (blocking Serper call) inside the
 * batch - same reasoning as niz-wa's synchronous webhook handling. Batches are
 * kept small and driven by WP-CLI / a nonce'd admin AJAX trigger / (later) a
 * throttled cron, never an unbounded loop.
 */

/**
 * Serper API key - constant (wp-config) wins, DB option is the fallback so it
 * can be set without a wp-config edit. Empty string if unconfigured.
 */
function mfa_serper_key() {
	if ( defined( 'MFA_SERPER_API_KEY' ) && '' !== (string) constant( 'MFA_SERPER_API_KEY' ) ) {
		return (string) constant( 'MFA_SERPER_API_KEY' );
	}
	return (string) get_option( 'mfa_serper_api_key', '' );
}

/* -------------------------------------------------------------------------
 * Config, credit budget, pause state, notifications. All stored as
 * mfa_crawl_* options so the pipeline is controllable from the admin panel
 * (and reproducible on live - no DB push, options are set per-site).
 * ---------------------------------------------------------------------- */

function mfa_crawl_opt( $key, $default = '' ) {
	return get_option( 'mfa_crawl_' . $key, $default );
}
function mfa_crawl_set( $key, $value ) {
	update_option( 'mfa_crawl_' . $key, $value );
}

// Serper Maps costs ~3 credits per cell (one mosque + one halal search).
// Budget + used-counter are tracked locally so we can pause *before* an
// out-of-credits error and show progress; Serper's own "Not enough credits"
// (HTTP 400) is the authoritative hard stop.
function mfa_crawl_credits_per_cell()  { return max( 1, (int) mfa_crawl_opt( 'credits_per_cell', 3 ) ); }
function mfa_crawl_credits_budget()    { return (int) mfa_crawl_opt( 'credits_budget', 164752 ); }
function mfa_crawl_credits_used()      { return (int) mfa_crawl_opt( 'credits_used', 0 ); }
function mfa_crawl_credits_remaining() { return max( 0, mfa_crawl_credits_budget() - mfa_crawl_credits_used() ); }
function mfa_crawl_credits_add( $n )   { mfa_crawl_set( 'credits_used', mfa_crawl_credits_used() + (int) $n ); }

function mfa_crawl_is_paused() { return (bool) mfa_crawl_opt( 'paused', 0 ); }
function mfa_crawl_pause( $reason ) {
	mfa_crawl_set( 'paused', 1 );
	mfa_crawl_set( 'pause_reason', $reason );
	mfa_crawl_notify(
		'Crawler paused: ' . $reason,
		"The Masjid4All directory crawler has PAUSED.\n\nReason: {$reason}\n\nCredits used: " . mfa_crawl_credits_used() . ' / ' . mfa_crawl_credits_budget() . "\n\nResume from wp-admin: Admin -> Directory Crawler, once resolved (e.g. top up Serper credits and raise the budget)."
	);
}
function mfa_crawl_resume() {
	mfa_crawl_set( 'paused', 0 );
	delete_option( 'mfa_crawl_pause_reason' );
}

// Email alerts on error / out-of-credits. De-duped per-reason for an hour so a
// repeatedly-firing cron can't spam. Recipient filterable, defaults to the
// site admin email.
function mfa_crawl_notify( $subject, $body ) {
	$to = apply_filters( 'mfa_crawl_notify_email', get_option( 'admin_email' ) );
	if ( ! $to ) {
		return;
	}
	$k = 'mfa_crawl_notified_' . md5( $subject );
	if ( get_transient( $k ) ) {
		return;
	}
	set_transient( $k, 1, HOUR_IN_SECONDS );
	wp_mail( $to, '[Masjid4All Crawler] ' . $subject, $body );
}

/**
 * One Serper Maps search from a point. Returns an array of place arrays, or a
 * WP_Error (missing key, HTTP error incl. "Not enough credits", transport
 * failure) - callers must bubble errors up and NOT mark the cell Done.
 */
function mfa_serper_maps( $query, $lat, $lng, $zoom = 13 ) {
	$key = mfa_serper_key();
	if ( '' === $key ) {
		return new WP_Error( 'mfa_no_serper_key', 'Serper API key not configured (define MFA_SERPER_API_KEY in wp-config.php).' );
	}

	$resp = wp_remote_post( 'https://google.serper.dev/maps', array(
		'headers' => array(
			'X-API-KEY'    => $key,
			'Content-Type' => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'q'  => $query,
			'll' => sprintf( '@%s,%s,%dz', $lat, $lng, (int) $zoom ),
		) ),
		'timeout' => 25,
	) );

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( 200 !== $code ) {
		$msg = isset( $data['message'] ) ? $data['message'] : ( 'Serper HTTP ' . $code );
		return new WP_Error( 'mfa_serper_http', $msg, array( 'status' => $code ) );
	}

	return ( isset( $data['places'] ) && is_array( $data['places'] ) ) ? $data['places'] : array();
}

/**
 * Normalise one Serper place into our CCT field set.
 */
function mfa_geohash_map_place( $p ) {
	$lat = isset( $p['latitude'] ) ? $p['latitude'] : '';
	$lng = isset( $p['longitude'] ) ? $p['longitude'] : '';

	$opening = '';
	if ( ! empty( $p['openingHours'] ) && is_array( $p['openingHours'] ) ) {
		foreach ( $p['openingHours'] as $day => $hrs ) {
			$val      = is_array( $hrs ) ? implode( ', ', $hrs ) : $hrs;
			$opening .= '<li>' . esc_html( $day ) . ': ' . esc_html( $val ) . '</li>';
		}
	}

	$types = '';
	if ( isset( $p['types'] ) ) {
		$types = is_array( $p['types'] ) ? implode( ', ', array_map( 'sanitize_text_field', $p['types'] ) ) : sanitize_text_field( $p['types'] );
	}

	return array(
		'place_id'     => isset( $p['placeId'] ) ? sanitize_text_field( $p['placeId'] ) : '',
		'name'         => isset( $p['title'] ) ? sanitize_text_field( $p['title'] ) : '',
		'address'      => isset( $p['address'] ) ? sanitize_text_field( $p['address'] ) : '',
		'latitude'     => $lat,
		'longitude'    => $lng,
		'rating'       => isset( $p['rating'] ) ? $p['rating'] : '',
		'rating_count' => isset( $p['ratingCount'] ) ? $p['ratingCount'] : '',
		'type'         => isset( $p['type'] ) ? sanitize_text_field( $p['type'] ) : '',
		'types'        => $types,
		'website'      => isset( $p['website'] ) ? esc_url_raw( $p['website'] ) : '',
		'phone'        => isset( $p['phoneNumber'] ) ? sanitize_text_field( $p['phoneNumber'] ) : '',
		'opening_hours'=> $opening,
		'geohash'      => ( '' !== $lat && '' !== $lng ) ? mfa_geohash_encode( (float) $lat, (float) $lng, 9 ) : '',
	);
}

/**
 * Upsert one place into the mosque or business directory, dedup by place_id.
 * Returns 'new' | 'existing' | 'skip'.
 *
 * @param string $type            'mosque' or 'business'.
 * @param array  $p               Raw Serper place.
 * @param string $country_fallback Cell country (Serper doesn't return a clean country).
 */
function mfa_geohash_upsert_place( $type, $p, $country_fallback = '' ) {
	global $wpdb;

	$f = mfa_geohash_map_place( $p );
	if ( '' === $f['place_id'] || '' === $f['name'] ) {
		return 'skip';
	}

	$is_mosque = ( 'mosque' === $type );
	$table     = $wpdb->prefix . ( $is_mosque ? 'jet_cct_mosque' : 'jet_cct_business' );
	$post_type = $is_mosque ? 'masjid' : 'business';

	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT _ID FROM {$table} WHERE place_id = %s LIMIT 1", $f['place_id'] ) );
	if ( $existing ) {
		// Already listed - refresh a few volatile fields, never duplicate.
		$wpdb->update(
			$table,
			array(
				'rating'       => $f['rating'],
				'rating_count' => $f['rating_count'],
				'phone'        => $f['phone'],
				'website'      => $f['website'],
				'cct_modified' => current_time( 'mysql' ),
			),
			array( '_ID' => $existing )
		);
		return 'existing';
	}

	$now = current_time( 'mysql' );
	$wpdb->insert(
		$table,
		array(
			'name'           => $f['name'],
			'place_id'       => $f['place_id'],
			'latitude'       => $f['latitude'],
			'longitude'      => $f['longitude'],
			'address'        => $f['address'],
			'country'        => $country_fallback,
			'phone'          => $f['phone'],
			'website'        => $f['website'],
			'rating'         => $f['rating'],
			'rating_count'   => $f['rating_count'],
			'types'          => $f['types'],
			'type'           => $f['type'],
			'opening_hours'  => $f['opening_hours'],
			'geohash'        => $f['geohash'],
			'listing_status' => 'New',
			'cct_author_id'  => 0,
			'cct_created'    => $now,
			'cct_modified'   => $now,
		)
	);
	$cct_id = (int) $wpdb->insert_id;
	if ( ! $cct_id ) {
		return 'skip';
	}

	$post_id = wp_insert_post( array(
		'post_type'   => $post_type,
		'post_status' => 'publish',
		'post_title'  => $f['name'],
		'post_author' => 0,
		'meta_input'  => array(
			'item_id'  => $cct_id,
			'place_id' => $f['place_id'],
		),
	) );

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		$wpdb->update(
			$table,
			array(
				'cct_single_post_id' => $post_id,
				'page_url'           => get_permalink( $post_id ),
			),
			array( '_ID' => $cct_id )
		);
	}

	return 'new';
}

/**
 * Crawl one coverage cell: mosque search + business search from the cell
 * centre. Returns a summary array, or a WP_Error (caller must then leave the
 * cell Pending so it retries).
 */
function mfa_geohash_crawl_cell( $cell ) {
	$lat     = $cell['latitude'];
	$lng     = $cell['longitude'];
	$country = isset( $cell['country'] ) ? $cell['country'] : '';

	$out = array(
		'geohash'        => $cell['geohash'],
		'mosque_found'   => 0,
		'mosque_new'     => 0,
		'business_found' => 0,
		'business_new'   => 0,
	);

	$mosques = mfa_serper_maps( 'mosque', $lat, $lng );
	if ( is_wp_error( $mosques ) ) {
		return $mosques;
	}
	foreach ( $mosques as $place ) {
		if ( 'new' === mfa_geohash_upsert_place( 'mosque', $place, $country ) ) {
			$out['mosque_new']++;
		}
	}
	$out['mosque_found'] = count( $mosques );

	$biz = mfa_serper_maps( 'halal', $lat, $lng );
	if ( is_wp_error( $biz ) ) {
		return $biz;
	}
	foreach ( $biz as $place ) {
		if ( 'new' === mfa_geohash_upsert_place( 'business', $place, $country ) ) {
			$out['business_new']++;
		}
	}
	$out['business_found'] = count( $biz );

	return $out;
}

/**
 * Process a batch of Pending cells (optionally scoped to a country_code).
 * Stops immediately on the first API error (e.g. out of credits), leaving the
 * remaining cells Pending for a later run. Returns a report array.
 */
function mfa_geohash_crawl_run_batch( $country_code = '', $limit = 20 ) {
	global $wpdb;
	$g     = mfa_geohash_table();
	$limit = max( 1, min( 200, (int) $limit ) );

	$report = array(
		'queued_found'      => 0,
		'processed'         => 0,
		'mosque_new'        => 0,
		'business_new'      => 0,
		'stopped'           => '',
		'credits_used'      => mfa_crawl_credits_used(),
		'credits_remaining' => mfa_crawl_credits_remaining(),
	);

	if ( mfa_crawl_is_paused() ) {
		$report['stopped'] = 'Paused: ' . mfa_crawl_opt( 'pause_reason', 'manually paused' );
		return $report;
	}

	if ( $country_code ) {
		$cells = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$g} WHERE status = 'Pending' AND country_code = %s ORDER BY id LIMIT %d",
			strtoupper( $country_code ),
			$limit
		), ARRAY_A );
	} else {
		$cells = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$g} WHERE status = 'Pending' ORDER BY id LIMIT %d",
			$limit
		), ARRAY_A );
	}
	$report['queued_found'] = count( $cells );

	$per = mfa_crawl_credits_per_cell();

	foreach ( $cells as $cell ) {
		// Proactive budget stop - pause + notify before Serper's own hard stop.
		if ( mfa_crawl_credits_remaining() < $per ) {
			mfa_crawl_pause( 'Out of credits (budget of ' . mfa_crawl_credits_budget() . ' reached)' );
			$report['stopped'] = 'Out of credits (local budget reached)';
			break;
		}

		$res = mfa_geohash_crawl_cell( $cell );

		if ( is_wp_error( $res ) ) {
			$msg               = $res->get_error_message();
			$report['stopped'] = $msg;
			// A credit error is a real pause; anything else is a transient alert.
			if ( false !== stripos( $msg, 'credit' ) ) {
				mfa_crawl_pause( 'Serper: ' . $msg );
			} else {
				mfa_crawl_notify( 'Crawler error', "The directory crawler hit an error and stopped:\n\n{$msg}" );
			}
			break; // leave this and the rest Pending
		}

		$wpdb->update(
			$g,
			array(
				'mosque'              => $res['mosque_found'],
				'business'            => $res['business_found'],
				'status'              => 'Done',
				'mosque_crawled_at'   => current_time( 'mysql' ),
				'business_crawled_at' => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'geohash' => $cell['geohash'] )
		);

		mfa_crawl_credits_add( $per );
		$report['processed']++;
		$report['mosque_new']   += $res['mosque_new'];
		$report['business_new'] += $res['business_new'];

		usleep( 300000 ); // 0.3s between cells - gentle on Serper's rate limit
	}

	$report['credits_used']      = mfa_crawl_credits_used();
	$report['credits_remaining'] = mfa_crawl_credits_remaining();
	return $report;
}

/**
 * Queue a country for crawling: flip its New cells to Pending. Crawls both
 * mosque + business per cell, so we queue ALL New cells (not just empty ones) -
 * cells that already hold a mosque still yield halal-business coverage.
 *
 * @param string $country_code ISO alpha-2.
 * @param int    $limit        Optional cap (0 = all).
 * @return int   Rows queued.
 */
function mfa_geohash_queue_country( $country_code, $limit = 0 ) {
	global $wpdb;
	$g            = mfa_geohash_table();
	$country_code = strtoupper( sanitize_text_field( $country_code ) );
	$now          = current_time( 'mysql' );

	if ( $limit > 0 ) {
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$g} SET status = 'Pending', updated_at = %s WHERE country_code = %s AND status = 'New' ORDER BY id LIMIT %d",
			$now,
			$country_code,
			(int) $limit
		) );
	}

	return (int) $wpdb->query( $wpdb->prepare(
		"UPDATE {$g} SET status = 'Pending', updated_at = %s WHERE country_code = %s AND status = 'New'",
		$now,
		$country_code
	) );
}

/**
 * Admin-only manual "run one batch" trigger (POST to admin-ajax.php,
 * action=mfa_geohash_crawl_batch, with country + limit + nonce). A UI button
 * on the /admin/ area can wire to this later; for now it is callable directly.
 */
add_action( 'wp_ajax_mfa_geohash_crawl_batch', 'mfa_geohash_crawl_ajax' );
function mfa_geohash_crawl_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	check_ajax_referer( 'mfa_geohash_crawl', 'nonce' );

	$country = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';
	$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 20;

	wp_send_json_success( mfa_geohash_crawl_run_batch( $country, $limit ) );
}

/* -------------------------------------------------------------------------
 * Pipeline seed steps (idempotent). Codified so the whole grid can be
 * (re)built on live from the admin panel with no DB push - each reads the
 * current site's own cities / mosque / business tables.
 * ---------------------------------------------------------------------- */

/** Step 1: seed cells from wp_jet_cct_cities. Returns total cell count. */
function mfa_geohash_seed_cities() {
	global $wpdb;
	$g = mfa_geohash_table();
	$c = $wpdb->prefix . 'jet_cct_cities';
	$wpdb->query(
		"INSERT IGNORE INTO {$g} (geohash,latitude,longitude,country_code,country,status,seed_source,created_at,updated_at)
		 SELECT geohash, latitude, longitude, country_code, country, 'New', 'city', NOW(), NOW()
		 FROM {$c}
		 WHERE geohash IS NOT NULL AND geohash <> '' AND latitude IS NOT NULL AND longitude IS NOT NULL"
	);
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g}" );
}

/** Step 2: fold existing mosque + business counts into their cells. */
function mfa_geohash_seed_counts() {
	global $wpdb;
	$g = mfa_geohash_table();
	$m = $wpdb->prefix . 'jet_cct_mosque';
	$b = $wpdb->prefix . 'jet_cct_business';
	$wpdb->query(
		"INSERT INTO {$g} (geohash,latitude,longitude,country_code,country,status,mosque,seed_source,created_at,updated_at)
		 SELECT LEFT(geohash,6), AVG(latitude), AVG(longitude), NULL, MAX(country), 'New', COUNT(*), 'mosque', NOW(), NOW()
		 FROM {$m} WHERE geohash IS NOT NULL AND geohash <> '' AND latitude IS NOT NULL AND longitude IS NOT NULL
		 GROUP BY LEFT(geohash,6)
		 ON DUPLICATE KEY UPDATE mosque=VALUES(mosque), updated_at=NOW()"
	);
	$wpdb->query(
		"INSERT INTO {$g} (geohash,latitude,longitude,country_code,country,status,business,seed_source,created_at,updated_at)
		 SELECT LEFT(geohash,6), AVG(latitude), AVG(longitude), NULL, MAX(country), 'New', COUNT(*), 'business', NOW(), NOW()
		 FROM {$b} WHERE geohash IS NOT NULL AND geohash <> '' AND latitude IS NOT NULL AND longitude IS NOT NULL
		 GROUP BY LEFT(geohash,6)
		 ON DUPLICATE KEY UPDATE business=VALUES(business), updated_at=NOW()"
	);
	return array(
		'cells_with_mosque'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g} WHERE mosque IS NOT NULL" ),
		'cells_with_business' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g} WHERE business IS NOT NULL" ),
	);
}

/** Step 3: tag existing US cells + expand a 5x5 (~5km) grid around each. */
function mfa_geohash_seed_us() {
	global $wpdb;
	$g = mfa_geohash_table();
	$wpdb->query( "UPDATE {$g} SET country_code='US' WHERE country='United States'" );
	$base = $wpdb->get_col( "SELECT geohash FROM {$g} WHERE country_code='US'" );
	$clat = 180 / 32768;
	$clng = 360 / 32768;
	$new  = array();
	foreach ( $base as $bh ) {
		$c = mfa_geohash_decode( $bh );
		for ( $i = -2; $i <= 2; $i++ ) {
			for ( $j = -2; $j <= 2; $j++ ) {
				if ( 0 === $i && 0 === $j ) {
					continue;
				}
				$la = $c['lat'] + $i * $clat;
				$lo = $c['lng'] + $j * $clng;
				$nh = mfa_geohash_encode( $la, $lo, 6 );
				if ( ! isset( $new[ $nh ] ) ) {
					$new[ $nh ] = array( $la, $lo );
				}
			}
		}
	}
	$ins  = 0;
	$vals = array();
	foreach ( $new as $nh => $ll ) {
		$vals[] = $wpdb->prepare( "(%s,%f,%f,'US','United States','New',NULL,NULL,'us_grid',NOW(),NOW())", $nh, $ll[0], $ll[1] );
		if ( count( $vals ) >= 2000 ) {
			$wpdb->query( "INSERT IGNORE INTO {$g} (geohash,latitude,longitude,country_code,country,status,mosque,business,seed_source,created_at,updated_at) VALUES " . implode( ',', $vals ) );
			$ins += $wpdb->rows_affected;
			$vals = array();
		}
	}
	if ( $vals ) {
		$wpdb->query( "INSERT IGNORE INTO {$g} (geohash,latitude,longitude,country_code,country,status,mosque,business,seed_source,created_at,updated_at) VALUES " . implode( ',', $vals ) );
		$ins += $wpdb->rows_affected;
	}
	return array(
		'base_us_cells'  => count( $base ),
		'grid_inserted'  => $ins,
		'us_cells_total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g} WHERE country_code='US'" ),
	);
}

/** Snapshot for the admin panel status board. */
function mfa_geohash_crawl_status() {
	global $wpdb;
	$g      = mfa_geohash_table();
	$exists = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
		$g
	) );
	if ( ! $exists ) {
		return array( 'table_exists' => false );
	}

	$by_status = array( 'New' => 0, 'Pending' => 0, 'Done' => 0 );
	foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$g} GROUP BY status", ARRAY_A ) as $r ) {
		$by_status[ $r['status'] ] = (int) $r['c'];
	}

	$by_country = array();
	foreach ( $wpdb->get_results( "SELECT country_code, status, COUNT(*) c FROM {$g} WHERE country_code IN ('ID','GB','AU','CA','MY','SG','BN','US') GROUP BY country_code, status", ARRAY_A ) as $r ) {
		$cc = $r['country_code'];
		if ( ! isset( $by_country[ $cc ] ) ) {
			$by_country[ $cc ] = array( 'New' => 0, 'Pending' => 0, 'Done' => 0 );
		}
		$by_country[ $cc ][ $r['status'] ] = (int) $r['c'];
	}

	return array(
		'table_exists'      => true,
		'total'             => array_sum( $by_status ),
		'by_status'         => $by_status,
		'by_country'        => $by_country,
		'mosque_total'      => (int) $wpdb->get_var( "SELECT SUM(mosque) FROM {$g}" ),
		'business_total'    => (int) $wpdb->get_var( "SELECT SUM(business) FROM {$g}" ),
		'credits_used'      => mfa_crawl_credits_used(),
		'credits_budget'    => mfa_crawl_credits_budget(),
		'credits_remaining' => mfa_crawl_credits_remaining(),
		'credits_per_cell'  => mfa_crawl_credits_per_cell(),
		'paused'            => mfa_crawl_is_paused(),
		'pause_reason'      => (string) mfa_crawl_opt( 'pause_reason', '' ),
		'cron_enabled'      => (bool) mfa_crawl_opt( 'enabled', 0 ),
		'batch_size'        => (int) mfa_crawl_opt( 'batch_size', 5 ),
		'cron_country'      => (string) mfa_crawl_opt( 'country', '' ),
	);
}

/** Cron entry point (Hostinger server-cron -> `wp mfa geohash-cron`). */
function mfa_geohash_crawl_cron_tick() {
	if ( ! mfa_crawl_opt( 'enabled', 0 ) ) {
		return array( 'skipped' => 'cron disabled' );
	}
	if ( mfa_crawl_is_paused() ) {
		return array( 'skipped' => 'paused: ' . mfa_crawl_opt( 'pause_reason', '' ) );
	}
	return mfa_geohash_crawl_run_batch( (string) mfa_crawl_opt( 'country', '' ), (int) mfa_crawl_opt( 'batch_size', 5 ) );
}

/**
 * WP-CLI:
 *   wp mfa geohash-crawl --country=ID --limit=20
 *   wp mfa geohash-queue --country=ID [--limit=500]
 *   wp mfa geohash-cron           (respects the admin on/off toggle + batch size)
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mfa geohash-crawl', function ( $args, $assoc ) {
		$country = isset( $assoc['country'] ) ? $assoc['country'] : '';
		$limit   = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 20;
		$r       = mfa_geohash_crawl_run_batch( $country, $limit );
		WP_CLI::log( print_r( $r, true ) );
		if ( '' !== $r['stopped'] ) {
			WP_CLI::warning( 'Stopped early: ' . $r['stopped'] );
		}
		WP_CLI::success( sprintf( 'Processed %d cells (+%d mosques, +%d businesses).', $r['processed'], $r['mosque_new'], $r['business_new'] ) );
	} );

	WP_CLI::add_command( 'mfa geohash-queue', function ( $args, $assoc ) {
		if ( empty( $assoc['country'] ) ) {
			WP_CLI::error( '--country=XX is required.' );
		}
		$limit = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 0;
		$n     = mfa_geohash_queue_country( $assoc['country'], $limit );
		WP_CLI::success( sprintf( 'Queued %d cells in %s.', $n, strtoupper( $assoc['country'] ) ) );
	} );

	// Cron entry - what the Hostinger server-cron calls. Honours the admin
	// on/off toggle, batch size and pause state.
	WP_CLI::add_command( 'mfa geohash-cron', function () {
		$r = mfa_geohash_crawl_cron_tick();
		WP_CLI::log( print_r( $r, true ) );
	} );
}
