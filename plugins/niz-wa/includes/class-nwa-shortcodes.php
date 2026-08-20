<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end [nwa_inbox] and [nwa_knowledge_base] shortcodes - the
 * portable replacement for the wp-admin Inbox/Knowledge Base screens
 * removed from class-nwa-admin.php, so Helpline Staff (see
 * class-nwa-roles.php) can run day-to-day WhatsApp operations without
 * any wp-admin access. Settings and Actions stay wp-admin-only - API
 * keys and raw PHP callback wiring are developer-level configuration,
 * not day-to-day helpline work.
 *
 * Form submissions are handled on template_redirect
 * (handle_form_submissions() below), not inside the shortcode render
 * functions - by the time a shortcode runs (inside the_content(), deep
 * into template output), wp_head() has already sent output, so a
 * wp_safe_redirect() from inside the shortcode itself would silently
 * fail. Both shortcodes can be placed on any page on any site niz-wa is
 * installed on - nothing here assumes a specific page/URL.
 */
class NWA_Shortcodes {

	public static function init() {
		add_shortcode( 'nwa_inbox', array( __CLASS__, 'render_inbox' ) );
		add_shortcode( 'nwa_knowledge_base', array( __CLASS__, 'render_knowledge_base' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_form_submissions' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	public static function maybe_enqueue_assets() {
		global $post;
		if ( ! $post || ( ! has_shortcode( $post->post_content, 'nwa_inbox' ) && ! has_shortcode( $post->post_content, 'nwa_knowledge_base' ) ) ) {
			return;
		}
		$css = NWA_PATH . 'assets/css/nwa-shortcodes.css';
		wp_enqueue_style( 'nwa-shortcodes', NWA_URL . 'assets/css/nwa-shortcodes.css', array(), file_exists( $css ) ? filemtime( $css ) : NWA_VERSION );
	}

	/**
	 * Approved templates offered in the inbox picker.
	 *
	 * Prefers the site's own list when one is registered, so the inbox and
	 * the member admin screen cannot offer different templates - but falls
	 * back to its own default, because niz-wa has to keep working on a site
	 * that provides no list at all.
	 *
	 * These are names only. Meta must have approved a template of the same
	 * name or the send is rejected; the handler reports that plainly rather
	 * than pretending it worked.
	 */
	public static function templates() {
		$templates = function_exists( 'mfa_admin_member_templates' )
			? mfa_admin_member_templates()
			: array( 'mfa_welcome' => 'Welcome', 'mfa_followup' => 'Follow-up' );

		return apply_filters( 'nwa_message_templates', $templates );
	}

	/**
	 * One-shot notice across the POST-redirect-GET the forms below use.
	 *
	 * Kept out of the query string: an API error can be long, and a redirect
	 * carrying it would both look alarming in the address bar and risk being
	 * truncated. Keyed per user so two staff cannot see each other's result.
	 */
	private static function set_notice( $type, $message ) {
		set_transient( 'nwa_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
	}

	private static function take_notice() {
		$key    = 'nwa_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
		}

		return is_array( $notice ) ? $notice : null;
	}

	private static function access_denied_message() {
		if ( ! is_user_logged_in() ) {
			return '<p class="nwa-access-denied">Please log in to view this page.</p>';
		}
		return '<p class="nwa-access-denied">You do not have access to this page.</p>';
	}

	/* ---------------- Inbox ---------------- */

	public static function render_inbox() {
		if ( ! NWA_Roles::current_user_can_manage() ) {
			return self::access_denied_message();
		}

		$conversations = NWA_DB::get_conversations( 100 );
		$selected_id   = isset( $_GET['nwa_user_id'] ) ? (int) $_GET['nwa_user_id'] : 0;
		$active        = $selected_id ? NWA_DB::get_conversation_by_user( $selected_id ) : null;
		$messages      = $active ? NWA_DB::get_messages( $active->id, 100 ) : array();
		$within_window = $active ? NWA_DB::is_within_window( $active ) : false;
		$profile       = $active ? NWA_DB::get_profile( $selected_id ) : array( 'summary' => array() );

		ob_start();
		?>
		<div class="nwa-inbox">
			<div class="nwa-inbox-list">
				<?php foreach ( $conversations as $c ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'nwa_user_id', $c->user_id ) ); ?>"
						class="nwa-inbox-list-item<?php echo ( $active && $active->id === $c->id ) ? ' is-active' : ''; ?>">
						<strong><?php echo esc_html( $c->contact_name ?: $c->wa_number ); ?></strong>
						<?php if ( $c->unread_count > 0 ) : ?><span class="nwa-inbox-unread-badge"><?php echo (int) $c->unread_count; ?></span><?php endif; ?>
						<br><small><?php echo esc_html( $c->wa_number ); ?></small>
					</a>
				<?php endforeach; ?>
				<?php if ( ! $conversations ) : ?>
					<p class="nwa-inbox-empty">No conversations yet.</p>
				<?php endif; ?>
			</div>

			<div class="nwa-inbox-thread">
				<?php if ( ! $active ) : ?>
					<p>Select a conversation.</p>
				<?php else : ?>
					<h2><?php echo esc_html( $active->contact_name ?: $active->wa_number ); ?></h2>

					<?php $notice = self::take_notice(); ?>
					<?php if ( $notice ) : ?>
						<div class="nwa-notice nwa-notice-<?php echo esc_attr( 'ok' === $notice['type'] ? 'success' : 'error' ); ?>">
							<?php echo esc_html( $notice['message'] ); ?>
						</div>
					<?php endif; ?>

					<div class="nwa-inbox-messages">
						<?php foreach ( $messages as $m ) : ?>
							<div class="nwa-message-bubble is-<?php echo esc_attr( $m->direction ); ?>">
								<?php
								// Downloaded media renders inline; without an attachment
								// (download failed, or the message predates it) the row
								// still shows its caption, so nothing looks blank.
								$attachment_id = isset( $m->media_attachment_id ) ? (int) $m->media_attachment_id : 0;
								if ( $attachment_id ) :
									$media_url  = wp_get_attachment_url( $attachment_id );
									$media_mime = get_post_mime_type( $attachment_id );
									?>
									<?php if ( $media_url && 0 === strpos( (string) $media_mime, 'image/' ) ) : ?>
										<a href="<?php echo esc_url( $media_url ); ?>" target="_blank" rel="noopener">
											<?php echo wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => 'nwa-message-media' ) ); ?>
										</a>
									<?php elseif ( $media_url && 0 === strpos( (string) $media_mime, 'video/' ) ) : ?>
										<video class="nwa-message-media" controls preload="none" src="<?php echo esc_url( $media_url ); ?>"></video>
									<?php elseif ( $media_url && 0 === strpos( (string) $media_mime, 'audio/' ) ) : ?>
										<audio class="nwa-message-media" controls preload="none" src="<?php echo esc_url( $media_url ); ?>"></audio>
									<?php elseif ( $media_url ) : ?>
										<a class="nwa-message-file" href="<?php echo esc_url( $media_url ); ?>" target="_blank" rel="noopener">
											&#128206; <?php echo esc_html( basename( get_attached_file( $attachment_id ) ) ); ?>
										</a>
									<?php endif; ?>
								<?php endif; ?>
								<?php echo esc_html( $m->content ); ?>
								<?php // Stored in GMT; shown in the site timezone so the thread reads in local time. ?>
								<br><small><?php echo esc_html( get_date_from_gmt( $m->created_at, 'Y-m-d H:i:s' ) ); ?> &middot; <?php echo esc_html( $m->msg_type ); ?></small>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $within_window ) : ?>
						<form method="post" class="nwa-reply-form">
							<?php wp_nonce_field( 'nwa_reply', 'nwa_reply_nonce' ); ?>
							<input type="hidden" name="nwa_active_user_id" value="<?php echo esc_attr( $selected_id ); ?>">
							<textarea name="nwa_reply_text" rows="2" placeholder="Type a reply..."></textarea>
							<p><button type="submit" class="nwa-btn nwa-btn-primary">Send</button></p>
						</form>
					<?php else : ?>
						<div class="nwa-notice nwa-notice-warning">
							24-hour window closed<?php
							// window_expires_at is GMT (see CLAUDE.md) - converted for
							// display only, never compared in local time.
							if ( ! empty( $active->window_expires_at ) ) {
								echo ' on ' . esc_html( date_i18n( 'j M, g:i a', strtotime( get_date_from_gmt( $active->window_expires_at ) ) ) );
							}
							?> &mdash; free-text replies aren't allowed. Send an approved template instead.
						</div>

						<form method="post" class="nwa-template-form">
							<?php wp_nonce_field( 'nwa_template', 'nwa_template_nonce' ); ?>
							<input type="hidden" name="nwa_active_user_id" value="<?php echo esc_attr( $selected_id ); ?>">
							<label for="nwa-template-<?php echo esc_attr( $selected_id ); ?>">Template</label>
							<select id="nwa-template-<?php echo esc_attr( $selected_id ); ?>" name="nwa_template_name" required>
								<?php foreach ( self::templates() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p><button type="submit" class="nwa-btn nwa-btn-primary" onclick="return confirm('Send this template to <?php echo esc_js( $active->contact_name ?: $active->wa_number ); ?>?');">Send Template</button></p>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( $active ) : ?>
				<div class="nwa-inbox-profile">
					<?php
					// Links into mfa-core's /admin/member/info/?id={user_id} - same
					// hardcoded-to-this-site caveat as site-integration.php's action
					// replies (see the "Not yet portable" note in CLAUDE.md); $selected_id
					// is always the WP user_id niz-wa already resolved this contact to.
					?>
					<a href="<?php echo esc_url( add_query_arg( 'id', $selected_id, home_url( '/admin/member/info/' ) ) ); ?>" target="_blank" rel="noopener" class="nwa-btn nwa-btn-small nwa-inbox-profile-link">View Member Info</a>
					<h3>Profile</h3>
					<?php if ( ! empty( $profile['summary'] ) ) : ?>
						<div class="nwa-profile-summary">
							<p>
							<?php
							$parts = array();
							foreach ( $profile['summary'] as $k => $v ) {
								$parts[] = esc_html( ucfirst( $k ) . ': ' . ( is_array( $v ) ? implode( ', ', $v ) : $v ) );
							}
							echo implode( ' &middot; ', $parts );
							?>
							</p>
							<form method="post">
								<input type="hidden" name="nwa_active_user_id" value="<?php echo esc_attr( $selected_id ); ?>">
								<button type="submit" name="nwa_clear_profile" value="1" class="nwa-btn nwa-btn-small" onclick="return confirm('Clear this user\'s stored profile?');">Clear profile</button>
							</form>
						</div>
					<?php else : ?>
						<p class="nwa-inbox-empty">No profile info stored for this contact yet.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------- Knowledge base ---------------- */

	public static function render_knowledge_base() {
		if ( ! NWA_Roles::current_user_can_manage() ) {
			return self::access_denied_message();
		}

		$entries = NWA_DB::get_all_knowledge();
		ob_start();
		?>
		<div class="nwa-kb">
			<p class="nwa-kb-intro">Entries here ground the Q&amp;A fallback &mdash; the AI is instructed to answer only from what's here for company/product questions, and say so when it doesn't know.</p>

			<h3>Add entry</h3>
			<form method="post" class="nwa-kb-form">
				<?php wp_nonce_field( 'nwa_save_kb', 'nwa_kb_nonce' ); ?>
				<p>
					<label>Title<br>
					<input type="text" name="title" required placeholder="Founding Member pricing tiers"></label>
				</p>
				<p>
					<label>Content<br>
					<textarea name="content" rows="6" required></textarea></label>
				</p>
				<button type="submit" class="nwa-btn nwa-btn-primary">Save Entry</button>
			</form>

			<h3>Entries</h3>
			<table class="nwa-kb-table">
				<thead><tr><th>Title</th><th>Updated</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $entries as $e ) : ?>
					<tr>
						<td><?php echo esc_html( $e->title ); ?></td>
						<td><?php echo esc_html( $e->updated_at ); ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( array( 'nwa_kb_delete' => $e->id, 'nwa_kb_nonce' => wp_create_nonce( 'nwa_delete_kb' ) ) ) ); ?>" onclick="return confirm('Delete this entry?');">Delete</a></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $entries ) : ?>
					<tr><td colspan="3">No entries yet.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------- Form handling (runs before any output) ---------------- */

	public static function handle_form_submissions() {
		if ( ! NWA_Roles::current_user_can_manage() ) {
			return;
		}

		if ( isset( $_POST['nwa_reply_nonce'] ) && wp_verify_nonce( $_POST['nwa_reply_nonce'], 'nwa_reply' ) ) {
			$user_id = isset( $_POST['nwa_active_user_id'] ) ? (int) $_POST['nwa_active_user_id'] : 0;
			$active  = $user_id ? NWA_DB::get_conversation_by_user( $user_id ) : null;
			$text    = sanitize_textarea_field( wp_unslash( $_POST['nwa_reply_text'] ?? '' ) );
			if ( ! $active || '' === $text ) {
				self::set_notice( 'err', 'Nothing was sent - no message text.' );
			} else {
				// The window can lapse between loading the page and pressing
				// Send, and Meta rejects a free-text message the moment it
				// does. Re-checked here so the refusal is explained rather
				// than looking like the reply silently vanished.
				if ( ! NWA_DB::is_within_window( $active ) ) {
					self::set_notice( 'err', 'The 24-hour window closed while this page was open. Send an approved template instead.' );
				} else {
					$result = nwa_send_message( $active->user_id, $active->wa_number, $text );
					if ( empty( $result['success'] ) ) {
						self::set_notice( 'err', 'Failed to send: ' . ( $result['error'] ?? 'unknown error' ) );
					} else {
						self::set_notice( 'ok', 'Reply sent.' );
					}
				}
			}
			wp_safe_redirect( add_query_arg( 'nwa_user_id', $user_id ) );
			exit;
		}

		if ( isset( $_POST['nwa_template_nonce'] ) && wp_verify_nonce( $_POST['nwa_template_nonce'], 'nwa_template' ) ) {
			$user_id  = isset( $_POST['nwa_active_user_id'] ) ? (int) $_POST['nwa_active_user_id'] : 0;
			$active   = $user_id ? NWA_DB::get_conversation_by_user( $user_id ) : null;
			$template = sanitize_text_field( wp_unslash( $_POST['nwa_template_name'] ?? '' ) );
			$allowed  = self::templates();

			if ( ! $active ) {
				self::set_notice( 'err', 'That conversation no longer exists.' );
			} elseif ( ! isset( $allowed[ $template ] ) ) {
				// Only names from the registered list, so a tampered form
				// cannot ask Meta to send an arbitrary template.
				self::set_notice( 'err', 'That template is not on the approved list.' );
			} else {
				// Note the argument order: nwa_send_template() takes the phone
				// number first, unlike nwa_send_message() which takes the user id.
				$result = nwa_send_template( $active->wa_number, $template, '', array(), $active->user_id );

				if ( empty( $result['success'] ) ) {
					self::set_notice( 'err', 'Template not sent: ' . ( $result['error'] ?? 'unknown error' ) . ' (a template must be approved in Meta under this exact name).' );
				} else {
					// A template does NOT reopen the 24-hour window - only an
					// inbound message does - so the free-text box stays closed.
					self::set_notice( 'ok', 'Template "' . $allowed[ $template ] . '" sent. The window reopens only when they reply.' );
				}
			}

			wp_safe_redirect( add_query_arg( 'nwa_user_id', $user_id ) );
			exit;
		}

		if ( isset( $_POST['nwa_clear_profile'] ) ) {
			$user_id = isset( $_POST['nwa_active_user_id'] ) ? (int) $_POST['nwa_active_user_id'] : 0;
			if ( $user_id ) {
				NWA_DB::clear_profile( $user_id );
			}
			wp_safe_redirect( add_query_arg( 'nwa_user_id', $user_id ) );
			exit;
		}

		if ( isset( $_POST['nwa_kb_nonce'] ) && wp_verify_nonce( $_POST['nwa_kb_nonce'], 'nwa_save_kb' ) ) {
			NWA_DB::save_knowledge( array(
				'title'   => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
				'content' => sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) ),
			) );
			wp_safe_redirect( remove_query_arg( array( 'nwa_kb_delete', 'nwa_kb_nonce' ) ) );
			exit;
		}

		if ( isset( $_GET['nwa_kb_delete'], $_GET['nwa_kb_nonce'] ) && wp_verify_nonce( $_GET['nwa_kb_nonce'], 'nwa_delete_kb' ) ) {
			NWA_DB::delete_knowledge( (int) $_GET['nwa_kb_delete'] );
			wp_safe_redirect( remove_query_arg( array( 'nwa_kb_delete', 'nwa_kb_nonce' ) ) );
			exit;
		}
	}
}
