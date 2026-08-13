<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time extraction: pull the `website` field already captured on crawled
 * business records (from Serper Maps, ~10K per user report, 2026-08-14)
 * into the website directory (wp_jet_cct_web / post_type=web) as New
 * listings pending review - a free first pass at indexing Muslim-related
 * websites per country, before spending any new Serper credits on the
 * harder multi-entity categories (associations, schools, media, etc - a
 * separate, not-yet-built effort using Serper's /search endpoint instead
 * of /maps). Business only, per explicit user direction (2026-08-14) -
 * mosque websites are out of scope for this extraction.
 *
 * wp_jet_cct_web has no place_id-style dedup key (websites aren't Google
 * Maps places) - dedup is by normalised hostname instead, against both the
 * website directory's own existing rows (593 already there as of
 * 2026-08-14, spanning 79 countries) and duplicates within the extraction
 * batch itself (many businesses share one org's website, or the same
 * domain appears under slightly different URL paths).
 *
 * Social-media/messaging links (Facebook, Instagram, WhatsApp, etc.) are
 * excluded outright - they're profiles, not the business's own website,
 * and don't belong in a website directory.
 */

/**
 * Lowercased, www-stripped hostname from a URL, or '' if it can't be
 * parsed. Adds a https:// scheme first if the stored value is bare
 * (e.g. "example.com" with no protocol - common in crawled data).
 */
function mfa_web_extract_normalize_host( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return '';
	}
	$host = strtolower( $host );
	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}
	return $host;
}

/**
 * Known social-media/messaging/maps domains to exclude - these are
 * profiles or shared links, not an organisation's own website.
 */
function mfa_web_extract_social_domains() {
	return array(
		'facebook.com', 'fb.com', 'fb.me', 'instagram.com', 'twitter.com', 'x.com',
		'youtube.com', 'youtu.be', 'wa.me', 'api.whatsapp.com', 'whatsapp.com',
		'linkedin.com', 'tiktok.com', 't.me', 'telegram.me', 'telegram.org',
		'linktr.ee', 'maps.google.com', 'goo.gl', 'g.page', 'bit.ly',
		'pinterest.com', 'wechat.com', 'threads.net', 'snapchat.com',
	);
}

function mfa_web_extract_is_social_host( $host ) {
	foreach ( mfa_web_extract_social_domains() as $sd ) {
		if ( $host === $sd || ( strlen( $host ) > strlen( $sd ) && substr( $host, -strlen( $sd ) - 1 ) === '.' . $sd ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Normalised hostnames already present in wp_jet_cct_web, for dedup.
 */
function mfa_web_extract_existing_hosts() {
	global $wpdb;
	$w    = $wpdb->prefix . 'jet_cct_web';
	$urls = $wpdb->get_col( "SELECT url FROM {$w} WHERE url IS NOT NULL AND url <> ''" );
	$out  = array();
	foreach ( $urls as $u ) {
		$host = mfa_web_extract_normalize_host( $u );
		if ( $host ) {
			$out[ $host ] = true;
		}
	}
	return $out;
}

/**
 * Insert one new website directory entry sourced from a business record's
 * own `website` field - mirrors mfa_geohash_upsert_place()'s
 * insert+publish pattern (mfa-core/includes/geohash-crawl.php) but for the
 * `web` CPT, which has no page_url column to backfill (unlike mosque/
 * business).
 */
function mfa_web_extract_insert( $row, $url ) {
	global $wpdb;
	$w = $wpdb->prefix . 'jet_cct_web';

	$name = $row['name'] ? $row['name'] : $url;
	$now  = current_time( 'mysql' );

	$wpdb->insert(
		$w,
		array(
			'name'           => $name,
			'url'            => esc_url_raw( $url ),
			'country'        => $row['country'],
			'city'           => isset( $row['city'] ) ? $row['city'] : '',
			'address'        => isset( $row['address'] ) ? $row['address'] : '',
			'category'       => 'Business Website',
			'listing_status' => 'New',
			'cct_author_id'  => 0,
			'cct_created'    => $now,
			'cct_modified'   => $now,
		)
	);
	$cct_id = (int) $wpdb->insert_id;
	if ( ! $cct_id ) {
		return 0;
	}

	$place   = $row['country'] ? ' in ' . $row['country'] : '';
	$content = '<p>' . esc_html( $name ) . ' is the official website for this business' . $place . '.</p>'
		. '<p>This listing was added automatically and has not been fully reviewed yet.</p>';

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'web',
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => $content,
			'post_author'  => 0,
			'meta_input'   => array(
				'item_id' => $cct_id,
			),
		)
	);

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		$wpdb->update( $w, array( 'cct_single_post_id' => $post_id ), array( '_ID' => $cct_id ) );
	}

	return $cct_id;
}

/**
 * @param bool $apply  false = dry run (report only), true = write the inserts.
 * @param int  $limit  Row cap for this call, 0 = no cap (scan everything
 *   from $offset onward - only safe for a dry run or a small dataset;
 *   large --apply runs must be chunked via $offset/$limit across several
 *   calls, since each insert also publishes a real post via
 *   wp_insert_post(), which is far slower than a raw SQL insert).
 * @param int    $offset Skip this many source rows first (stable order via
 *   ORDER BY _ID), so repeated calls can advance through the full table
 *   instead of re-scanning the same first N rows every time.
 * @param string $since  MySQL datetime - only scan business rows crawled
 *   at or after this time (cct_created >= $since). Used by the daily cron
 *   (see mfa_web_extract_daily_run()) so an ever-growing business table
 *   doesn't mean an ever-slower daily scan - only rows added since the
 *   last successful run are looked at. '' = no time filter (the full
 *   one-time backfill scan).
 * @return array Report: scanned, social_excluded, duplicate_existing,
 *   duplicate_in_batch, eligible (would-insert / applied count), samples.
 */
function mfa_web_extract_from_business( $apply = false, $limit = 0, $offset = 0, $since = '' ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_business';

	$report = array(
		'scanned'            => 0,
		'social_excluded'    => 0,
		'duplicate_existing' => 0,
		'duplicate_in_batch' => 0,
		'eligible'           => 0,
		'applied'            => 0,
		'samples'            => array(),
	);

	$existing_hosts = mfa_web_extract_existing_hosts();
	$seen_this_run  = array();

	$sql = "SELECT name, website, country, city, address FROM {$table} WHERE website IS NOT NULL AND website <> ''";
	if ( '' !== $since ) {
		$sql .= $wpdb->prepare( ' AND cct_created >= %s', $since );
	}
	$sql .= ' ORDER BY _ID';
	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
	}
	$rows = $wpdb->get_results( $sql, ARRAY_A );

	foreach ( $rows as $row ) {
		$report['scanned']++;

		$host = mfa_web_extract_normalize_host( $row['website'] );
		if ( '' === $host ) {
			continue;
		}

		if ( mfa_web_extract_is_social_host( $host ) ) {
			$report['social_excluded']++;
			continue;
		}

		if ( isset( $existing_hosts[ $host ] ) ) {
			$report['duplicate_existing']++;
			continue;
		}

		if ( isset( $seen_this_run[ $host ] ) ) {
			$report['duplicate_in_batch']++;
			continue;
		}
		$seen_this_run[ $host ] = true;

		$report['eligible']++;
		if ( count( $report['samples'] ) < 30 ) {
			$report['samples'][] = array(
				'name'    => $row['name'],
				'url'     => $row['website'],
				'host'    => $host,
				'country' => $row['country'],
			);
		}

		if ( $apply ) {
			$url = trim( $row['website'] );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				$url = 'https://' . $url;
			}
			if ( mfa_web_extract_insert( $row, $url ) ) {
				$report['applied']++;
			}
		}
	}

	return $report;
}

/**
 * WP-CLI:
 *   wp mfa web-extract [--limit=N] [--offset=N] [--since="Y-m-d H:i:s"] [--apply]
 *       Dry-run by default (reports what would be inserted); pass --apply
 *       to write. --limit caps rows scanned per call; --offset skips that
 *       many source rows first (stable order) so a large --apply run can
 *       be chunked across several calls instead of one long-running one.
 *       --since only scans business rows crawled at/after that time
 *       (matches what the daily cron does automatically - see below).
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mfa web-extract', function ( $args, $assoc ) {
		$apply  = isset( $assoc['apply'] );
		$limit  = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 0;
		$offset = isset( $assoc['offset'] ) ? (int) $assoc['offset'] : 0;
		$since  = isset( $assoc['since'] ) ? $assoc['since'] : '';

		$r = mfa_web_extract_from_business( $apply, $limit, $offset, $since );

		foreach ( $r['samples'] as $s ) {
			WP_CLI::log( sprintf( '%s :: %s (%s)', $s['name'], $s['url'], $s['country'] ) );
		}
		if ( $r['eligible'] > count( $r['samples'] ) ) {
			WP_CLI::log( sprintf( '... and %d more not shown.', $r['eligible'] - count( $r['samples'] ) ) );
		}

		WP_CLI::log( sprintf(
			'Scanned %d, social-excluded %d, already-in-directory %d, duplicate-in-batch %d, eligible %d.',
			$r['scanned'], $r['social_excluded'], $r['duplicate_existing'], $r['duplicate_in_batch'], $r['eligible']
		) );

		if ( $apply ) {
			WP_CLI::success( sprintf( 'Inserted %d new website listings.', $r['applied'] ) );
		} else {
			WP_CLI::success( 'DRY RUN - re-run with --apply to write.' );
		}
	} );

	WP_CLI::add_command( 'mfa web-extract-cron', function () {
		WP_CLI::log( print_r( mfa_web_extract_daily_run(), true ) );
	} );
}

/**
 * Daily incremental run: only scans business rows crawled since the last
 * successful run (tracked in the mfa_web_extract_last_run option), so the
 * scan stays cheap as the business table keeps growing (~10K/day per user
 * report, 2026-08-14) instead of re-scanning the whole table every day.
 * The checkpoint is captured BEFORE the scan runs (not after), so any
 * business crawled while this run is executing gets picked up on the
 * NEXT run rather than silently skipped.
 */
function mfa_web_extract_daily_run() {
	$since      = get_option( 'mfa_web_extract_last_run', '1970-01-01 00:00:00' );
	$checkpoint = current_time( 'mysql' );

	$r = mfa_web_extract_from_business( true, 0, 0, $since );

	update_option( 'mfa_web_extract_last_run', $checkpoint );

	$r['since']      = $since;
	$r['checkpoint'] = $checkpoint;
	return $r;
}

/**
 * Token-protected REST trigger for an external daily cron (cron-job.org,
 * same tool already driving the mosque/business crawl - see geohash-crawl.
 * php's crawl-run endpoint, which this mirrors exactly, including the
 * LiteSpeed no-cache headers: without them LiteSpeed caches the first
 * response and serves it to every later hit, so the job runs once then
 * appears to "skip" forever - cost an hour to debug the first time this
 * bit the geohash cron, not repeating that here).
 *   GET /wp-json/mfa/v1/web-extract-run?token=XXXX
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'mfa/v1', '/web-extract-run', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'mfa_web_extract_rest_run',
	) );
} );
function mfa_web_extract_rest_run( $req ) {
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
	nocache_headers();

	$token = (string) get_option( 'mfa_web_extract_cron_token', '' );
	if ( '' === $token || ! hash_equals( $token, (string) $req->get_param( 'token' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'forbidden' ), 403 );
	}

	// Finish server-side even if the external cron client disconnects at its
	// own timeout - daily volume is small enough this should finish in
	// seconds, but this is a safety net, not a design assumption.
	ignore_user_abort( true );
	@set_time_limit( 90 );

	return new WP_REST_Response( mfa_web_extract_daily_run(), 200 );
}
