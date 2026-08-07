<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_site_header] - sitewide header, replacing the Kadence Theme Builder
 * "Header" element (post 145920). Plain HTML, no Kadence blocks. Content
 * parity with the original: same 6 nav links (Home/Masjid/Business/
 * Websites/Knowledge/My Account, matching the "Desktop Menu" navigation
 * post 230001), same mobile dropdown categories (Main Menu/Tools/
 * Information) sourced from the original "Dropdown Menu" reusable block
 * (post 230310). Login/Register links to /member/, which already shows
 * its own "available soon" notice - no special-casing needed here since
 * public login/registration is closed sitewide.
 *
 * Desktop nav gets an active-page highlight, matching the footer's
 * existing is-active-footer-item pattern (see share-button.js) - class
 * added client-side in header.js since detecting "which page am I on"
 * needs the actual browser URL.
 *
 * Mobile popup (2026-08-07 revision): icons + 2-column TOOLS/INFORMATION
 * grids to fit without scrolling, plus the [niz_mfa_set_cookies] geo
 * widget appended at the bottom - matching live's richer popup design
 * (screenshot reference), which the original mfa-core rebuild only
 * partially replicated.
 */
add_shortcode( 'mfa_site_header', 'mfa_site_header_shortcode' );
function mfa_site_header_shortcode() {
	$is_logged_in = is_user_logged_in();

	$icon_home     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
	$icon_mosque   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 1 5 5c0 2-2 3-2 5H9c0-2-2-3-2-5a5 5 0 0 1 5-5z"></path><path d="M4 21v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6"></path><line x1="2" y1="21" x2="22" y2="21"></line></svg>';
	$icon_business = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>';
	$icon_web      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
	$icon_book     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>';
	$icon_clock    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
	$icon_compass  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>';
	$icon_arrow    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 16 16 12 12 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>';

	$main_nav = array(
		array( '/', 'Home', $icon_home ),
		array( '/masjid/', 'Masjid', $icon_mosque ),
		array( '/business/', 'Business', $icon_business ),
		array( '/web/', 'Websites', $icon_web ),
		array( '/knowledge-hub', 'Knowledge', $icon_book ),
	);

	$mobile_tools = array(
		array( '/prayer-times/', 'Prayer Times', $icon_clock ),
		array( '/qibla-finder/', 'Qibla Finder', $icon_compass ),
		array( '/quran', 'Daily Quran', $icon_book ),
	);

	$mobile_info = array(
		array( '/about-us/', 'About Us', $icon_arrow ),
		array( '/contact-us/', 'Contact Us', $icon_arrow ),
		array( '/privacy-policy/', 'Privacy Policy', $icon_arrow ),
		array( '/terms-of-service/', 'Service Policy', $icon_arrow ),
	);

	ob_start();
	?>
	<header class="mfa-header">
		<div class="mfa-header-inner">
			<a href="/" class="mfa-header-logo">
				<img src="/wp-content/uploads/2025/12/Masjid4All-logo-header.png" alt="Masjid4All">
			</a>

			<nav class="mfa-header-nav" aria-label="Primary">
				<?php foreach ( $main_nav as $item ) : ?>
					<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-nav-link"><?php echo esc_html( $item[1] ); ?></a>
				<?php endforeach; ?>
				<button type="button" class="mfa-header-nav-link mfa-header-more-trigger" id="mfa-header-more-trigger" aria-haspopup="true" aria-controls="mfa-header-mobile-menu">Tools</button>
			</nav>

			<div class="mfa-header-actions">
				<?php if ( $is_logged_in ) : ?>
					<a href="/member/" class="mfa-header-account-btn">My Account</a>
				<?php else : ?>
					<a href="/member/" class="mfa-header-account-btn">Login/Register</a>
				<?php endif; ?>

				<button type="button" class="mfa-header-burger" id="mfa-header-burger" aria-label="Open menu" aria-expanded="false" aria-controls="mfa-header-mobile-menu">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
				</button>
			</div>
		</div>

		<div class="mfa-header-mobile-overlay" id="mfa-header-mobile-overlay"></div>
		<div class="mfa-header-mobile-menu" id="mfa-header-mobile-menu" aria-hidden="true" inert>
			<div class="mfa-header-mobile-inner">
				<div class="mfa-header-mobile-topbar">
					<a href="/" class="mfa-header-mobile-logo">
						<img src="/wp-content/uploads/2025/12/Masjid4All-logo-header.png" alt="Masjid4All">
					</a>
					<button type="button" class="mfa-header-mobile-close" id="mfa-header-mobile-close" aria-label="Close menu">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>

				<?php if ( $is_logged_in ) : ?>
					<a href="/member/" class="mfa-header-mobile-account">My Account</a>
					<?php echo do_shortcode( '[niz_user_logout]' ); ?>
				<?php else : ?>
					<a href="/member/" class="mfa-header-mobile-account">Login/Register to My Account &rarr;</a>
				<?php endif; ?>

				<div class="mfa-header-mobile-group">
					<?php foreach ( $main_nav as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo $item[2]; ?><span><?php echo esc_html( $item[1] ); ?></span></a>
					<?php endforeach; ?>
				</div>

				<h4 class="mfa-header-mobile-heading">TOOLS</h4>
				<div class="mfa-header-mobile-group mfa-header-mobile-group--grid">
					<?php foreach ( $mobile_tools as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo $item[2]; ?><span><?php echo esc_html( $item[1] ); ?></span></a>
					<?php endforeach; ?>
				</div>

				<h4 class="mfa-header-mobile-heading">INFORMATION</h4>
				<div class="mfa-header-mobile-group mfa-header-mobile-group--grid">
					<?php foreach ( $mobile_info as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo $item[2]; ?><span><?php echo esc_html( $item[1] ); ?></span></a>
					<?php endforeach; ?>
				</div>

				<div class="mfa-header-mobile-geo">
					<?php echo do_shortcode( '[niz_mfa_set_cookies]' ); ?>
				</div>
			</div>
		</div>
	</header>
	<?php
	return ob_get_clean();
}
