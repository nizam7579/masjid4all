<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates the entire /admin/* area (staff-only tool: Helpline Staff, Editors,
 * Administrators) to those roles, redirecting everyone else to /member/ -
 * same template_redirect-before-output reasoning as
 * member-access-control.php. Unlike /member/, the /admin/ root itself is
 * gated too here - there's no public logged-out view for a staff tool.
 *
 * The three roles don't share one existing capability: 'editor' and
 * 'administrator' are checked as legacy role-slug capabilities (a WP core
 * quirk already relied on elsewhere in this plugin - see business-single.php/
 * website-single.php/mosque-single.php), and niz-wa's 'nwa_helpline' role
 * has its own real capability (nwa_manage_inbox) via NWA_Roles - checked
 * through niz-wa's own helper rather than duplicating that capability name
 * here, so this stays correct if niz-wa's capability model ever changes.
 */
function mfa_user_can_access_admin_area() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
		return true;
	}

	return class_exists( 'NWA_Roles' ) && NWA_Roles::current_user_can_manage();
}

add_action( 'template_redirect', 'mfa_admin_area_access_control' );
function mfa_admin_area_access_control() {
	if ( is_admin() || ! is_page() || ! mfa_is_admin_area() ) {
		return;
	}

	if ( ! mfa_user_can_access_admin_area() ) {
		wp_safe_redirect( home_url( '/member/' ) );
		exit;
	}
}
