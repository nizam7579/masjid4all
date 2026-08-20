<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured data for the directory records themselves.
 *
 * Rank Math describes a mosque page as an `Article` and a halal business as an
 * `Article` too - `pt_masjid_default_rich_snippet` and its business twin are
 * both set to 'article'. That is a news story about a mosque, not a mosque, and
 * it means none of the record's own data reaches search engines: the CCT row
 * holds latitude/longitude on 100% of rows, an address on 99.9%, and a phone
 * number on 81% of businesses, and not one of those values appears in the
 * page's JSON-LD.
 *
 * What is already correct and is deliberately left alone: the site-level
 * `Place` node (@id .../#place) is referenced only by `Organization.location`
 * and describes Masjid4All's own office - it does NOT claim the mosque is in
 * Kuala Lumpur, which is easy to misread from the raw graph. `BreadcrumbList`,
 * `WebSite` and `WebPage` are all well-formed. This file replaces exactly one
 * node - `richSnippet`, the page's subject - and links the WebPage to it.
 *
 * Two fields are deliberately NOT emitted:
 *
 * - `aggregateRating`. The ratings are scraped Google Maps figures, and
 *   republishing them as our own is a structured-data policy question for a
 *   human, not a technical one. Separately the data will not support it:
 *   mosques carry `rating` on 122,985 rows but `rating_count` on only 48,764,
 *   and a ratingValue without a count is invalid markup.
 * - `openingHoursSpecification`. `opening_hours` is stored as HTML
 *   ("<li>Tuesday: 5 AM-10 PM</li>"), so it needs a real parser, and only 14%
 *   of mosques have any value at all.
 *
 * Every field below is guarded: an absent value is omitted, never emitted
 * empty. Invalid structured data is worse than none.
 */

define( 'MFA_SCHEMA_CCT_INDEX_VERSION', '1' );

/**
 * The post -> CCT lookup runs on every single directory page and had no index
 * to use: EXPLAIN reported `type: ALL` over 94,594 rows at ~34ms per query.
 * This is not new to the schema work - mosque-single.php:92 and
 * business-single.php:111 already pay it on every page view.
 *
 * Following the lesson recorded on mfa_geohash_maybe_add_state_column(): the
 * version option alone is not proof, because an environment rebuilt from an
 * older backup keeps the option while losing the index. Verify the real state
 * every request; SHOW INDEX is cheap.
 */
add_action( 'plugins_loaded', 'mfa_schema_maybe_add_cct_index' );
function mfa_schema_maybe_add_cct_index() {
	global $wpdb;

	foreach ( array( 'jet_cct_mosque', 'jet_cct_business', 'jet_cct_web' ) as $table ) {
		$full = $wpdb->prefix . $table;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) ) {
			continue;
		}
		$has = $wpdb->get_var(
			$wpdb->prepare( "SHOW INDEX FROM {$full} WHERE Key_name = %s", 'idx_cct_single_post_id' )
		);
		if ( ! $has ) {
			$wpdb->query( "ALTER TABLE {$full} ADD INDEX idx_cct_single_post_id (cct_single_post_id)" );
		}

		// The /places/ hub membership query filters on country + state + city
		// together (mfa_place_listing_where), and `city` was indexed nowhere:
		// EXPLAIN reported type ALL over 136,880 rows at ~35ms for a single
		// city lookup. Composite rather than a bare city index because all
		// three columns are in the same WHERE. Prefix lengths keep the key
		// under InnoDB's limit, matching the idx_state (state(50)) precedent.
		if ( 'jet_cct_web' === $table ) {
			continue; // No state/city columns on this one.
		}
		$has_geo = $wpdb->get_var(
			$wpdb->prepare( "SHOW INDEX FROM {$full} WHERE Key_name = %s", 'idx_country_state_city' )
		);
		if ( ! $has_geo ) {
			$wpdb->query( "ALTER TABLE {$full} ADD INDEX idx_country_state_city (country(50), state(50), city(80))" );
		}
	}

	if ( get_option( 'mfa_schema_cct_index_version' ) !== MFA_SCHEMA_CCT_INDEX_VERSION ) {
		update_option( 'mfa_schema_cct_index_version', MFA_SCHEMA_CCT_INDEX_VERSION );
	}
}

/**
 * Post type -> [ CCT suffix, schema @type ].
 *
 * `Mosque` is a real schema.org type (Place > CivicStructure > PlaceOfWorship >
 * Mosque), so it is more precise than the PlaceOfWorship the brief offered as
 * a fallback.
 */
function mfa_schema_directory_types() {
	return array(
		'masjid'   => array( 'cct' => 'mosque',   'type' => 'Mosque' ),
		'business' => array( 'cct' => 'business', 'type' => 'LocalBusiness' ),
	);
}

/**
 * Narrow LocalBusiness to a subtype when the record's own category says so
 * plainly. Conservative on purpose - anything unrecognised stays
 * LocalBusiness, which is always valid.
 *
 * @param string $category The CCT `type` column, e.g. "Halal restaurant".
 */
function mfa_schema_business_subtype( $category ) {
	$category = strtolower( (string) $category );

	$map = array(
		'restaurant' => 'Restaurant',
		'cafe'       => 'CafeOrCoffeeShop',
		'coffee'     => 'CafeOrCoffeeShop',
		'bakery'     => 'Bakery',
		'butcher'    => 'Store',
		'grocery'    => 'GroceryStore',
		'supermarket'=> 'GroceryStore',
		'hotel'      => 'Hotel',
		'pharmacy'   => 'Pharmacy',
	);

	foreach ( $map as $needle => $type ) {
		if ( false !== strpos( $category, $needle ) ) {
			return $type;
		}
	}

	return 'LocalBusiness';
}

/**
 * The record behind a directory post. One query, cached per request.
 *
 * @return array|null
 */
function mfa_schema_directory_record( $post_id, $post_type ) {
	static $cache = array();

	$key = $post_type . ':' . (int) $post_id;
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$types = mfa_schema_directory_types();
	if ( ! isset( $types[ $post_type ] ) ) {
		return $cache[ $key ] = null;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_' . $types[ $post_type ]['cct'];

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT name, address, city, state, country, latitude, longitude, phone, website, type
			   FROM `{$table}` WHERE cct_single_post_id = %d LIMIT 1",
			(int) $post_id
		),
		ARRAY_A
	);

	return $cache[ $key ] = ( $row ? $row : null );
}

/**
 * Coordinates, or null. Rejects the empty string, the literal 0,0 that a failed
 * geocode leaves behind, and anything outside the real range.
 */
function mfa_schema_geo( $lat, $lng ) {
	$lat = trim( (string) $lat );
	$lng = trim( (string) $lng );

	if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return null;
	}

	$lat = (float) $lat;
	$lng = (float) $lng;

	if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
		return null;
	}
	if ( 0.0 === $lat && 0.0 === $lng ) {
		return null;
	}

	return array(
		'@type'     => 'GeoCoordinates',
		'latitude'  => $lat,
		'longitude' => $lng,
	);
}

/**
 * PostalAddress from whichever parts exist, or null if none do.
 *
 * `address` holds Google's full formatted string, which already contains the
 * city and country. It is still the best `streetAddress` we have, and the
 * separate fields let the locality/region/country be stated explicitly rather
 * than left for a parser to guess.
 */
function mfa_schema_address( $row ) {
	$parts = array(
		'streetAddress'   => trim( (string) $row['address'] ),
		'addressLocality' => trim( (string) $row['city'] ),
		'addressRegion'   => trim( (string) $row['state'] ),
		'addressCountry'  => trim( (string) $row['country'] ),
	);

	$parts = array_filter(
		$parts,
		function ( $v ) {
			return '' !== $v;
		}
	);

	if ( empty( $parts ) ) {
		return null;
	}

	return array( '@type' => 'PostalAddress' ) + $parts;
}

/**
 * Replace the page's subject node with one that describes the actual record.
 *
 * Priority 99 so it runs after Rank Math has assembled `richSnippet`; the two
 * useful pieces it already computed (description, image) are carried over
 * rather than recomputed.
 *
 * @param array $data Nodes keyed by schema id - 'richSnippet', 'WebPage', ...
 */
add_filter( 'rank_math/json_ld', 'mfa_schema_directory_json_ld', 99, 2 );
function mfa_schema_directory_json_ld( $data, $jsonld ) {
	$types = mfa_schema_directory_types();

	if ( ! is_singular( array_keys( $types ) ) ) {
		return $data;
	}

	$post_id   = get_queried_object_id();
	$post_type = get_post_type( $post_id );

	if ( ! $post_id || ! isset( $types[ $post_type ] ) ) {
		return $data;
	}

	// A boilerplate page is already noindex (seo-index-control.php). Marking it
	// up as a real place as well would be asserting an entity we have nothing
	// to say about, so drop the subject node entirely and leave the page with
	// its breadcrumb and WebPage only.
	if ( function_exists( 'mfa_seo_is_thin_post' ) && mfa_seo_is_thin_post( $post_id ) ) {
		unset( $data['richSnippet'] );
		return $data;
	}

	$row = mfa_schema_directory_record( $post_id, $post_type );
	if ( ! $row ) {
		return $data;
	}

	$name = trim( (string) $row['name'] );
	if ( '' === $name ) {
		$name = get_the_title( $post_id );
	}

	$schema_type = $types[ $post_type ]['type'];
	if ( 'business' === $post_type ) {
		$schema_type = mfa_schema_business_subtype( $row['type'] );
	}

	$permalink = get_permalink( $post_id );
	$existing  = isset( $data['richSnippet'] ) && is_array( $data['richSnippet'] ) ? $data['richSnippet'] : array();

	$node = array(
		'@type' => $schema_type,
		// Keep Rank Math's own id convention so anything referencing the
		// subject node keeps resolving.
		'@id'   => $permalink . '#richSnippet',
		'name'  => $name,
		'url'   => $permalink,
	);

	if ( ! empty( $existing['description'] ) ) {
		$node['description'] = $existing['description'];
	}
	if ( ! empty( $existing['image'] ) ) {
		$node['image'] = $existing['image'];
	}

	$address = mfa_schema_address( $row );
	if ( $address ) {
		$node['address'] = $address;
	}

	$geo = mfa_schema_geo( $row['latitude'], $row['longitude'] );
	if ( $geo ) {
		$node['geo'] = $geo;
	}

	$phone = trim( (string) $row['phone'] );
	if ( '' !== $phone ) {
		$node['telephone'] = $phone;
	}

	// The record's OWN website, where it has one. Not our page - that is `url`.
	$website = trim( (string) $row['website'] );
	if ( '' !== $website && filter_var( $website, FILTER_VALIDATE_URL ) ) {
		$node['sameAs'] = $website;
	}

	if ( ! empty( $data['WebPage']['@id'] ) ) {
		$node['mainEntityOfPage'] = array( '@id' => $data['WebPage']['@id'] );
		// State the association from both ends, so the page is unambiguously
		// about this place rather than merely containing it.
		$data['WebPage']['about'] = array( '@id' => $node['@id'] );
	}

	/**
	 * @param array  $node    The assembled node.
	 * @param array  $row     The CCT row behind it.
	 * @param string $post_type
	 */
	$data['richSnippet'] = apply_filters( 'mfa_schema_directory_node', $node, $row, $post_type );

	return $data;
}
