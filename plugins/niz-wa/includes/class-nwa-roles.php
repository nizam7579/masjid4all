<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Helpline Staff" role + a single nwa_manage_inbox capability - lets
 * non-admin staff use the front-end [nwa_inbox]/[nwa_knowledge_base]
 * shortcodes (class-nwa-shortcodes.php) without any wp-admin access at
 * all. Administrators get the same capability automatically so they can
 * use the same front-end pages too, instead of the wp-admin Inbox/
 * Knowledge Base screens those shortcodes replaced (removed from
 * class-nwa-admin.php - Settings/Actions stay wp-admin-only).
 *
 * activate() is idempotent (add_role()/add_cap() are both safe to call
 * repeatedly) and is called both on plugin activation (fresh installs)
 * and on every 'init' (so an already-active install picking up this
 * update - e.g. via git pull rather than deactivate/reactivate - gets
 * the role/capability without needing to be manually reactivated).
 */
class NWA_Roles {

	const CAPABILITY = 'nwa_manage_inbox';
	const ROLE       = 'nwa_helpline';

	public static function activate() {
		add_role( self::ROLE, 'Helpline Staff', array(
			'read'             => true,
			self::CAPABILITY  => true,
		) );

		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
			$admin->add_cap( self::CAPABILITY );
		}
	}

	public static function current_user_can_manage() {
		return is_user_logged_in() && current_user_can( self::CAPABILITY );
	}
}
