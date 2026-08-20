<?php
/**
 * Plugin Name: Niz WA
 * Description: WhatsApp Business Cloud API chatbot — webhook, conversation log, action registry, AI intent routing, grounded Q&A, and user profile memory.
 * Version: 1.0.0
 * Author: Nizam
 * Text Domain: nemkad-wa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NWA_VERSION', '1.0.0' );
// Schema version - bump this whenever NWA_DB::create_tables() changes (new
// table / column / index). nwa_init() re-runs dbDelta on load when it differs
// from the stored nwa_db_version option, so a plain plugin-file update
// auto-syncs the schema in production without touching data.
define( 'NWA_DB_VERSION', '1.1.0' );
define( 'NWA_PATH', plugin_dir_path( __FILE__ ) );
define( 'NWA_URL', plugin_dir_url( __FILE__ ) );

require_once NWA_PATH . 'includes/class-nwa-config.php';
require_once NWA_PATH . 'includes/class-nwa-db.php';
require_once NWA_PATH . 'includes/class-nwa-sender.php';
require_once NWA_PATH . 'includes/class-nwa-media.php';
require_once NWA_PATH . 'includes/class-nwa-ai.php';
require_once NWA_PATH . 'includes/class-nwa-router.php';
require_once NWA_PATH . 'includes/class-nwa-webhook.php';
require_once NWA_PATH . 'includes/class-nwa-admin.php';
require_once NWA_PATH . 'includes/class-nwa-roles.php';
require_once NWA_PATH . 'includes/class-nwa-shortcodes.php';
// site-integration.php removed — moved to mfa-core so niz-wa has zero
// masjid4all-specific code. See mfa-core/includes/niz-wa-integration.php.

function nwa_activate() {
	NWA_DB::create_tables();
	NWA_Roles::activate();
}
register_activation_hook( __FILE__, 'nwa_activate' );

function nwa_init() {
	// Re-run idempotently on every load (not just on activation) so an
	// already-active install picks up the role/capability after a plain
	// code update — register_activation_hook() only fires on fresh
	// activation, not on files changing underneath an active plugin.
	NWA_Roles::activate();

	// Same reasoning for the DB schema: sync it on a code update, not only on
	// activation. Version-gated (dbDelta is non-destructive — creates tables
	// and adds missing columns/indexes, never drops), so it is safe to run
	// against live data. Bump NWA_DB_VERSION when the schema changes.
	if ( get_option( 'nwa_db_version' ) !== NWA_DB_VERSION ) {
		NWA_DB::create_tables();
		update_option( 'nwa_db_version', NWA_DB_VERSION );
	}

	NWA_Webhook::init();
	NWA_Admin::init();
	NWA_Shortcodes::init();
}
add_action( 'plugins_loaded', 'nwa_init' );
