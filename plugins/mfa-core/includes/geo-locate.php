<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side reverse geocoding for the visitor location cookie.
 *
 * The browser used to call Nominatim directly from every visitor. That breaks
 * two ways at this traffic level: their usage policy is roughly one request a
 * second and wants an identifying User-Agent (a browser cannot set one), and
 * when they throttle, the old client code silently kept whatever stale values
 * were already in the cookies. Proxying it here fixes both - we send a proper
 * User-Agent, and we cache by geohash cell so thousands of visitors in one
 * city collapse into a single upstream call.
 *
 * Precision 5 is about a 5km cell, which is the right grain for "which city
 * and country is this" - finer would multiply cache misses for no benefit,
 * coarser would start naming the wrong town near a boundary.
 */

const MFA_GEO_CELL_PRECISION = 5;
const MFA_GEO_CACHE_TTL      = 30 * DAY_IN_SECONDS;

/**
 * City/country for a coordinate, cached per geohash cell.
 *
 * @return array|WP_Error array( country, city, cached )
 */
function mfa_geo_reverse_lookup( $lat, $lng ) {
	$lat = (float) $lat;
	$lng = (float) $lng;

	if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) {
		return new WP_Error( 'bad_coords', 'Coordinates out of range.' );
	}

	$cell = function_exists( 'mfa_geohash_encode' ) ? mfa_geohash_encode( $lat, $lng, MFA_GEO_CELL_PRECISION ) : '';
	$key  = 'mfa_geo_rev_' . ( $cell ? $cell : md5( $lat . ',' . $lng ) );

	$hit = get_transient( $key );
	if ( is_array( $hit ) && isset( $hit['country'] ) ) {
		$hit['cached'] = true;
		return $hit;
	}

	// Nominatim allows ~1 req/sec. Without this, a burst of visitors in
	// uncached cells would fire concurrent requests and get us blocked - the
	// caller treats a throttle as "keep the location you already have",
	// which is safe precisely because nothing is written unless we succeed.
	if ( get_transient( 'mfa_geo_lock' ) ) {
		return new WP_Error( 'throttled', 'Lookup busy, try again shortly.' );
	}
	set_transient( 'mfa_geo_lock', 1, 1 );

	$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query( array(
		'lat'            => $lat,
		'lon'            => $lng,
		'format'         => 'json',
		'zoom'           => 10, // City level - street detail is not needed here.
		'addressdetails' => 1,
	) );

	$resp = wp_remote_get( $url, array(
		'timeout' => 12,
		'headers' => array( 'User-Agent' => 'Masjid4AllLocate/1.0 (' . home_url() . ')' ),
	) );

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return new WP_Error( 'upstream', 'Geocoder returned ' . wp_remote_retrieve_response_code( $resp ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$addr = isset( $data['address'] ) && is_array( $data['address'] ) ? $data['address'] : array();

	$country = ! empty( $addr['country'] ) ? (string) $addr['country'] : '';
	if ( '' === $country ) {
		return new WP_Error( 'no_country', 'No country for that point.' );
	}

	// OSM names the populated place differently by region, so try the whole
	// ladder rather than just 'city' - the old client code checked three keys
	// and left the city cookie stale whenever none of them matched.
	$city = '';
	foreach ( array( 'city', 'town', 'village', 'municipality', 'suburb', 'county', 'state_district', 'state' ) as $k ) {
		if ( ! empty( $addr[ $k ] ) ) {
			$city = (string) $addr[ $k ];
			break;
		}
	}

	$out = array( 'country' => $country, 'city' => $city );
	set_transient( $key, $out, MFA_GEO_CACHE_TTL );

	$out['cached'] = false;
	return $out;
}

/**
 * admin-ajax endpoint the location widget calls. Public by design (visitors
 * are not logged in), and safe to be: it takes only a coordinate, returns only
 * a city and country, validates the range, and is cached and rate-limited
 * above, so it cannot be used to hammer the upstream service.
 */
add_action( 'wp_ajax_mfa_geo_locate', 'mfa_geo_locate_ajax' );
add_action( 'wp_ajax_nopriv_mfa_geo_locate', 'mfa_geo_locate_ajax' );
function mfa_geo_locate_ajax() {
	$lat = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0;
	$lng = isset( $_GET['lon'] ) ? (float) $_GET['lon'] : 0;

	$res = mfa_geo_reverse_lookup( $lat, $lng );

	if ( is_wp_error( $res ) ) {
		wp_send_json_error( array( 'message' => $res->get_error_message() ) );
	}

	wp_send_json_success( array(
		'country' => $res['country'],
		'city'    => $res['city'],
		'geohash' => function_exists( 'mfa_geohash_encode' ) ? mfa_geohash_encode( $lat, $lng, 9 ) : '',
		'cached'  => ! empty( $res['cached'] ),
	) );
}
