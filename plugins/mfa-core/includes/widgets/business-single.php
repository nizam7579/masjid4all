<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_business_home_tab] - the "Home" tab content on the single business
 * post template (Kadence Theme Builder "Business" element, post 9151).
 * Plain HTML, no Kadence blocks - replaces that tab's action-button row
 * (Update Info / Upload Image, each a Kadence modal block) and its
 * [niz_mfa_business_info] content column. The outer 4-tab structure
 * (Home / Nearby Mosques / Review / Claim) stays Kadence for now; only
 * this tab's own content is rebuilt.
 *
 * The modal markup below intentionally mirrors Kadence's own modal DOM
 * shape (id on the aria-hidden target div, .kt-modal-overlay +
 * data-modal-close, .kt-modal-close button, data-modal-open="<id>" on
 * the trigger) so Kadence's still-active frontend JS opens/closes it
 * exactly as it did for the original block - this isn't a dependency on
 * the Kadence *block editor*, just its already-loaded theme/plugin JS,
 * the same technique already used for the Sofia popup on the footer.
 *
 * The old "Update Image" modal is dropped: its Kadence block content was
 * a bare `<!-- wp:shortcode /-->` with no shortcode text, so it always
 * opened to an empty modal - dead, not a real feature. The original
 * "Share" action button/modal is also dropped - redundant with the
 * sitewide floating share button (share-button-v12.js) already present
 * on every page.
 */
add_shortcode( 'mfa_business_home_tab', 'mfa_business_home_tab_shortcode' );
function mfa_business_home_tab_shortcode() {
	global $wpdb;
	$post_id = get_the_ID();

	// Same ownership check niz_mfa_business_info() and
	// mfa_claim_business_listing_shortcode() already use.
	$is_authorized = false;
	if ( is_user_logged_in() ) {
		$current_user_id = get_current_user_id();
		if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) {
			$is_authorized = true;
		} else {
			$owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
			$is_owner    = $wpdb->get_var( $wpdb->prepare(
				"SELECT user_id FROM {$owner_table} WHERE post_id = %d AND user_id = %d LIMIT 1",
				$post_id,
				$current_user_id
			) );
			if ( $is_owner ) {
				$is_authorized = true;
			}
		}
	}
	$is_admin = current_user_can( 'administrator' );

	ob_start();
	?>
	<div class="mfa-biz-home">
		<div class="mfa-biz-actions">
			<?php if ( $is_authorized ) : ?>
				<div class="mfa-biz-modal-wrap">
					<div id="mfa-biz-modal-info-<?php echo esc_attr( $post_id ); ?>" class="kadence-block-pro-modal kt-m-animate-in-fadeup kt-m-animate-out-fadeout" aria-hidden="true">
						<div class="kt-modal-overlay" tabindex="-1" data-modal-close="true">
							<div class="kt-modal-container kt-modal-height-full kt-close-position-inside" role="dialog" aria-modal="true">
								<button class="kt-modal-close" aria-label="Close Modal" data-modal-close="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<div class="kt-modal-content">
									<h2>Update Information</h2>
									<?php echo do_shortcode( '[fluentform id="68"]' ); ?>
								</div>
							</div>
						</div>
					</div>
					<button type="button" class="mfa-biz-action-btn" data-modal-open="mfa-biz-modal-info-<?php echo esc_attr( $post_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
						Update Info
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $is_admin ) : ?>
				<div class="mfa-biz-modal-wrap">
					<div id="mfa-biz-modal-image-<?php echo esc_attr( $post_id ); ?>" class="kadence-block-pro-modal kt-m-animate-in-fadeup kt-m-animate-out-fadeout" aria-hidden="true">
						<div class="kt-modal-overlay" tabindex="-1" data-modal-close="true">
							<div class="kt-modal-container kt-modal-height-fittocontent kt-close-position-inside" role="dialog" aria-modal="true">
								<button class="kt-modal-close" aria-label="Close Modal" data-modal-close="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<div class="kt-modal-content">
									<?php echo do_shortcode( '[cpt_image_manager]' ); ?>
									<?php echo do_shortcode( '[niz_business_ai_updater]' ); ?>
								</div>
							</div>
						</div>
					</div>
					<button type="button" class="mfa-biz-action-btn" data-modal-open="mfa-biz-modal-image-<?php echo esc_attr( $post_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
						Upload Image
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php echo do_shortcode( '[niz_mfa_business_info]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [mfa_business_nearby_mosques_tab] - the "Nearby Mosques" tab content on
 * the single business post template (Kadence Theme Builder "Business"
 * element, post 9151). Plain HTML, no Kadence blocks - replaces that tab's
 * "Add Mosque" button, heading, and subheading; keeps reusing the existing
 * [niz_mfa_local_mosques] shortcode (enaizi-mfa/shortcodes/mosque.php) for
 * the actual mosque list/AJAX loading, unchanged - that widget was already
 * a plain shortcode, not a Kadence block, so it didn't need rebuilding.
 */
add_shortcode( 'mfa_business_nearby_mosques_tab', 'mfa_business_nearby_mosques_tab_shortcode' );
function mfa_business_nearby_mosques_tab_shortcode() {
	$name = get_the_title( get_the_ID() );

	ob_start();
	?>
	<div class="mfa-biz-nearby">
		<div class="mfa-biz-nearby-header">
			<h1 class="mfa-biz-nearby-title">Mosques near <?php echo esc_html( $name ); ?></h1>
			<a href="/add-mosque/" class="mfa-biz-action-btn">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
				Add Mosque
			</a>
		</div>
		<p class="mfa-biz-nearby-sub">We&rsquo;ve listed the closest mosques to this business to help you maintain your prayers while on the go. May Allah accept your ibadah.</p>
		<?php echo do_shortcode( '[niz_mfa_local_mosques]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [mfa_business_sidebar_mosques] - right-column "Nearby Mosques" widget on
 * the single business post template, replacing the "Business Directory"
 * widget that previously occupied that column. Reuses the same
 * [niz_mfa_local_mosques] widget as the tab above - it already generates a
 * unique wrapper ID per instance (wp_generate_password), so two copies on
 * one page load independently.
 */
add_shortcode( 'mfa_business_sidebar_mosques', 'mfa_business_sidebar_mosques_shortcode' );
function mfa_business_sidebar_mosques_shortcode() {
	ob_start();
	?>
	<div class="mfa-biz-sidebar-mosques">
		<h2 class="mfa-biz-sidebar-title">Nearby Mosques</h2>
		<?php echo do_shortcode( '[niz_mfa_local_mosques]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}
