<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_mosque_home_tab] - the "Home" tab content on the single mosque
 * listing, rendered via [mfa_directory_single] (single-masjid.php in
 * mfa-theme). That component owns the tab UI itself (Home / Community /
 * Local Business / Review - .mfa-dir-tabs, plain HTML/JS, zero Kadence);
 * this shortcode is just the "Home" tab's own pane content. The old
 * Kadence Theme Builder "Mosque" template (post 875) this used to live
 * inside is vestigial now - kept only as a Kadence-theme-rollback
 * fallback, not part of the render path.
 *
 * Upload Image is [mfa_modal]-based - no Kadence dependency of any kind
 * on this page. "Share" was dropped per request - the site already has a
 * sitewide floating share button (share-button-v12.js).
 *
 * Edit Mosque (added 2026-08-17, same Administrator/Editor gate as Upload
 * Image): opens [mfa_mosque_update_form] (mosque-update-form.php) in the
 * same [mfa_modal] pattern.
 *
 * Update Content (added 2026-08-17, same gate): a second, always-visible
 * entry point to the same AI-regeneration engine
 * mfa_mosque_info_display()'s inline "Click to Update" prompt already uses
 * for New/Pending mosques - that prompt stays exactly as-is (own DOM id/
 * class, own inline script scoped via getElementById), this is a
 * deliberately separate [mfa_mosque_ai_updater] button/class so both can
 * coexist on one page with zero collision. Same AJAX action
 * (mfa_mosque_ai_update) either way, which was already status-agnostic -
 * this just gives staff a way to trigger it regardless of the mosque's
 * current listing_status, e.g. after editing info via Edit Mosque above.
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
				<?php
				$upload_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>';
				echo do_shortcode( '[mfa_modal id="mosque-image-' . esc_attr( $post_id ) . '" title="Upload Image" label="Upload Image" button_class="mfa-mosque-action-btn" icon=\'' . $upload_icon . '\'][cpt_image_manager][/mfa_modal]' );

				$edit_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>';
				echo do_shortcode( '[mfa_modal id="mosque-edit-' . esc_attr( $post_id ) . '" title="Edit Mosque" label="Edit Mosque" button_class="mfa-mosque-action-btn" icon=\'' . $edit_icon . '\'][mfa_mosque_update_form][/mfa_modal]' );

				echo do_shortcode( '[mfa_mosque_ai_updater]' );
				?>
			</div>
		<?php endif; ?>

		<?php echo mfa_mosque_info_display( $post_id ); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * mfa_mosque_info_display() - status-gated body for the single-mosque Home
 * tab, replacing the old [niz_mfa_mosques_info] shortcode (enaizi-mfa) whose
 * auto-fire AJAX was dead code (it posted action="niz_mfa_mosques_ajax" but
 * the handler is registered as wp_ajax_mfa_mosques_callback - a name mismatch,
 * so it never ran and the spinner spun forever).
 *
 * Display is driven by the mosque's jet_cct_mosque.listing_status:
 *   - Approved / Active            -> the actual post content
 *   - New / Pending / (empty)      -> a "Click to Update" button that triggers
 *                                     AI content generation (mfa_mosque_ai_update
 *                                     below). "New" = freshly added via
 *                                     /add-mosque or seeded by our crawler; the
 *                                     public can help fill these in. Empty status
 *                                     is treated as New for older/imported rows.
 *   - Rejected / Error / Deleted   -> name, address, status + a "we're
 *                                     verifying the information" remark
 *
 * The AI generation reuses the existing engine (mosques_perplexity via
 * niz_mfa_mosques_callback), now click-triggered instead of auto-firing on
 * every page load - strictly more conservative on the paid Perplexity API.
 */
function mfa_mosque_info_display( $post_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_mosque';
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT name, address, listing_status FROM {$table} WHERE cct_single_post_id = %d LIMIT 1",
		$post_id
	) );

	$status  = $row ? trim( (string) $row->listing_status ) : '';
	$name    = ( $row && '' !== (string) $row->name ) ? $row->name : get_the_title( $post_id );
	$address = $row ? trim( (string) $row->address ) : '';

	ob_start();
	echo '<div id="mosque-info-container" class="mosque-info-wrapper">';

	// One-time confirmation shown right after a fresh /add-mosque submission
	// (which redirects here with ?added=1).
	if ( isset( $_GET['added'] ) ) {
		echo '<div class="mfa-mosque-added-banner" role="status">&#10003; Mosque added! Help us complete its details below.</div>';
	}

	if ( in_array( $status, array( 'Approved', 'Active' ), true ) ) {
		echo '<div class="mosque-actual-content">' . apply_filters( 'the_content', get_the_content( null, false, $post_id ) ) . '</div>';

	} elseif ( '' === $status || in_array( $status, array( 'New', 'Pending' ), true ) ) {
		$nonce = wp_create_nonce( 'mfa_mosque_update_' . $post_id );
		?>
		<div class="mfa-mosque-update" id="mfa-mosque-update-<?php echo (int) $post_id; ?>">
			<h1 class="mfa-mosque-update-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( '' !== $address ) : ?>
				<p class="mfa-mosque-update-address"><?php echo esc_html( $address ); ?></p>
			<?php endif; ?>
			<p class="mfa-mosque-update-intro">Full details for this mosque haven&rsquo;t been generated yet. Click below to fetch and publish up-to-date information.</p>
			<button type="button" class="mfa-btn mfa-mosque-update-btn" data-post="<?php echo (int) $post_id; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-name="<?php echo esc_attr( $name ); ?>">Click to Update</button>
			<div class="mfa-mosque-update-spinner" hidden>
				<i class="fa-solid fa-spinner fa-spin fa-2x" aria-hidden="true"></i>
				<p>Please wait&hellip; we are updating the mosque information.</p>
			</div>
			<p class="mfa-mosque-update-error" role="alert" hidden></p>
		</div>
		<script>
		(function(){
			var wrap = document.getElementById('mfa-mosque-update-<?php echo (int) $post_id; ?>');
			if (!wrap) { return; }
			var btn  = wrap.querySelector('.mfa-mosque-update-btn');
			var spin = wrap.querySelector('.mfa-mosque-update-spinner');
			var err  = wrap.querySelector('.mfa-mosque-update-error');
			btn.addEventListener('click', function(){
				btn.disabled = true; btn.hidden = true; err.hidden = true; spin.hidden = false;
				var fd = new FormData();
				fd.append('action', 'mfa_mosque_ai_update');
				fd.append('post_id', btn.dataset.post);
				fd.append('nonce', btn.dataset.nonce);
				fd.append('mosque_name', btn.dataset.name);
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
					.then(function(r){ return r.json(); })
					.then(function(res){
						if (res && res.success) { location.reload(); return; }
						spin.hidden = true; btn.hidden = false; btn.disabled = false;
						err.hidden = false; err.textContent = (res && res.data) ? res.data : 'Update failed. Please try again.';
					})
					.catch(function(){
						spin.hidden = true; btn.hidden = false; btn.disabled = false;
						err.hidden = false; err.textContent = 'Network error. Please try again.';
					});
			});
		})();
		</script>
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
 * AJAX: generate AI content for a Pending mosque, triggered by the
 * "Click to Update" button above. Verifies a per-post nonce (the old
 * auto-fire path had none), then delegates to the existing enaizi-mfa
 * engine niz_mfa_mosques_callback(), which reads $_POST['post_id'] /
 * mosque_name, calls mosques_perplexity(), writes the post content +
 * CCT status, and emits its own wp_send_json_* response.
 *
 * Kept available to logged-out visitors (nopriv) to match the original
 * design; the bot guard inside the engine (m4a_is_bot_request) still
 * short-circuits crawlers.
 */
add_action( 'wp_ajax_mfa_mosque_ai_update', 'mfa_mosque_ai_update' );
add_action( 'wp_ajax_nopriv_mfa_mosque_ai_update', 'mfa_mosque_ai_update' );
function mfa_mosque_ai_update() {
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( ! $post_id || ! wp_verify_nonce( $nonce, 'mfa_mosque_update_' . $post_id ) ) {
		wp_send_json_error( 'Invalid or expired request. Please refresh the page and try again.' );
	}
	if ( ! function_exists( 'niz_mfa_mosques_callback' ) ) {
		wp_send_json_error( 'Update engine unavailable.' );
	}

	niz_mfa_mosques_callback(); // emits wp_send_json_* itself.
}

/**
 * [mfa_mosque_ai_updater] - the always-visible "Update Content" action-row
 * button (Administrator/Editor only, gated by the caller in
 * mfa_mosque_home_tab_shortcode() above, not here - this shortcode itself
 * has no permission check, same division of responsibility
 * [mfa_modal]-wrapped buttons on this page already use). Own
 * querySelectorAll-based JS (mosque-ai-updater-v1.js) so any number of
 * instances can coexist on one page - mirrors business's
 * [niz_business_ai_updater] / website's [mfa_website_update] in shape.
 * Posts to the same mfa_mosque_ai_update AJAX action as the inline
 * "Click to Update" prompt above.
 */
add_shortcode( 'mfa_mosque_ai_updater', 'mfa_mosque_ai_updater_shortcode' );
function mfa_mosque_ai_updater_shortcode() {
	$post_id = get_the_ID();
	if ( 'masjid' !== get_post_type( $post_id ) ) {
		return '';
	}

	$nonce = wp_create_nonce( 'mfa_mosque_update_' . $post_id );
	ob_start();
	?>
	<div class="mfa-mosque-ai-update-wrapper">
		<button
			type="button"
			class="mfa-mosque-ai-update-btn mfa-mosque-action-btn"
			data-post-id="<?php echo esc_attr( $post_id ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-name="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
			data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"></path><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
			Update Content
		</button>
		<div class="mfa-mosque-ai-update-msg"></div>
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
