<?php
/**
 * Staff actions on a member: edit their details, and contact them.
 *
 * Rendered inside [mfa_admin_member_info]. Everything here is gated on the
 * same 'member' admin section as the page around it, and every send is
 * nonce-checked, confirmed in the browser first, and written to the
 * member's activity timeline afterwards - a message sent from an admin
 * screen is invisible otherwise, and "did we already contact them?" is the
 * first question anyone asks.
 *
 * The WhatsApp rules are not UI decoration. Meta only accepts a free-form
 * message inside 24 hours of the member's last inbound one; outside that
 * window it must be an approved template. So the plain-message button is
 * disabled once the window closes, and the template button is offered
 * instead. See CLAUDE.md on wp_nwa_* datetimes being GMT - the window
 * comparison depends on it.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Can the current user act on members? */
function mfa_admin_member_can_act() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( function_exists( 'mfa_user_can_access_admin_section' ) ) {
		return (bool) mfa_user_can_access_admin_section( 'member' );
	}

	return current_user_can( 'edit_users' );
}

/**
 * Templates offered in the Send Template picker.
 *
 * These are names only. No template has been approved in Meta yet, so a
 * send will fail until they exist - the handler reports that plainly
 * rather than pretending it worked.
 */
function mfa_admin_member_templates() {
	return apply_filters( 'mfa_admin_member_templates', array(
		'mfa_welcome'   => 'Welcome',
		'mfa_followup'  => 'Follow-up',
	) );
}

/**
 * Prepared messages staff can start from, per channel.
 *
 * Keyed off what a member is MISSING rather than which route they arrived
 * by - the principle already agreed for follow-ups, which is what lets one
 * set serve Sofia, Google and web-form members alike.
 *
 * Copy lives here rather than in the database on purpose while the wording
 * is still being worked out: it is versioned, reviewable in a diff, and
 * cannot be edited into something nobody approved. Every read goes through
 * this one function, so moving to an editable store later is a change in
 * one place. Filter `mfa_admin_member_messages` to add or reword without
 * touching the file.
 *
 * {{name}} and {{site}} are substituted at render time. Nothing else is,
 * deliberately - a placeholder that silently stays literal in a sent
 * message is worse than not offering it.
 *
 * @param string $channel email|whatsapp
 */
/**
 * Should this prepared message be offered for this member?
 *
 * A named rule rather than a callback, so the catalogue stays plain data and
 * the same rule can be re-checked server-side before a send.
 */
function mfa_admin_member_message_applies( $when, $user_id ) {
	$when = (string) $when;

	if ( '' === $when ) {
		return true;
	}

	if ( 'prospect' === $when ) {
		// "Not yet a member", not "explicitly a prospect". Two reasons:
		// ~74,800 accounts carry no user_status meta at all, and they are
		// exactly the people who still need activating; and the meta is
		// overwhelmingly lowercase but not entirely (production has one
		// 'Prospect', staging more), so an exact match would hide the option
		// from those rows too.
		$status = strtolower( (string) get_user_meta( $user_id, 'user_status', true ) );

		return ! in_array( $status, array( 'member', 'premium' ), true );
	}

	if ( 'not_on_waitlist' === $when ) {
		$leads = get_user_meta( $user_id, 'mfa_sofia_leads', true );
		$entry = is_array( $leads ) && isset( $leads['founding_member'] ) ? $leads['founding_member'] : null;

		if ( ! $entry ) {
			return true;
		}

		// mfa_sofia_leads holds two different things under one key: a SIGNAL
		// (interest shown, carries 'count', no email) and a CAPTURE (they
		// finished the flow). Only a capture means they are actually on the
		// list, so somebody who once asked and never finished still gets
		// invited - which is the whole point of inviting them.
		return isset( $entry['count'] );
	}

	if ( 'email_unverified' === $when ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}

		// A placeholder address cannot be verified - there is nobody at the
		// other end. Those members need Activate Account or the email-capture
		// flow, which is a different offer, so this one is hidden for them.
		if ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $user->user_email ) ) {
			return false;
		}

		return 'yes' !== strtolower( (string) get_user_meta( $user_id, 'niz_email_verified', true ) );
	}

	return true;
}

function mfa_admin_member_messages( $channel ) {
	$site = get_bloginfo( 'name' ) ?: 'Masjid4All';

	$messages = array(
		'email' => array(
			'welcome' => array(
				'label'   => 'Welcome',
				'subject' => 'Welcome to ' . $site,
				'body'    => "Assalamualaikum {{name}},\n\nWelcome to {{site}} — we're glad to have you.\n\nYou can find mosques and halal businesses near you, save the ones you visit, and earn Barakah points as you contribute to the directory.\n\nIf there's anything you need, just reply to this email.\n\n{{site}} Team",
			),
			'verify_whatsapp' => array(
				'label'   => 'Ask them to verify WhatsApp',
				'subject' => 'Add your WhatsApp to ' . $site,
				'body'    => "Assalamualaikum {{name}},\n\nYour {{site}} account doesn't have a verified WhatsApp number yet. Adding one lets us reach you with prayer time reminders and lets you use Sofia, our WhatsApp assistant, for mosque and halal business lookups.\n\nYou can add and verify it from your member page:\n" . home_url( '/member/' ) . "\n\nIt takes less than a minute.\n\n{{site}} Team",
			),
			'set_password' => array(
				'label'   => 'Ask them to set a password',
				'subject' => 'Set a password for your ' . $site . ' account',
				'body'    => "Assalamualaikum {{name}},\n\nYou signed up without setting a password, so you can currently only sign in the way you first joined.\n\nSetting one means you can always get in by email and password too:\n" . home_url( '/member/' ) . "\n\n{{site}} Team",
			),
			'complete_profile' => array(
				'label'   => 'Nudge to complete profile',
				'subject' => 'Finish setting up your ' . $site . ' profile',
				'body'    => "Assalamualaikum {{name}},\n\nYour profile is still incomplete. Finishing it takes a couple of minutes and earns you Barakah points:\n" . home_url( '/member/' ) . "\n\n{{site}} Team",
			),
		),

		'whatsapp' => array(
			'activate_account' => array(
				'label' => 'Activate Account',
				// Only offered for a prospect: a member has nothing to activate.
				'when'  => 'prospect',
				'body'  => "Assalamualaikum {{name}} 👋\n\nYou're on {{site}} as a contact, but your account isn't active yet.\n\nActivating takes a minute — I'll confirm your *name* and *email*, and then you can save mosques, earn Barakah points and manage your own listings.\n\nTap *Register* below to start.",
				// Titles are ROUTING KEYWORDS, not labels: a tap sends the title
				// back as the message and it is matched whole-string against
				// wp_nwa_actions. 'Register' -> register, 'Not now' -> not_now.
				// Renaming one here silently breaks the button.
				'buttons' => array(
					array( 'id' => 'act_register', 'title' => 'Register' ),
					array( 'id' => 'act_not_now',  'title' => 'Not now' ),
				),
			),
			'welcome' => array(
				'label' => 'Welcome',
				'body'  => "Assalamualaikum {{name}} 👋\n\nWelcome to {{site}}. You can ask me for prayer times, the nearest mosque, or halal businesses near you — just tell me what you need.",
			),
			'verify_email' => array(
				'label' => 'Verify Email',
				// Hidden for a placeholder address: there is nothing at the other
				// end to verify. Those members get Activate Account instead.
				'when'  => 'email_unverified',
				'body'  => "Assalamualaikum {{name}},

Your email address isn't verified yet, so we can't send you updates or help you reset a password.

Tap *Verify Email* below and I'll send a verification link straight to it.",
				// Titles are ROUTING KEYWORDS - 'Verify Email' -> verify_email,
				// 'Not now' -> not_now. Renaming one breaks the button silently.
				'buttons' => array(
					array( 'id' => 'ver_email',   'title' => 'Verify Email' ),
					array( 'id' => 'ver_not_now', 'title' => 'Not now' ),
				),
			),
			'complete_profile' => array(
				'label' => 'Nudge to complete profile',
				'body'  => "Assalamualaikum {{name}},\n\nYour {{site}} profile is still incomplete — finishing it takes a minute and earns you Barakah points:\n" . home_url( '/member/' ),
			),
			'invite_directory' => array(
				'label' => 'Invite to Add Mosque/Business/Website',
				'body'  => "Assalamualaikum {{name}} 👋\n\nDo you know a mosque, halal business or Islamic website that isn't on {{site}} yet?\n\nAdding one is free and takes a minute — I'll ask for a Google Maps link (or the web address) and do the rest.\n\nPick one below to start.",
				// Three buttons is WhatsApp's hard maximum, so there is no room
				// for a "Not now" here. Titles are routing keywords - all three
				// resolve to the directory action.
				'buttons' => array(
					array( 'id' => 'inv_mosque',   'title' => 'Add Mosque' ),
					array( 'id' => 'inv_business', 'title' => 'Add Business' ),
					array( 'id' => 'inv_website',  'title' => 'Add Website' ),
				),
			),
			'invite_founding' => array(
				'label' => 'Invite to Founding Member waitlist',
				// Hidden once they are actually on the list - see the
				// 'not_on_waitlist' rule for why a mere signal does not count.
				'when'  => 'not_on_waitlist',
				// Terms deliberately mirror the waitlist flow's own intro
				// (mfa_lead_types()['founding_member']['intro']) so the offer
				// cannot drift between the invitation and the thing invited to.
				'body'  => "Assalamualaikum {{name}},\n\n*Founding Member* is for the people who back {{site}} from the start. ⭐\n\nThe plan: a one-time joining fee, lifetime Premium access, the full amount returned to you as Platform Credit, and permanent Founding Member status.\n\nIt's *not on sale yet* — we're building the waitlist now, and you'd be told first when it opens. No payment, no commitment.\n\nTap *Founding Member* to join the waitlist.",
				'buttons' => array(
					array( 'id' => 'inv_founding', 'title' => 'Founding Member' ),
					array( 'id' => 'inv_fnot_now', 'title' => 'Not now' ),
				),
			),
			'check_in' => array(
				'label' => 'Friendly check-in',
				'body'  => "Assalamualaikum {{name}},\n\nJust checking in from {{site}} — is there anything we can help you with?",
			),
		),
	);

	$channel  = ( 'email' === $channel ) ? 'email' : 'whatsapp';
	$messages = apply_filters( 'mfa_admin_member_messages', $messages, $channel );
	$list     = isset( $messages[ $channel ] ) ? $messages[ $channel ] : array();

	// Dropdown order for staff: the ones that move a member forward first,
	// then the general-purpose notes. Done here rather than by shuffling the
	// array literal so a filter can add entries without having to know where
	// to put them - anything unlisted keeps its own position, after these.
	$first = array( 'activate_account', 'verify_email', 'invite_directory', 'invite_founding' );
	$head  = array();

	foreach ( $first as $key ) {
		if ( isset( $list[ $key ] ) ) {
			$head[ $key ] = $list[ $key ];
			unset( $list[ $key ] );
		}
	}

	return $head + $list;
}

/** Fills the two placeholders the prepared messages use. */
function mfa_admin_member_message_fill( $text, $row ) {
	$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
	if ( $name ) {
		$parts = preg_split( '/\s+/', $name );
		$name  = $parts[0];
	}

	return str_replace(
		array( '{{name}}', '{{site}}' ),
		array( $name ? $name : 'there', get_bloginfo( 'name' ) ?: 'Masjid4All' ),
		(string) $text
	);
}

/**
 * What can we do for this member right now, and why not.
 *
 * Returns the reasons as text so the UI can explain a disabled button
 * instead of just greying it out.
 */
function mfa_admin_member_contact_state( $user_id ) {
	$user  = get_userdata( $user_id );
	$state = array(
		'email'          => '',
		'can_email'      => false,
		'email_reason'   => '',
		'phone'          => '',
		'can_whatsapp'   => false,
		'wa_reason'      => '',
		'can_template'   => false,
		'template_reason'=> '',
		'window_expires' => '',
		'opted_out'      => false,
	);

	if ( ! $user ) {
		return $state;
	}

	// --- email
	$state['email'] = $user->user_email;
	if ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $user->user_email ) ) {
		$state['email_reason'] = 'No real email address on file.';
	} else {
		$state['can_email'] = true;
	}

	// --- whatsapp
	$phone = trim( (string) get_user_meta( $user_id, 'user_phone', true ) );
	$state['phone'] = $phone;

	if ( '' === $phone ) {
		$state['wa_reason']       = 'No WhatsApp number on file.';
		$state['template_reason'] = 'No WhatsApp number on file.';
		return $state;
	}

	// An opt-out blocks the template outright - it is the business-initiated
	// channel, and niz-wa refuses it in the sender too, so offering the button
	// would only produce a failure. Free-form stays available: that window
	// exists because they messaged us, and answering someone who just wrote in
	// is not what they opted out of.
	if ( function_exists( 'nwa_is_opted_out' ) && nwa_is_opted_out( $user_id ) ) {
		$state['opted_out']       = true;
		$state['template_reason'] = 'They replied STOP — templates are blocked. Free-form replies inside an open window are still allowed.';
	} else {
		$state['can_template'] = true;
	}

	if ( ! class_exists( 'NWA_DB' ) ) {
		$state['wa_reason'] = 'WhatsApp plugin unavailable.';
		return $state;
	}

	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation ) {
		$state['wa_reason'] = 'They have never messaged Sofia, so there is no open window.';
		return $state;
	}

	$state['window_expires'] = (string) $conversation->window_expires_at;

	if ( NWA_DB::is_within_window( $conversation ) ) {
		$state['can_whatsapp'] = true;
	} else {
		$state['wa_reason'] = 'The 24-hour window closed'
			. ( $conversation->window_expires_at
				? ' on ' . date_i18n( 'j M, g:i a', strtotime( get_date_from_gmt( $conversation->window_expires_at ) ) )
				: '' )
			. '. Send an approved template instead.';
	}

	return $state;
}

/* ---------------- Render ---------------- */

/**
 * The action bar plus its modals, for the member detail page.
 *
 * @param array $row     jet_cct_member row.
 * @param int   $user_id
 */
function mfa_admin_member_actions_render( $row, $user_id ) {
	if ( ! mfa_admin_member_can_act() ) {
		return '';
	}

	global $wpdb;

	$state     = mfa_admin_member_contact_state( $user_id );
	$templates = mfa_admin_member_templates();
	$statuses  = function_exists( 'mfa_admin_member_status_options' )
		? mfa_admin_member_status_options()
		: array( 'Prospect', 'Member' );

	$countries = $wpdb->get_col(
		"SELECT DISTINCT country FROM {$wpdb->prefix}jet_cct_member WHERE country IS NOT NULL AND TRIM(country) != '' ORDER BY country ASC"
	);

	$uid   = (int) $user_id;
	$nonce = wp_create_nonce( 'mfa_admin_member_action_' . $uid );
	$name  = isset( $row['name'] ) ? $row['name'] : '';
	$email = isset( $row['email'] ) ? $row['email'] : '';
	$cty   = isset( $row['country'] ) ? $row['country'] : '';
	$stat  = isset( $row['status'] ) ? $row['status'] : '';

	ob_start();
	?>
	<?php if ( ! empty( $state['opted_out'] ) ) : ?>
		<?php // Stated plainly above the buttons - a disabled button explains itself only on hover. ?>
		<p class="mfa-mact-optout">This member replied <strong>STOP</strong>. Templates are blocked; don't market to them.</p>
	<?php endif; ?>

	<div class="mfa-admin-member-actions-bar" data-user="<?php echo $uid; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<button type="button" class="mfa-btn mfa-btn-secondary mfa-mact-btn" data-mact-open="mfa-mact-edit-<?php echo $uid; ?>">Update Info</button>
		<button type="button" class="mfa-btn mfa-btn-secondary mfa-mact-btn" data-mact-open="mfa-mact-email-<?php echo $uid; ?>" <?php disabled( ! $state['can_email'] ); ?> title="<?php echo esc_attr( $state['email_reason'] ); ?>">Send Email</button>
		<button type="button" class="mfa-btn mfa-btn-secondary mfa-mact-btn" data-mact-open="mfa-mact-wa-<?php echo $uid; ?>" <?php disabled( ! $state['can_whatsapp'] ); ?> title="<?php echo esc_attr( $state['wa_reason'] ); ?>">Send WhatsApp</button>
		<button type="button" class="mfa-btn mfa-btn-secondary mfa-mact-btn" data-mact-open="mfa-mact-tpl-<?php echo $uid; ?>" <?php disabled( ! $state['can_template'] ); ?> title="<?php echo esc_attr( $state['template_reason'] ); ?>">Send Template</button>
	</div>

	<?php if ( '' !== $state['wa_reason'] ) : ?>
		<p class="mfa-admin-member-actions-note"><?php echo esc_html( $state['wa_reason'] ); ?></p>
	<?php endif; ?>

	<div class="mfa-mact-overlay" id="mfa-mact-edit-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true">
		<div class="mfa-mact-modal">
			<button type="button" class="mfa-mact-close" data-mact-close aria-label="Close">&times;</button>
			<h3 class="mfa-h3">Update Info</h3>
			<form class="mfa-mact-form" data-mact-action="update">
				<div class="mfa-form-group">
					<label>Name</label>
					<input type="text" name="name" value="<?php echo esc_attr( $name ); ?>" required>
				</div>
				<div class="mfa-form-group">
					<label>Email</label>
					<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>">
				</div>
				<div class="mfa-form-group">
					<label>WhatsApp number</label>
					<input type="tel" name="phone" value="<?php echo esc_attr( $state['phone'] ); ?>" placeholder="60123456789">
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label>Country</label>
						<select name="country">
							<option value="">&mdash;</option>
							<?php foreach ( $countries as $c ) : ?>
								<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cty, $c ); ?>><?php echo esc_html( $c ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mfa-form-group">
						<label>Status</label>
						<select name="status">
							<?php foreach ( $statuses as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $stat, $s ); ?>><?php echo esc_html( $s ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<button type="submit" class="mfa-btn mfa-btn-primary mfa-mact-submit">Save Changes</button>
				<p class="mfa-mact-msg" data-mact-msg></p>
			</form>
		</div>
	</div>

	<div class="mfa-mact-overlay" id="mfa-mact-email-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true">
		<div class="mfa-mact-modal">
			<button type="button" class="mfa-mact-close" data-mact-close aria-label="Close">&times;</button>
			<h3 class="mfa-h3">Send Email</h3>
			<p class="mfa-body-muted">To <strong><?php echo esc_html( $state['email'] ); ?></strong></p>
			<form class="mfa-mact-form" data-mact-action="email" data-mact-confirm="Send this email to <?php echo esc_attr( $state['email'] ); ?>?">
				<?php $email_messages = mfa_admin_member_messages( 'email' ); ?>
				<?php if ( $email_messages ) : ?>
					<div class="mfa-form-group">
						<label>Start from</label>
						<select class="mfa-mact-preset" name="preset" data-mact-preset>
							<option value="">Free-form (write your own)</option>
							<?php foreach ( $email_messages as $key => $msg ) : ?>
								<option
									value="<?php echo esc_attr( $key ); ?>"
									data-subject="<?php echo esc_attr( mfa_admin_member_message_fill( $msg['subject'] ?? '', $row ) ); ?>"
									data-body="<?php echo esc_attr( mfa_admin_member_message_fill( $msg['body'] ?? '', $row ) ); ?>"
								><?php echo esc_html( $msg['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<small class="mfa-mact-hint">Picking one fills the fields below &mdash; edit them before sending.</small>
					</div>
				<?php endif; ?>
				<div class="mfa-form-group">
					<label>Subject</label>
					<input type="text" name="subject" required>
				</div>
				<div class="mfa-form-group">
					<label>Message</label>
					<textarea name="body" rows="7" required></textarea>
				</div>
				<button type="submit" class="mfa-btn mfa-btn-primary mfa-mact-submit">Send Email</button>
				<p class="mfa-mact-msg" data-mact-msg></p>
			</form>
		</div>
	</div>

	<div class="mfa-mact-overlay" id="mfa-mact-wa-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true">
		<div class="mfa-mact-modal">
			<button type="button" class="mfa-mact-close" data-mact-close aria-label="Close">&times;</button>
			<h3 class="mfa-h3">Send WhatsApp</h3>
			<p class="mfa-body-muted">To <strong>+<?php echo esc_html( $state['phone'] ); ?></strong></p>
			<form class="mfa-mact-form" data-mact-action="whatsapp" data-mact-confirm="Send this WhatsApp message to +<?php echo esc_attr( $state['phone'] ); ?>?">
				<?php $wa_messages = mfa_admin_member_messages( 'whatsapp' ); ?>
				<?php if ( $wa_messages ) : ?>
					<div class="mfa-form-group">
						<label>Start from</label>
						<select class="mfa-mact-preset" name="preset" data-mact-preset>
							<option value="">Free-form (write your own)</option>
							<?php foreach ( $wa_messages as $key => $msg ) : ?>
								<?php if ( ! mfa_admin_member_message_applies( $msg['when'] ?? '', $user_id ) ) { continue; } ?>
								<option
									value="<?php echo esc_attr( $key ); ?>"
									data-body="<?php echo esc_attr( mfa_admin_member_message_fill( $msg['body'] ?? '', $row ) ); ?>"
								><?php echo esc_html( $msg['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<small class="mfa-mact-hint">Picking one fills the message below &mdash; edit it before sending.</small>
					</div>
				<?php endif; ?>
				<div class="mfa-form-group">
					<label>Message</label>
					<textarea name="body" rows="6" required></textarea>
				</div>
				<button type="submit" class="mfa-btn mfa-btn-primary mfa-mact-submit">Send Message</button>
				<p class="mfa-mact-msg" data-mact-msg></p>
			</form>
		</div>
	</div>

	<div class="mfa-mact-overlay" id="mfa-mact-tpl-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-hidden="true">
		<div class="mfa-mact-modal">
			<button type="button" class="mfa-mact-close" data-mact-close aria-label="Close">&times;</button>
			<h3 class="mfa-h3">Send Template</h3>
			<p class="mfa-body-muted">To <strong>+<?php echo esc_html( $state['phone'] ); ?></strong>. A template reaches them outside the 24-hour window, but it must already be approved in Meta.</p>
			<form class="mfa-mact-form" data-mact-action="template" data-mact-confirm="Send this template to +<?php echo esc_attr( $state['phone'] ); ?>?">
				<div class="mfa-form-group">
					<label>Template</label>
					<select name="template" required>
						<?php foreach ( $templates as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?> (<?php echo esc_html( $key ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="mfa-btn mfa-btn-primary mfa-mact-submit">Send Template</button>
				<p class="mfa-mact-msg" data-mact-msg></p>
			</form>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------------- AJAX ----------------
   One entry point per action. Every one re-checks the capability and the
   nonce server-side: the browser confirmation is a courtesy to the person
   clicking, never the control. Every send is written to the member's
   activity timeline, because a message sent from here is otherwise
   invisible and "have we contacted them?" is the first thing anyone asks. */

add_action( 'wp_ajax_mfa_admin_member_update', 'mfa_admin_member_ajax_update' );
function mfa_admin_member_ajax_update() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	if ( ! mfa_admin_member_can_act() || ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ) );
	}
	check_ajax_referer( 'mfa_admin_member_action_' . $user_id, 'nonce' );

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		wp_send_json_error( array( 'message' => 'That member no longer exists.' ) );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
	$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => 'Name cannot be empty.' ) );
	}
	if ( '' !== $email && ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'That email address is not valid.' ) );
	}

	$allowed = function_exists( 'mfa_admin_member_status_options' ) ? mfa_admin_member_status_options() : array();
	if ( '' !== $status && $allowed && ! in_array( $status, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => 'Unknown status.' ) );
	}

	// Normalised the same way the WhatsApp side stores it, or the same
	// number typed differently will never match a conversation.
	if ( '' !== $phone ) {
		$phone = function_exists( 'niz_user_normalize_phone' )
			? niz_user_normalize_phone( $phone )
			: preg_replace( '/\D/', '', $phone );
	}

	$changed = array();

	if ( '' !== $email && $email !== $user->user_email ) {
		// Changing the account email is an auth-adjacent change: it is what
		// login and password reset both key on, so it is applied to the real
		// user record, not only the CCT row.
		$exists = email_exists( $email );
		if ( $exists && (int) $exists !== $user_id ) {
			wp_send_json_error( array( 'message' => 'Another account already uses that email.' ) );
		}
		wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
		$changed[] = 'email';
	}

	if ( $name !== $user->display_name ) {
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
		$changed[] = 'name';
	}

	$old_phone = trim( (string) get_user_meta( $user_id, 'user_phone', true ) );
	if ( $phone !== $old_phone ) {
		update_user_meta( $user_id, 'user_phone', $phone );
		$changed[] = 'phone';
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'name', $name );
		if ( '' !== $email ) {
			niz_user_update_field( $user_id, 'email', $email );
		}
		niz_user_update_field( $user_id, 'phone', $phone );
		niz_user_update_field( $user_id, 'country', $country );
		if ( '' !== $status ) {
			niz_user_update_field( $user_id, 'status', $status );
			// Keep the usermeta the rest of the site reads in step with the
			// CCT row - they drifted apart once already and took 22 rows of
			// reconciliation to put right.
			update_user_meta( $user_id, 'user_status', in_array( $status, array( 'Member', 'Premium Member', 'Premium Lifetime' ), true ) ? 'member' : 'prospect' );
			$changed[] = 'status';
		}
	}

	if ( function_exists( 'mfa_log_activity' ) ) {
		mfa_log_activity( $user_id, 'admin_update', 'Details updated by ' . wp_get_current_user()->display_name
			. ( $changed ? ' (' . implode( ', ', array_unique( $changed ) ) . ')' : '' ) );
	}

	wp_send_json_success( array( 'message' => 'Saved.' ) );
}

add_action( 'wp_ajax_mfa_admin_member_email', 'mfa_admin_member_ajax_email' );
function mfa_admin_member_ajax_email() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	if ( ! mfa_admin_member_can_act() || ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ) );
	}
	check_ajax_referer( 'mfa_admin_member_action_' . $user_id, 'nonce' );

	$state = mfa_admin_member_contact_state( $user_id );
	if ( ! $state['can_email'] ) {
		wp_send_json_error( array( 'message' => $state['email_reason'] ? $state['email_reason'] : 'Cannot email this member.' ) );
	}

	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

	if ( '' === $subject || '' === $body ) {
		wp_send_json_error( array( 'message' => 'Subject and message are both required.' ) );
	}

	$sent = wp_mail(
		$state['email'],
		$subject,
		$body,
		array( 'From: Masjid4All <' . get_option( 'admin_email' ) . '>' )
	);

	if ( ! $sent ) {
		error_log( 'mfa_admin_member_ajax_email: wp_mail failed for user ' . $user_id );
		wp_send_json_error( array( 'message' => 'The mail server rejected it. Nothing was sent.' ) );
	}

	if ( function_exists( 'mfa_log_activity' ) ) {
		mfa_log_activity( $user_id, 'admin_email', 'Email sent by ' . wp_get_current_user()->display_name . ': ' . $subject );
	}

	wp_send_json_success( array( 'message' => 'Email sent.' ) );
}

add_action( 'wp_ajax_mfa_admin_member_whatsapp', 'mfa_admin_member_ajax_whatsapp' );
function mfa_admin_member_ajax_whatsapp() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	if ( ! mfa_admin_member_can_act() || ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ) );
	}
	check_ajax_referer( 'mfa_admin_member_action_' . $user_id, 'nonce' );

	// Re-checked here, not just in the UI: the window can close between the
	// page rendering and the button being pressed, and Meta would reject it.
	$state = mfa_admin_member_contact_state( $user_id );
	if ( ! $state['can_whatsapp'] ) {
		wp_send_json_error( array( 'message' => $state['wa_reason'] ? $state['wa_reason'] : 'Cannot message this member.' ) );
	}

	$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
	if ( '' === $body ) {
		wp_send_json_error( array( 'message' => 'Message cannot be empty.' ) );
	}

	if ( ! function_exists( 'nwa_send_message' ) ) {
		wp_send_json_error( array( 'message' => 'WhatsApp plugin unavailable.' ) );
	}

	// Buttons come from the SERVER-side registry, keyed by the chosen preset -
	// never from the request. A tap sends the button's title back as the
	// message, and that title is matched against wp_nwa_actions, so a client
	// able to name its own buttons could route a member into any flow.
	$preset  = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
	$catalog = mfa_admin_member_messages( 'whatsapp' );
	$buttons = ( $preset && isset( $catalog[ $preset ]['buttons'] ) ) ? $catalog[ $preset ]['buttons'] : array();

	if ( $buttons && function_exists( 'nwa_send_buttons' ) ) {
		$res = nwa_send_buttons( $user_id, $state['phone'], $body, $buttons );
	} else {
		$res = nwa_send_message( $user_id, $state['phone'], $body );
	}

	if ( empty( $res['success'] ) ) {
		$why = ! empty( $res['error'] ) ? $res['error'] : 'unknown error';
		wp_send_json_error( array( 'message' => 'WhatsApp refused it (' . $why . '). Nothing was sent.' ) );
	}

	if ( function_exists( 'mfa_log_activity' ) ) {
		mfa_log_activity( $user_id, 'admin_whatsapp', 'WhatsApp sent by ' . wp_get_current_user()->display_name );
	}

	wp_send_json_success( array( 'message' => 'Message sent.' ) );
}

add_action( 'wp_ajax_mfa_admin_member_template', 'mfa_admin_member_ajax_template' );
function mfa_admin_member_ajax_template() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	if ( ! mfa_admin_member_can_act() || ! $user_id ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ) );
	}
	check_ajax_referer( 'mfa_admin_member_action_' . $user_id, 'nonce' );

	$state = mfa_admin_member_contact_state( $user_id );
	if ( ! $state['can_template'] ) {
		wp_send_json_error( array( 'message' => $state['template_reason'] ? $state['template_reason'] : 'Cannot message this member.' ) );
	}

	$template  = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
	$templates = mfa_admin_member_templates();

	if ( ! isset( $templates[ $template ] ) ) {
		wp_send_json_error( array( 'message' => 'Unknown template.' ) );
	}

	if ( ! function_exists( 'nwa_send_template' ) ) {
		wp_send_json_error( array( 'message' => 'WhatsApp plugin unavailable.' ) );
	}

	// Signature is ( $to, $template_name, $lang_code, $components, $user_id ) -
	// the number comes first here, unlike nwa_send_message().
	$res = nwa_send_template( $state['phone'], $template, '', array(), $user_id );

	if ( empty( $res['success'] ) ) {
		$why = ! empty( $res['error'] ) ? $res['error'] : 'unknown error';
		// Expected until the templates are created and approved in Meta.
		wp_send_json_error( array( 'message' => 'Meta refused it (' . $why . '). Templates must be created and approved in Meta before they can be sent.' ) );
	}

	if ( function_exists( 'mfa_log_activity' ) ) {
		mfa_log_activity( $user_id, 'admin_template', 'Template "' . $template . '" sent by ' . wp_get_current_user()->display_name );
	}

	wp_send_json_success( array( 'message' => 'Template sent.' ) );
}
