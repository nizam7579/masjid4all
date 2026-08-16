<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Place hubs - the crawlable geographic layer over the mosque/business
 * directory: /places/malaysia/ -> /places/malaysia/selangor/ ->
 * /places/malaysia/selangor/shah-alam/ and so on.
 *
 * WHY THIS EXISTS: before this, the directory grids (/masjid/, /business/,
 * /web/) rendered an empty container and loaded every listing through
 * admin-ajax.php, and the single pages had no server-rendered links to any
 * other listing. So the ~100K listing pages had ZERO internal links pointing
 * at them - the only discovery path Google had was the XML sitemap. That
 * fails at 100K and fails completely at the 1M target: sitemap-only URLs with
 * no internal links get crawled once, classified low-value, and dropped.
 * These hubs are the internal-linking structure that fixes it, and they double
 * as the promotable "Mosques in <city>" page once a city's crawl is complete.
 *
 * WHY A HIERARCHICAL CPT: registering with 'hierarchical' => true (both on the
 * post type and in its rewrite args) makes WordPress build the nested URL from
 * the parent chain on its own - core's get_post_permalink() calls
 * get_page_uri() for hierarchical types. No custom rewrite rules, no manual
 * path building, and depth is free-form: Malaysia is country > state >
 * district, the US is country > state > city > neighborhood, and neither has
 * to be special-cased.
 *
 * WHY MEMBERSHIP IS GEOGRAPHIC, NOT STRING-MATCHED: the crawler stores
 * country, latitude, longitude and geohash on every listing but NOT city -
 * mfa_geohash_guess_city() is computed for the SEO text and thrown away (see
 * geohash-crawl.php's upsert). So "which listings belong to this hub" cannot
 * be answered from a city column. Each hub instead stores the bounding box
 * Nominatim returns for its own name, and membership is a lat/lng range check
 * inside the hub's country. A bounding box rather than a radius because states
 * and districts aren't circles - a radius around Selangor's centre would spill
 * into Kuala Lumpur and miss the coast.
 *
 * Creation is deliberately manual (user decision 2026-08-16): hubs are created
 * by us, country first, then state by state, so we never auto-generate a long
 * tail of near-empty hub pages - which would recreate the thin-content problem
 * one level up.
 */

/** Bumping this reflushes rewrite rules once on the next load - without it a
 * freshly-uploaded plugin's /places/... URLs 404 until someone manually
 * re-saves permalinks, which looks exactly like a broken feature. */
define( 'MFA_PLACES_REWRITE_VERSION', '1' );

const MFA_PLACE_POST_TYPE = 'place';

/**
 * Statuses a hub will NOT show or count. Everything else counts.
 *
 * Deliberately an exclude-list, not an include-list. The first version of this
 * copied mfa_homepage_live_counts()'s include-list and undercounted badly:
 * Malaysia's hub showed 4,545 mosques and 909 businesses against 8,491 and
 * 6,207 in the crawler panel. Two causes, both structural:
 *
 * 1. The codebase has three disagreeing definitions of "listed" -
 *    mfa_geohash_crawl_status() counts mosques as New/Pending/Approved/Active,
 *    mfa_homepage_live_counts() as New/Pending/Approved/Verified/Premium
 *    (no Active, so it drops every Active mosque), and
 *    mfa_geohash_country_summary() applies no status filter at all. Copying any
 *    one of them just inherits its particular blind spot.
 * 2. An include-list silently drops any status nobody thought of - an empty
 *    string, or a value added later - which is how 85% of Malaysia's
 *    businesses disappeared from their own hub.
 *
 * An exclude-list matches the actual product decision (2026-08-16: "index all
 * that already listed except rejected, error etc") and fails safe: an unknown
 * status shows up and gets noticed, rather than vanishing silently.
 */
function mfa_place_excluded_statuses() {
	return array( 'Rejected', 'Error', 'Deleted' );
}

/* -------------------------------------------------------------------------
 * Post type
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mfa_place_register_post_type' );
function mfa_place_register_post_type() {
	register_post_type(
		MFA_PLACE_POST_TYPE,
		array(
			'labels'             => array(
				'name'               => 'Places',
				'singular_name'      => 'Place',
				'add_new_item'       => 'Add New Place',
				'edit_item'          => 'Edit Place',
				'all_items'          => 'All Places',
				'search_items'       => 'Search Places',
				'parent_item_colon'  => 'Parent Place:',
				'not_found'          => 'No places yet.',
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-location-alt',
			'hierarchical'       => true,
			'supports'           => array( 'title', 'editor', 'excerpt', 'page-attributes', 'thumbnail' ),
			'has_archive'        => false,
			'rewrite'            => array(
				'slug'         => 'places',
				'hierarchical' => true,
				'with_front'   => false,
			),
		)
	);
}

add_action( 'init', 'mfa_place_maybe_flush_rewrites', 99 );
function mfa_place_maybe_flush_rewrites() {
	if ( get_option( 'mfa_places_rewrite_version' ) === MFA_PLACES_REWRITE_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'mfa_places_rewrite_version', MFA_PLACES_REWRITE_VERSION );
}

/* -------------------------------------------------------------------------
 * Place data (meta accessors)
 * ---------------------------------------------------------------------- */

/**
 * A hub's own geography. `country` is inherited from the top-level ancestor
 * so a district never has to repeat it, and it's the country NAME (matching
 * jet_cct_mosque.country's free-text values), not the ISO code - the listing
 * tables store names, and mfa_geohash_fix_existing_countries() has already
 * normalised them.
 */
function mfa_place_geo( $post_id ) {
	$post_id = (int) $post_id;

	$ancestors = get_post_ancestors( $post_id );
	$root_id   = $ancestors ? (int) end( $ancestors ) : $post_id;

	$country = (string) get_post_meta( $root_id, '_mfa_place_country', true );
	if ( '' === $country ) {
		// A country hub that hasn't been geocoded yet still has its own title.
		$country = get_the_title( $root_id );
	}

	return array(
		'country'  => $country,
		'is_root'  => ( $root_id === $post_id ),
		'lat'      => (float) get_post_meta( $post_id, '_mfa_place_lat', true ),
		'lng'      => (float) get_post_meta( $post_id, '_mfa_place_lng', true ),
		'south'    => (float) get_post_meta( $post_id, '_mfa_place_south', true ),
		'north'    => (float) get_post_meta( $post_id, '_mfa_place_north', true ),
		'west'     => (float) get_post_meta( $post_id, '_mfa_place_west', true ),
		'east'     => (float) get_post_meta( $post_id, '_mfa_place_east', true ),
	);
}

/** True once a non-root hub has a usable bounding box. Root (country) hubs
 * don't need one - they match on the country field alone, which is both more
 * reliable and cheaper than a lat/lng range scan. */
function mfa_place_has_bbox( $geo ) {
	return ( $geo['north'] > $geo['south'] ) && ( $geo['east'] > $geo['west'] );
}

/* -------------------------------------------------------------------------
 * Geocoding
 * ---------------------------------------------------------------------- */

/**
 * Nominatim lookup that also captures the bounding box, which
 * mfa_geohash_geocode_city() (the crawler's own geocoder) doesn't return -
 * kept as a separate function rather than extending that one, because the
 * crawler's city search is on a live, credit-spending path and this is an
 * occasional admin-save-time call. Same endpoint and User-Agent convention.
 *
 * The query is the full ancestor chain ("Setapak, Kuala Lumpur, Malaysia"),
 * which is what stops Nominatim returning a same-named place on the other
 * side of the world.
 *
 * @return array|WP_Error lat/lng/south/north/west/east/display_name
 */
function mfa_place_geocode( $query ) {
	$query = trim( (string) $query );
	if ( '' === $query ) {
		return new WP_Error( 'empty_query', 'Nothing to geocode.' );
	}

	$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(
		array(
			'q'      => $query,
			'format' => 'json',
			'limit'  => 1,
		)
	);

	$resp = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array( 'User-Agent' => 'Masjid4AllPlaces/1.0 (admin tool; ' . home_url() . ')' ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}

	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
		return new WP_Error( 'not_found', 'Nominatim found nothing for "' . $query . '".' );
	}

	$hit = $data[0];

	// Nominatim's boundingbox is [south, north, west, east], as strings.
	$box = isset( $hit['boundingbox'] ) && is_array( $hit['boundingbox'] ) && 4 === count( $hit['boundingbox'] )
		? array_map( 'floatval', $hit['boundingbox'] )
		: array( 0, 0, 0, 0 );

	return array(
		'lat'          => (float) $hit['lat'],
		'lng'          => (float) $hit['lon'],
		'south'        => $box[0],
		'north'        => $box[1],
		'west'         => $box[2],
		'east'         => $box[3],
		'display_name' => isset( $hit['display_name'] ) ? (string) $hit['display_name'] : $query,
	);
}

/** The geocoding query for a hub: its own title plus every ancestor's,
 * outermost last ("Setapak, Kuala Lumpur, Malaysia"). */
function mfa_place_geocode_query( $post_id ) {
	$parts = array( get_the_title( $post_id ) );

	foreach ( get_post_ancestors( $post_id ) as $ancestor_id ) {
		$parts[] = get_the_title( $ancestor_id );
	}

	return implode( ', ', array_filter( $parts ) );
}

/**
 * Geocode on save, but only when the hub has no bounding box yet - so a
 * hand-corrected box is never silently overwritten by a later edit, and
 * re-saving a hub doesn't hit Nominatim every time. Publishing "Selangor"
 * with Malaysia as its parent is therefore the whole creation flow: no
 * coordinates to look up by hand.
 */
add_action( 'save_post_' . MFA_PLACE_POST_TYPE, 'mfa_place_autogeocode_on_save', 10, 3 );
function mfa_place_autogeocode_on_save( $post_id, $post, $update ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}

	$geo = mfa_place_geo( $post_id );
	if ( mfa_place_has_bbox( $geo ) ) {
		return;
	}

	$result = mfa_place_geocode( mfa_place_geocode_query( $post_id ) );
	if ( is_wp_error( $result ) ) {
		update_post_meta( $post_id, '_mfa_place_geocode_error', $result->get_error_message() );
		return;
	}

	delete_post_meta( $post_id, '_mfa_place_geocode_error' );
	update_post_meta( $post_id, '_mfa_place_lat', $result['lat'] );
	update_post_meta( $post_id, '_mfa_place_lng', $result['lng'] );
	update_post_meta( $post_id, '_mfa_place_south', $result['south'] );
	update_post_meta( $post_id, '_mfa_place_north', $result['north'] );
	update_post_meta( $post_id, '_mfa_place_west', $result['west'] );
	update_post_meta( $post_id, '_mfa_place_east', $result['east'] );

	// A top-level hub IS a country, so it owns the country name every
	// descendant inherits (see mfa_place_geo()).
	if ( ! get_post_ancestors( $post_id ) ) {
		update_post_meta( $post_id, '_mfa_place_country', get_the_title( $post_id ) );
	}

	mfa_place_flush_counts( $post_id );
}

/**
 * Give a hub a real SEO title/description on first publish, mirroring the
 * pattern mfa_geohash_default_seo() uses for crawled listings so the two read
 * consistently in search results. Only fills empties - a hand-written title on
 * a promoted city is never overwritten by a later save.
 */
add_action( 'save_post_' . MFA_PLACE_POST_TYPE, 'mfa_place_seed_seo_meta', 20, 2 );
function mfa_place_seed_seo_meta( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
		return;
	}

	$name    = get_the_title( $post_id );
	$parent  = wp_get_post_parent_id( $post_id );
	$context = $parent ? $name . ', ' . get_the_title( $parent ) : $name;

	if ( '' === (string) get_post_meta( $post_id, 'rank_math_title', true ) ) {
		update_post_meta( $post_id, 'rank_math_title', 'Mosques & Halal Businesses in ' . $context . ' | Masjid4All' );
	}

	if ( '' === (string) get_post_meta( $post_id, 'rank_math_description', true ) ) {
		$counts = mfa_place_counts( $post_id );
		update_post_meta(
			$post_id,
			'rank_math_description',
			sprintf(
				'Find %s mosques and %s halal businesses in %s. Prayer times, addresses, and directions on Masjid4All.',
				number_format_i18n( $counts['mosque'] ),
				number_format_i18n( $counts['business'] ),
				$context
			)
		);
	}

	if ( '' === (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ) {
		update_post_meta( $post_id, 'rank_math_focus_keyword', 'mosques in ' . $name . ', masjid ' . $name . ', halal food ' . $name );
	}
}

/* -------------------------------------------------------------------------
 * Listing membership
 * ---------------------------------------------------------------------- */

/**
 * WHERE fragment + args selecting the listings that belong to a hub.
 * Country hubs match on the indexed `country` column alone. A direct child of
 * a country hub (state-level, depth 1) matches on the exact `state` column
 * instead of a bounding box wherever a listing has one - a plain equality
 * check can't overlap the way two neighbouring states' boxes can (Malaysia's
 * 16 states summed to ~9,000 mosques against the country's own 4,545 before
 * this; see the `state` column work in geohash-crawl.php and
 * [[project_places_hub]] for the full story). Rows still missing a `state`
 * value (not yet crawled/backfilled, or a country the parser/reverse-geocode
 * fallback hasn't reached) fall back to the bounding box, so a hub doesn't
 * silently lose coverage mid-migration. Deeper hubs (district/city, depth 2+)
 * have no per-listing column at that granularity and still use the bounding
 * box only. Returns null when a non-root hub has no box yet (not geocoded, or
 * geocoding failed) - the caller shows an empty state rather than silently
 * listing an entire country under a district.
 */
function mfa_place_listing_where( $place_id, $table_alias = '' ) {
	$geo = mfa_place_geo( $place_id );
	if ( '' === $geo['country'] ) {
		return null;
	}
	if ( ! $geo['is_root'] && ! mfa_place_has_bbox( $geo ) ) {
		return null;
	}

	$p        = $table_alias ? $table_alias . '.' : '';
	$statuses = mfa_place_excluded_statuses();
	$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// NOT IN (...) is NULL-unsafe in SQL - a NULL listing_status makes the whole
	// predicate NULL, i.e. false - so spell out the NULL case rather than losing
	// those rows the same way the include-list lost them.
	$sql  = "( {$p}listing_status IS NULL OR {$p}listing_status NOT IN ({$in}) ) AND {$p}country = %s";
	$args = array_merge( $statuses, array( $geo['country'] ) );

	if ( $geo['is_root'] ) {
		return array( 'sql' => $sql, 'args' => $args );
	}

	if ( 1 === count( get_post_ancestors( $place_id ) ) ) {
		$sql   .= " AND ( {$p}state = %s OR ( ( {$p}state IS NULL OR {$p}state = '' ) AND {$p}latitude BETWEEN %f AND %f AND {$p}longitude BETWEEN %f AND %f ) )";
		$args[] = get_the_title( $place_id );
	} else {
		$sql .= " AND {$p}latitude BETWEEN %f AND %f AND {$p}longitude BETWEEN %f AND %f";
	}

	$args[] = $geo['south'];
	$args[] = $geo['north'];
	$args[] = $geo['west'];
	$args[] = $geo['east'];

	return array( 'sql' => $sql, 'args' => $args );
}

function mfa_place_table( $type ) {
	global $wpdb;
	return $wpdb->prefix . ( 'mosque' === $type ? 'jet_cct_mosque' : 'jet_cct_business' );
}

/**
 * One page of listings for a hub, newest-rated first so the most useful
 * records lead. Ordered with `_ID` as a tie-breaker for the same reason the
 * directory's Load More queries needed one: without it MySQL's row order for
 * ties isn't stable across paginated requests, which silently skips and
 * duplicates rows between pages.
 */
function mfa_place_listings( $place_id, $type, $paged = 1, $per_page = 24 ) {
	global $wpdb;

	$where = mfa_place_listing_where( $place_id );
	if ( null === $where ) {
		return array( 'rows' => array(), 'total' => 0 );
	}

	$table  = mfa_place_table( $type );
	$paged  = max( 1, (int) $paged );
	$offset = ( $paged - 1 ) * $per_page;

	$total = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where['sql']}", $where['args'] )
	);

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT _ID, name, address, page_url, cct_single_post_id, rating, rating_count
			 FROM {$table}
			 WHERE {$where['sql']}
			 ORDER BY rating_count DESC, rating DESC, _ID ASC
			 LIMIT %d OFFSET %d",
			array_merge( $where['args'], array( $per_page, $offset ) )
		),
		ARRAY_A
	);

	return array( 'rows' => $rows ? $rows : array(), 'total' => $total );
}

/**
 * Mosque + business totals for a hub, cached for 6h. Cached because a hub page
 * shows its own counts plus one count per child hub - a Malaysia page with 16
 * states would otherwise run 34 COUNT(*) queries per view.
 */
function mfa_place_counts( $place_id ) {
	global $wpdb;

	$key    = 'mfa_place_counts_' . (int) $place_id;
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}

	$where = mfa_place_listing_where( $place_id );
	if ( null === $where ) {
		$counts = array( 'mosque' => 0, 'business' => 0 );
	} else {
		$counts = array(
			'mosque'   => (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . mfa_place_table( 'mosque' ) . " WHERE {$where['sql']}", $where['args'] )
			),
			'business' => (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . mfa_place_table( 'business' ) . " WHERE {$where['sql']}", $where['args'] )
			),
		);
	}

	set_transient( $key, $counts, 6 * HOUR_IN_SECONDS );
	return $counts;
}

/** Clear a hub's cached counts and its ancestors' (a district gaining
 * listings changes its state's and country's totals too). */
function mfa_place_flush_counts( $place_id ) {
	delete_transient( 'mfa_place_counts_' . (int) $place_id );
	foreach ( get_post_ancestors( $place_id ) as $ancestor_id ) {
		delete_transient( 'mfa_place_counts_' . (int) $ancestor_id );
	}
}

/** Immediate children of a hub, alphabetical - the "all states" list on a
 * country page. */
function mfa_place_children( $place_id ) {
	return get_posts(
		array(
			'post_type'      => MFA_PLACE_POST_TYPE,
			'post_parent'    => (int) $place_id,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		)
	);
}

/** Sibling hubs, for the "Mosques in Setapak / Ampang" cross-links. */
function mfa_place_siblings( $place_id, $limit = 12 ) {
	$parent = wp_get_post_parent_id( $place_id );
	if ( ! $parent ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => MFA_PLACE_POST_TYPE,
			'post_parent'    => $parent,
			'post_status'    => 'publish',
			'post__not_in'   => array( (int) $place_id ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => (int) $limit,
		)
	);
}

/**
 * The most specific hub containing a given point - what a mosque page uses to
 * link back up ("Mosques in Setapak"). Deepest match wins, so a listing inside
 * both Kuala Lumpur and Setapak links to Setapak.
 *
 * Kept as a plain meta_query over what will be a small set of hubs (hundreds,
 * not thousands, because creation is manual) rather than a spatial index.
 */
function mfa_place_for_coords( $lat, $lng, $country = '' ) {
	$lat = (float) $lat;
	$lng = (float) $lng;
	if ( ! $lat || ! $lng ) {
		return null;
	}

	$hubs = get_posts(
		array(
			'post_type'      => MFA_PLACE_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$best       = null;
	$best_depth = -1;

	foreach ( $hubs as $hub_id ) {
		$geo = mfa_place_geo( $hub_id );

		if ( $country && $geo['country'] && strcasecmp( $country, $geo['country'] ) !== 0 ) {
			continue;
		}
		if ( ! mfa_place_has_bbox( $geo ) ) {
			continue;
		}
		if ( $lat < $geo['south'] || $lat > $geo['north'] || $lng < $geo['west'] || $lng > $geo['east'] ) {
			continue;
		}

		$depth = count( get_post_ancestors( $hub_id ) );
		if ( $depth > $best_depth ) {
			$best       = $hub_id;
			$best_depth = $depth;
		}
	}

	return $best;
}

/* -------------------------------------------------------------------------
 * Admin: geography meta box
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes_' . MFA_PLACE_POST_TYPE, 'mfa_place_add_meta_box' );
function mfa_place_add_meta_box() {
	add_meta_box( 'mfa-place-geo', 'Geography', 'mfa_place_render_meta_box', MFA_PLACE_POST_TYPE, 'side', 'high' );
}

function mfa_place_render_meta_box( $post ) {
	$geo   = mfa_place_geo( $post->ID );
	$error = get_post_meta( $post->ID, '_mfa_place_geocode_error', true );

	wp_nonce_field( 'mfa_place_geo_' . $post->ID, 'mfa_place_geo_nonce' );

	echo '<p style="margin-top:0;color:#666;">Looked up automatically from the place name and its parents on publish. Clear the box below and update to look it up again.</p>';

	if ( $error ) {
		echo '<p style="color:#b32d2e;"><strong>Lookup failed:</strong> ' . esc_html( $error ) . '</p>';
	}

	$fields = array(
		'_mfa_place_lat'   => 'Latitude',
		'_mfa_place_lng'   => 'Longitude',
		'_mfa_place_south' => 'South',
		'_mfa_place_north' => 'North',
		'_mfa_place_west'  => 'West',
		'_mfa_place_east'  => 'East',
	);

	foreach ( $fields as $key => $label ) {
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="text" id="%1$s" name="%1$s" value="%3$s" style="width:100%%"></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( get_post_meta( $post->ID, $key, true ) )
		);
	}

	if ( ! wp_get_post_parent_id( $post->ID ) ) {
		printf(
			'<p><label for="_mfa_place_country"><strong>Country name</strong> (must match the listings\' country field)</label><br><input type="text" id="_mfa_place_country" name="_mfa_place_country" value="%s" style="width:100%%"></p>',
			esc_attr( get_post_meta( $post->ID, '_mfa_place_country', true ) )
		);
	} else {
		echo '<p style="color:#666;">Country inherited: <strong>' . esc_html( $geo['country'] ) . '</strong></p>';
	}
}

add_action( 'save_post_' . MFA_PLACE_POST_TYPE, 'mfa_place_save_meta_box', 5 );
function mfa_place_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['mfa_place_geo_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_place_geo_nonce'] ) ), 'mfa_place_geo_' . $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array( '_mfa_place_lat', '_mfa_place_lng', '_mfa_place_south', '_mfa_place_north', '_mfa_place_west', '_mfa_place_east' ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		if ( '' === $raw ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, (float) $raw );
		}
	}

	if ( isset( $_POST['_mfa_place_country'] ) ) {
		update_post_meta( $post_id, '_mfa_place_country', sanitize_text_field( wp_unslash( $_POST['_mfa_place_country'] ) ) );
	}

	mfa_place_flush_counts( $post_id );
}

/* -------------------------------------------------------------------------
 * WP-CLI: bulk-create a level
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Create child hubs under a parent in one go, geocoding each.
	 *
	 *   wp mfa places-add --parent=malaysia --names="Selangor,Johor,Penang"
	 *   wp mfa places-add --parent=0 --names="Malaysia"
	 *
	 * Sleeps 1s between lookups to stay inside Nominatim's usage policy.
	 */
	WP_CLI::add_command( 'mfa places-add', function ( $args, $assoc ) {
		$parent_arg = isset( $assoc['parent'] ) ? $assoc['parent'] : '0';
		$names      = isset( $assoc['names'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['names'] ) ) ) : array();

		if ( ! $names ) {
			WP_CLI::error( 'Pass --names="A,B,C".' );
		}

		$parent_id = 0;
		if ( '0' !== (string) $parent_arg ) {
			$parent = is_numeric( $parent_arg )
				? get_post( (int) $parent_arg )
				: get_page_by_path( $parent_arg, OBJECT, MFA_PLACE_POST_TYPE );
			if ( ! $parent ) {
				WP_CLI::error( 'Parent not found: ' . $parent_arg );
			}
			$parent_id = $parent->ID;
		}

		foreach ( $names as $name ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => MFA_PLACE_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $name,
					'post_parent' => $parent_id,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( $name . ': ' . $post_id->get_error_message() );
				continue;
			}

			$counts = mfa_place_counts( $post_id );
			$error  = get_post_meta( $post_id, '_mfa_place_geocode_error', true );

			WP_CLI::log( sprintf(
				'%s -> %s (%d mosques, %d businesses)%s',
				$name,
				get_permalink( $post_id ),
				$counts['mosque'],
				$counts['business'],
				$error ? ' [geocode failed: ' . $error . ']' : ''
			) );

			sleep( 1 );
		}

		WP_CLI::success( 'Done.' );
	} );
}
