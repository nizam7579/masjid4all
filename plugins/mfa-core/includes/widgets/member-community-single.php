<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_community_single] - /member/community/?id=<mosque_post_id>
 * (post 225183), linked from member-dashboard.php's "My Community" list.
 * Mirrors member-listing-single.php's MVP scope: shows the mosque's name.
 * The interactive community space (post messages, etc.) is explicitly
 * "not ready yet" per the original request - not built here. Replaces
 * the page's previous Kadence-block content entirely, per the project's
 * one-shortcode-per-page standing rule.
 *
 * Login and membership (wp_jet_cct_community) are already enforced before
 * this ever runs - includes/member-access-control.php redirects anyone
 * who isn't logged in or hasn't joined this ?id='s community back to
 * /member/ on template_redirect, well before the_content() gets to this
 * shortcode.
 */
add_shortcode( 'mfa_member_community_single', 'mfa_member_community_single_shortcode' );
function mfa_member_community_single_shortcode() {

	$mosque_post_id = absint( $_GET['id'] );
	$title          = get_the_title( $mosque_post_id );

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
