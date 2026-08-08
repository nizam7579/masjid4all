<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_community_single] - /member/community/?id=<mosque_post_id>
 * (post 225183), linked from member-dashboard.php's "My Community" list.
 * Mirrors member-listing-single.php's MVP scope: resolve the mosque and
 * confirm the current user actually joined its community (via
 * wp_jet_cct_community, same table enaizi-mfa/shortcodes/community.php's
 * [mosque_community] join form writes to), then show just its name.
 * The interactive community space (post messages, etc.) is explicitly
 * "not ready yet" per the original request - not built here. Replaces
 * the page's previous Kadence-block content entirely, per the project's
 * one-shortcode-per-page standing rule.
 */
add_shortcode( 'mfa_member_community_single', 'mfa_member_community_single_shortcode' );
function mfa_member_community_single_shortcode() {

	if ( ! is_user_logged_in() ) {
		return '<p class="mfa-body-muted">Please log in to view this community.</p>';
	}

	$mosque_post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	if ( ! $mosque_post_id ) {
		return '<div class="mfa-dash"><div class="mfa-card mfa-dash-points-card"><p class="mfa-body-muted">No mosque was specified.</p><a href="/member/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Back to Dashboard</a></div></div>';
	}

	// Same Post ID -> CCT ID mapping the join form itself uses.
	$cct_mosque_id = get_post_meta( $mosque_post_id, 'item_id', true );
	$cct_mosque_id = ! empty( $cct_mosque_id ) ? intval( $cct_mosque_id ) : 0;

	global $wpdb;
	$user_id = get_current_user_id();

	$is_member = $cct_mosque_id ? $wpdb->get_var(
		$wpdb->prepare(
			"SELECT _ID FROM {$wpdb->prefix}jet_cct_community WHERE mosque_id = %d AND user_id = %d",
			$cct_mosque_id,
			$user_id
		)
	) : null;

	if ( ! $is_member ) {
		return '<div class="mfa-dash"><div class="mfa-card mfa-dash-points-card"><p class="mfa-body-muted">You haven\'t joined this mosque\'s community yet.</p><a href="/member/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Back to Dashboard</a></div></div>';
	}

	$title = get_the_title( $mosque_post_id );

	ob_start();
	?>
	<div class="mfa-dash">
		<a href="/member/" class="mfa-dash-link-btn">&larr; Back to Dashboard</a>
		<div class="mfa-card mfa-dash-points-card">
			<h1 class="mfa-h2"><?php echo esc_html( $title ); ?></h1>
			<p class="mfa-body-muted">You're a member of this mosque's community. Posting updates, discussions, and more are coming soon.</p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
