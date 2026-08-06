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
	'includes/whatsapp-verify.php',
	'includes/widgets/prayer-times.php',
	'includes/widgets/qibla.php',
	'includes/widgets/daily-quran.php',
	'includes/widgets/set-cookies.php',
	'includes/widgets/share-button.php',
	'includes/widgets/qr-code.php',
	'includes/widgets/user-logout.php',
	'includes/widgets/homepage-stats.php',
	'includes/widgets/quran-page.php',
	'includes/widgets/quran-single.php',
	'includes/widgets/tool-pages.php',
	'includes/widgets/brand-pages.php',
	'includes/widgets/legal-pages.php',
	'includes/widgets/directory-pages.php',
	'includes/widgets/business-single.php',
	'includes/widgets/mosque-single.php',
	'includes/widgets/website-single.php',
	'includes/widgets/member-logged-out.php',
	'includes/widgets/coming-soon.php',
	'includes/widgets-enqueue.php',
);

foreach ( $mfa_core_includes as $mfa_core_include ) {
	$mfa_core_include_path = MFA_CORE_PATH . $mfa_core_include;
	if ( file_exists( $mfa_core_include_path ) ) {
		require_once $mfa_core_include_path;
	}
}
