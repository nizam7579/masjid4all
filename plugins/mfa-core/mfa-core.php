<?php
/**
 * Plugin Name: MFA Core
 * Description: Consolidated core plugin for Masjid4All — identity/user management and niz-wa site integration. Replaces enaizi-identity and enaizi-user (phased).
 * Version: 1.0.0
 * Author: Nizam
 * Text Domain: mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MFA_CORE_VERSION', '1.0.0' );
define( 'MFA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MFA_CORE_URL', plugin_dir_url( __FILE__ ) );

$mfa_core_includes = array(
	'includes/identity-email.php',
	'includes/niz-wa-integration.php',
	'includes/identity-core.php',
);

foreach ( $mfa_core_includes as $mfa_core_include ) {
	$mfa_core_include_path = MFA_CORE_PATH . $mfa_core_include;
	if ( file_exists( $mfa_core_include_path ) ) {
		require_once $mfa_core_include_path;
	}
}
