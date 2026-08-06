<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_coming_soon message="..."] - shared "not available yet" badge used to
 * replace login/register modules across /register/, /add-mosque/,
 * /add-business/, /add-website/, and the Review/Claim tabs on the
 * business/mosque/website single-post templates, while public
 * registration/login is closed. Same visual style as the notice on
 * /member/ (see member-logged-out.php), pulled out here since it's reused
 * across many pages/tabs.
 */
add_shortcode( 'mfa_coming_soon', 'mfa_coming_soon_shortcode' );
function mfa_coming_soon_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'message' => 'This option will be available soon.',
	), $atts, 'mfa_coming_soon' );

	return '<p class="mfa-coming-soon-notice">' . esc_html( $atts['message'] ) . '</p>';
}
