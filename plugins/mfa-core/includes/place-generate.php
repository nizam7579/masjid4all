<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk creation of /places/ state hubs.
 *
 * Hubs have always been made by hand-publishing a `place` post - which is the
 * whole creation flow, because save_post_place already geocodes the hub
 * (mfa_place_autogeocode_on_save) and writes its SEO title/description
 * (mfa_place_seed_seo_meta). That was fine for Malaysia's 16 states. Indonesia
 * and India together need ~74, which is not a hand job.
 *
 * Three things this file is careful about:
 *
 * 1. IT ITERATES mfa_state_canonical_list(), NEVER `SELECT DISTINCT state`.
 *    Indonesia still holds 23 unrecognised values and India 147 - Mumbai and
 *    Delhi localities the address parser mistook for regions, plus `NJ`/`NY`
 *    from mis-tagged US rows. state-normalize.php deliberately leaves those in
 *    place rather than blanking them, so the guard against them becoming pages
 *    has to live here.
 *
 * 2. A HUB TITLE MUST EQUAL THE CANONICAL STATE EXACTLY. mfa_place_listing_where()
 *    matches a depth-1 hub with `state = <hub title>` - an equality check, not
 *    a fuzzy one. "Jawa Timur" works; "Jawa Timur Province" silently matches
 *    nothing and publishes an empty page.
 *
 * 3. GEOCODING IS RATE-LIMITED. Each publish fires one Nominatim lookup, whose
 *    usage policy is ~1 request/second. Runs are therefore capped by `limit`
 *    and sleep between inserts; generating 74 hubs is several runs by design,
 *    not one long request this host would kill anyway.
 */

/**
 * An existing hub with this title under this parent, any status - so a re-run
 * never publishes a second copy, and a hub someone drafted on purpose is not
 * quietly recreated.
 *
 * @return int Post ID, or 0.
 */
function mfa_place_find_hub( $title, $parent_id ) {
	$found = get_posts(
		array(
			'post_type'        => MFA_PLACE_POST_TYPE,
			'post_parent'      => (int) $parent_id,
			'title'            => $title,
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	return $found ? (int) $found[0] : 0;
}

/**
 * How many listings a prospective hub would hold.
 *
 * Deliberately mirrors mfa_place_listing_where()'s exclusions rather than a
 * plain COUNT(*), so the floor is measured against what the page would
 * actually show - otherwise a hub can clear a floor of 5 and then render 2.
 *
 * @return array mosque + business counts.
 */
function mfa_place_candidate_counts( $country, $state ) {
	global $wpdb;

	$excluded = mfa_place_excluded_statuses();
	$holes    = implode( ',', array_fill( 0, count( $excluded ), '%s' ) );
	$counts   = array();

	foreach ( array( 'mosque', 'business' ) as $type ) {
		$table = mfa_place_table( $type );
		$args  = array_merge( array( $country, $state ), $excluded );

		$counts[ $type ] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				  WHERE country = %s AND state = %s
				    AND ( listing_status IS NULL OR listing_status NOT IN ( {$holes} ) )",
				$args
			)
		);
	}

	return $counts;
}

/**
 * The country's top-level hub, creating it if asked.
 *
 * A state hub cannot exist without one: mfa_place_geo() inherits the country
 * name from the root, and mfa_place_listing_where() keys depth off ancestry.
 *
 * @return array [ id, action ]
 */
function mfa_place_ensure_country_root( $country, $dry_run = true ) {
	$existing = mfa_place_find_hub( $country, 0 );
	if ( $existing ) {
		return array(
			'id'     => $existing,
			'action' => 'exists',
			'status' => get_post_status( $existing ),
		);
	}

	if ( $dry_run ) {
		return array( 'id' => 0, 'action' => 'would create' );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => MFA_PLACE_POST_TYPE,
			'post_title'  => $country,
			'post_status' => 'publish',
			'post_parent' => 0,
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		return array( 'id' => 0, 'action' => 'error: ' . $id->get_error_message() );
	}

	return array( 'id' => (int) $id, 'action' => 'created' );
}

/**
 * Create the state hubs for one country.
 *
 * @param string $country
 * @param array  $args floor, limit, dry_run.
 * @return array Report.
 */
function mfa_place_generate_state_hubs( $country, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'floor'   => 5,
			'limit'   => 10,
			'dry_run' => true,
		)
	);

	$report = array(
		'country'   => $country,
		'dry_run'   => (bool) $args['dry_run'],
		'floor'     => (int) $args['floor'],
		'limit'     => (int) $args['limit'],
		'supported' => function_exists( 'mfa_state_country_supported' ) && mfa_state_country_supported( $country ),
		'created'   => array(),
		'existing'  => array(),
		'below_floor' => array(),
		'remaining' => 0,
	);

	if ( ! $report['supported'] ) {
		$report['error'] = "No canonical state table for {$country} - see state-normalize.php. "
			. 'Generating from raw DISTINCT values would publish a page per parse error.';
		return $report;
	}

	$root = mfa_place_ensure_country_root( $country, $args['dry_run'] );
	$report['root'] = $root;
	if ( ! $root['id'] ) {
		// Dry run with no root yet, or a real failure. Either way the children
		// cannot be attached, so report the plan and stop.
		$report['note'] = 'No country root yet; state hubs need it as parent.';
		if ( ! $args['dry_run'] ) {
			return $report;
		}
	}

	$made = 0;

	foreach ( mfa_state_canonical_list( $country ) as $state ) {
		$counts = mfa_place_candidate_counts( $country, $state );
		$total  = $counts['mosque'] + $counts['business'];

		if ( $total < $args['floor'] ) {
			$report['below_floor'][ $state ] = $total;
			continue;
		}

		$existing = $root['id'] ? mfa_place_find_hub( $state, $root['id'] ) : 0;
		if ( $existing ) {
			$report['existing'][ $state ] = $existing;
			continue;
		}

		if ( $made >= $args['limit'] ) {
			$report['remaining']++;
			continue;
		}

		$label = sprintf( '%s (%d mosques, %d businesses)', $state, $counts['mosque'], $counts['business'] );

		if ( $args['dry_run'] ) {
			$report['created'][ $state ] = 'would create - ' . $label;
			$made++;
			continue;
		}

		// One Nominatim lookup fires inside this insert, via save_post_place.
		// Space them out; the policy is roughly one request per second.
		if ( $made > 0 ) {
			sleep( 1 );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => MFA_PLACE_POST_TYPE,
				// Must match the CCT `state` value exactly - see this file's
				// docblock, rule 2.
				'post_title'  => $state,
				'post_status' => 'publish',
				'post_parent' => (int) $root['id'],
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			$report['created'][ $state ] = 'ERROR: ' . $id->get_error_message();
			continue;
		}

		$geo_error = get_post_meta( $id, '_mfa_place_geocode_error', true );

		$report['created'][ $state ] = array(
			'id'       => (int) $id,
			'url'      => get_permalink( $id ),
			'mosques'  => $counts['mosque'],
			'business' => $counts['business'],
			'geocoded' => $geo_error ? 'FAILED: ' . $geo_error : 'ok',
		);
		$made++;
	}

	$report['created_count'] = $made;

	return $report;
}

/**
 * Cities in one state that are worth a hub.
 *
 * Cities have no enumerable canonical list the way states do - after
 * city-normalize.php has run, the canonical set simply IS the distinct column
 * values. So the guard against junk cannot be a whitelist here, and is instead
 * two explicit tests:
 *
 * - the floor, which removes the long tail of one-off localities the address
 *   parser mistook for a city;
 * - an ambiguity check, because a bare "Pasuruan" sitting beside an explicit
 *   "Kota Pasuruan" or "Kabupaten Pasuruan" cannot be assigned to either.
 *   city-normalize.php deliberately leaves those 68 values alone rather than
 *   guessing, so this is where they get excluded from becoming pages.
 *
 * @return array city => array( mosque, business, total ), largest first.
 */
function mfa_place_city_candidates( $country, $state, $floor ) {
	global $wpdb;

	$mosque   = mfa_place_table( 'mosque' );
	$business = mfa_place_table( 'business' );
	$excluded = mfa_place_excluded_statuses();
	$holes    = implode( ',', array_fill( 0, count( $excluded ), '%s' ) );

	$sql = "SELECT city,
	               SUM( m ) AS mosque,
	               SUM( b ) AS business
	          FROM (
	            SELECT city, COUNT(*) m, 0 b FROM `{$mosque}`
	             WHERE country = %s AND state = %s AND city <> ''
	               AND ( listing_status IS NULL OR listing_status NOT IN ( {$holes} ) )
	             GROUP BY city
	            UNION ALL
	            SELECT city, 0 m, COUNT(*) b FROM `{$business}`
	             WHERE country = %s AND state = %s AND city <> ''
	               AND ( listing_status IS NULL OR listing_status NOT IN ( {$holes} ) )
	             GROUP BY city
	          ) t
	         GROUP BY city";

	$args = array_merge(
		array( $country, $state ),
		$excluded,
		array( $country, $state ),
		$excluded
	);

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

	// Build the core/type map first so ambiguity can be tested.
	$by_key = array();
	foreach ( $rows as $row ) {
		list( $core, $type ) = mfa_city_split( $country, $row['city'] );
		$by_key[ $core . "\0" . $type ] = true;
	}

	$out = array();
	foreach ( $rows as $row ) {
		$total = (int) $row['mosque'] + (int) $row['business'];
		if ( $total < $floor ) {
			continue;
		}

		list( $core, $type ) = mfa_city_split( $country, $row['city'] );
		if ( '' === $type
			&& ( isset( $by_key[ $core . "\0regency" ] ) || isset( $by_key[ $core . "\0city" ] ) ) ) {
			continue; // Ambiguous bare name - see this function's docblock.
		}

		$out[ $row['city'] ] = array(
			'mosque'   => (int) $row['mosque'],
			'business' => (int) $row['business'],
			'total'    => $total,
		);
	}

	arsort( $out );

	return $out;
}

/**
 * Create city hubs beneath every existing state hub of a country.
 *
 * Same contract as mfa_place_generate_state_hubs(): dry-run by default,
 * capped by `limit` because each publish costs one Nominatim lookup.
 *
 * A city hub is only ever created under a state hub that already exists - the
 * depth-2 match in mfa_place_listing_where() reads its parent's title as the
 * state, so an orphaned city hub would match nothing.
 *
 * @param string $country
 * @param array  $args floor, limit, dry_run, state (optional, one state only).
 * @return array Report.
 */
function mfa_place_generate_city_hubs( $country, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'floor'   => 20,
			'limit'   => 15,
			'dry_run' => true,
			'state'   => '',
		)
	);

	$report = array(
		'country'       => $country,
		'dry_run'       => (bool) $args['dry_run'],
		'floor'         => (int) $args['floor'],
		'created'       => array(),
		'existing'      => 0,
		'candidates'    => 0,
		'remaining'     => 0,
		'created_count' => 0,
	);

	$root = mfa_place_find_hub( $country, 0 );
	if ( ! $root ) {
		$report['error'] = "No country hub for {$country} - create the country and its states first.";
		return $report;
	}

	$states = get_posts(
		array(
			'post_type'   => MFA_PLACE_POST_TYPE,
			'post_parent' => $root,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);

	$made = 0;

	foreach ( $states as $state_post ) {
		$state = get_the_title( $state_post );
		if ( '' !== $args['state'] && $state !== $args['state'] ) {
			continue;
		}

		foreach ( mfa_place_city_candidates( $country, $state, $args['floor'] ) as $city => $counts ) {
			$report['candidates']++;

			if ( mfa_place_find_hub( $city, $state_post->ID ) ) {
				$report['existing']++;
				continue;
			}

			if ( $made >= $args['limit'] ) {
				$report['remaining']++;
				continue;
			}

			if ( $args['dry_run'] ) {
				$report['created'][ $state . ' / ' . $city ] = sprintf(
					'would create - %d mosques, %d businesses',
					$counts['mosque'],
					$counts['business']
				);
				$made++;
				continue;
			}

			if ( $made > 0 ) {
				sleep( 1 ); // Nominatim, ~1 req/sec.
			}

			$id = wp_insert_post(
				array(
					'post_type'   => MFA_PLACE_POST_TYPE,
					// Exact match with the CCT `city` value is required -
					// mfa_place_listing_where() compares with equality.
					'post_title'  => $city,
					'post_status' => 'publish',
					'post_parent' => (int) $state_post->ID,
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				$report['created'][ $state . ' / ' . $city ] = 'ERROR: ' . $id->get_error_message();
				continue;
			}

			$geo_error = get_post_meta( $id, '_mfa_place_geocode_error', true );

			$report['created'][ $state . ' / ' . $city ] = array(
				'id'       => (int) $id,
				'url'      => get_permalink( $id ),
				'mosques'  => $counts['mosque'],
				'business' => $counts['business'],
				'geocoded' => $geo_error ? 'FAILED: ' . $geo_error : 'ok',
			);
			$made++;
		}
	}

	$report['created_count'] = $made;

	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mfa generate-city-hubs',
		function ( $args, $assoc ) {
			$country = isset( $assoc['country'] ) ? $assoc['country'] : '';
			if ( '' === $country ) {
				WP_CLI::error( 'Pass --country=Indonesia' );
			}

			$r = mfa_place_generate_city_hubs(
				$country,
				array(
					'floor'   => isset( $assoc['floor'] ) ? (int) $assoc['floor'] : 20,
					'limit'   => isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 15,
					'dry_run' => ! isset( $assoc['apply'] ),
					'state'   => isset( $assoc['state'] ) ? $assoc['state'] : '',
				)
			);

			if ( ! empty( $r['error'] ) ) {
				WP_CLI::error( $r['error'] );
			}

			foreach ( $r['created'] as $label => $info ) {
				WP_CLI::log( '  ' . $label . ': ' . ( is_array( $info ) ? $info['url'] : $info ) );
			}
			WP_CLI::log( sprintf(
				'%s: %d created, %d already existed, %d candidates at floor %d, %d still queued.',
				$country, $r['created_count'], $r['existing'], $r['candidates'], $r['floor'], $r['remaining']
			) );
			WP_CLI::success( $r['dry_run'] ? 'Dry run - pass --apply to write.' : 'Applied.' );
		}
	);

	WP_CLI::add_command(
		'mfa generate-place-hubs',
		function ( $args, $assoc ) {
			$country = isset( $assoc['country'] ) ? $assoc['country'] : '';
			if ( '' === $country ) {
				WP_CLI::error( 'Pass --country=Indonesia' );
			}

			$r = mfa_place_generate_state_hubs(
				$country,
				array(
					'floor'   => isset( $assoc['floor'] ) ? (int) $assoc['floor'] : 5,
					'limit'   => isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 10,
					'dry_run' => ! isset( $assoc['apply'] ),
				)
			);

			if ( ! empty( $r['error'] ) ) {
				WP_CLI::error( $r['error'] );
			}

			foreach ( $r['created'] as $state => $info ) {
				WP_CLI::log( '  ' . $state . ': ' . ( is_array( $info ) ? $info['url'] : $info ) );
			}
			WP_CLI::log( sprintf(
				'%s: %d created, %d already existed, %d below floor of %d, %d still queued.',
				$country, $r['created_count'], count( $r['existing'] ),
				count( $r['below_floor'] ), $r['floor'], $r['remaining']
			) );
			WP_CLI::success( empty( $r['dry_run'] ) ? 'Applied.' : 'Dry run - pass --apply to write.' );
		}
	);
}
