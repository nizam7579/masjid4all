<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_site_footer] - sitewide footer for the public (non-member) area,
 * replacing the Kadence Theme Builder "Footer Menu" element (post 83). Plain
 * HTML, no Kadence blocks. Renders the mobile bottom navigation bar (5 items,
 * matching the header's main nav) plus the floating Sofia WhatsApp button and
 * share button (their own shortcodes, already enqueued sitewide on public
 * pages by widgets-enqueue.php).
 *
 * Intended to be called from the mfa-theme footer.php. The bottom-nav styling
 * and the body bottom-padding that reserves space for it live in the theme's
 * footer-nav.css (loaded only when mfa-theme is active), so this shortcode has
 * no visual effect while the Kadence theme is still active and nothing calls
 * it. Active-item highlighting is applied client-side by footer-nav.js.
 */
add_shortcode( 'mfa_site_footer', 'mfa_site_footer_shortcode' );
function mfa_site_footer_shortcode() {
	$icon_home     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
	$icon_mosque   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 1 5 5c0 2-2 3-2 5H9c0-2-2-3-2-5a5 5 0 0 1 5-5z"></path><path d="M4 21v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6"></path><line x1="2" y1="21" x2="22" y2="21"></line></svg>';
	$icon_business = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>';
	$icon_web      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
	$icon_book     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>';

	$nav = array(
		array( '/', 'Home', $icon_home ),
		array( '/masjid/', 'Masjid', $icon_mosque ),
		array( '/business/', 'Business', $icon_business ),
		array( '/web/', 'Websites', $icon_web ),
		array( '/knowledge-hub/', 'Knowledge', $icon_book ),
	);

	ob_start();
	?>
	<nav class="mfa-footer-nav" aria-label="Bottom navigation">
		<?php foreach ( $nav as $item ) : ?>
			<a href="<?php echo esc_url( $item[0] ); ?>" class="mfa-footer-nav-item" data-path="<?php echo esc_attr( $item[0] ); ?>">
				<?php echo $item[2]; ?>
				<span><?php echo esc_html( $item[1] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
	// Floating Sofia + share buttons (their CSS/JS is enqueued sitewide on
	// public pages by widgets-enqueue.php).
	echo do_shortcode( '[mfa_sofia_button]' );
	echo do_shortcode( '[mfa_share_button]' );

	return ob_get_clean();
}
