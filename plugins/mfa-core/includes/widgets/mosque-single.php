<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_mosque_home_tab] - the "Home" tab content on the single mosque post
 * template (Kadence Theme Builder "Mosque" element, post 875). Plain HTML,
 * no Kadence blocks - replaces that tab's action-button row (Upload
 * Image / Share, each a Kadence modal block) and its
 * [niz_mfa_mosques_info] content column. The outer 4-tab structure
 * (Home / Community / Local Business / Review) stays Kadence for now;
 * only this tab's own content is rebuilt.
 *
 * The modal markup mirrors Kadence's own modal DOM shape (id on the
 * aria-hidden target div, .kt-modal-overlay + data-modal-close,
 * .kt-modal-close button, data-modal-open="<id>" on the trigger) so
 * Kadence's still-active frontend JS opens/closes it - same technique
 * used for the business single-post page's Home tab.
 *
 * The "Share" action button/modal is dropped per request - the site
 * already has a sitewide floating share button (share-button-v12.js).
 */
add_shortcode( 'mfa_mosque_home_tab', 'mfa_mosque_home_tab_shortcode' );
function mfa_mosque_home_tab_shortcode() {
	$post_id = get_the_ID();

	// Matches the original Kadence modal's blockVisibility restriction
	// (administrator + editor roles).
	$can_upload_image = current_user_can( 'administrator' ) || current_user_can( 'editor' );

	ob_start();
	?>
	<div class="mfa-mosque-home">
		<?php if ( $can_upload_image ) : ?>
			<div class="mfa-mosque-actions">
				<div class="mfa-mosque-modal-wrap">
					<div id="mfa-mosque-modal-image-<?php echo esc_attr( $post_id ); ?>" class="kadence-block-pro-modal kt-m-animate-in-fadeup kt-m-animate-out-fadeout" aria-hidden="true">
						<div class="kt-modal-overlay" tabindex="-1" data-modal-close="true">
							<div class="kt-modal-container kt-modal-height-fittocontent kt-close-position-inside" role="dialog" aria-modal="true">
								<button class="kt-modal-close" aria-label="Close Modal" data-modal-close="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<div class="kt-modal-content">
									<?php echo do_shortcode( '[cpt_image_manager]' ); ?>
								</div>
							</div>
						</div>
					</div>
					<button type="button" class="mfa-mosque-action-btn" data-modal-open="mfa-mosque-modal-image-<?php echo esc_attr( $post_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
						Upload Image
					</button>
				</div>
			</div>
		<?php endif; ?>

		<?php echo do_shortcode( '[niz_mfa_mosques_info]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [mfa_mosque_local_business_tab] - the "Local Business" tab content on
 * the single mosque post template. Plain HTML, no Kadence blocks -
 * replaces that tab's heading/subheading; keeps reusing the existing
 * [niz_mfa_local_business] shortcode (enaizi-mfa/shortcodes/business.php)
 * for the actual list/AJAX loading, unchanged - that widget was already
 * a plain shortcode, not a Kadence block, so it didn't need rebuilding.
 */
add_shortcode( 'mfa_mosque_local_business_tab', 'mfa_mosque_local_business_tab_shortcode' );
function mfa_mosque_local_business_tab_shortcode() {
	$name = get_the_title( get_the_ID() );

	ob_start();
	?>
	<div class="mfa-mosque-local">
		<h1 class="mfa-mosque-local-title">Businesses near <?php echo esc_html( $name ); ?></h1>
		<p class="mfa-mosque-local-sub">Support local businesses in your mosque community. Discover nearby products and services, and encourage local businesses around the mosque to join our directory and connect with the community.</p>
		<?php echo do_shortcode( '[niz_mfa_local_business]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}
