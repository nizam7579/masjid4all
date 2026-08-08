<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_listing_single] - /member/business/?id=<post_id> (post 225188),
 * linked from member-dashboard.php's "My Business" list. MVP scope only:
 * shows the claimed listing's name. Modify Info (phone/WhatsApp/Facebook
 * page/etc.) and premium-only image upload are deliberately not built yet -
 * explicitly deferred to a later pass. Replaces the page's previous
 * Kadence-block content entirely, per the project's one-shortcode-per-page
 * standing rule.
 *
 * Login and ownership (wp_jet_cct_listing_owner) are already enforced
 * before this ever runs - includes/member-access-control.php redirects
 * anyone who isn't logged in or doesn't own this ?id= back to /member/ on
 * template_redirect, well before the_content() gets to this shortcode.
 */
add_shortcode( 'mfa_member_listing_single', 'mfa_member_listing_single_shortcode' );
function mfa_member_listing_single_shortcode() {

	$listing_id = absint( $_GET['id'] );
	$title      = get_the_title( $listing_id );

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
