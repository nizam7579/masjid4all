<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	public static function add_menu() {
		add_menu_page( 'Niz WA', 'Niz WA', 'manage_options', 'nemkad-wa', array( __CLASS__, 'render_settings' ), 'dashicons-whatsapp', 58 );
		add_submenu_page( 'nemkad-wa', 'Settings', 'Settings', 'manage_options', 'nemkad-wa', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'nemkad-wa', 'Actions', 'Actions', 'manage_options', 'nemkad-wa-actions', array( __CLASS__, 'render_actions' ) );
		add_submenu_page( 'nemkad-wa', 'Knowledge Base', 'Knowledge Base', 'manage_options', 'nemkad-wa-kb', array( __CLASS__, 'render_kb' ) );
		add_submenu_page( 'nemkad-wa', 'Inbox', 'Inbox', 'manage_options', 'nemkad-wa-inbox', array( __CLASS__, 'render_inbox' ) );
	}

	/* ---------------- Settings ---------------- */

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['nwa_ai_settings_nonce'] ) && wp_verify_nonce( $_POST['nwa_ai_settings_nonce'], 'nwa_save_ai_settings' ) ) {
			$settings = get_option( NWA_Config::OPTION_KEY, array() );
			$settings['ai_provider'] = sanitize_key( wp_unslash( $_POST['ai_provider'] ?? '' ) );
			$settings['ai_model']    = sanitize_text_field( wp_unslash( $_POST['ai_model'] ?? '' ) );

			$new_key = trim( wp_unslash( $_POST['ai_api_key'] ?? '' ) );
			if ( '' !== $new_key ) {
				$settings['ai_api_key'] = $new_key;
			}

			update_option( NWA_Config::OPTION_KEY, $settings );
			echo '<div class="notice notice-success"><p>AI settings saved.</p></div>';
		}

		$webhook_url = get_rest_url( null, 'nemkad-wa/v1/webhook' );
		$wa_fields = array(
			'phone_number_id' => 'Phone Number ID',
			'access_token'    => 'Access Token',
			'app_secret'      => 'App Secret',
			'verify_token'    => 'Webhook Verify Token',
			'api_version'     => 'Graph API Version',
		);
		?>
		<div class="wrap">
			<h1>Niz WA — Settings</h1>
			<div class="notice notice-info"><p>
				<strong>Webhook Callback URL:</strong> <code><?php echo esc_html( $webhook_url ); ?></code>
			</p></div>

			<h2>WhatsApp Cloud API</h2>
			<div class="notice notice-warning"><p>
				Credentials below are set via constants in a secrets file required from <code>wp-config.php</code> —
				see the comment block at the top of <code>class-nwa-config.php</code>. This section is status-only.
			</p></div>
			<table class="widefat" style="max-width:700px;">
				<thead><tr><th>Setting</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( $wa_fields as $key => $label ) :
					$locked = NWA_Config::is_locked( $key );
					$value  = NWA_Config::get( $key );
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td>
							<?php if ( $locked ) : ?>
								<span style="color:#00a32a;">✓ Set via constant</span>
							<?php elseif ( $value ) : ?>
								<span style="color:#dba617;">Set via DB option (consider moving to a constant)</span>
							<?php else : ?>
								<span style="color:#d63638;">Not set</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px;">AI Assistant</h2>
			<?php if ( NWA_Config::is_locked( 'ai_provider' ) || NWA_Config::is_locked( 'ai_model' ) || NWA_Config::is_locked( 'ai_api_key' ) ) : ?>
				<div class="notice notice-error"><p>
					One or more AI settings (Provider / Model / API Key) are still set via a constant
					(<code>NWA_AI_PROVIDER</code> / <code>NWA_AI_MODEL</code> / <code>NWA_AI_API_KEY</code>) in <code>wp-config.php</code>,
					which always overrides the form below. Remove those constants to control AI settings from here instead.
				</p></div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'nwa_save_ai_settings', 'nwa_ai_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label>Provider</label></th>
						<td>
							<select name="ai_provider">
								<?php foreach ( array( 'anthropic' => 'Anthropic', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter' ) as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( NWA_Config::get( 'ai_provider' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label>Model</label></th>
						<td>
							<input type="text" name="ai_model" value="<?php echo esc_attr( NWA_Config::get( 'ai_model' ) ); ?>" class="regular-text" placeholder="e.g. anthropic/claude-3.5-sonnet">
							<p class="description">This is the field to change when testing different models. For OpenRouter, use its "provider/model" format — e.g. <code>anthropic/claude-3.5-sonnet</code>, <code>openai/gpt-4o</code>, <code>google/gemini-2.0-flash</code>, <code>deepseek/deepseek-chat</code>.</p>
						</td>
					</tr>
					<tr>
						<th><label>API Key</label></th>
						<td>
							<input type="password" name="ai_api_key" value="" class="regular-text" placeholder="<?php echo NWA_Config::get( 'ai_api_key' ) ? '(current key set — leave blank to keep it)' : ''; ?>">
							<p class="description">Leave blank to keep the current key. Used for whichever provider is selected above.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save AI Settings' ); ?>
			</form>
		</div>
		<?php
	}

	/* ---------------- Action registry ---------------- */

	public static function render_actions() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['nwa_action_nonce'] ) && wp_verify_nonce( $_POST['nwa_action_nonce'], 'nwa_save_action' ) ) {
			$callback = sanitize_text_field( wp_unslash( $_POST['callback_function'] ) );
			if ( ! function_exists( $callback ) ) {
				echo '<div class="notice notice-error"><p>Function <code>' . esc_html( $callback ) . '</code> does not exist — not saved. Define it first, then save this action.</p></div>';
			} else {
				NWA_DB::save_action( array(
					'intent_key'            => wp_unslash( $_POST['intent_key'] ),
					'keywords'              => wp_unslash( $_POST['keywords'] ?? '' ),
					'description'           => wp_unslash( $_POST['description'] ?? '' ),
					'requires_confirmation' => ! empty( $_POST['requires_confirmation'] ),
					'confirm_message'       => wp_unslash( $_POST['confirm_message'] ?? '' ),
					'callback_function'     => $callback,
					'enabled'               => ! empty( $_POST['enabled'] ),
				) );
				echo '<div class="notice notice-success"><p>Action saved.</p></div>';
			}
		}

		if ( isset( $_GET['delete'] ) ) {
			NWA_DB::delete_action( (int) $_GET['delete'] );
			echo '<div class="notice notice-success"><p>Deleted.</p></div>';
		}

		$actions = NWA_DB::get_all_actions();
		?>
		<div class="wrap">
			<h1>Niz WA — Actions</h1>

			<h2>Add new action</h2>
			<form method="post">
				<?php wp_nonce_field( 'nwa_save_action', 'nwa_action_nonce' ); ?>
				<table class="form-table">
					<tr><th><label>Intent key</label></th><td><input type="text" name="intent_key" placeholder="reset_password" required style="width:300px"></td></tr>
					<tr><th><label>Keywords</label></th><td><input type="text" name="keywords" placeholder="status, check status" style="width:400px"><p class="description">Comma-separated. Exact match (case-insensitive) triggers this action directly — useful for wa.me deep links.</p></td></tr>
					<tr><th><label>Description</label></th><td><textarea name="description" rows="2" style="width:400px" placeholder="User wants to reset their account password"></textarea><p class="description">This is what the AI reads to classify free-text messages.</p></td></tr>
					<tr><th><label>Requires confirmation?</label></th><td><input type="checkbox" name="requires_confirmation" checked></td></tr>
					<tr><th><label>Confirmation message</label></th><td><textarea name="confirm_message" rows="2" style="width:400px" placeholder="Do you want to reset your password? (Yes/No)"></textarea></td></tr>
					<tr><th><label>Callback function</label></th><td><input type="text" name="callback_function" placeholder="reset_password" required style="width:300px"><p class="description">Global PHP function, signature: <code>function_name( $user_id, $context )</code>, must return a string reply.</p></td></tr>
					<tr><th><label>Enabled?</label></th><td><input type="checkbox" name="enabled" checked></td></tr>
				</table>
				<?php submit_button( 'Save Action' ); ?>
			</form>

			<h2>Registered actions</h2>
			<table class="widefat">
				<thead><tr><th>Intent</th><th>Keywords</th><th>Confirm?</th><th>Callback</th><th>Enabled</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $actions as $a ) : ?>
					<tr>
						<td><?php echo esc_html( $a->intent_key ); ?></td>
						<td><?php echo esc_html( $a->keywords ); ?></td>
						<td><?php echo $a->requires_confirmation ? 'Yes' : 'No'; ?></td>
						<td><code><?php echo esc_html( $a->callback_function ); ?></code><?php if ( ! function_exists( $a->callback_function ) ) : ?> <span style="color:#d63638;">(missing!)</span><?php endif; ?></td>
						<td><?php echo $a->enabled ? '✓' : '—'; ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'delete', $a->id ) ); ?>" onclick="return confirm('Delete this action?');">Delete</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ---------------- Knowledge base ---------------- */

	public static function render_kb() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['nwa_kb_nonce'] ) && wp_verify_nonce( $_POST['nwa_kb_nonce'], 'nwa_save_kb' ) ) {
			NWA_DB::save_knowledge( array(
				'title'   => wp_unslash( $_POST['title'] ),
				'content' => wp_unslash( $_POST['content'] ),
			) );
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}

		if ( isset( $_GET['delete'] ) ) {
			NWA_DB::delete_knowledge( (int) $_GET['delete'] );
			echo '<div class="notice notice-success"><p>Deleted.</p></div>';
		}

		$entries = NWA_DB::get_all_knowledge();
		?>
		<div class="wrap">
			<h1>Niz WA — Knowledge Base</h1>
			<p>Entries here ground the Q&A fallback — the AI is instructed to answer only from what's here for company/product questions, and say so when it doesn't know.</p>

			<h2>Add entry</h2>
			<form method="post">
				<?php wp_nonce_field( 'nwa_save_kb', 'nwa_kb_nonce' ); ?>
				<table class="form-table">
					<tr><th><label>Title</label></th><td><input type="text" name="title" required style="width:400px" placeholder="Founding Member pricing tiers"></td></tr>
					<tr><th><label>Content</label></th><td><textarea name="content" rows="6" style="width:600px" required></textarea></td></tr>
				</table>
				<?php submit_button( 'Save Entry' ); ?>
			</form>

			<h2>Entries</h2>
			<table class="widefat">
				<thead><tr><th>Title</th><th>Updated</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $entries as $e ) : ?>
					<tr>
						<td><?php echo esc_html( $e->title ); ?></td>
						<td><?php echo esc_html( $e->updated_at ); ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'delete', $e->id ) ); ?>" onclick="return confirm('Delete this entry?');">Delete</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ---------------- Inbox ---------------- */

	public static function render_inbox() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$conversations = NWA_DB::get_conversations( 100 );
		$selected_id   = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$active        = $selected_id ? NWA_DB::get_conversation_by_user( $selected_id ) : null;
		$messages      = $active ? NWA_DB::get_messages( $active->id, 100 ) : array();
		$within_window = $active ? NWA_DB::is_within_window( $active ) : false;
		$profile       = $active ? NWA_DB::get_profile( $selected_id ) : array( 'summary' => array() );

		if ( isset( $_POST['nwa_reply_nonce'] ) && wp_verify_nonce( $_POST['nwa_reply_nonce'], 'nwa_reply' ) && $active ) {
			$text = sanitize_textarea_field( wp_unslash( $_POST['nwa_reply_text'] ?? '' ) );
			if ( $text ) {
				nwa_send_message( $active->user_id, $active->wa_number, $text );
				wp_safe_redirect( add_query_arg( 'user_id', $active->user_id ) );
				exit;
			}
		}

		if ( isset( $_POST['nwa_clear_profile'] ) && $active ) {
			NWA_DB::clear_profile( $active->user_id );
			wp_safe_redirect( add_query_arg( 'user_id', $active->user_id ) );
			exit;
		}
		?>
		<div class="wrap">
			<h1>Niz WA — Inbox</h1>
			<div style="display:flex; gap:20px; margin-top:20px;">

				<div style="width:280px; border:1px solid #ccd0d4; background:#fff; max-height:650px; overflow-y:auto;">
					<?php foreach ( $conversations as $c ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'user_id', $c->user_id ) ); ?>"
							style="display:block; padding:10px; text-decoration:none; border-bottom:1px solid #eee; <?php echo ( $active && $active->id === $c->id ) ? 'background:#f0f6fc;' : ''; ?>">
							<strong><?php echo esc_html( $c->contact_name ?: $c->wa_number ); ?></strong>
							<?php if ( $c->unread_count > 0 ) : ?><span style="background:#d63638;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;float:right;"><?php echo (int) $c->unread_count; ?></span><?php endif; ?>
							<br><small><?php echo esc_html( $c->wa_number ); ?></small>
						</a>
					<?php endforeach; ?>
				</div>

				<div style="flex:1; border:1px solid #ccd0d4; background:#fff; padding:15px;">
					<?php if ( ! $active ) : ?>
						<p>Select a conversation.</p>
					<?php else : ?>
						<h2><?php echo esc_html( $active->contact_name ?: $active->wa_number ); ?></h2>

						<?php if ( ! empty( $profile['summary'] ) ) : ?>
							<div class="notice notice-info inline" style="margin:0 0 10px;">
								<p><strong>Profile:</strong>
								<?php
								$parts = array();
								foreach ( $profile['summary'] as $k => $v ) {
									$parts[] = esc_html( ucfirst( $k ) . ': ' . ( is_array( $v ) ? implode( ', ', $v ) : $v ) );
								}
								echo implode( ' &middot; ', $parts );
								?>
								</p>
								<form method="post" style="margin-top:5px;">
									<button type="submit" name="nwa_clear_profile" value="1" class="button button-small" onclick="return confirm('Clear this user\'s stored profile?');">Clear profile</button>
								</form>
							</div>
						<?php endif; ?>

						<div style="max-height:380px; overflow-y:auto; display:flex; flex-direction:column-reverse; gap:8px; margin-bottom:15px;">
							<?php foreach ( $messages as $m ) : ?>
								<div style="align-self:<?php echo 'inbound' === $m->direction ? 'flex-start' : 'flex-end'; ?>; background:<?php echo 'inbound' === $m->direction ? '#f0f0f1' : '#d1e7dd'; ?>; padding:8px 12px; border-radius:8px; max-width:70%;">
									<?php echo esc_html( $m->content ); ?>
									<br><small style="color:#666;"><?php echo esc_html( $m->created_at ); ?> &middot; <?php echo esc_html( $m->msg_type ); ?></small>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $within_window ) : ?>
							<form method="post">
								<?php wp_nonce_field( 'nwa_reply', 'nwa_reply_nonce' ); ?>
								<textarea name="nwa_reply_text" rows="2" style="width:100%;" placeholder="Type a reply..."></textarea>
								<p><button type="submit" class="button button-primary">Send</button></p>
							</form>
						<?php else : ?>
							<div class="notice notice-warning inline"><p>24-hour window closed — free-text replies aren't allowed. Send a template instead via <code>nwa_send_template()</code>.</p></div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
