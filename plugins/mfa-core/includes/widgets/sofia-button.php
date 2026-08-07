<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_sofia_button] - floating WhatsApp chat-launcher button + popup,
 * used in the site footer (Kadence Theme Builder "Footer Menu", post 83)
 * alongside [mfa_share_button]. Restores the "Sofia" floating button
 * removed 2026-08-06 (DEPLOYMENT-MANIFEST.md), now that niz-wa is
 * confirmed standalone - same WhatsApp click-to-chat link and copy as
 * the original Kadence modal (recovered from post 83 revision 230563),
 * rebuilt as a plain shortcode instead of a Kadence block/modal so it
 * isn't affected by the Theme Builder footer's do_blocks() gap (see
 * share-button-v13.css's "Footer nav bar" section for that same bug
 * hitting other blocks in this template).
 */
add_shortcode( 'mfa_sofia_button', 'mfa_sofia_button_shortcode' );
function mfa_sofia_button_shortcode() {
	$avatar = '/wp-content/uploads/2025/03/Sofia-icon.jpeg';
	$wa_link = 'https://api.whatsapp.com/send?phone=60189897579&text=I+have+a+question';

	ob_start();
	?>
	<button type="button" class="mfa-sofia-btn" id="mfa-sofia-btn" aria-haspopup="dialog" aria-controls="mfa-sofia-modal" aria-label="Chat with Sofia">
		<img src="<?php echo esc_url( $avatar ); ?>" alt="Sofia" loading="lazy">
	</button>

	<div class="mfa-sofia-overlay" id="mfa-sofia-overlay"></div>
	<div class="mfa-sofia-modal" id="mfa-sofia-modal" role="dialog" aria-modal="true" aria-label="Sofia - Masjid4All AI Agent" aria-hidden="true">
		<button type="button" class="mfa-sofia-modal-close" id="mfa-sofia-modal-close" aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<img class="mfa-sofia-modal-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="Sofia">
		<p class="mfa-sofia-modal-text">Hi, I&rsquo;m Sofia, your friendly AI assistant.<br>I&rsquo;m here to help you via WhatsApp.</p>
		<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noreferrer noopener" class="mfa-sofia-modal-cta">Ask Question</a>
	</div>
	<?php
	return ob_get_clean();
}
