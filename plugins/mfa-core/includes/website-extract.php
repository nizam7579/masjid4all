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
 * @param bool $apply false = dry run (report only), true = write the inserts.
 * @param int  $limit Row cap, 0 = no cap.
 * @return array Report: scanned, social_excluded, duplicate_existing,
 *   duplicate_in_batch, eligible (would-insert / applied count), samples.
 */
function mfa_web_extract_from_business( $apply = false, $limit = 0 ) {
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
	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
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
 *   wp mfa web-extract [--limit=N] [--apply]
 *       Dry-run by default (reports what would be inserted); pass --apply
 *       to write. --limit caps rows scanned, not the total inserted.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mfa web-extract', function ( $args, $assoc ) {
		$apply = isset( $assoc['apply'] );
		$limit = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 0;

		$r = mfa_web_extract_from_business( $apply, $limit );

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
}
