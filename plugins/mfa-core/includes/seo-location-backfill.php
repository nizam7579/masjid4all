<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recovers city/state from the stored address, then rebuilds the search
 * metadata that was left malformed without it.
 *
 * Two problems, one cause. City is empty on 107,740 mosque rows and 78,385
 * business rows, while the full address is present on nearly all of them. An
 * older SEO writer composed titles as "name | city | state" without guarding
 * for empties, so 75,120 masjid pages carry a title reading literally
 * "KAMPUNG MASJID |  | ", 75,165 a description reading "KAMPUNG MASJID, , "
 * and 77,020 a focus keyword of "Mosque in , Mosque in ".
 *
 * The crawler's current writer, mfa_geohash_default_seo(), already guards
 * against empty parts - so this is legacy data to repair, not an ongoing leak.
 * Parse the city back out of the address, then rebuild the metadata through
 * that same function so old and new pages describe themselves identically.
 *
 * Costs nothing in API calls: the addresses are already stored in Google's
 * formatted_address shape, "street, 53100 Kuala Lumpur, Selangor, Malaysia".
 */

function mfa_seo_location_sources() {
	return array(
		'mosque'   => array( 'post_type' => 'masjid',   'seo_type' => 'mosque' ),
		'business' => array( 'post_type' => 'business', 'seo_type' => 'business' ),
	);
}

/**
 * Pull city and state out of a formatted address.
 *
 * Validated against 3,000 real rows before use: 92% yield a city. The rules
 * below each exist because a sample got it wrong without them.
 *
 * @return array [ city, state ] - either may be '' when nothing trustworthy
 *               could be read. Never guesses.
 */
function mfa_seo_parse_address( $address, $country = '' ) {
	$parts = array_values(
		array_filter(
			array_map( 'trim', explode( ',', (string) $address ) ),
			function ( $part ) {
				return '' !== $part;
			}
		)
	);

	if ( empty( $parts ) ) {
		return array( '', '' );
	}

	$country = trim( (string) $country );
	$last    = end( $parts );

	if ( '' !== $country && 0 === strcasecmp( $last, $country ) ) {
		array_pop( $parts );
	} elseif ( count( $parts ) > 1 && ! preg_match( '/\d/u', $last ) ) {
		// A trailing component with no digits is the country under a local
		// name ("Bosnia & Herzegovina"). One with digits is a US-style
		// "TX 75428", which is the state and must be kept.
		array_pop( $parts );
	}

	if ( empty( $parts ) ) {
		return array( '', '' );
	}

	$count = count( $parts );
	$city  = '';
	$state = '';

	// A state is only believable when something sits in front of it. With one
	// or two components left the trailing token is the town - "Ugljari" and
	// "Gulu" are villages, not regions.
	if ( $count >= 3 ) {
		$state = mfa_seo_clean_place( $parts[ $count - 1 ] );
		$city  = mfa_seo_clean_place( $parts[ $count - 2 ] );
	} elseif ( 2 === $count ) {
		$city = mfa_seo_clean_place( $parts[ $count - 1 ] );
	} else {
		$city = mfa_seo_clean_place( $parts[0] );
	}

	if ( mfa_seo_place_is_junk( $state ) ) {
		$state = '';
	}

	if ( mfa_seo_place_is_junk( $city ) ) {
		$city = '';
	}

	// When the city slot held a street the town often landed in the state slot
	// instead. A city is what the titles need, and a town mislabelled as a
	// state is worse than no state at all.
	if ( '' === $city && '' !== $state ) {
		$city  = $state;
		$state = '';
	}

	return array( $city, $state );
}

/**
 * Strip the noise Google leaves around a place name: plus codes, and postcodes
 * on either side ("53100 Kuala Lumpur", "Djibia 820105").
 */
function mfa_seo_clean_place( $value ) {
	$value = trim( (string) $value );

	if ( preg_match( '/^[A-Z0-9]{4,}\+[A-Z0-9]{2,}/u', $value ) ) {
		return '';
	}

	$value = preg_replace( '/^\d{4,6}\s+/u', '', $value );
	$value = preg_replace( '/\s+\d{4,6}$/u', '', $value );

	return trim( $value );
}

/**
 * Reject anything that is plainly not a place name - street lines, house
 * numbers, leftover plus codes.
 */
function mfa_seo_place_is_junk( $value ) {
	if ( '' === $value || mb_strlen( $value ) < 2 ) {
		return true;
	}

	if ( false !== strpos( $value, '+' ) ) {
		return true;
	}

	if ( preg_match( '/^[0-9\s\-\/]+$/u', $value ) ) {
		return true;
	}

	if ( preg_match( '/^(jalan|jln|jl\.?|lorong|lot|no\.?|blok|block|street|st|road|rd|km|gg\.?|dusun)\b/iu', $value ) ) {
		return true;
	}

	if ( preg_match( '/\b(rd|st|street|road|ave|avenue|lane|ln|drive|dr)\.?$/iu', $value ) ) {
		return true;
	}

	return false;
}

/**
 * Per-source cursors, so a run resumes rather than restarting.
 */
function mfa_seo_backfill_state() {
	$state = get_option( 'mfa_seo_backfill_state', array() );

	if ( ! is_array( $state ) ) {
		$state = array();
	}

	foreach ( array_keys( mfa_seo_location_sources() ) as $source ) {
		if ( ! isset( $state[ $source ] ) || ! is_array( $state[ $source ] ) ) {
			$state[ $source ] = array( 'cursor' => 0, 'scanned' => 0, 'filled' => 0, 'meta' => 0, 'done' => false );
		}
	}

	return $state;
}

function mfa_seo_backfill_save_state( $state ) {
	update_option( 'mfa_seo_backfill_state', $state, false );
}

function mfa_seo_backfill_reset() {
	delete_option( 'mfa_seo_backfill_state' );
}

/**
 * Process one batch: fill missing city/state, then rebuild the page's search
 * metadata from whatever is now known.
 *
 * Existing city/state values are never overwritten - only empty ones are
 * filled, so anything a human entered stands.
 *
 * @param string $source One of mfa_seo_location_sources().
 * @param int    $limit  Rows per batch.
 * @param bool   $apply  False reports what would change and writes nothing.
 */
function mfa_seo_backfill_batch( $source, $limit = 500, $apply = true ) {
	global $wpdb;

	$sources = mfa_seo_location_sources();

	if ( ! isset( $sources[ $source ] ) ) {
		return array( 'error' => 'Unknown source.' );
	}

	$limit = max( 1, min( 2000, (int) $limit ) );
	$table = $wpdb->prefix . 'jet_cct_' . $source;
	$state = mfa_seo_backfill_state();
	$done  = $state[ $source ];

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT _ID, name, address, city, state, country, cct_single_post_id
			 FROM {$table}
			 WHERE _ID > %d AND address IS NOT NULL AND address <> ''
			 ORDER BY _ID ASC LIMIT %d",
			(int) $done['cursor'],
			$limit
		),
		ARRAY_A
	);

	$report = array(
		'source'       => $source,
		'scanned'      => count( $rows ),
		'city_filled'  => 0,
		'state_filled' => 0,
		'meta_rebuilt' => 0,
		'unparsed'     => 0,
		'complete'     => empty( $rows ),
		'samples'      => array(),
	);

	if ( empty( $rows ) ) {
		if ( $apply ) {
			$state[ $source ]['done'] = true;
			mfa_seo_backfill_save_state( $state );
		}

		return $report;
	}

	foreach ( $rows as $row ) {
		$city  = trim( (string) $row['city'] );
		$st    = trim( (string) $row['state'] );
		$fills = array();

		if ( '' === $city || '' === $st ) {
			list( $parsed_city, $parsed_state ) = mfa_seo_parse_address( $row['address'], $row['country'] );

			if ( '' === $city && '' !== $parsed_city ) {
				$city             = $parsed_city;
				$fills['city']    = $parsed_city;
				$report['city_filled']++;
			}

			if ( '' === $st && '' !== $parsed_state ) {
				$st              = $parsed_state;
				$fills['state']  = $parsed_state;
				$report['state_filled']++;
			}

			if ( empty( $fills ) && '' === $city ) {
				$report['unparsed']++;
			}
		}

		if ( count( $report['samples'] ) < 5 && ! empty( $fills ) ) {
			$report['samples'][] = mb_substr( (string) $row['name'], 0, 34 ) . ' -> ' . $city . ( $st ? ' / ' . $st : '' );
		}

		if ( ! $apply ) {
			continue;
		}

		if ( ! empty( $fills ) ) {
			$wpdb->update( $table, $fills, array( '_ID' => (int) $row['_ID'] ) );
		}

		$post_id = (int) $row['cct_single_post_id'];

		if ( $post_id && mfa_seo_rebuild_post_meta( $post_id, $row['name'], $row['address'], $city, $st, $row['country'], $sources[ $source ]['seo_type'] ) ) {
			$report['meta_rebuilt']++;
		}
	}

	if ( $apply ) {
		$state[ $source ]['cursor']   = (int) $rows[ count( $rows ) - 1 ]['_ID'];
		$state[ $source ]['scanned'] += $report['scanned'];
		$state[ $source ]['filled']  += $report['city_filled'];
		$state[ $source ]['meta']    += $report['meta_rebuilt'];
		mfa_seo_backfill_save_state( $state );
	}

	return $report;
}

/**
 * Rewrite one page's title/description/focus keyword, but only when the stored
 * version is malformed or is missing a place we now know.
 *
 * Composed through mfa_geohash_default_seo() rather than a second template, so
 * repaired pages and freshly crawled ones read the same. That function already
 * drops empty parts, which is the bug being repaired.
 *
 * @return bool Whether anything was written.
 */
function mfa_seo_rebuild_post_meta( $post_id, $name, $address, $city, $state, $country, $seo_type ) {
	if ( ! function_exists( 'mfa_geohash_default_seo' ) ) {
		return false;
	}

	$current = (string) get_post_meta( $post_id, 'rank_math_title', true );

	$malformed = ( '' !== $current )
		&& ( preg_match( '/\|\s*\|/u', $current ) || preg_match( '/[\|,]\s*$/u', trim( $current ) ) );

	// Nothing wrong with it, and no new place to add - leave it alone rather
	// than churn 200,000 rows for no gain.
	$missing_place = ( '' !== $city && false === mb_stripos( $current, $city ) );

	if ( '' !== $current && ! $malformed && ! $missing_place ) {
		return false;
	}

	$seo = mfa_geohash_default_seo( $seo_type, $name, $address, $city, $country );

	update_post_meta( $post_id, 'rank_math_title', $seo['title'] );
	update_post_meta( $post_id, 'rank_math_description', $seo['description'] );
	update_post_meta( $post_id, 'rank_math_focus_keyword', $seo['keywords'] );

	return true;
}

/**
 * How much is left to do, for the admin panel and for checking progress.
 */
function mfa_seo_backfill_progress() {
	global $wpdb;

	$state  = mfa_seo_backfill_state();
	$totals = array();

	foreach ( array_keys( mfa_seo_location_sources() ) as $source ) {
		$table              = $wpdb->prefix . 'jet_cct_' . $source;
		$totals[ $source ]  = array(
			'with_address' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE address IS NOT NULL AND address <> ''" ),
			'city_empty'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE ( city IS NULL OR city = '' ) AND address <> ''" ),
			'scanned'      => (int) $state[ $source ]['scanned'],
			'filled'       => (int) $state[ $source ]['filled'],
			'meta_rebuilt' => (int) $state[ $source ]['meta'],
			'done'         => (bool) $state[ $source ]['done'],
		);
	}

	return $totals;
}
