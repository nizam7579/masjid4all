<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_inquiry_info] - /admin/inquiry/info/?id={_ID}, linked from
 * the "View" button in [mfa_admin_inquiry_list] (admin-inquiry-list.php).
 * `id` is the CCT's own `_ID`, not a user_id - an inquiry isn't
 * necessarily tied to a logged-in member (cct_author_id is 0 for
 * logged-out submitters). Reads wp_jet_cct_contact_us directly via
 * $wpdb, never the JetEngine PHP API, per the project's standing rule.
 *
 * Deliberately basic, same precedent as admin-member-info.php's first
 * version: read-only, no status-editing UI yet - just the full message
 * (the whole point of this page, since the list table only shows a
 * preview-free summary) plus a plain mailto: link to reply.
 *
 * WhatsApp reply (added 2026-08-15): whenever an inquiry came in via
 * niz-wa (both the native Flow and the older text-conversation path set a
 * real WP user id as cct_author_id, never 0 - see niz-wa-integration.php),
 * this also offers an in-page WhatsApp reply box, but ONLY while that
 * user's niz-wa conversation is still inside Meta's 24h free-form-message
 * window (NWA_DB::is_within_window()) - outside it, Meta would reject a
 * plain text send outright, so the page falls back to email-only. The
 * eligibility check runs again, server-side, at send time in the AJAX
 * handler below (not just at page-render time), since the window can
 * lapse between opening the page and clicking Send.
 */

/**
 * Returns array( 'user_id', 'wa_number' ) if this inquiry's author has a
 * live (within-window) niz-wa conversation, or null otherwise (no linked
 * WhatsApp user, niz-wa inactive, or the 24h window has lapsed).
 */
function mfa_admin_inquiry_whatsapp_eligibility( $cct_author_id ) {
	$user_id = (int) $cct_author_id;
	if ( ! $user_id || ! class_exists( 'NWA_DB' ) ) {
		return null;
	}

	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
		return null;
	}

	return array( 'user_id' => $user_id, 'wa_number' => $conversation->wa_number );
}

add_shortcode( 'mfa_admin_inquiry_info', 'mfa_admin_inquiry_info_shortcode' );
function mfa_admin_inquiry_info_shortcode() {
	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_contact_us';

	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	ob_start();
	?>
	<div class="mfa-admin-inquiry-info">
		<?php
		if ( ! $id ) {
			echo '<p class="mfa-body-muted">No inquiry specified.</p>';
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$cct_table} WHERE _ID = %d", $id ), ARRAY_A );

			if ( ! $row ) {
				echo '<p class="mfa-body-muted">Inquiry not found.</p>';
			} else {
				$reply_url   = ! empty( $row['email'] ) ? 'mailto:' . rawurlencode( $row['email'] ) . '?subject=' . rawurlencode( 'Re: ' . $row['subject'] ) : '';
					$wa_eligible = mfa_admin_inquiry_whatsapp_eligibility( $row['cct_author_id'] ?? 0 );
				?>
				<h1 class="mfa-h2"><?php echo esc_html( $row['subject'] ? $row['subject'] : '—' ); ?></h1>

				<div class="mfa-admin-inquiry-info-grid">
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Name</span>
						<span class="mfa-body"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Email</span>
						<span class="mfa-body"><?php echo esc_html( $row['email'] ? $row['email'] : '—' ); ?></span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Whatsapp / Phone</span>
						<span class="mfa-body"><?php echo esc_html( $row['phone'] ? $row['phone'] : '—' ); ?></span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Status</span>
						<span class="mfa-body">
							<?php if ( ! empty( $row['cct_status'] ) ) : ?>
								<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $row['cct_status'] ) ) ); ?>"><?php echo esc_html( $row['cct_status'] ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Received</span>
						<span class="mfa-body"><?php echo esc_html( $row['cct_created'] ? date_i18n( 'j M Y, g:i a', strtotime( $row['cct_created'] ) ) : '—' ); ?></span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Message</span>
						<span class="mfa-body mfa-admin-inquiry-info-message"><?php echo nl2br( esc_html( $row['message'] ? $row['message'] : '—' ) ); ?></span>
					</div>
				</div>

				<?php if ( $reply_url ) : ?>
					<a href="<?php echo esc_url( $reply_url ); ?>" class="mfa-btn mfa-btn-primary mfa-admin-inquiry-info-reply-btn">Reply via Email</a>
				<?php endif; ?>

				<?php if ( $wa_eligible ) : ?>
					<form class="mfa-admin-inquiry-whatsapp-form" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_admin_inquiry_reply_' . $id ) ); ?>">
						<div class="mfa-form-group">
							<label for="mfa-admin-inquiry-wa-message">Reply via WhatsApp</label>
							<textarea id="mfa-admin-inquiry-wa-message" name="message" rows="4" placeholder="Type your reply…" required></textarea>
						</div>
						<button type="submit" class="mfa-btn mfa-btn-primary">Send via WhatsApp</button>
						<p class="mfa-modal-message" data-mfa-form-message></p>
					</form>
				<?php else : ?>
					<p class="mfa-body-muted mfa-admin-inquiry-whatsapp-note">Outside the 24-hour WhatsApp messaging window (or this inquiry has no linked WhatsApp conversation) — please reply via email instead.</p>
				<?php endif; ?>
				<?php
			}
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for the WhatsApp reply form above. Re-checks eligibility
 * from fresh DB state (not anything posted by the client) since the 24h
 * window can lapse between page load and clicking Send.
 */
add_action( 'wp_ajax_mfa_admin_inquiry_reply_whatsapp', 'mfa_ajax_admin_inquiry_reply_whatsapp' );
function mfa_ajax_admin_inquiry_reply_whatsapp() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to do this.' ) );
	}

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id ) {
		wp_send_json_error( array( 'message' => 'Invalid inquiry.' ) );
	}

	check_ajax_referer( 'mfa_admin_inquiry_reply_' . $id, 'nonce' );

	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	if ( '' === $message ) {
		wp_send_json_error( array( 'message' => 'Please type a reply first.' ) );
	}

	global $wpdb;
	$cct_author_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT cct_author_id FROM {$wpdb->prefix}jet_cct_contact_us WHERE _ID = %d", $id
	) );
	if ( null === $cct_author_id ) {
		wp_send_json_error( array( 'message' => 'Inquiry not found.' ) );
	}

	$eligibility = mfa_admin_inquiry_whatsapp_eligibility( $cct_author_id );
	if ( ! $eligibility ) {
		wp_send_json_error( array( 'message' => 'This conversation is now outside the 24-hour WhatsApp window — please reply via email instead.' ) );
	}

	if ( ! function_exists( 'nwa_send_message' ) ) {
		wp_send_json_error( array( 'message' => 'WhatsApp sending is not available right now.' ) );
	}

	$result = nwa_send_message( $eligibility['user_id'], $eligibility['wa_number'], $message );

	if ( empty( $result['success'] ) ) {
		wp_send_json_error( array( 'message' => 'Failed to send: ' . ( $result['error'] ?? 'unknown error' ) ) );
	}

	wp_send_json_success( array( 'message' => 'Sent via WhatsApp.' ) );
}
