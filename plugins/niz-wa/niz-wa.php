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
define( 'NWA_PATH', plugin_dir_path( __FILE__ ) );
define( 'NWA_URL', plugin_dir_url( __FILE__ ) );

require_once NWA_PATH . 'includes/class-nwa-config.php';
require_once NWA_PATH . 'includes/class-nwa-db.php';
require_once NWA_PATH . 'includes/class-nwa-sender.php';
require_once NWA_PATH . 'includes/class-nwa-ai.php';
require_once NWA_PATH . 'includes/class-nwa-router.php';
require_once NWA_PATH . 'includes/class-nwa-webhook.php';
require_once NWA_PATH . 'includes/class-nwa-admin.php';
require_once NWA_PATH . 'includes/site-integration.php';

function nwa_activate() {
	NWA_DB::create_tables();
}
register_activation_hook( __FILE__, 'nwa_activate' );

function nwa_init() {
	NWA_Webhook::init();
	NWA_Admin::init();
}
add_action( 'plugins_loaded', 'nwa_init' );
