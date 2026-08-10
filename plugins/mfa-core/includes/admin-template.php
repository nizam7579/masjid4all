<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /admin/* pages get a fully custom page template, same "bypass Kadence
 * entirely" approach as /member/* (see includes/member-template.php) -
 * this is an internal staff tool (Helpline Staff / Editors /
 * Administrators, see admin-access-control.php), not a themed variant of
 * the public site.
 */

define( 'MFA_ADMIN_ROOT_ID', 9343 );

function mfa_is_admin_area( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_queried_object_id();

	if ( ! $post_id ) {
		return false;
	}

	if ( MFA_ADMIN_ROOT_ID === $post_id ) {
		return true;
	}

	return in_array( MFA_ADMIN_ROOT_ID, get_post_ancestors( $post_id ), true );
}

/**
 * Pages that render without the shared [mfa_admin_header]/[mfa_admin_sidebar]
 * chrome - just the content, full-bleed (footer still shows). Currently only
 * /admin/member/info/ (217911), since it's opened from the Members list's
 * "View" button in a new tab for a quick lookup, not primary navigation.
 */
function mfa_admin_page_hides_chrome( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_queried_object_id();

	$chromeless_ids = array( 217911 );

	return in_array( $post_id, $chromeless_ids, true );
}

add_filter( 'template_include', 'mfa_admin_template_include' );
function mfa_admin_template_include( $template ) {
	if ( is_admin() || ! is_page() || ! mfa_is_admin_area() ) {
		return $template;
	}

	$custom_template = MFA_CORE_PATH . 'templates/admin-page.php';

	return file_exists( $custom_template ) ? $custom_template : $template;
}
