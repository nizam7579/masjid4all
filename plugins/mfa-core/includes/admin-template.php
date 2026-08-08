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

add_filter( 'template_include', 'mfa_admin_template_include' );
function mfa_admin_template_include( $template ) {
	if ( is_admin() || ! is_page() || ! mfa_is_admin_area() ) {
		return $template;
	}

	$custom_template = MFA_CORE_PATH . 'templates/admin-page.php';

	return file_exists( $custom_template ) ? $custom_template : $template;
}
