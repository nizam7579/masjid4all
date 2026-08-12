<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_website_home_tab] - the "Home" tab content on the single website
 * post template (Kadence Theme Builder "Web" element, post 220902). Plain
 * HTML, no Kadence blocks - replaces that tab's action-button row (Update
 * Info / Upload Image / Share, each a Kadence modal block) and its
 * [mfa_website_info] content column. The outer 3-tab structure
 * (Home / Review / Claim Website) stays Kadence for now; only this tab's
 * own content is rebuilt. The right-column "Islamic Resources" directory
 * sidebar ([niz_mfa_web_directory]) is untouched.
 *
 * The modal markup mirrors Kadence's own modal DOM shape (id on the
 * aria-hidden target div, .kt-modal-overlay + data-modal-close,
 * .kt-modal-close button, data-modal-open="<id>" on the trigger) so
 * Kadence's still-active frontend JS opens/closes it - same technique
 * used for the business and mosque single-post pages' Home tabs.
 *
 * Ownership check (Update Info visibility) replicates the same
 * jet_cct_listing_owner lookup niz_mfa_business_info()/mfa_website_info()
 * already use, done directly here instead of relying on the original
 * markup's ".owner" CSS-hide-for-unauthorized-visitors technique (that
 * technique required a separate [mfa_website_status] call purely for its
 * CSS-injection side effect - not needed once visibility is a real PHP
 * conditional).
 *
 * The old "Update Image" modal is dropped: its Kadence block content was
 * a bare `<!-- wp:shortcode /-->` with no shortcode text, so it always
 * opened to an empty modal - dead, not a real feature (same pattern
 * confirmed on the business page). The "Share" action button/modal is
 * also dropped per request - redundant with the sitewide floating share
 * button (share-button-v12.js) already present on every page.
 */
add_shortcode( 'mfa_website_home_tab', 'mfa_website_home_tab_shortcode' );
function mfa_website_home_tab_shortcode() {
	global $wpdb;
	$post_id = get_the_ID();

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
	<div class="mfa-web-home">
		<div class="mfa-web-actions">
			<?php if ( $is_authorized ) : ?>
				<div class="mfa-web-modal-wrap">
					<div id="mfa-web-modal-info-<?php echo esc_attr( $post_id ); ?>" class="kadence-block-pro-modal kt-m-animate-in-fadeup kt-m-animate-out-fadeout" aria-hidden="true">
						<div class="kt-modal-overlay" tabindex="-1" data-modal-close="true">
							<div class="kt-modal-container kt-modal-height-full kt-close-position-inside" role="dialog" aria-modal="true">
								<button class="kt-modal-close" aria-label="Close Modal" data-modal-close="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<div class="kt-modal-content">
									<h2>Update Information</h2>
									<?php echo do_shortcode( '[fluentform id="69"]' ); ?>
								</div>
							</div>
						</div>
					</div>
					<button type="button" class="mfa-web-action-btn" data-modal-open="mfa-web-modal-info-<?php echo esc_attr( $post_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
						Update Info
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $is_admin ) : ?>
				<div class="mfa-web-modal-wrap">
					<div id="mfa-web-modal-image-<?php echo esc_attr( $post_id ); ?>" class="kadence-block-pro-modal kt-m-animate-in-fadeup kt-m-animate-out-fadeout" aria-hidden="true">
						<div class="kt-modal-overlay" tabindex="-1" data-modal-close="true">
							<div class="kt-modal-container kt-modal-height-fittocontent kt-close-position-inside" role="dialog" aria-modal="true">
								<button class="kt-modal-close" aria-label="Close Modal" data-modal-close="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<div class="kt-modal-content">
									<?php echo do_shortcode( '[cpt_image_manager]' ); ?>
									<?php echo do_shortcode( '[mfa_website_update]' ); ?>
								</div>
							</div>
						</div>
					</div>
					<button type="button" class="mfa-web-action-btn" data-modal-open="mfa-web-modal-image-<?php echo esc_attr( $post_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
						Upload Image
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php echo mfa_web_info_display( $post_id ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * mfa_web_info_display() - status-gated body for the single-website Home tab,
 * replacing the old [mfa_website_info] shortcode. Driven by
 * jet_cct_web.listing_status:
 *   - Approved / Verified / Premium -> the actual post content
 *   - New / Pending / (empty)       -> a "Click to Update" button that triggers
 *                                      AI content generation (reuses the existing
 *                                      [mfa_website_update], its own nonce'd AJAX
 *                                      -> web_update_content). Empty status is
 *                                      treated as New (crawler-seeded rows).
 *   - Rejected / Error / Deleted    -> name, url, status + a "we're verifying
 *                                      the information" remark
 * Mirrors mfa_business_info_display() / mfa_mosque_info_display() and reuses
 * their CSS classes (directory-single-v1.css loads on the web single too).
 */
function mfa_web_info_display( $post_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT name, url, listing_status FROM {$table} WHERE cct_single_post_id = %d LIMIT 1",
		$post_id
	) );

	$status = $row ? trim( (string) $row->listing_status ) : '';
	$name   = ( $row && '' !== (string) $row->name ) ? $row->name : get_the_title( $post_id );
	$url    = $row ? trim( (string) $row->url ) : '';

	ob_start();
	echo '<div id="web-info-container" class="mosque-info-wrapper">';

	if ( isset( $_GET['added'] ) ) {
		echo '<div class="mfa-mosque-added-banner" role="status">&#10003; Website added! Help us complete its details below.</div>';
	}

	if ( in_array( $status, array( 'Approved', 'Verified', 'Premium' ), true ) ) {
		echo '<div class="mosque-actual-content">' . apply_filters( 'the_content', get_the_content( null, false, $post_id ) ) . '</div>';

	} elseif ( '' === $status || in_array( $status, array( 'New', 'Pending' ), true ) ) {
		?>
		<div class="mfa-mosque-update">
			<h1 class="mfa-mosque-update-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( '' !== $url ) : ?>
				<p class="mfa-mosque-update-address"><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></p>
			<?php endif; ?>
			<p class="mfa-mosque-update-intro">Full details for this website haven&rsquo;t been generated yet. Click below to fetch and publish up-to-date information.</p>
			<?php echo do_shortcode( '[mfa_website_update]' ); ?>
		</div>
		<?php
	} else {
		$show_status = '' !== $status ? $status : 'New';
		?>
		<div class="mfa-mosque-pending-card">
			<h1 class="mfa-mosque-pending-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( '' !== $url ) : ?>
				<p class="mfa-mosque-pending-address"><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></p>
			<?php endif; ?>
			<p class="mfa-mosque-pending-status">Status: <span><?php echo esc_html( $show_status ); ?></span></p>
			<p class="mfa-mosque-pending-remark">We are verifying the information. For any questions, please contact us.</p>
		</div>
		<?php
	}

	echo '</div>';
	return ob_get_clean();
}
