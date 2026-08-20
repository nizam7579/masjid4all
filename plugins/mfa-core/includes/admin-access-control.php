<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates the entire /admin/* area (staff-only tool: Helpline Staff, Authors,
 * Editors, Administrators) to those roles, redirecting everyone else to
 * /member/ - same template_redirect-before-output reasoning as
 * member-access-control.php. Unlike /member/, the /admin/ root itself is
 * gated too here - there's no public logged-out view for a staff tool.
 *
 * These roles don't share one existing capability: 'editor', 'administrator'
 * and 'author' are checked as legacy role-slug capabilities (a WP core
 * quirk already relied on elsewhere in this plugin - see business-single.php/
 * website-single.php/mosque-single.php), and niz-wa's 'nwa_helpline' role
 * has its own real capability (nwa_manage_inbox) via NWA_Roles - checked
 * through niz-wa's own helper rather than duplicating that capability name
 * here, so this stays correct if niz-wa's capability model ever changes.
 *
 * Getting past this gate only means "allowed into the /admin/ area at
 * all" - which *sections* inside it each role can actually use is a
 * separate, finer-grained check below (mfa_user_can_access_admin_section()),
 * added 2026-08-17 alongside the /wp-admin lockdown and the /member/
 * "Admin" button - see [[project_admin_module]] for the full role model:
 * Administrator/Editor get every section, Helpline gets every section
 * except Reports, Author gets only Knowledge.
 */
function mfa_user_can_access_admin_area() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( current_user_can( 'administrator' ) || current_user_can( 'editor' ) || current_user_can( 'author' ) ) {
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

/**
 * Per-section access within the /admin/ area, for a user already known to
 * have passed mfa_user_can_access_admin_area() above. 'dashboard' (the
 * /admin/ root's own card grid) is always allowed for anyone in the area -
 * it's just a set of links, each of which is separately gated on arrival.
 *
 * Section slugs are the `post_name` of each top-level page directly under
 * MFA_ADMIN_ROOT_ID (mosque/business/whatsapp/member/website/knowledge/
 * blog/inquiry/reports/crawler) - see mfa_admin_section_from_post() below
 * for how a given page resolves to one of these, and mfa_admin_nav_items()
 * in admin-shell.php for where the same slugs are declared per nav item.
 */
function mfa_user_can_access_admin_section( $section, $user = null ) {
	$user  = $user ? $user : wp_get_current_user();
	$roles = (array) $user->roles;

	if ( 'dashboard' === $section ) {
		return true;
	}

	if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) ) {
		return true;
	}

	// 'nwa_helpline' - niz-wa's NWA_Roles::ROLE constant, not referenced
	// directly so this doesn't hard-depend on niz-wa being active; role
	// membership is plain usermeta regardless of which plugin registered it.
	if ( in_array( 'nwa_helpline', $roles, true ) ) {
		return 'reports' !== $section;
	}

	if ( in_array( 'author', $roles, true ) ) {
		return 'knowledge' === $section;
	}

	return false;
}

/**
 * Walks a post up to whichever of its ancestors is a direct child of the
 * /admin/ root (MFA_ADMIN_ROOT_ID) - that ancestor's post_name is the
 * section slug every sub-page (member/info, inquiry/info, website/generate,
 * crawler/start, whatsapp/knowledge-base, etc.) inherits its access from.
 * Same "walk to the root-level parent" shape as mfa_admin_page_hides_chrome()
 * uses for slug matching, just walking arbitrarily deep instead of one level.
 */
function mfa_admin_section_from_post( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_queried_object_id();
	if ( ! $post_id ) {
		return '';
	}

	if ( defined( 'MFA_ADMIN_ROOT_ID' ) && MFA_ADMIN_ROOT_ID === $post_id ) {
		return 'dashboard';
	}

	$post  = get_post( $post_id );
	$depth = 0;
	while ( $post && $post->post_parent && $depth < 10 ) {
		if ( defined( 'MFA_ADMIN_ROOT_ID' ) && MFA_ADMIN_ROOT_ID === (int) $post->post_parent ) {
			return $post->post_name;
		}
		$post = get_post( $post->post_parent );
		$depth++;
	}

	return $post ? $post->post_name : '';
}

/**
 * The one-line gate every section-specific admin shortcode calls as its
 * first statement (same shape as the pre-existing
 * `if ( ! current_user_can( 'manage_options' ) ) { return '...'; }` checks
 * this replaced in reports/crawler/crawler-start/website-generate-start/
 * inquiry-info - those had been Administrator-only by accident, since
 * manage_options isn't a capability Editor/Helpline/Author hold, which
 * would have silently blocked the access this whole feature is meant to
 * grant them). Returns '' (falsy) when allowed - callers only act on a
 * truthy return.
 */
function mfa_admin_require_section_access( $section ) {
	if ( mfa_user_can_access_admin_section( $section ) ) {
		return '';
	}

	return '<p class="mfa-body-muted">No Access.</p>';
}

/**
 * Restricts wp-admin itself (the native WordPress dashboard, not this
 * plugin's custom /admin/ area) to Administrator/Editor, with one scoped
 * exception for Author (see mfa_lockdown_wp_admin_for_author() below) -
 * added 2026-08-17 per explicit user request. Helpline and every other
 * role does its work entirely through the custom /admin/ area or /member/,
 * never wp-admin. Anyone else logged in and hitting a wp-admin screen is
 * bounced to /member/ - not-logged-in visitors are untouched (wp-login.php's
 * own flow already handles them) and AJAX/cron requests are excluded so
 * the rest of the site (which calls admin-ajax.php from the front end
 * regardless of the visitor's role) keeps working. This only changes where
 * an already-authenticated "wrong role" user is allowed to browse - it
 * doesn't touch passwords, sessions, tokens, or capabilities themselves.
 */
add_action( 'admin_init', 'mfa_lockdown_wp_admin_for_non_staff' );
function mfa_lockdown_wp_admin_for_non_staff() {
	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
		return;
	}

	if ( current_user_can( 'author' ) ) {
		mfa_lockdown_wp_admin_for_author();
		return;
	}

	wp_safe_redirect( home_url( '/member/' ) );
	exit;
}

/**
 * Author gets INTO wp-admin (2026-08-17 refinement - the /admin/knowledge/
 * list's "Add New"/"Edit" links open wp-admin's native post editor, same as
 * Blog, since neither content type has a public front-end submission form;
 * the outright lockdown above was bouncing Author back to /member/ the
 * moment they clicked Edit), but scoped to Knowledge-post-type screens
 * only - NOT left to WordPress's own role/capability system alone.
 *
 * Why that matters: 'knowledge' shares WordPress's generic 'post'
 * capability_type with nearly every other custom post type on this site
 * (mosque, business, web/website, blog, place, quran, city, card,
 * contactus - confirmed via get_post_type_object( $type )->capability_type
 * - and it's set via JetEngine's DB-stored CPT config, not any plugin's
 * PHP, so there's no simple code edit to give it a distinct capability
 * set). Author's default edit_posts/publish_posts capabilities are
 * therefore NOT naturally scoped to Knowledge alone - without this
 * function, Author could browse (and, for anything they're the post_author
 * of, edit) wp-admin's list/edit screens for any of those other post
 * types too. This function is the enforcement; MFA_ADMIN_KNOWLEDGE_PT
 * below is the one place that scope is declared.
 *
 * Known residual gap, deliberately not solved here (flag before expanding
 * Author's role further): this only gates wp-admin *page* navigation.
 * 'knowledge' (like most CPTs here) has show_in_rest => true, so its
 * capabilities are also reachable via the REST API directly
 * (/wp-json/wp/v2/...) - a technically-inclined Author could still create
 * posts of another generically-typed CPT (blog, mosque, etc.) via a direct
 * API call using their own real session, bypassing this wp-admin-only
 * check entirely. Fully closing that needs the post type itself to get a
 * distinct capability set (a register_post_type_args filter, or editing
 * JetEngine's stored config) - a bigger, separate change, not attempted
 * here since it risks affecting Administrator/Editor's existing access to
 * the same post type if done carelessly.
 */
define( 'MFA_ADMIN_KNOWLEDGE_PT', 'knowledge' );

function mfa_lockdown_wp_admin_for_author() {
	global $pagenow;

	// Own profile and the plain media-upload endpoints (needed to insert an
	// image into a Knowledge article from the block editor) - no post-type
	// concept applies to these, so they're allowed outright.
	if ( in_array( $pagenow, array( 'profile.php', 'async-upload.php', 'media-upload.php', 'upload.php' ), true ) ) {
		return;
	}

	if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		if ( MFA_ADMIN_KNOWLEDGE_PT === $post_type ) {
			return;
		}
	} elseif ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
		if ( MFA_ADMIN_KNOWLEDGE_PT === get_post_type( (int) $_GET['post'] ) ) {
			return;
		}
	}

	wp_safe_redirect( admin_url( 'edit.php?post_type=' . MFA_ADMIN_KNOWLEDGE_PT ) );
	exit;
}

/**
 * Who sees the dashboard's counts, as opposed to just its navigation.
 *
 * Three tiers, because the roles do different jobs:
 *
 *  - Administrator / Editor: everything.
 *  - Helpline: the Needs follow-up tiles only. Following up IS the role, and
 *    those five numbers are the queue; the Overview, arrival routes and lead
 *    counts are management reporting they have no action to take on.
 *  - Author: neither. Their remit is the Knowledge Hub, so the dashboard is
 *    only a way through to it.
 *
 * Unknown roles get nothing, which is the safe default - /admin/ access is
 * gated separately, so anyone reaching this page is already staff.
 */
function mfa_admin_dashboard_shows_metrics( $user = null ) {
	$roles = (array) ( $user ? $user : wp_get_current_user() )->roles;

	return in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true );
}

function mfa_admin_dashboard_shows_followup( $user = null ) {
	$roles = (array) ( $user ? $user : wp_get_current_user() )->roles;

	// 'nwa_helpline' by string for the same reason as
	// mfa_user_can_access_admin_section(): role membership is plain usermeta,
	// so this must not hard-depend on niz-wa being active.
	return mfa_admin_dashboard_shows_metrics( $user ) || in_array( 'nwa_helpline', $roles, true );
}
