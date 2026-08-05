<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_share_button] - floating share button used in the site footer
 * (Kadence Theme Builder "Footer Menu", post 83), replacing the old
 * mfa_member_share modal. Triggers the OS-native share sheet via the
 * Web Share API instead of a custom in-page popup.
 *
 * The referrer id (from the `affiliateid` cookie) is read client-side
 * in JS rather than server-side in PHP: the footer is sitewide Kadence
 * Theme Builder content that can be page-cached for anonymous visitors,
 * so a PHP-time cookie read would bake in whichever visitor's affiliate
 * id happened to be present when the cache was written.
 */
add_shortcode( 'mfa_share_button', 'mfa_share_button_shortcode' );
function mfa_share_button_shortcode() {
	ob_start();
	?>
	<button type="button" class="mfa-share-btn" id="mfa-share-btn" aria-label="Share this page">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<circle cx="18" cy="5" r="3"></circle>
			<circle cx="6" cy="12" r="3"></circle>
			<circle cx="18" cy="19" r="3"></circle>
			<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
			<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
		</svg>
	</button>
	<span class="mfa-share-toast" id="mfa-share-toast" role="status" aria-live="polite"></span>
	<?php
	return ob_get_clean();
}
