<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical province/state names for the /places/ hubs.
 *
 * The `state` column is parsed out of Google's formatted address, which gives
 * whichever name the source happened to use. For Indonesia that means the same
 * province arrives in two languages and several abbreviations:
 *
 *   Jawa Timur      4,662   +  East Java          930
 *   Sumatera Utara  3,153   +  North Sumatra    2,711
 *   Jawa Barat      1,998   +  West Java          968
 *   Nusa Tenggara Bar. 443  +  West Nusa Tenggara 342
 *
 * 93 distinct values for 38 real provinces. Generating a hub per distinct value
 * would publish two competing pages for "mosques in East Java", each holding
 * half the inventory - measurably worse than publishing none. India is milder
 * (183 values for 36 states) but has the same shape, plus a long tail of
 * Mumbai/Delhi neighbourhoods the address parser mistook for a region.
 *
 * Two rules this file exists to enforce:
 *
 * 1. LONGEST ALIAS WINS. Matching is prefix-based, and Indonesian province
 *    names nest: `Papua` is a prefix of `Papua Barat`, which is a prefix of
 *    `Papua Barat Daya`. First-match-wins would file all of Southwest Papua
 *    under Papua. mfa_malaysia_normalize_state_name() gets away without this
 *    only because no Malaysian state name prefixes another.
 *
 * 2. AN UNRECOGNISED VALUE IS LEFT ALONE, NEVER BLANKED. `NJ` and `NY` appear
 *    under Indonesia (US rows mis-tagged), and India's tail is full of
 *    localities like `Connaught Place`. Those are data bugs worth seeing.
 *    Emptying the column would destroy the evidence irreversibly and is a
 *    one-way trip; hub generation reads the canonical list instead, so junk
 *    simply never becomes a hub.
 */

/**
 * country => canonical => aliases (lowercase, punctuation-stripped).
 *
 * Canonical form follows the majority spelling already in the data, which for
 * Indonesia is the Indonesian name - also what people search for locally.
 */
function mfa_state_alias_tables() {
	$tables = array(
		'Indonesia' => array(
			'Aceh'                      => array( 'aceh' ),
			'Sumatera Utara'            => array( 'sumatera utara', 'sumatra utara', 'north sumatra' ),
			'Sumatera Barat'            => array( 'sumatera barat', 'west sumatra' ),
			'Sumatera Selatan'          => array( 'sumatera selatan', 'south sumatra' ),
			'Riau'                      => array( 'riau' ),
			'Kepulauan Riau'            => array( 'kepulauan riau', 'riau islands' ),
			'Jambi'                     => array( 'jambi' ),
			'Bengkulu'                  => array( 'bengkulu' ),
			'Lampung'                   => array( 'lampung' ),
			'Kepulauan Bangka Belitung' => array( 'kepulauan bangka belitung', 'bangka belitung islands', 'bangka belitung' ),
			'DKI Jakarta'               => array( 'daerah khusus ibukota jakarta', 'dki jakarta', 'jakarta' ),
			'Jawa Barat'                => array( 'jawa barat', 'west java' ),
			'Jawa Tengah'               => array( 'jawa tengah', 'central java' ),
			'Jawa Timur'                => array( 'jawa timur', 'east java' ),
			'DI Yogyakarta'             => array( 'daerah istimewa yogyakarta', 'special region of yogyakarta', 'di yogyakarta', 'yogyakarta' ),
			'Banten'                    => array( 'banten' ),
			'Bali'                      => array( 'bali' ),
			'Nusa Tenggara Barat'       => array( 'nusa tenggara barat', 'nusa tenggara bar', 'west nusa tenggara' ),
			'Nusa Tenggara Timur'       => array( 'nusa tenggara timur', 'nusa tenggara tim', 'east nusa tenggara' ),
			'Kalimantan Barat'          => array( 'kalimantan barat', 'west kalimantan' ),
			'Kalimantan Tengah'         => array( 'kalimantan tengah', 'central kalimantan' ),
			'Kalimantan Selatan'        => array( 'kalimantan selatan', 'south kalimantan' ),
			'Kalimantan Timur'          => array( 'kalimantan timur', 'east kalimantan' ),
			'Kalimantan Utara'          => array( 'kalimantan utara', 'north kalimantan' ),
			'Sulawesi Utara'            => array( 'sulawesi utara', 'north sulawesi' ),
			'Sulawesi Tengah'           => array( 'sulawesi tengah', 'central sulawesi' ),
			'Sulawesi Selatan'          => array( 'sulawesi selatan', 'south sulawesi' ),
			'Sulawesi Tenggara'         => array( 'sulawesi tenggara', 'southeast sulawesi', 'south east sulawesi' ),
			'Sulawesi Barat'            => array( 'sulawesi barat', 'west sulawesi' ),
			'Gorontalo'                 => array( 'gorontalo' ),
			'Maluku'                    => array( 'maluku' ),
			'Maluku Utara'              => array( 'maluku utara', 'north maluku' ),
			'Papua'                     => array( 'papua' ),
			'Papua Barat'               => array( 'papua barat', 'west papua', 'papua bar' ),
			'Papua Barat Daya'          => array( 'papua barat daya', 'southwest papua' ),
			'Papua Tengah'              => array( 'papua tengah', 'central papua' ),
			'Papua Pegunungan'          => array( 'papua pegunungan', 'highland papua' ),
			'Papua Selatan'             => array( 'papua selatan', 'south papua' ),
		),
		'India' => array(
			'Andhra Pradesh'    => array( 'andhra pradesh' ),
			'Arunachal Pradesh' => array( 'arunachal pradesh' ),
			'Assam'             => array( 'assam' ),
			'Bihar'             => array( 'bihar' ),
			'Chhattisgarh'      => array( 'chhattisgarh', 'chattisgarh' ),
			'Goa'               => array( 'goa' ),
			'Gujarat'           => array( 'gujarat' ),
			'Haryana'           => array( 'haryana' ),
			'Himachal Pradesh'  => array( 'himachal pradesh' ),
			'Jharkhand'         => array( 'jharkhand' ),
			'Karnataka'         => array( 'karnataka' ),
			'Kerala'            => array( 'kerala' ),
			'Madhya Pradesh'    => array( 'madhya pradesh' ),
			'Maharashtra'       => array( 'maharashtra' ),
			'Manipur'           => array( 'manipur' ),
			'Meghalaya'         => array( 'meghalaya' ),
			'Mizoram'           => array( 'mizoram' ),
			'Nagaland'          => array( 'nagaland' ),
			'Odisha'            => array( 'odisha', 'orissa' ),
			'Punjab'            => array( 'punjab' ),
			'Rajasthan'         => array( 'rajasthan' ),
			'Sikkim'            => array( 'sikkim' ),
			'Tamil Nadu'        => array( 'tamil nadu' ),
			'Telangana'         => array( 'telangana' ),
			'Tripura'           => array( 'tripura' ),
			'Uttar Pradesh'     => array( 'uttar pradesh', 'utttar pardesh', 'uttar pardesh' ),
			'Uttarakhand'       => array( 'uttarakhand', 'uttaranchal' ),
			'West Bengal'       => array( 'west bengal' ),
			// Union territories.
			'Andaman and Nicobar Islands' => array( 'andaman and nicobar islands', 'andaman nicobar' ),
			'Chandigarh'        => array( 'chandigarh' ),
			'Dadra and Nagar Haveli and Daman and Diu' => array( 'dadra and nagar haveli and daman and diu', 'dadra and nagar haveli', 'daman and diu' ),
			'Delhi'             => array( 'delhi', 'new delhi', 'nct of delhi' ),
			'Jammu and Kashmir' => array( 'jammu and kashmir', 'jammu kashmir' ),
			'Ladakh'            => array( 'ladakh' ),
			'Lakshadweep'       => array( 'lakshadweep' ),
			'Puducherry'        => array( 'puducherry', 'pondicherry' ),
		),
	);

	// Malaysia's 16 canonical names already exist in geohash-crawl.php and are
	// the source of truth for the hubs live since 2026-08-16. Reuse that table
	// rather than restating it here - two copies would drift, and the hub
	// titles depend on an exact match. This makes Malaysia visible to
	// mfa_state_canonical_list() (so the hub generator can handle it) WITHOUT
	// changing how its states are parsed: mfa_geohash_guess_state() still
	// routes Malaysia through mfa_malaysia_normalize_state_name().
	if ( function_exists( 'mfa_malaysia_state_aliases' ) ) {
		$tables['Malaysia'] = mfa_malaysia_state_aliases();
	}

	/**
	 * @param array $tables country => canonical => aliases.
	 */
	return apply_filters( 'mfa_state_alias_tables', $tables );
}

/**
 * The canonical names for a country, for hub generation to iterate.
 *
 * Hub generation MUST read this rather than SELECT DISTINCT state, or the junk
 * values this file deliberately leaves in place would each become a page.
 *
 * @return string[]
 */
function mfa_state_canonical_list( $country ) {
	$tables = mfa_state_alias_tables();
	return isset( $tables[ $country ] ) ? array_keys( $tables[ $country ] ) : array();
}

/**
 * Is this country covered? Callers should not attempt hubs for one that is not
 * - Pakistan's `state` column holds city names (Lahore, Karachi) on 347 of
 * 3,656 rows, which no alias table can rescue.
 */
function mfa_state_country_supported( $country ) {
	$tables = mfa_state_alias_tables();
	return isset( $tables[ $country ] );
}

/**
 * Lowercase, strip punctuation and collapse whitespace, so "Nusa Tenggara Bar."
 * and "nusa tenggara bar" compare equal.
 */
function mfa_state_normalize_key( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = str_replace( array( '.', ',', '-', '_', '/' ), ' ', $value );
	$value = preg_replace( '/[^a-z0-9\s]/u', '', $value );
	return trim( preg_replace( '/\s+/', ' ', $value ) );
}

/**
 * Canonical name for a raw state value, or '' when unrecognised.
 *
 * '' means "leave the stored value alone" - never "blank it".
 */
function mfa_normalize_state_name( $country, $candidate ) {
	$tables = mfa_state_alias_tables();
	if ( ! isset( $tables[ $country ] ) ) {
		return '';
	}

	$key = mfa_state_normalize_key( $candidate );
	if ( '' === $key ) {
		return '';
	}

	// Flatten to alias => canonical, then match longest alias first so nested
	// names (papua / papua barat / papua barat daya) resolve to the most
	// specific province rather than the shortest one that happens to match.
	static $flat = array();
	if ( ! isset( $flat[ $country ] ) ) {
		$pairs = array();
		foreach ( $tables[ $country ] as $canonical => $aliases ) {
			foreach ( $aliases as $alias ) {
				$pairs[ mfa_state_normalize_key( $alias ) ] = $canonical;
			}
		}
		uksort(
			$pairs,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$flat[ $country ] = $pairs;
	}

	foreach ( $flat[ $country ] as $alias => $canonical ) {
		if ( $key === $alias || 0 === strpos( $key, $alias . ' ' ) ) {
			return $canonical;
		}
	}

	return '';
}

/**
 * Rewrite `state` to its canonical form for one country.
 *
 * Dry-run by default, mirroring wp mfa geohash-backfill-state. Only rows whose
 * value both resolves AND differs are touched; an unrecognised value is
 * counted and reported, never written.
 *
 * @param string $country
 * @param bool   $apply   false = report only.
 * @return array
 */
function mfa_state_normalize_backfill( $country, $apply = false ) {
	global $wpdb;

	$report = array(
		'country'     => $country,
		'applied'     => (bool) $apply,
		'supported'   => mfa_state_country_supported( $country ),
		'changed'     => 0,
		'already_ok'  => 0,
		'unmatched'   => 0,
		'changes'     => array(),
		'unmatched_values' => array(),
	);

	if ( ! $report['supported'] ) {
		return $report;
	}

	foreach ( array( 'mosque', 'business' ) as $cct ) {
		$table = $wpdb->prefix . 'jet_cct_' . $cct;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT state, COUNT(*) n FROM `{$table}`
				  WHERE country = %s AND state IS NOT NULL AND state <> ''
				  GROUP BY state",
				$country
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$raw       = $row['state'];
			$n         = (int) $row['n'];
			$canonical = mfa_normalize_state_name( $country, $raw );

			if ( '' === $canonical ) {
				$report['unmatched'] += $n;
				$report['unmatched_values'][ $raw ] = ( $report['unmatched_values'][ $raw ] ?? 0 ) + $n;
				continue;
			}
			if ( $canonical === $raw ) {
				$report['already_ok'] += $n;
				continue;
			}

			$report['changed'] += $n;
			$label = $raw . ' -> ' . $canonical;
			$report['changes'][ $label ] = ( $report['changes'][ $label ] ?? 0 ) + $n;

			if ( $apply ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET state = %s WHERE country = %s AND state = %s",
						$canonical,
						$country,
						$raw
					)
				);
			}
		}
	}

	arsort( $report['changes'] );
	arsort( $report['unmatched_values'] );

	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mfa normalize-state',
		function ( $args, $assoc ) {
			$country = isset( $assoc['country'] ) ? $assoc['country'] : '';
			$apply   = isset( $assoc['apply'] );

			if ( '' === $country ) {
				WP_CLI::error( 'Pass --country=Indonesia (supported: ' . implode( ', ', array_keys( mfa_state_alias_tables() ) ) . ')' );
			}

			$r = mfa_state_normalize_backfill( $country, $apply );
			if ( ! $r['supported'] ) {
				WP_CLI::error( "No alias table for {$country}." );
			}

			WP_CLI::log( sprintf(
				'%s: %d to change, %d already canonical, %d unmatched (left alone).',
				$country, $r['changed'], $r['already_ok'], $r['unmatched']
			) );
			foreach ( $r['changes'] as $label => $n ) {
				WP_CLI::log( sprintf( '  %-60s %d', $label, $n ) );
			}
			WP_CLI::success( $apply ? 'Applied.' : 'Dry run - pass --apply to write.' );
		}
	);
}
