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
 */
add_shortcode( 'mfa_site_header', 'mfa_site_header_shortcode' );
function mfa_site_header_shortcode() {
	$is_logged_in = is_user_logged_in();

	$main_nav = array(
		array( '/', 'Home' ),
		array( '/masjid/', 'Masjid' ),
		array( '/business/', 'Business' ),
		array( '/web/', 'Websites' ),
		array( '/knowledge-hub', 'Knowledge' ),
	);

	$mobile_tools = array(
		array( '/prayer-times/', 'Prayer Times' ),
		array( '/qibla-finder/', 'Qibla Finder' ),
	);

	$mobile_info = array(
		array( '/about-us/', 'About Us' ),
		array( '/contact-us/', 'Contact Us' ),
		array( '/privacy-policy/', 'Privacy Policy' ),
		array( '/terms-of-service/', 'Terms of Service' ),
	);

	ob_start();
	?>
	<header class="mfa-header">
		<div class="mfa-header-inner">
			<a href="/" class="mfa-header-logo">
				<img src="https://staging.masjid4all.com/wp-content/uploads/2025/12/Masjid4All-logo.png" alt="Masjid4All">
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
		<div class="mfa-header-mobile-menu" id="mfa-header-mobile-menu" aria-hidden="true">
			<div class="mfa-header-mobile-inner">
				<button type="button" class="mfa-header-mobile-close" id="mfa-header-mobile-close" aria-label="Close menu">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>

				<?php if ( $is_logged_in ) : ?>
					<a href="/member/" class="mfa-header-mobile-account">My Account</a>
					<?php echo do_shortcode( '[niz_user_logout]' ); ?>
				<?php else : ?>
					<a href="/member/" class="mfa-header-mobile-account">Login/Register to My Account &rarr;</a>
				<?php endif; ?>

				<div class="mfa-header-mobile-group">
					<?php foreach ( $main_nav as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo esc_html( $item[1] ); ?></a>
					<?php endforeach; ?>
				</div>

				<h4 class="mfa-header-mobile-heading">TOOLS</h4>
				<div class="mfa-header-mobile-group">
					<?php foreach ( $mobile_tools as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo esc_html( $item[1] ); ?></a>
					<?php endforeach; ?>
				</div>

				<h4 class="mfa-header-mobile-heading">INFORMATION</h4>
				<div class="mfa-header-mobile-group">
					<?php foreach ( $mobile_info as $item ) : ?>
						<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-header-mobile-link"><?php echo esc_html( $item[1] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</header>
	<?php
	return ob_get_clean();
}
