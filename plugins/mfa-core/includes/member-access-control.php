<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access control for the /member/* sub-pages (community, business,
 * affiliate, premium - 2026-08-09). Runs on template_redirect so the
 * redirect fires BEFORE any output starts (wp_head(), etc.) - doing this
 * inside the page's own shortcode (as member-listing-single.php and
 * member-community-single.php previously did, showing an inline "not
 * found"/"please log in" message instead) is too late for a real
 * wp_safe_redirect(): do_shortcode() runs from inside the_content(), deep
 * into templates/member-page.php's output, well after wp_head() has
 * already sent HTML. /member/ itself is deliberately NOT gated here - it
 * already has its own logged-out view ([mfa_member_logged_out]) and
 * doesn't need a redirect.
 *
 * Two layers:
 * 1. Logged-out visitor on any gated sub-page -> redirect to /member/.
 * 2. Logged-in visitor on an id-scoped sub-page (business, community)
 *    whose ?id= doesn't belong to them -> redirect to /member/, rather
 *    than showing them someone else's resource state (or even just
 *    confirming a given id is valid/invalid, which is its own small
 *    information leak).
 */
add_action( 'template_redirect', 'mfa_member_subpage_access_control' );
function mfa_member_subpage_access_control() {
	if ( is_admin() || ! is_page() ) {
		return;
	}

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) || 70180 !== (int) $post->post_parent ) {
		return;
	}

	$gated_slugs = array( 'community', 'business', 'affiliate', 'premium' );
	if ( ! in_array( $post->post_name, $gated_slugs, true ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/member/' ) );
		exit;
	}

	$user_id    = get_current_user_id();
	$listing_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	if ( 'business' === $post->post_name ) {
		if ( ! $listing_id || ! mfa_user_owns_listing( $user_id, $listing_id ) ) {
			wp_safe_redirect( home_url( '/member/' ) );
			exit;
		}
	} elseif ( 'community' === $post->post_name ) {
		if ( ! $listing_id || ! mfa_user_joined_community( $user_id, $listing_id ) ) {
			wp_safe_redirect( home_url( '/member/' ) );
			exit;
		}
	}
}

/**
 * @param int $user_id
 * @param int $post_id A business/web CPT post ID.
 */
function mfa_user_owns_listing( $user_id, $post_id ) {
	global $wpdb;
	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}jet_cct_listing_owner WHERE post_id = %d AND user_id = %d",
			$post_id,
			$user_id
		)
	);
}

/**
 * @param int $user_id
 * @param int $mosque_post_id A masjid CPT post ID.
 */
function mfa_user_joined_community( $user_id, $mosque_post_id ) {
	// Same Post ID -> CCT ID mapping the join form itself uses
	// (enaizi-mfa/shortcodes/community.php).
	$cct_mosque_id = get_post_meta( $mosque_post_id, 'item_id', true );
	$cct_mosque_id = ! empty( $cct_mosque_id ) ? intval( $cct_mosque_id ) : 0;
	if ( ! $cct_mosque_id ) {
		return false;
	}

	global $wpdb;
	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT _ID FROM {$wpdb->prefix}jet_cct_community WHERE mosque_id = %d AND user_id = %d",
			$cct_mosque_id,
			$user_id
		)
	);
}
