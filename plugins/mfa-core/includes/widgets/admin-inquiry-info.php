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
 * preview-free summary) plus a reply box.
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
 *
 * Unified reply box (2026-08-17): one "Reply Message" textarea with two
 * submit buttons underneath, "Reply via Email" and "Send via WhatsApp"
 * (the latter only rendered when eligible, same rule as before) - replaces
 * the earlier split UI (a plain `mailto:` link plus a separate WhatsApp-
 * only form). Email now sends server-side via wp_mail()
 * (mfa_ajax_admin_inquiry_reply_email() below), not a `mailto:` link that
 * hands off to the staff member's own local mail client - matches the
 * WhatsApp path's shape (AJAX, marks the inquiry Replied on success) so
 * both channels behave the same way from the admin's point of view.
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

/**
 * Whether each channel can actually be used for this inquiry, and why not.
 *
 * Previously the page only decided whether to *render* a button, which
 * left staff guessing why a reply option had vanished. Worse, the email
 * test was is_email() alone, so a <phone>@mfa.com or <phone>@noemail.com
 * placeholder passed it and offered a "Reply via Email" button that would
 * hard-bounce. Same helper and the same wording as the member detail page
 * (admin-member-actions.php) so the two screens don't disagree.
 */
function mfa_admin_inquiry_contact_state( $row ) {
	$state = array(
		'can_email'    => false,
		'email_reason' => '',
		'can_whatsapp' => false,
		'wa_reason'    => '',
		'wa_eligible'  => null,
	);

	$email = isset( $row['email'] ) ? trim( (string) $row['email'] ) : '';

	// is_email() first: mfa_is_placeholder_email() also returns true for a
	// malformed address, so testing it earlier would swallow this case and
	// tell staff "no real email address" about a plain typo.
	if ( '' === $email ) {
		$state['email_reason'] = 'No email address on file.';
	} elseif ( ! is_email( $email ) ) {
		$state['email_reason'] = 'Not a valid email address.';
	} elseif ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $email ) ) {
		$state['email_reason'] = 'No real email address on file.';
	} else {
		$state['can_email'] = true;
	}

	$author_id = isset( $row['cct_author_id'] ) ? (int) $row['cct_author_id'] : 0;

	if ( ! $author_id ) {
		$state['wa_reason'] = 'Not linked to a WhatsApp conversation.';
		return $state;
	}

	$eligible = mfa_admin_inquiry_whatsapp_eligibility( $author_id );
	if ( $eligible ) {
		$state['can_whatsapp'] = true;
		$state['wa_eligible']  = $eligible;
		return $state;
	}

	if ( ! class_exists( 'NWA_DB' ) ) {
		$state['wa_reason'] = 'WhatsApp plugin unavailable.';
		return $state;
	}

	$conversation = NWA_DB::get_conversation_by_user( $author_id );
	if ( ! $conversation ) {
		$state['wa_reason'] = 'They have never messaged Sofia, so there is no open window.';
		return $state;
	}

	$state['wa_reason'] = 'The 24-hour window closed'
		. ( $conversation->window_expires_at
			? ' on ' . date_i18n( 'j M, g:i a', strtotime( get_date_from_gmt( $conversation->window_expires_at ) ) )
			: '' )
		. '.';

	return $state;
}

/** Small ok/blocked badge used beside the Email and WhatsApp fields. */
function mfa_admin_inquiry_check_badge( $ok, $ok_label, $reason ) {
	$class = $ok ? 'mfa-admin-check-badge is-ok' : 'mfa-admin-check-badge is-no';
	$label = $ok ? $ok_label : 'Unavailable';

	$html = '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';

	if ( ! $ok && $reason ) {
		$html .= '<span class="mfa-admin-check-reason">' . esc_html( $reason ) . '</span>';
	}

	return $html;
}

add_shortcode( 'mfa_admin_inquiry_info', 'mfa_admin_inquiry_info_shortcode' );
function mfa_admin_inquiry_info_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'inquiry' );
		if ( $no_access ) {
			return $no_access;
		}
	}

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
				// Only New -> Read, opening an already-Replied/Archived inquiry
				// shouldn't downgrade its status.
				if ( 'New' === $row['cct_status'] ) {
					$wpdb->update(
						$cct_table,
						array( 'cct_status' => 'Read', 'cct_modified' => current_time( 'mysql' ) ),
						array( '_ID' => $id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					$row['cct_status'] = 'Read';
				}

				$state       = mfa_admin_inquiry_contact_state( $row );
				$can_email   = $state['can_email'];
				$wa_eligible = $state['wa_eligible'];
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
						<span class="mfa-admin-check"><?php echo wp_kses_post( mfa_admin_inquiry_check_badge( $can_email, 'Can reply by email', $state['email_reason'] ) ); ?></span>
					</div>
					<div class="mfa-admin-inquiry-info-item">
						<span class="mfa-label">Whatsapp / Phone</span>
						<span class="mfa-body"><?php echo esc_html( $row['phone'] ? $row['phone'] : '—' ); ?></span>
						<span class="mfa-admin-check"><?php echo wp_kses_post( mfa_admin_inquiry_check_badge( $state['can_whatsapp'], 'Window open', $state['wa_reason'] ) ); ?></span>
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

				<?php if ( $can_email || $wa_eligible ) : ?>
					<form class="mfa-admin-inquiry-reply-form" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_admin_inquiry_reply_' . $id ) ); ?>">
						<div class="mfa-form-group">
							<div class="mfa-admin-inquiry-reply-head">
								<label for="mfa-admin-inquiry-reply-message">Reply Message</label>
								<?php if ( class_exists( 'NWA_AI' ) ) : ?>
									<button type="button" class="mfa-btn mfa-btn-ghost mfa-admin-inquiry-ai-btn" data-channel="<?php echo esc_attr( $can_email ? 'email' : 'whatsapp' ); ?>">Generate with AI</button>
								<?php endif; ?>
							</div>
							<textarea id="mfa-admin-inquiry-reply-message" name="message" rows="4" placeholder="Type your reply…" required></textarea>
							<p class="mfa-admin-inquiry-ai-note" data-mfa-ai-note></p>
						</div>
						<div class="mfa-admin-inquiry-reply-actions">
							<?php if ( $can_email ) : ?>
								<button type="submit" name="mfa_reply_channel" value="email" class="mfa-btn mfa-btn-primary">Reply via Email</button>
							<?php endif; ?>
							<?php if ( $wa_eligible ) : ?>
								<button type="submit" name="mfa_reply_channel" value="whatsapp" class="mfa-btn mfa-btn-solid-dark">Send via WhatsApp</button>
							<?php endif; ?>
						</div>
						<p class="mfa-modal-message" data-mfa-form-message></p>
					</form>
				<?php endif; ?>
				<?php if ( ! $can_email && ! $wa_eligible ) : ?>
					<p class="mfa-body-muted mfa-admin-inquiry-whatsapp-note">Neither channel is available for this inquiry — see the reasons above.</p>
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
	if ( function_exists( 'mfa_user_can_access_admin_section' ) ? ! mfa_user_can_access_admin_section( 'inquiry' ) : ! current_user_can( 'manage_options' ) ) {
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

	// Best-effort: the message already sent successfully, so a status-update
	// hiccup here shouldn't turn a real send into a reported failure.
	$wpdb->update(
		$wpdb->prefix . 'jet_cct_contact_us',
		array( 'cct_status' => 'Replied', 'cct_modified' => current_time( 'mysql' ) ),
		array( '_ID' => $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	wp_send_json_success( array( 'message' => 'Sent via WhatsApp.' ) );
}

/**
 * AJAX handler for the "Reply via Email" button in the same unified reply
 * form above. Sends server-side via wp_mail() - deliberately not a
 * `mailto:` link, so the reply is logged/attributable and doesn't depend
 * on the staff member having a local mail client configured.
 */
add_action( 'wp_ajax_mfa_admin_inquiry_reply_email', 'mfa_ajax_admin_inquiry_reply_email' );
function mfa_ajax_admin_inquiry_reply_email() {
	if ( function_exists( 'mfa_user_can_access_admin_section' ) ? ! mfa_user_can_access_admin_section( 'inquiry' ) : ! current_user_can( 'manage_options' ) ) {
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
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT email, subject, cct_author_id FROM {$wpdb->prefix}jet_cct_contact_us WHERE _ID = %d", $id
	), ARRAY_A );

	if ( ! $row ) {
		wp_send_json_error( array( 'message' => 'Inquiry not found.' ) );
	}

	// Same placeholder-aware test the page renders, re-run here rather than
	// trusting that the button was only shown when it should have been.
	$state = mfa_admin_inquiry_contact_state( $row );
	if ( ! $state['can_email'] ) {
		wp_send_json_error( array( 'message' => $state['email_reason'] ? $state['email_reason'] : 'This inquiry has no valid email address on file.' ) );
	}

	$subject = 'Re: ' . ( $row['subject'] ? $row['subject'] : 'Your inquiry to Masjid4All' );
	$headers = array( 'From: Masjid4All <' . get_option( 'admin_email' ) . '>' );

	$sent = wp_mail( $row['email'], $subject, $message, $headers );

	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => 'Failed to send email. Please try again.' ) );
	}

	// Best-effort, same reasoning as the WhatsApp handler above - the email
	// already sent successfully, so a status-update hiccup here shouldn't
	// turn a real send into a reported failure.
	$wpdb->update(
		$wpdb->prefix . 'jet_cct_contact_us',
		array( 'cct_status' => 'Replied', 'cct_modified' => current_time( 'mysql' ) ),
		array( '_ID' => $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	wp_send_json_success( array( 'message' => 'Sent via email.' ) );
}

/**
 * AJAX: draft a reply with AI. Fills the textarea only - it never sends.
 *
 * Kept deliberately separate from the two send handlers so there is no
 * path where generating and sending happen in one step: a model writing
 * to a member on our behalf without anyone reading it first is exactly
 * what we don't want. The draft comes back, staff edit it, and the
 * existing Reply buttons do the sending under their own gates.
 *
 * Grounded on the Knowledge Hub via NWA_DB::search_knowledge() - the same
 * source Sofia answers from - so an admin reply and a Sofia reply don't
 * contradict each other.
 */
add_action( 'wp_ajax_mfa_admin_inquiry_ai_draft', 'mfa_ajax_admin_inquiry_ai_draft' );
function mfa_ajax_admin_inquiry_ai_draft() {
	if ( function_exists( 'mfa_user_can_access_admin_section' ) ? ! mfa_user_can_access_admin_section( 'inquiry' ) : ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to do this.' ) );
	}

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id ) {
		wp_send_json_error( array( 'message' => 'Invalid inquiry.' ) );
	}

	check_ajax_referer( 'mfa_admin_inquiry_reply_' . $id, 'nonce' );

	if ( ! class_exists( 'NWA_AI' ) ) {
		wp_send_json_error( array( 'message' => 'AI drafting is unavailable - the WhatsApp plugin is not active.' ) );
	}

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT name, subject, message FROM {$wpdb->prefix}jet_cct_contact_us WHERE _ID = %d", $id
	), ARRAY_A );

	if ( ! $row ) {
		wp_send_json_error( array( 'message' => 'Inquiry not found.' ) );
	}

	$channel = ( isset( $_POST['channel'] ) && 'whatsapp' === $_POST['channel'] ) ? 'whatsapp' : 'email';

	$first_name = trim( (string) $row['name'] );
	if ( $first_name ) {
		$parts      = preg_split( '/\s+/', $first_name );
		$first_name = $parts[0];
	}

	// The provider call is capped at 20s (NWA_AI::call_deepseek). A long
	// inquiry plus five long Knowledge Hub entries can push a generation
	// past that, and the failure is a blank draft. Bound both inputs rather
	// than letting prompt size decide whether the feature works.
	$inquiry_text = mb_substr( (string) $row['message'], 0, 2000 );

	// Ground on the Knowledge Hub, same search Sofia uses.
	$kb_text = '';
	if ( class_exists( 'NWA_DB' ) ) {
		$query   = trim( $row['subject'] . ' ' . $inquiry_text );
		$results = NWA_DB::search_knowledge( $query, 5 );
		foreach ( (array) $results as $kb ) {
			$kb_text .= "### {$kb->title}\n{$kb->content}\n\n";
			if ( mb_strlen( $kb_text ) > 6000 ) {
				break;
			}
		}
	}

	$site_name = get_bloginfo( 'name' ) ?: 'Masjid4All';

	if ( 'whatsapp' === $channel ) {
		$shape = 'Write 2 to 4 short sentences suitable for WhatsApp. No greeting block and no sign-off. '
			. 'Plain text only - no markdown, no bullet points.';
	} else {
		$shape = 'Write 2 to 4 short paragraphs. '
			. ( $first_name ? 'Open by addressing them as ' . $first_name . '. ' : 'Open with a brief greeting. ' )
			. 'Close with "Masjid4All Team" on its own line. Plain text only - no markdown, no subject line.';
	}

	$system = "You are drafting a reply that a {$site_name} staff member will read, edit and send. "
		. "Write only the body of the reply - never a subject line, never square-bracket placeholders "
		. "to fill in later, never a note explaining what you did.\n\n"
		. $shape . "\n\n"
		. "Answer using ONLY the reference information below. If it does not contain the answer, say "
		. "the team will come back to them with the detail - do not guess, and never state a price, "
		. "date, or feature that is not in the reference information.";

	if ( $kb_text ) {
		$system .= "\n\nReference information:\n" . $kb_text;
	} else {
		$system .= "\n\nThere is no reference information for this inquiry, so keep the reply to an "
			. "acknowledgement and a promise to follow up with specifics.";
	}

	$user_prompt = 'Subject: ' . ( $row['subject'] ? $row['subject'] : '(none)' ) . "\n\n"
		. "Their message:\n" . $inquiry_text;

	$draft = NWA_AI::call_ai( $system, $user_prompt );
	$draft = is_string( $draft ) ? trim( $draft ) : '';

	if ( '' === $draft ) {
		wp_send_json_error( array( 'message' => 'The AI did not return a draft. Please try again, or write the reply yourself.' ) );
	}

	wp_send_json_success( array(
		'draft'   => $draft,
		'channel' => $channel,
		'message' => 'whatsapp' === $channel
			? 'Drafted for WhatsApp. Read it before sending.'
			: 'Drafted for email. Read it before sending.',
	) );
}
