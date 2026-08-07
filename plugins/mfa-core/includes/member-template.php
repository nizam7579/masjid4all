<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /member/* pages get a fully custom page template - no Kadence Theme
 * Builder header/footer/promo-bar/sidebar/chatbot (decision 2026-08-07:
 * "fully custom page template, bypass Kadence entirely" over the
 * alternative of Theme Builder Display Rules). wp_head()/wp_footer()
 * still fire in templates/member-page.php so SEO/analytics/cache plugins
 * keep working - only Kadence's own header.php/footer.php chrome is
 * skipped, since that's what Theme Builder's fixed elements hook into.
 */

function mfa_is_member_area( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_queried_object_id();

	if ( ! $post_id ) {
		return false;
	}

	if ( 70180 === $post_id ) {
		return true;
	}

	return in_array( 70180, get_post_ancestors( $post_id ), true );
}

add_filter( 'template_include', 'mfa_member_template_include' );
function mfa_member_template_include( $template ) {
	if ( is_admin() || ! is_page() || ! mfa_is_member_area() ) {
		return $template;
	}

	$custom_template = MFA_CORE_PATH . 'templates/member-page.php';

	return file_exists( $custom_template ) ? $custom_template : $template;
}
