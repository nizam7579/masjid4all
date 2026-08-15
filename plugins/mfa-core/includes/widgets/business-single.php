<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_business_home_tab] - the "Home" tab content on the single business
 * listing, rendered via [mfa_directory_single] (mfa-core/includes/widgets/
 * directory-single.php -> single-business.php in mfa-theme). That component
 * owns the tab UI itself (Home / Nearby Mosques / Review / Claim -
 * .mfa-dir-tabs, plain HTML/JS, zero Kadence); this shortcode is just the
 * "Home" tab's own pane content. The old Kadence Theme Builder "Business"
 * template (post 9151) this used to live inside is vestigial now - kept
 * only as a Kadence-theme-rollback fallback, not part of the render path.
 *
 * Update Info / Upload Image are both [mfa_modal]-based (see modal-v1.css/
 * js) - no Kadence dependency of any kind on this page anymore, verified
 * 2026-08-13 via a live HTTP fetch (zero kadence-block-pro-modal markup or
 * script in the rendered output).
 */
/**
 * Same ownership check niz_mfa_business_info() and
 * mfa_claim_business_listing_shortcode() already use - extracted so
 * business-update-form.php's AJAX handler can re-check it server-side
 * (the form only being visible to authorized users doesn't stop a raw
 * POST to admin-ajax.php from anyone else).
 */
function mfa_business_user_can_manage( $post_id ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) {
		return true;
	}

	global $wpdb;
	$owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
	$is_owner    = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$owner_table} WHERE post_id = %d AND user_id = %d LIMIT 1",
		$post_id,
		get_current_user_id()
	) );

	return (bool) $is_owner;
}

add_shortcode( 'mfa_business_home_tab', 'mfa_business_home_tab_shortcode' );
function mfa_business_home_tab_shortcode() {
	$post_id       = get_the_ID();
	$is_authorized = mfa_business_user_can_manage( $post_id );
	$is_admin      = current_user_can( 'administrator' );

	ob_start();
	?>
	<div class="mfa-biz-home">
		<div class="mfa-biz-actions">
			<?php if ( $is_authorized ) : ?>
				<?php
				$update_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
				echo do_shortcode( '[mfa_modal id="biz-update-' . esc_attr( $post_id ) . '" title="Update Information" label="Update Info" button_class="mfa-biz-action-btn" icon=\'' . $update_icon . '\'][mfa_business_update_form][/mfa_modal]' );
				?>
			<?php endif; ?>

			<?php if ( $is_admin ) : ?>
				<?php
				$upload_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>';
				echo do_shortcode( '[mfa_modal id="biz-image-' . esc_attr( $post_id ) . '" title="Upload Image" label="Upload Image" button_class="mfa-biz-action-btn" icon=\'' . $upload_icon . '\'][cpt_image_manager][niz_business_ai_updater][/mfa_modal]' );
				?>
			<?php endif; ?>

			<?php echo mfa_claim_or_manage_cta( 'business', $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<?php echo mfa_business_info_display( $post_id ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * mfa_business_info_display() - status-gated body for the single-business Home
 * tab, replacing the old [niz_mfa_business_info] shortcode. Driven by the
 * jet_cct_business.listing_status:
 *   - Approved / Verified / Premium -> the actual post content
 *   - New / Pending / (empty)       -> a "Click to Update" button that triggers
 *                                      AI content generation (reuses the existing
 *                                      [niz_business_ai_updater], its own nonce'd
 *                                      AJAX -> business_update_content ->
 *                                      mfa_business_perplexity). Empty status is
 *                                      treated as New (crawler-seeded rows).
 *   - Rejected / Error / Deleted    -> name, address, status + a "we're
 *                                      verifying the information" remark
 * Mirrors mfa_mosque_info_display() and reuses its CSS classes (both single
 * templates load directory-single-v1.css).
 */
function mfa_business_info_display( $post_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_business';
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT name, address, listing_status FROM {$table} WHERE cct_single_post_id = %d LIMIT 1",
		$post_id
	) );

	$status  = $row ? trim( (string) $row->listing_status ) : '';
	$name    = ( $row && '' !== (string) $row->name ) ? $row->name : get_the_title( $post_id );
	$address = $row ? trim( (string) $row->address ) : '';

	ob_start();
	echo '<div id="business-info-container" class="mosque-info-wrapper">';

	if ( isset( $_GET['added'] ) ) {
		echo '<div class="mfa-mosque-added-banner" role="status">&#10003; Business added! Help us complete its details below.</div>';
	}

	if ( in_array( $status, array( 'Approved', 'Verified', 'Premium' ), true ) ) {
		echo '<div class="mosque-actual-content">' . apply_filters( 'the_content', get_the_content( null, false, $post_id ) ) . '</div>';

	} elseif ( '' === $status || in_array( $status, array( 'New', 'Pending' ), true ) ) {
		?>
		<div class="mfa-mosque-update">
			<h1 class="mfa-mosque-update-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( '' !== $address ) : ?>
				<p class="mfa-mosque-update-address"><?php echo esc_html( $address ); ?></p>
			<?php endif; ?>
			<p class="mfa-mosque-update-intro">Full details for this business haven&rsquo;t been generated yet. Click below to fetch and publish up-to-date information.</p>
			<?php echo do_shortcode( '[niz_business_ai_updater]' ); ?>
		</div>
		<?php
	} else {
		$show_status = '' !== $status ? $status : 'New';
		?>
		<div class="mfa-mosque-pending-card">
			<h1 class="mfa-mosque-pending-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( '' !== $address ) : ?>
				<p class="mfa-mosque-pending-address"><?php echo esc_html( $address ); ?></p>
			<?php endif; ?>
			<p class="mfa-mosque-pending-status">Status: <span><?php echo esc_html( $show_status ); ?></span></p>
			<p class="mfa-mosque-pending-remark">We are verifying the information. For any questions, please contact us.</p>
		</div>
		<?php
	}

	echo '</div>';
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
 * [mfa_business_sidebar_directory] - right-column "Business Directory"
 * widget on the single business post template. Plain HTML heading wrapping
 * the existing [niz_mfa_nearest_business columns="1"] widget (search box +
 * country select + business grid) - the same widget the original Kadence
 * template used here, and the same one used on the business directory page
 * itself, just not a Kadence block so no rebuilding needed.
 *
 * A [mfa_business_sidebar_mosques] widget briefly occupied this column
 * (reusing [niz_mfa_local_mosques]), but that duplicated the mosque list
 * already shown in the "Nearby Mosques" tab - reverted in favor of this.
 */
add_shortcode( 'mfa_business_sidebar_directory', 'mfa_business_sidebar_directory_shortcode' );
function mfa_business_sidebar_directory_shortcode() {
	ob_start();
	?>
	<div class="mfa-biz-sidebar-directory">
		<h2 class="mfa-biz-sidebar-title">Business Directory</h2>
		<?php echo do_shortcode( '[niz_mfa_nearest_business columns="1"]' ); ?>
	</div>
	<?php
	return ob_get_clean();
}
