<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_website_home_tab] - the "Home" tab content on the single website
 * listing, rendered via [mfa_directory_single] (single-web.php in
 * mfa-theme). That component owns the tab UI itself (Home / Review /
 * Claim Website - .mfa-dir-tabs, plain HTML/JS, zero Kadence); this
 * shortcode is just the "Home" tab's own pane content. The old Kadence
 * Theme Builder "Web" template (post 220902) this used to live inside is
 * vestigial now - kept only as a Kadence-theme-rollback fallback, not
 * part of the render path. The right-column "Islamic Resources" directory
 * sidebar ([niz_mfa_web_directory]) is a separate part of the config.
 *
 * Update Info / Upload Image are both [mfa_modal]-based - no Kadence
 * dependency of any kind on this page anymore, verified 2026-08-13 via a
 * live HTTP fetch (zero kadence-block-pro-modal markup or script in the
 * rendered output).
 *
 * Ownership check (Update Info visibility) replicates the same
 * jet_cct_listing_owner lookup niz_mfa_business_info()/mfa_website_info()
 * already use, done directly here instead of relying on the original
 * markup's ".owner" CSS-hide-for-unauthorized-visitors technique (that
 * technique required a separate [mfa_website_status] call purely for its
 * CSS-injection side effect - not needed once visibility is a real PHP
 * conditional).
 */
/**
 * Same ownership check mfa_website_info()/business-single.php's
 * mfa_business_user_can_manage() already use - extracted so
 * website-update-form.php's AJAX handler can re-check it server-side
 * (the form only being visible to authorized users doesn't stop a raw
 * POST to admin-ajax.php from anyone else).
 */
function mfa_website_user_can_manage( $post_id ) {
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

add_shortcode( 'mfa_website_home_tab', 'mfa_website_home_tab_shortcode' );
function mfa_website_home_tab_shortcode() {
	$post_id       = get_the_ID();
	$is_authorized = mfa_website_user_can_manage( $post_id );
	$is_admin      = current_user_can( 'administrator' );

	ob_start();
	?>
	<div class="mfa-web-home">
		<div class="mfa-web-actions">
			<?php if ( $is_authorized ) : ?>
				<?php
				$update_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
				echo do_shortcode( '[mfa_modal id="web-update-' . esc_attr( $post_id ) . '" title="Update Information" label="Update Info" button_class="mfa-web-action-btn" icon=\'' . $update_icon . '\'][mfa_website_update_form][/mfa_modal]' );
				?>
			<?php endif; ?>

			<?php if ( $is_admin ) : ?>
				<?php
				$upload_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>';
				echo do_shortcode( '[mfa_modal id="web-image-' . esc_attr( $post_id ) . '" title="Upload Image" label="Upload Image" button_class="mfa-web-action-btn" icon=\'' . $upload_icon . '\'][cpt_image_manager][/mfa_modal]' );

				// Always-visible "Update Content" (2026-08-17, moved out of the
				// Upload Image modal above where it was previously buried -
				// [mfa_website_update] was enclosed inside that modal's content,
				// easy to miss). Same engine as the inline New/Pending prompt in
				// mfa_web_info_display() below - its own JS is already
				// querySelectorAll-based, safe to render in both places - just
				// available regardless of listing_status.
				echo do_shortcode( '[mfa_website_update]' );
				?>
			<?php endif; ?>

			<?php echo mfa_claim_or_manage_cta( 'web', $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
