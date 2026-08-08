<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_listing_single] - /member/business/?id=<post_id> (post 225188),
 * linked from member-dashboard.php's "My Business" list. MVP scope only
 * (2026-08-09): resolve the claimed listing and show its name, scoped to
 * the current user's own row in wp_jet_cct_listing_owner (same ownership
 * table the dashboard's My Business list already queries). Modify Info
 * (phone/WhatsApp/Facebook page/etc.) and premium-only image upload are
 * deliberately not built yet - explicitly deferred to a later pass.
 * Replaces the page's previous Kadence-block content entirely, per the
 * project's one-shortcode-per-page standing rule.
 */
add_shortcode( 'mfa_member_listing_single', 'mfa_member_listing_single_shortcode' );
function mfa_member_listing_single_shortcode() {

	if ( ! is_user_logged_in() ) {
		return '<p class="mfa-body-muted">Please log in to view this listing.</p>';
	}

	$listing_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	if ( ! $listing_id ) {
		return '<div class="mfa-dash"><div class="mfa-card mfa-dash-points-card"><p class="mfa-body-muted">No listing was specified.</p><a href="/member/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Back to Dashboard</a></div></div>';
	}

	global $wpdb;
	$user_id = get_current_user_id();

	$owned = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}jet_cct_listing_owner WHERE post_id = %d AND user_id = %d",
			$listing_id,
			$user_id
		)
	);

	if ( ! $owned ) {
		return '<div class="mfa-dash"><div class="mfa-card mfa-dash-points-card"><p class="mfa-body-muted">This listing wasn\'t found, or isn\'t linked to your account.</p><a href="/member/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Back to Dashboard</a></div></div>';
	}

	$title = get_the_title( $listing_id );

	ob_start();
	?>
	<div class="mfa-dash">
		<a href="/member/" class="mfa-dash-link-btn">&larr; Back to Dashboard</a>
		<div class="mfa-card mfa-dash-points-card">
			<h1 class="mfa-h2"><?php echo esc_html( $title ); ?></h1>
			<p class="mfa-body-muted">More management tools for this listing - updating contact info, uploading images, and more - are coming soon.</p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
