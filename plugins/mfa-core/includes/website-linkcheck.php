<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link checker for the website directory.
 *
 * Judging a listing by its domain name was always a guess - a free Wix
 * subdomain can be a thriving business and a custom domain can be three years
 * dead. This checks the only thing that actually matters: does the site
 * answer.
 *
 * The classification is where the care is needed. A sample of 25 live rows on
 * 2026-08-18 returned 84% 200s, 4% connection failures and **12% 403s** -
 * working sites behind Cloudflare-style protection that simply refuse
 * automated requests. Treating those as dead would have unpublished roughly
 * 3,000 legitimate businesses, so 401/403/429 count as alive here. That is the
 * single most important rule in this file.
 *
 * Everything else that fails must fail three times, on three separate days,
 * before anything is hidden. A site can be down for a weekend; that is not a
 * reason to remove it from the directory.
 */

const MFA_LINKCHECK_RECHECK_DAYS = 30;
const MFA_LINKCHECK_RETRY_HOURS  = 24;
const MFA_LINKCHECK_FAIL_LIMIT   = 3;
const MFA_LINKCHECK_CONCURRENCY  = 10;
const MFA_LINKCHECK_TIMEOUT      = 10;
const MFA_LINKCHECK_COLUMN_VERSION = '1';

/**
 * Adds the checker's own columns to the web table.
 *
 * Always verifies the columns rather than trusting the version option: the
 * option lives in wp_options, so an environment restored from an older DB
 * backup keeps it while losing the columns, and an option-first early return
 * would then never heal them. Learned from the state column, which silently
 * went missing on staging exactly that way.
 */
add_action( 'plugins_loaded', 'mfa_web_linkcheck_maybe_add_columns' );
function mfa_web_linkcheck_maybe_add_columns() {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';

	$wanted = array(
		'http_status'  => "ADD COLUMN http_status VARCHAR(24) NULL",
		'http_checked' => "ADD COLUMN http_checked DATETIME NULL",
		'http_fails'   => "ADD COLUMN http_fails SMALLINT NOT NULL DEFAULT 0",
	);

	$missing = array();
	foreach ( $wanted as $col => $clause ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', $col ) ) ) {
			$missing[] = $clause;
		}
	}

	if ( $missing ) {
		$wpdb->query( "ALTER TABLE {$table} " . implode( ', ', $missing ) );
		// Indexed because the "what is due a check" query filters on it every run.
		$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_http_checked (http_checked)" );
	}

	if ( get_option( 'mfa_web_linkcheck_column_version' ) !== MFA_LINKCHECK_COLUMN_VERSION ) {
		update_option( 'mfa_web_linkcheck_column_version', MFA_LINKCHECK_COLUMN_VERSION );
	}
}

/**
 * What a response means for the listing.
 *
 * @return string alive | blocked | dead | transient
 */
function mfa_web_linkcheck_classify( $code, $curl_errno ) {
	if ( $curl_errno ) {
		// 6 = DNS failure, 7 = connection refused. Both mean the site is not
		// there, as opposed to slow or guarded, so they are hard failures.
		return 'dead_soft';
	}

	$code = (int) $code;

	if ( $code >= 200 && $code < 400 ) {
		return 'alive';
	}

	// Live sites refusing robots. 12% of a real sample - never treat as dead.
	if ( in_array( $code, array( 401, 403, 405, 406, 429 ), true ) ) {
		return 'blocked';
	}

	if ( 404 === $code || 410 === $code ) {
		return 'dead';
	}

	return 'dead_soft';
}

/** Rows due a check: never checked, failing and retryable, or simply stale. */
function mfa_web_linkcheck_due( $limit ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';

	$retry = gmdate( 'Y-m-d H:i:s', time() - ( MFA_LINKCHECK_RETRY_HOURS * HOUR_IN_SECONDS ) );
	$stale = gmdate( 'Y-m-d H:i:s', time() - ( MFA_LINKCHECK_RECHECK_DAYS * DAY_IN_SECONDS ) );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT _ID, url, listing_status, http_fails, cct_single_post_id
			 FROM {$table}
			 WHERE url IS NOT NULL AND url <> ''
			   AND ( listing_status IS NULL OR listing_status <> 'Rejected' )
			   AND (
			         http_checked IS NULL
			         OR ( http_fails > 0 AND http_checked < %s )
			         OR ( http_fails = 0 AND http_checked < %s )
			       )
			 ORDER BY http_checked IS NOT NULL, http_checked ASC
			 LIMIT %d",
			$retry,
			$stale,
			(int) $limit
		),
		ARRAY_A
	);
}

/**
 * Checks one batch in parallel.
 *
 * curl_multi rather than wp_remote_head in a loop: at roughly a second per
 * site, 24K rows would be seven hours in series. Ten at a time keeps a full
 * sweep to well under an hour without being rude to anyone - hosts are already
 * deduplicated in this table, so a batch is a batch of distinct sites.
 *
 * @param int  $limit How many rows to check.
 * @param bool $apply Write results. False = report only.
 */
function mfa_web_linkcheck_batch( $limit = 50, $apply = false ) {
	$rows = mfa_web_linkcheck_due( $limit );

	$report = array(
		'checked'     => 0,
		'alive'       => 0,
		'blocked'     => 0,
		'dead'        => 0,
		'transient'   => 0,
		'unpublished' => 0,
		'samples'     => array(),
	);
	if ( ! $rows ) {
		return $report;
	}

	$mh      = curl_multi_init();
	$handles = array();

	foreach ( $rows as $i => $row ) {
		$url = trim( (string) $row['url'] );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_NOBODY         => true, // HEAD - we only want the status.
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 4,
			CURLOPT_TIMEOUT        => MFA_LINKCHECK_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_SSL_VERIFYPEER => false, // Expired certs are a site problem, not a dead site.
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Masjid4AllLinkCheck/1.0; +' . home_url() . ')',
		) );

		$handles[ $i ] = array( 'ch' => $ch, 'row' => $row );
		curl_multi_add_handle( $mh, $ch );

		// Run the pool whenever it is full, and again for the remainder.
		if ( count( $handles ) >= MFA_LINKCHECK_CONCURRENCY || $i === count( $rows ) - 1 ) {
			do {
				$status = curl_multi_exec( $mh, $running );
				if ( $running ) {
					curl_multi_select( $mh, 1.0 );
				}
			} while ( $running && CURLM_OK === $status );

			// curl_errno() stays 0 on a multi handle, so the real result code has to
			// be drained from the info queue - without this a DNS failure and a
			// timeout both recorded as status '0' and the reason was lost.
			$errmap = array();
			while ( $info = curl_multi_info_read( $mh ) ) {
				$errmap[ spl_object_id( $info['handle'] ) ] = (int) $info['result'];
			}

			foreach ( $handles as $h ) {
				$errno = isset( $errmap[ spl_object_id( $h['ch'] ) ] ) ? $errmap[ spl_object_id( $h['ch'] ) ] : 0;
				mfa_web_linkcheck_record( $h['ch'], $h['row'], $apply, $report, $errno );
				curl_multi_remove_handle( $mh, $h['ch'] );
				curl_close( $h['ch'] );
			}
			$handles = array();
		}
	}

	curl_multi_close( $mh );

	return $report;
}

/** Applies one result: counters, columns, and unpublishing when it is due. */
function mfa_web_linkcheck_record( $ch, $row, $apply, &$report, $errno = 0 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';

	$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$errno = (int) $errno;
	// A response code of 0 with no curl error still means nothing came back.
	if ( ! $errno && ! $code ) {
		$errno = 7;
	}
	$verdict = mfa_web_linkcheck_classify( $code, $errno );

	$report['checked']++;

	$status_text = $errno ? 'ERR:' . $errno : (string) $code;
	$fails       = (int) $row['http_fails'];

	if ( 'alive' === $verdict || 'blocked' === $verdict ) {
		$report[ 'alive' === $verdict ? 'alive' : 'blocked' ]++;
		$fails = 0;
	} else {
		$report[ 'dead' === $verdict ? 'dead' : 'transient' ]++;
		$fails++;
	}

	if ( count( $report['samples'] ) < 12 ) {
		$report['samples'][] = $status_text . '  ' . $verdict . '  fails=' . $fails . '  ' . substr( (string) $row['url'], 0, 54 );
	}

	if ( ! $apply ) {
		return;
	}

	$data = array(
		'http_status'  => $status_text,
		'http_checked' => current_time( 'mysql' ),
		'http_fails'   => $fails,
		'cct_modified' => current_time( 'mysql' ),
	);

	// Three strikes, and because a failing row is only retried once a day,
	// three strikes means three separate days rather than three rapid retries.
	if ( $fails >= MFA_LINKCHECK_FAIL_LIMIT && 'Error' !== $row['listing_status'] ) {
		$data['listing_status'] = 'Error';

		if ( ! empty( $row['cct_single_post_id'] ) ) {
			$post = get_post( (int) $row['cct_single_post_id'] );
			if ( $post && 'publish' === $post->post_status ) {
				wp_update_post( array( 'ID' => (int) $row['cct_single_post_id'], 'post_status' => 'draft' ) );
				$report['unpublished']++;
			}
		}
	}

	$wpdb->update( $table, $data, array( '_ID' => (int) $row['_ID'] ) );
}

/** How much is outstanding - drives the admin panel's progress line. */
function mfa_web_linkcheck_progress() {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';

	$checkable = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE url IS NOT NULL AND url <> '' AND ( listing_status IS NULL OR listing_status <> 'Rejected' )" );
	$never     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE url IS NOT NULL AND url <> '' AND ( listing_status IS NULL OR listing_status <> 'Rejected' ) AND http_checked IS NULL" );

	return array(
		'checkable' => $checkable,
		'checked'   => $checkable - $never,
		'never'     => $never,
		'errors'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE listing_status = 'Error'" ),
		'failing'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE http_fails > 0" ),
	);
}

/**
 * Token-protected REST trigger for the external cron, mirroring the web-extract
 * and crawl endpoints - including the LiteSpeed no-cache header, without which
 * the first response is cached and every later run appears to do nothing.
 *   GET /wp-json/mfa/v1/linkcheck-run?token=XXXX&limit=200
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'mfa/v1', '/linkcheck-run', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'mfa_web_linkcheck_rest_run',
	) );
} );
function mfa_web_linkcheck_rest_run( $req ) {
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}
	nocache_headers();

	$token = (string) get_option( 'mfa_web_linkcheck_cron_token', '' );
	if ( '' === $token || ! hash_equals( $token, (string) $req->get_param( 'token' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'forbidden' ), 403 );
	}

	ignore_user_abort( true );
	@set_time_limit( 300 );

	$limit = (int) $req->get_param( 'limit' );
	$limit = $limit > 0 ? min( $limit, 500 ) : 200;

	$r             = mfa_web_linkcheck_batch( $limit, true );
	$r['progress'] = mfa_web_linkcheck_progress();

	return new WP_REST_Response( $r, 200 );
}
