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
	'includes/member-cct-core.php',
	'includes/identity-registration.php',
	'includes/whatsapp-verify.php',
	'includes/barakah.php',
	'includes/activity-log.php',
	'includes/commission.php',
	'includes/geohash.php',
	'includes/geohash-crawl.php',
	'includes/places.php',
	'includes/updates.php',
	'includes/website-extract.php',
	'includes/knowledge-ai.php',
	'includes/founding-member.php',
	'includes/ads.php',
	'includes/email-verification.php',
	'includes/member-template.php',
	'includes/member-access-control.php',
	'includes/member-relogin-status.php',
	'includes/admin-template.php',
	'includes/admin-access-control.php',
	'includes/widgets/prayer-times.php',
	'includes/widgets/qibla.php',
	'includes/widgets/daily-quran.php',
	'includes/widgets/set-cookies.php',
	'includes/widgets/share-button.php',
	'includes/widgets/sofia-button.php',
	'includes/widgets/qr-code.php',
	'includes/widgets/user-logout.php',
	'includes/widgets/homepage-stats.php',
	'includes/widgets/homepage.php',
	'includes/widgets/auth-pages.php',
	'includes/widgets/premium-page.php',
	'includes/widgets/contribute-pages.php',
	'includes/widgets/quran-page.php',
	'includes/widgets/quran-single.php',
	'includes/widgets/tool-pages.php',
	'includes/widgets/brand-pages.php',
	'includes/widgets/contact-form.php',
	'includes/widgets/legal-pages.php',
	'includes/widgets/directory-pages.php',
	'includes/widgets/place-hub.php',
	'includes/widgets/place-links.php',
	'includes/widgets/run-update.php',
	'includes/widgets/business-single.php',
	'includes/widgets/business-update-form.php',
	'includes/widgets/mosque-single.php',
	'includes/widgets/mosque-update-form.php',
	'includes/widgets/website-single.php',
	'includes/widgets/website-update-form.php',
	'includes/widgets/member-logged-out.php',
	'includes/widgets/member-header-footer.php',
	'includes/widgets/member-dashboard.php',
	'includes/widgets/member-listing-single.php',
	'includes/widgets/member-community-single.php',
	'includes/widgets/member-affiliate-single.php',
	'includes/widgets/member-account-modals.php',
	'includes/widgets/member-namecard.php',
	'includes/widgets/admin-shell.php',
	'includes/widgets/admin-member-list.php',
	'includes/widgets/admin-member-info.php',
	'includes/widgets/admin-mosque-list.php',
	'includes/widgets/admin-business-list.php',
	'includes/widgets/admin-website-list.php',
	'includes/widgets/admin-website-generate-start.php',
	'includes/widgets/admin-knowledge-list.php',
	'includes/widgets/admin-knowledge-ai.php',
	'includes/widgets/admin-knowledge-ai-generate.php',
	'includes/widgets/admin-blog-list.php',
	'includes/widgets/admin-inquiry-list.php',
	'includes/widgets/admin-inquiry-info.php',
	'includes/widgets/admin-reports.php',
	'includes/widgets/admin-crawler.php',
	'includes/widgets/admin-crawler-start.php',
	'includes/widgets/coming-soon.php',
	'includes/widgets/site-header.php',
	'includes/widgets/site-footer.php',
	'includes/widgets/modal.php',
	'includes/widgets/directory-single.php',
	'includes/widgets-enqueue.php',
);

foreach ( $mfa_core_includes as $mfa_core_include ) {
	$mfa_core_include_path = MFA_CORE_PATH . $mfa_core_include;
	if ( file_exists( $mfa_core_include_path ) ) {
		require_once $mfa_core_include_path;
	}
}
