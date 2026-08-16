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

					<div class="nwa-inbox-messages">
						<?php foreach ( $messages as $m ) : ?>
							<div class="nwa-message-bubble is-<?php echo esc_attr( $m->direction ); ?>">
								<?php echo esc_html( $m->content ); ?>
								<br><small><?php echo esc_html( $m->created_at ); ?> &middot; <?php echo esc_html( $m->msg_type ); ?></small>
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
						<div class="nwa-notice nwa-notice-warning">24-hour window closed &mdash; free-text replies aren't allowed. Send a template instead.</div>
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
			if ( $active && $text ) {
				nwa_send_message( $active->user_id, $active->wa_number, $text );
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
