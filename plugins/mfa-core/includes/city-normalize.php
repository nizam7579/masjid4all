<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical city names for the depth-2 /places/ hubs.
 *
 * Same disease as `state` had, one level down and forty times wider: the same
 * Indonesian regency arrives in two languages, 1,050 distinct values deep.
 *
 *   Kabupaten Aceh Besar   140  <->  Aceh Besar Regency    273
 *   Kabupaten Deli Serdang 173  <->  Deli Serdang Regency
 *   Kota Banda Aceh        122  <->  Banda Aceh City        64
 *
 * A hand-written alias table is not viable at that size. Unlike states,
 * though, this split is MECHANICAL rather than arbitrary - `Kabupaten X` and
 * `X Regency` are the same words in two languages, as are `Kota X` and
 * `X City` - so the rule can be derived instead of enumerated.
 *
 * Four rules keep the derivation honest:
 *
 * 1. NEVER MERGE ACROSS ADMINISTRATIVE TYPE. In Indonesia `Kota Pasuruan` and
 *    `Kabupaten Pasuruan` are DIFFERENT places sharing a name. A regency only
 *    ever merges with a regency, a city only with a city.
 *
 * 2. A BARE NAME IS AMBIGUOUS AND IS LEFT ALONE. Plain "Pasuruan" could be
 *    either unit, so it is never folded into one. 68 such names exist; they
 *    keep their own value and simply do not gain a hub.
 *
 * 3. THE CANONICAL FORM IS THE MAJORITY VALUE THAT ALREADY EXISTS, never a
 *    synthesised one. Rewriting every regency to "Kabupaten {core}" would
 *    turn "East Aceh Regency" into "Kabupaten East Aceh" - a name nobody
 *    uses, because the English form carries an English core too. Picking the
 *    commonest real spelling avoids inventing anything.
 *
 * 4. ONLY GROUPS THAT ACTUALLY COLLIDE ARE TOUCHED. A city with one spelling
 *    is left exactly as it is; this merges duplicates, it does not restyle
 *    the column.
 *
 * Rows with an empty `state` are skipped entirely: they cannot belong to a
 * city hub anyway (mfa_place_listing_where() matches state AND city), and
 * grouping them country-wide could merge two same-named cities in different
 * regions.
 */

/**
 * Administrative affixes per country, longest/most-specific first.
 *
 * A country absent from this table still gets grouped, but only case and
 * whitespace variants can then collide - which is safe, just less useful.
 *
 * @return array<array{0:string,1:string}> [ regex, type ]
 */
function mfa_city_affix_rules( $country ) {
	$rules = array(
		'Indonesia' => array(
			// Prefixes. "Administrasi" forms are Jakarta's and must be tried
			// before the plain ones, or "Kota Administrasi Jakarta Barat"
			// would keep "administrasi" in its core.
			array( '/^kabupaten administrasi\s+/u', 'regency' ),
			array( '/^kota administrasi\s+/u', 'city' ),
			array( '/^kabupaten\s+/u', 'regency' ),
			array( '/^kab\.?\s+/u', 'regency' ),
			array( '/^kotamadya\s+/u', 'city' ),
			array( '/^kota\s+/u', 'city' ),
			// Suffixes.
			array( '/\s+regency$/u', 'regency' ),
			array( '/\s+city$/u', 'city' ),
			array( '/\s+municipality$/u', 'city' ),
		),
	);

	/**
	 * @param array  $rules
	 * @param string $country
	 */
	$rules = apply_filters( 'mfa_city_affix_rules', $rules, $country );

	return isset( $rules[ $country ] ) ? $rules[ $country ] : array();
}

/**
 * Split a city value into its comparable core and administrative type.
 *
 * @return array{0:string,1:string} [ core, type ] - type is '' when the name
 *                                  carries no administrative marker.
 */
function mfa_city_split( $country, $city ) {
	$value = trim( preg_replace( '/\s+/u', ' ', (string) $city ) );
	$lower = mb_strtolower( $value );

	if ( '' === $lower ) {
		return array( '', '' );
	}

	foreach ( mfa_city_affix_rules( $country ) as $rule ) {
		if ( preg_match( $rule[0], $lower ) ) {
			return array( trim( preg_replace( $rule[0], '', $lower ) ), $rule[1] );
		}
	}

	return array( $lower, '' );
}

/**
 * Which raw spelling wins a collision group.
 *
 * Highest row count, because that is the spelling most of the data already
 * uses and therefore the smallest write. Ties break toward the Indonesian
 * form so the outcome is deterministic rather than dependent on row order,
 * and then alphabetically.
 *
 * @param array $members [ [ city, n ], ... ]
 */
function mfa_city_pick_canonical( $members ) {
	usort(
		$members,
		function ( $a, $b ) {
			if ( $a['n'] !== $b['n'] ) {
				return $b['n'] - $a['n'];
			}

			$a_local = (int) preg_match( '/^(kabupaten|kota)\s/ui', $a['city'] );
			$b_local = (int) preg_match( '/^(kabupaten|kota)\s/ui', $b['city'] );
			if ( $a_local !== $b_local ) {
				return $b_local - $a_local;
			}

			return strcmp( $a['city'], $b['city'] );
		}
	);

	return $members[0];
}

/**
 * Merge colliding city spellings for one country.
 *
 * Dry-run by default, same contract as mfa_state_normalize_backfill().
 *
 * @param string $country
 * @param bool   $apply
 * @return array Report.
 */
function mfa_city_normalize_backfill( $country, $apply = false ) {
	global $wpdb;

	$report = array(
		'country'      => $country,
		'applied'      => (bool) $apply,
		'has_affix_rules' => (bool) mfa_city_affix_rules( $country ),
		'groups'       => 0,
		'collisions'   => 0,
		'rows_moved'   => 0,
		'changes'      => array(),
		'ambiguous_left_alone' => array(),
	);

	$mosque   = $wpdb->prefix . 'jet_cct_mosque';
	$business = $wpdb->prefix . 'jet_cct_business';

	// One pass over both tables. Empty state is excluded deliberately - see
	// this file's docblock.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT state, city, SUM(n) n FROM (
			   SELECT state, city, COUNT(*) n FROM `{$mosque}`
			    WHERE country = %s AND city <> '' AND state <> '' GROUP BY state, city
			   UNION ALL
			   SELECT state, city, COUNT(*) n FROM `{$business}`
			    WHERE country = %s AND city <> '' AND state <> '' GROUP BY state, city
			 ) t GROUP BY state, city",
			$country,
			$country
		),
		ARRAY_A
	);

	$groups = array();
	foreach ( $rows as $row ) {
		list( $core, $type ) = mfa_city_split( $country, $row['city'] );
		if ( '' === $core ) {
			continue;
		}
		$groups[ $row['state'] . "\0" . $core . "\0" . $type ][] = array(
			'city'  => $row['city'],
			'n'     => (int) $row['n'],
			'state' => $row['state'],
		);
	}
	$report['groups'] = count( $groups );

	foreach ( $groups as $key => $members ) {
		list( $state, $core, $type ) = explode( "\0", $key );

		// A bare name sitting beside an explicit Kota or Kabupaten of the same
		// core cannot be assigned to either - record it and move on.
		if ( '' === $type
			&& ( isset( $groups[ $state . "\0" . $core . "\0regency" ] ) || isset( $groups[ $state . "\0" . $core . "\0city" ] ) ) ) {
			$report['ambiguous_left_alone'][] = $members[0]['city'] . ' [' . $state . ']';
		}

		if ( count( $members ) < 2 ) {
			continue;
		}

		$report['collisions']++;
		$winner = mfa_city_pick_canonical( $members );

		foreach ( $members as $member ) {
			if ( $member['city'] === $winner['city'] ) {
				continue;
			}

			$report['rows_moved'] += $member['n'];
			$report['changes'][] = sprintf(
				'%s: %s (%d) -> %s (%d)',
				$state,
				$member['city'],
				$member['n'],
				$winner['city'],
				$winner['n']
			);

			if ( $apply ) {
				foreach ( array( $mosque, $business ) as $table ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE `{$table}` SET city = %s WHERE country = %s AND state = %s AND city = %s",
							$winner['city'],
							$country,
							$state,
							$member['city']
						)
					);
				}
			}
		}
	}

	sort( $report['changes'] );
	sort( $report['ambiguous_left_alone'] );

	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mfa normalize-city',
		function ( $args, $assoc ) {
			$country = isset( $assoc['country'] ) ? $assoc['country'] : '';
			if ( '' === $country ) {
				WP_CLI::error( 'Pass --country=Indonesia' );
			}

			$r = mfa_city_normalize_backfill( $country, isset( $assoc['apply'] ) );

			foreach ( $r['changes'] as $line ) {
				WP_CLI::log( '  ' . $line );
			}
			WP_CLI::log( sprintf(
				'%s: %d groups, %d collisions, %d rows to move, %d ambiguous bare names left alone.',
				$country, $r['groups'], $r['collisions'], $r['rows_moved'], count( $r['ambiguous_left_alone'] )
			) );
			WP_CLI::success( $r['applied'] ? 'Applied.' : 'Dry run - pass --apply to write.' );
		}
	);
}
