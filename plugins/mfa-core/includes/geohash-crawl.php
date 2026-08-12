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

	$report = array(
		'queued_found' => count( $cells ),
		'processed'    => 0,
		'mosque_new'   => 0,
		'business_new' => 0,
		'stopped'      => '',
	);

	foreach ( $cells as $cell ) {
		$res = mfa_geohash_crawl_cell( $cell );

		if ( is_wp_error( $res ) ) {
			$report['stopped'] = $res->get_error_message();
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

		$report['processed']++;
		$report['mosque_new']   += $res['mosque_new'];
		$report['business_new'] += $res['business_new'];

		usleep( 300000 ); // 0.3s between cells - gentle on Serper's rate limit
	}

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

/**
 * WP-CLI:
 *   wp mfa geohash-crawl --country=ID --limit=20
 *   wp mfa geohash-queue --country=ID [--limit=500]
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
}
