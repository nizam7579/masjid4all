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

// How many /admin/crawler/start/ tabs may actually be doing DB/Serper work
// at once - running many tabs in parallel (one per country) can exceed the
// host's MySQL max_connections and produce "Error establishing a database
// connection" for the whole site, not just the crawler. Default is
// deliberately conservative for shared/cloud hosting.
function mfa_crawl_max_concurrent() { return max( 1, (int) mfa_crawl_opt( 'max_concurrent', 4 ) ); }

/**
 * Try to claim one of the N concurrency slots using MySQL's own named locks
 * (GET_LOCK), not a manually-tracked counter - a counter can get stuck
 * elevated forever if a request dies mid-way (fatal error, host timeout
 * kill) without decrementing it. A GET_LOCK is tied to the DB connection
 * itself and is released automatically by MySQL when that connection closes,
 * however the request ended, so it can never leak.
 *
 * @return string|false The lock name to pass to mfa_crawl_release_slot(), or
 *                       false if every slot is currently taken.
 */
function mfa_crawl_try_acquire_slot() {
	global $wpdb;
	$max = mfa_crawl_max_concurrent();
	for ( $i = 0; $i < $max; $i++ ) {
		$lock_name = 'mfa_crawl_slot_' . $i;
		// Non-blocking (0s timeout) - either it's free right now or it isn't.
		$got = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );
		if ( 1 === $got ) {
			return $lock_name;
		}
	}
	return false;
}

function mfa_crawl_release_slot( $lock_name ) {
	global $wpdb;
	if ( $lock_name ) {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}
}
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
 * Best-effort city guess from a Serper place's flat address string - Serper
 * doesn't return a separately parsed city/locality field, only one address
 * line, so this is approximate: drops a trailing country-name segment and a
 * trailing postal-code-only segment, then takes whatever's left at the end.
 * Works reasonably for "Street, City, Country" addresses; for "Street, City,
 * State, Country" ones it sometimes lands on the state instead. Good enough
 * for SEO title/keyword targeting, not meant to be authoritative.
 */
function mfa_geohash_guess_city( $address, $country ) {
	if ( '' === $address ) {
		return '';
	}
	$parts = array_values( array_filter( array_map( 'trim', explode( ',', $address ) ) ) );
	if ( empty( $parts ) ) {
		return '';
	}

	$last = end( $parts );
	if ( $country && ( false !== stripos( $last, $country ) || false !== stripos( $country, $last ) ) ) {
		array_pop( $parts );
	}

	$last = end( $parts );
	if ( $last && preg_match( '/^[\d\s\-]+$/', $last ) ) {
		array_pop( $parts );
	}

	$city = end( $parts );
	return $city ? sanitize_text_field( $city ) : '';
}

/**
 * The real country a Serper place belongs to, read from the place's OWN
 * address rather than trusted from the search cell's nominal country. A
 * geohash cell's Serper search (q=mosque / q=halal, zoom 13) is NOT
 * country-aware - a cell right on a border can and does return real places
 * physically in the neighbouring country (found 2026-08-13: a mosque in
 * Dayr Yusuf, Jordan returned by a border-adjacent Israel-tagged cell,
 * inserted with country='Israel' because mfa_geohash_upsert_place() used
 * to just take the cell's own country on faith).
 *
 * Reuses mfa_get_country_list() (member-account-modals.php) as the set of
 * known country names. Checks comma-separated address segments from the
 * end first (country is conventionally the last segment), then falls back
 * to a full-string scan (rightmost match) for addresses that don't cleanly
 * comma-split. Returns '' when no known country name appears anywhere -
 * caller should fall back to the search cell's own country in that case,
 * since many Serper addresses are just "City" or "Street, City" with no
 * country suffix at all.
 */
function mfa_geohash_guess_country( $address ) {
	if ( '' === $address || ! function_exists( 'mfa_get_country_list' ) ) {
		return '';
	}

	$countries = mfa_get_country_list();
	$parts     = array_values( array_filter( array_map( 'trim', explode( ',', $address ) ) ) );

	for ( $i = count( $parts ) - 1; $i >= 0; $i-- ) {
		foreach ( $countries as $c ) {
			if ( 0 === strcasecmp( $parts[ $i ], $c ) ) {
				return $c;
			}
		}
	}

	$best_pos = -1;
	$best     = '';
	foreach ( $countries as $c ) {
		$pos = stripos( $address, $c );
		if ( false !== $pos && $pos > $best_pos ) {
			$best_pos = $pos;
			$best     = $c;
		}
	}

	return $best;
}

/**
 * Lightweight, non-AI default content + SEO meta for a freshly crawled
 * listing. New/Pending listings are now included in directory search/
 * listing pages (not just Approved), so a crawled record needs to be
 * findable and indexable right away instead of sitting blank until someone
 * clicks "Click to Update" to run the full AI generation. Mirrors the AI
 * prompt's title/keyword pattern (enaizi/mosque.php's mosques_perplexity(),
 * "((Name)) | Mosque in ((City)), ((Country))" + a comma-separated keyword
 * list) so the SEO shape stays consistent whether or not a listing has been
 * AI-generated yet.
 *
 * @param string $type    'mosque' or 'business'.
 * @return array 'content' (post_content HTML), 'title', 'description', 'keywords'.
 */
function mfa_geohash_default_seo( $type, $name, $address, $city, $country ) {
	$is_mosque = ( 'mosque' === $type );
	$noun      = $is_mosque ? 'Mosque' : 'Halal business';
	$place     = $city ? ( $country ? $city . ', ' . $country : $city ) : $country;

	$title = $place ? "{$name} | {$noun} in {$place}" : $name;
	if ( mb_strlen( $title ) > 70 ) {
		$title = mb_substr( $title, 0, 67 ) . '...';
	}

	$description = $place
		? "{$name} is a " . strtolower( $noun ) . " in {$place}. Find location and contact details on Masjid4All."
		: "{$name}. Find location and contact details on Masjid4All.";

	$keywords = array( $name );
	if ( $city ) {
		$keywords[] = strtolower( $noun ) . ' in ' . $city;
	}
	if ( $country ) {
		$keywords[] = strtolower( $noun ) . ' in ' . $country;
	}

	$content = '<p>' . esc_html( $name ) . ' is a ' . strtolower( $noun ) . ( $place ? ' located in ' . esc_html( $place ) : '' ) . '.</p>';
	if ( $address ) {
		$content .= '<p><strong>Address:</strong> ' . esc_html( $address ) . '</p>';
	}
	$content .= '<p>This listing was added automatically and has not been fully reviewed yet. If you manage this location, click &ldquo;Click to Update&rdquo; below to add more details.</p>';

	return array(
		'content'     => $content,
		'title'       => $title,
		'description' => $description,
		'keywords'    => implode( ', ', $keywords ),
	);
}

/**
 * The Serper Maps "halal" query (used for the business search) is a loose
 * text/keyword match, not an actual halal-certification filter - it happily
 * returns places whose own name says the opposite (e.g. a listing titled
 * "... Non-Halal ..." or "... (Not Halal) ..."), which would otherwise get
 * auto-added to the directory as if it were a verified halal business. This
 * is a name-only phrase check per explicit user direction (2026-08-13), not
 * a full cuisine/category classifier.
 */
function mfa_geohash_is_non_halal( $name ) {
	return (bool) preg_match( '/\bnon[\s\-]*halal\b|\bnot\s+halal\b|\bno\s+halal\b/i', $name );
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

	if ( 'business' === $type && mfa_geohash_is_non_halal( $f['name'] ) ) {
		return 'skip';
	}

	// Prefer the country named in the place's own address (see
	// mfa_geohash_guess_country()'s docblock for why the search cell's
	// nominal country can't be trusted blindly); only fall back to the
	// cell's country when Serper's address doesn't name one at all.
	$country = mfa_geohash_guess_country( $f['address'] );
	if ( '' === $country ) {
		$country = $country_fallback;
	}

	$is_mosque = ( 'mosque' === $type );
	$table     = $wpdb->prefix . ( $is_mosque ? 'jet_cct_mosque' : 'jet_cct_business' );
	$post_type = $is_mosque ? 'masjid' : 'business';

	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT _ID, cct_author_id, listing_status FROM {$table} WHERE place_id = %s LIMIT 1", $f['place_id'] ) );
	if ( $existing ) {
		// Already listed - never duplicate, and never clobber a user-added or
		// already-approved/generated listing (a real concern now that live
		// users add their own mosques/businesses). Only refresh the rating on
		// records THIS crawler created that are still untouched (author 0 +
		// 'New'); leave name, phone, website, status and content alone in every
		// other case - especially user-owned records (cct_author_id > 0).
		if ( 0 === (int) $existing->cct_author_id && 'New' === $existing->listing_status ) {
			$wpdb->update(
				$table,
				array(
					'rating'       => $f['rating'],
					'rating_count' => $f['rating_count'],
					'cct_modified' => current_time( 'mysql' ),
				),
				array( '_ID' => $existing->_ID )
			);
		}
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
			'country'        => $country,
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

	$city    = mfa_geohash_guess_city( $f['address'], $country );
	$default = mfa_geohash_default_seo( $type, $f['name'], $f['address'], $city, $country );

	$post_id = wp_insert_post( array(
		'post_type'    => $post_type,
		'post_status'  => 'publish',
		'post_title'   => $f['name'],
		'post_content' => $default['content'],
		'post_author'  => 0,
		'meta_input'   => array(
			'item_id'                 => $cct_id,
			'place_id'                => $f['place_id'],
			'rank_math_title'         => $default['title'],
			'rank_math_description'   => $default['description'],
			'rank_math_focus_keyword' => $default['keywords'],
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

/** Total cells + Done count for one country (for the /admin/crawler/start/ page). */
function mfa_geohash_country_totals( $country_code ) {
	global $wpdb;
	$g = mfa_geohash_table();
	$r = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) total, SUM(status = 'Done') done FROM {$g} WHERE country_code = %s",
		strtoupper( $country_code )
	), ARRAY_A );
	return array( 'total' => (int) $r['total'], 'done' => (int) $r['done'] );
}

/**
 * Claim and crawl exactly one non-Done cell for a country - the engine behind
 * /admin/crawler/start/, which drives itself via full page reloads (one cell
 * per page load) instead of an in-page AJAX loop, so several browser tabs can
 * run in parallel with no client-side JS. Claiming happens inside a short
 * transaction with SELECT ... FOR UPDATE so two tabs hitting this at once
 * never grab the same cell and double-spend Serper credits on it.
 *
 * Works directly off status <> 'Done' (New or Pending), so there is no
 * separate "queue a country" step - opening a start page for a country is
 * enough to begin crawling it.
 *
 * @return array One of:
 *   array('state'=>'paused', 'reason'=>string)
 *   array('state'=>'busy')
 *   array('state'=>'done_all', 'totals'=>array)
 *   array('state'=>'error', 'message'=>string, 'geohash'=>string)
 *   array('state'=>'ok', 'cell'=>array, 'result'=>array, 'totals'=>array)
 */
function mfa_geohash_crawl_claim_and_run_one( $country_code ) {
	global $wpdb;
	$g            = mfa_geohash_table();
	$country_code = strtoupper( sanitize_text_field( $country_code ) );

	if ( mfa_crawl_is_paused() ) {
		return array( 'state' => 'paused', 'reason' => (string) mfa_crawl_opt( 'pause_reason', '' ) );
	}

	$per = mfa_crawl_credits_per_cell();
	if ( mfa_crawl_credits_remaining() < $per ) {
		mfa_crawl_pause( 'Out of credits (budget of ' . mfa_crawl_credits_budget() . ' reached)' );
		return array( 'state' => 'paused', 'reason' => (string) mfa_crawl_opt( 'pause_reason', '' ) );
	}

	// Cap how many tabs can be doing real DB/Serper work at the same time -
	// see mfa_crawl_try_acquire_slot()'s docblock for why. A tab that misses
	// out just reports 'busy' and retries next reload cycle; it never
	// touches the queue, so it can't block anyone else either.
	$slot = mfa_crawl_try_acquire_slot();
	if ( ! $slot ) {
		return array( 'state' => 'busy' );
	}

	try {
		// Claim into a third status ('Claimed') distinct from both 'New' and
		// 'Pending' (legacy queued-but-untouched cells from the old batch flow) -
		// selecting on status <> 'Done' alone would still match a cell another
		// tab just claimed as 'Pending', letting two tabs grab the same cell.
		$wpdb->query( 'START TRANSACTION' );
		$cell = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$g} WHERE country_code = %s AND status IN ('New','Pending') ORDER BY id LIMIT 1 FOR UPDATE",
			$country_code
		), ARRAY_A );
		if ( $cell ) {
			$wpdb->update( $g, array( 'status' => 'Claimed', 'updated_at' => current_time( 'mysql' ) ), array( 'geohash' => $cell['geohash'] ) );
		}
		$wpdb->query( 'COMMIT' );

		if ( ! $cell ) {
			return array( 'state' => 'done_all', 'totals' => mfa_geohash_country_totals( $country_code ) );
		}

		// The browser tab's own page load initiated this request - a client-side
		// navigation away must not cut a cell's Serper calls / DB write off
		// mid-way (same reasoning as the REST cron trigger's abort-safety).
		ignore_user_abort( true );
		@set_time_limit( 30 );

		$res = mfa_geohash_crawl_cell( $cell );

		if ( is_wp_error( $res ) ) {
			$msg = $res->get_error_message();
			// Un-claim so the cell is retried later instead of stuck as 'Claimed'.
			$wpdb->update( $g, array( 'status' => 'Pending', 'updated_at' => current_time( 'mysql' ) ), array( 'geohash' => $cell['geohash'] ) );
			if ( false !== stripos( $msg, 'credit' ) ) {
				mfa_crawl_pause( 'Serper: ' . $msg );
			} else {
				mfa_crawl_notify( 'Crawler error', "The directory crawler hit an error and stopped:\n\n{$msg}" );
			}
			return array( 'state' => 'error', 'message' => $msg, 'geohash' => $cell['geohash'] );
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

		return array(
			'state'  => 'ok',
			'cell'   => $cell,
			'result' => $res,
			'totals' => mfa_geohash_country_totals( $country_code ),
		);
	} finally {
		mfa_crawl_release_slot( $slot );
	}
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

/**
 * Every country actually present in the grid (not just the original 8-country
 * crawl scope), with a display name, location/done counts, and mosque/business
 * totals - the reference the overview table and the /start country picker
 * both use to decide where to crawl next. The 'country' column is free text
 * (Serper/city-import source data, occasionally inconsistent - e.g. "Turkey"
 * vs "Türkiye" for the same country_code) so MAX() just picks one spelling
 * per code for display; country_code stays the canonical filter key
 * everywhere else. Sorted alphabetically by that display name.
 *
 * @return array country_code => array('name','locations','done','mosque','business')
 */
function mfa_geohash_country_summary() {
	global $wpdb;
	$g = mfa_geohash_table();

	$rows = $wpdb->get_results(
		"SELECT country_code,
		        MAX(country) AS name,
		        COUNT(*) AS locations,
		        SUM(status = 'Done') AS done,
		        SUM(mosque) AS mosque_total,
		        SUM(business) AS business_total
		 FROM {$g}
		 WHERE country_code <> ''
		 GROUP BY country_code
		 ORDER BY name ASC",
		ARRAY_A
	);

	$out = array();
	foreach ( $rows as $r ) {
		$out[ $r['country_code'] ] = array(
			'name'      => $r['name'] ? $r['name'] : $r['country_code'],
			'locations' => (int) $r['locations'],
			'done'      => (int) $r['done'],
			'mosque'    => (int) $r['mosque_total'],
			'business'  => (int) $r['business_total'],
		);
	}
	return $out;
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

	return array(
		'table_exists'      => true,
		'total'             => array_sum( $by_status ),
		'by_status'         => $by_status,
		'countries'         => mfa_geohash_country_summary(),
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
 * Token-protected REST trigger - a scheduler-independent alternative to the
 * WP-CLI cron for when the host's own cron scheduler is unreliable. Point an
 * external cron service (e.g. cron-job.org) at it. Same guards as the CLI cron
 * (enabled / paused / budget). Being a web request it's subject to PHP /
 * LiteSpeed timeouts, so keep the per-hit limit small (?limit=3 ~ 25-30s).
 *   GET /wp-json/mfa/v1/crawl-run?token=XXXX&limit=3
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'mfa/v1', '/crawl-run', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'mfa_geohash_crawl_rest',
	) );
} );
function mfa_geohash_crawl_rest( $req ) {
	// MUST NOT be cached. LiteSpeed will otherwise cache the first response and
	// serve it to every subsequent identical cron hit, so the crawl runs once
	// then appears to "skip" forever (each hit returns ~3s with the same stale
	// report and never executes). Tell LiteSpeed + clients not to cache it.
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
	nocache_headers();

	$token = (string) mfa_crawl_opt( 'cron_token', '' );
	if ( '' === $token || ! hash_equals( $token, (string) $req->get_param( 'token' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'forbidden' ), 403 );
	}
	if ( ! mfa_crawl_opt( 'enabled', 0 ) ) {
		return new WP_REST_Response( array( 'skipped' => 'cron disabled' ), 200 );
	}
	if ( mfa_crawl_is_paused() ) {
		return new WP_REST_Response( array( 'skipped' => 'paused: ' . mfa_crawl_opt( 'pause_reason', '' ) ), 200 );
	}
	// Finish the batch server-side even if the external cron (cron-job.org etc.)
	// disconnects at its own timeout - otherwise a client abort would kill the
	// run mid-batch and the caller might mark the job failed. Bounded well under
	// a 1-minute cron interval so runs can't overlap.
	ignore_user_abort( true );
	@set_time_limit( 90 );

	$limit = $req->get_param( 'limit' ) ? (int) $req->get_param( 'limit' ) : (int) mfa_crawl_opt( 'batch_size', 5 );
	return new WP_REST_Response( mfa_geohash_crawl_run_batch( (string) mfa_crawl_opt( 'country', '' ), $limit ), 200 );
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
