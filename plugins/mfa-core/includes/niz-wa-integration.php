<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glue between niz-wa (the portable WhatsApp plugin) and mfa-core's own
 * identity system. Moved here from niz-wa/includes/site-integration.php
 * so niz-wa has zero Masjid4All-specific code — this is the ONLY place
 * that decides what a WhatsApp number means on this site.
 */

/* ---------------- User resolution ---------------- */

add_filter( 'nwa_resolve_user_id', 'niz_wa_resolve_user_id', 10, 3 );

function niz_wa_resolve_user_id( $user_id, $wa_number, $contact_name ) {
	if ( ! function_exists( 'niz_user_check' ) ) {
		return $user_id; // Identity core not loaded — fall back to niz-wa's own default resolver.
	}

	// Link to an already-existing WordPress member if this WhatsApp number
	// is recognized.
	$existing = niz_user_check( $wa_number );
	if ( $existing ) {
		return $existing;
	}

	// 2026-08-08 decision: reversed from the 2026-08-04 "niz-wa standalone"
	// cutover — unrecognized numbers now get a real WordPress user instead
	// of only living in niz-wa's own wp_nwa_contacts table, so identity is
	// shareable across other Masjid4All-family sites. Deliberately lighter
	// than niz_user_complete_registration() though: no jet_cct_member row,
	// no Welcome Bonus. Those only happen if the contact explicitly replies
	// REGISTER (niz_wa_action_register(), below) — someone who messages
	// Sofia once and never comes back shouldn't end up with a half-built
	// "member" record. niz-wa itself is untouched — this hook is still the
	// only place that decides what a WhatsApp number means on this site.
	$new_user_id = niz_wa_create_contact_user( $wa_number, $contact_name );

	return $new_user_id ? $new_user_id : $user_id;
}

/**
 * Creates a bare WordPress user for a WhatsApp number with no existing
 * account. Marked phone-verified immediately — Meta's webhook only
 * delivers messages from a number that actually controls that WhatsApp,
 * so receiving one is already proof of ownership. Not yet a registered
 * "member" (see niz_wa_resolve_user_id() above).
 */
function niz_wa_create_contact_user( $wa_number, $contact_name ) {
	$phone = niz_user_normalize_phone( $wa_number );
	if ( empty( $phone ) ) {
		return 0;
	}

	$name     = sanitize_text_field( $contact_name );
	$username = 'mfa_' . $phone;
	if ( username_exists( $username ) ) {
		$username .= wp_rand( 100, 999 );
	}

	$user_id = wp_create_user( $username, wp_generate_password( 20 ), $phone . '@mfa.com' );

	if ( is_wp_error( $user_id ) ) {
		error_log( 'niz_wa_create_contact_user: wp_create_user failed - ' . $user_id->get_error_message() );
		return 0;
	}

	if ( $name ) {
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => $name,
		) );
	}

	update_user_meta( $user_id, 'user_phone', $phone );
	update_user_meta( $user_id, 'user_status', 'prospect' );
	update_user_meta( $user_id, 'lead_source', 'whatsapp' );
	update_user_meta( $user_id, 'niz_whatsapp_verified', 'Yes' );

	return $user_id;
}

/* ---------------- Action callbacks ---------------- */
/* Signature: function( $user_id, $context ): string reply. Never send messages
   directly — NWA_Router sends whatever string is returned. */

function niz_wa_action_start( $user_id, $context ) {
	return "Welcome to Masjid4All.\n\nHow can I help you?";
}

/**
 * Help / bantuan: an overview of what Sofia can do, with quick-add buttons.
 * The buttons' titles are exact directory keywords, so tapping them routes
 * straight into that branch (no session needed).
 */
function niz_wa_action_help( $user_id, $context ) {
	$body = "👋 Hi, I'm *Sofia*, your Masjid4All assistant.\n\n"
		. "I can help you list and manage things in our free directories:\n"
		. "🕌 Add your *mosque* (or surau/musolla)\n"
		. "🏪 Add or *claim* your *business*\n"
		. "🌐 Add or *claim* your *website*\n\n"
		. "You can also just ask me a question — for example _how do I get a Google Maps link?_ or _how do I claim my business?_ — and I'll guide you.\n\n"
		. "What would you like to do?";

	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( $conversation ) {
		nwa_send_buttons( $user_id, $conversation->wa_number, $body, array(
			array( 'id' => 'help_mosque',   'title' => 'Add Mosque' ),
			array( 'id' => 'help_business', 'title' => 'Add Business' ),
			array( 'id' => 'help_website',  'title' => 'Add Website' ),
		) );
		return '';
	}

	return $body;
}

/* ---------------- Account access: register / login / forgot-password ---------
   AUTH-SENSITIVE. Passwords are no longer issued over WhatsApp (site login is
   email/Google only). A member is logged straight in with a one-time magic
   link; anyone not yet registered gives an email, verified by a code we send
   to it, which either sets up their account or links their WhatsApp number to
   an existing one. Both 'register' and 'reset_password' share this flow.
   An optional pending action (e.g. a directory claim) is carried through and
   applied once the account is ready. */

function niz_wa_action_register( $user_id, $context ) {
	return niz_wa_account_start( $user_id );
}

function niz_wa_action_reset_password( $user_id, $context ) {
	return niz_wa_account_start( $user_id );
}

function niz_wa_is_member( $user_id ) {
	return in_array( get_user_meta( $user_id, 'user_status', true ), array( 'member', 'premium' ), true );
}

/**
 * Entry point. A member gets a magic-login link immediately; anyone else is
 * asked for an email to set up (or link) their account. $then is an optional
 * follow-up to run once they're a member, e.g.
 * array( 'claim' => array( 'post_id' => .., 'dtype' => 'web'|'business', 'name' => .. ) ).
 */
function niz_wa_account_start( $user_id, $then = null ) {
	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation ) {
		return "Please try again in a moment.";
	}
	$wa = $conversation->wa_number;

	if ( niz_wa_is_member( $user_id ) ) {
		niz_wa_account_finish( $user_id, $wa, "You're a Masjid4All member. 🎉", $then );
		return '';
	}

	$ctx = array( 'step' => 'await_email' );
	if ( is_array( $then ) ) {
		$ctx['then'] = $then;
	}
	nwa_send_message( $user_id, $wa,
		"Let's set up your free Masjid4All account.\n\nWhat's your *email address*?" . niz_wa_dir_stop_hint() );
	NWA_DB::set_pending_action( $conversation->id, 'account_flow', $ctx, 20 );
	return '';
}

/* ---- Account-flow session handler (override filter, priority 15) ---- */
add_filter( 'nwa_route_message_override', 'niz_wa_account_route', 15, 5 );

function niz_wa_account_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override;
	}
	if ( ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}
	if ( 'account_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		return $override;
	}

	$ctx  = json_decode( (string) $conversation->pending_context, true );
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$step = isset( $ctx['step'] ) ? $ctx['step'] : '';
	$then = isset( $ctx['then'] ) ? $ctx['then'] : null;
	$text = trim( (string) $message_text );

	if ( in_array( strtolower( $text ), array( 'stop', 'cancel', 'exit', 'quit', 'batal' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "No problem, I've cancelled that. 👍" );
		return '';
	}

	if ( 'await_email' === $step ) {
		$email = sanitize_email( $text );
		if ( ! is_email( $email ) || false !== stripos( $email, '@mfa.com' ) ) {
			nwa_send_message( $user_id, $wa_number,
				"That doesn't look like a valid email. Please send a real email address (for example you@example.com)." . niz_wa_dir_stop_hint() );
			return '';
		}
		$code = (string) random_int( 100000, 999999 );
		niz_wa_send_email_code( $email, $code );
		$new = array( 'step' => 'await_code', 'email' => $email, 'code_hash' => wp_hash( $code ), 'attempts' => 0 );
		if ( null !== $then ) {
			$new['then'] = $then;
		}
		NWA_DB::set_pending_action( $conversation->id, 'account_flow', $new, 20 );
		nwa_send_message( $user_id, $wa_number,
			"📧 I've sent a 6-digit code to *{$email}*.\n\nPlease enter the code here to confirm the email is yours." . niz_wa_dir_stop_hint() );
		return '';
	}

	if ( 'await_code' === $step ) {
		$email     = isset( $ctx['email'] ) ? $ctx['email'] : '';
		$code_hash = isset( $ctx['code_hash'] ) ? $ctx['code_hash'] : '';
		$attempts  = isset( $ctx['attempts'] ) ? (int) $ctx['attempts'] : 0;
		$entered   = preg_replace( '/\D/', '', $text );

		if ( '' === $email || '' === $code_hash ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number, "Something went wrong. Please send *register* to start again." );
			return '';
		}

		if ( strlen( $entered ) !== 6 || ! hash_equals( $code_hash, wp_hash( $entered ) ) ) {
			$attempts++;
			if ( $attempts >= 4 ) {
				NWA_DB::set_pending_action( $conversation->id, null );
				nwa_send_message( $user_id, $wa_number, "That code wasn't right. Let's start over — send *register* when you're ready." );
				return '';
			}
			$new = array( 'step' => 'await_code', 'email' => $email, 'code_hash' => $code_hash, 'attempts' => $attempts );
			if ( null !== $then ) {
				$new['then'] = $then;
			}
			NWA_DB::set_pending_action( $conversation->id, 'account_flow', $new, 20 );
			nwa_send_message( $user_id, $wa_number,
				"That code doesn't match. Please check your email and enter the 6-digit code again." . niz_wa_dir_stop_hint() );
			return '';
		}

		return niz_wa_account_apply_email( $user_id, $wa_number, $conversation, $email, $then );
	}

	if ( 'await_link_confirm' === $step ) {
		$target = isset( $ctx['target'] ) ? (int) $ctx['target'] : 0;
		$email  = isset( $ctx['email'] ) ? $ctx['email'] : '';
		$t      = strtolower( $text );

		if ( false !== strpos( $t, 'yes' ) ) {
			return niz_wa_account_link_to( $user_id, $wa_number, $conversation, $target, $then );
		}
		if ( false !== strpos( $t, 'no' ) ) {
			$new = array( 'step' => 'await_email' );
			if ( null !== $then ) {
				$new['then'] = $then;
			}
			NWA_DB::set_pending_action( $conversation->id, 'account_flow', $new, 20 );
			nwa_send_message( $user_id, $wa_number,
				"No problem. Please send a different *email address* to use." . niz_wa_dir_stop_hint() );
			return '';
		}
		nwa_send_message( $user_id, $wa_number, "Please tap *Yes, link it* or *No*." . niz_wa_dir_stop_hint() );
		return '';
	}

	return '';
}

/**
 * Applies a verified email: sets up the account when the email is free or
 * already this account's, or offers to link when it belongs to another.
 */
function niz_wa_account_apply_email( $user_id, $wa_number, $conversation, $email, $then = null ) {
	$existing = get_user_by( 'email', $email );

	if ( ! $existing || (int) $existing->ID === (int) $user_id ) {
		if ( ! $existing ) {
			wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
		}
		$user = get_userdata( $user_id );
		$name = $user ? trim( (string) $user->first_name ) : '';
		if ( '' === $name || 0 === strpos( $name, 'Prospect ' ) ) {
			$name = ( $user && $user->display_name && 0 !== strpos( $user->display_name, 'Prospect ' ) ) ? $user->display_name : 'Member';
		}
		if ( function_exists( 'niz_user_complete_registration' ) ) {
			niz_user_complete_registration( $user_id, array( 'name' => $name, 'email' => $email, 'route' => 'whatsapp' ) );
		} else {
			update_user_meta( $user_id, 'user_status', 'member' );
		}
		update_user_meta( $user_id, 'niz_whatsapp_verified', 'Yes' );

		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_account_finish( $user_id, $wa_number, "✅ You're all set — welcome to Masjid4All! 🎉", $then );
		return '';
	}

	$new = array( 'step' => 'await_link_confirm', 'email' => $email, 'target' => (int) $existing->ID );
	if ( null !== $then ) {
		$new['then'] = $then;
	}
	NWA_DB::set_pending_action( $conversation->id, 'account_flow', $new, 20 );
	nwa_send_buttons( $user_id, $wa_number,
		"This email is already registered to a Masjid4All account (" . niz_wa_mask_email( $email ) . ").\n\nWould you like to link your WhatsApp number to that account?",
		array(
			array( 'id' => 'acct_link_yes', 'title' => 'Yes, link it' ),
			array( 'id' => 'acct_link_no',  'title' => 'No' ),
		) );
	return '';
}

/**
 * Links this WhatsApp number to an existing account (merges the placeholder
 * contact into it, reusing the WhatsApp-verify merge), then finishes up.
 */
function niz_wa_account_link_to( $user_id, $wa_number, $conversation, $target_id, $then = null ) {
	$target = get_userdata( $target_id );
	if ( ! $target ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "Sorry, that account is no longer available. Please try again later." );
		return '';
	}

	NWA_DB::set_pending_action( $conversation->id, null );

	if ( function_exists( 'niz_wa_merge_prospect_into_verified_user' ) ) {
		niz_wa_merge_prospect_into_verified_user( (int) $user_id, (int) $target_id );
	}
	$phone = function_exists( 'niz_user_normalize_phone' ) ? niz_user_normalize_phone( $wa_number ) : preg_replace( '/\D/', '', $wa_number );
	update_user_meta( $target_id, 'user_phone', $phone );
	update_user_meta( $target_id, 'niz_whatsapp_verified', 'Yes' );

	// The conversation may have been reassigned to the target — send as target.
	niz_wa_account_finish( (int) $target_id, $wa_number, "✅ Your WhatsApp number is now linked to your Masjid4All account. 🎉", $then );
	return '';
}

/**
 * Runs the carried follow-up (a directory claim) if any, then sends the
 * one-time magic-login link. Used by every "account is ready" exit.
 */
function niz_wa_account_finish( $member_id, $wa_number, $intro, $then ) {
	$msg = $intro;

	if ( is_array( $then ) && isset( $then['claim'] ) && function_exists( 'niz_wa_web_do_claim' ) ) {
		$c      = $then['claim'];
		$result = niz_wa_web_do_claim( (int) $c['post_id'], (int) $member_id, isset( $c['dtype'] ) ? $c['dtype'] : 'business' );
		if ( in_array( $result, array( 'claimed', 'already_yours' ), true ) ) {
			$msg .= "\n\n🎉 I've also claimed *" . ( isset( $c['name'] ) ? $c['name'] : 'your listing' ) . "* for you.";
		}
	}

	$link = niz_wa_magic_login_url( (int) $member_id, '/member/' );
	$msg .= "\n\nTap to log in — no password needed (valid 20 minutes):\n{$link}";
	nwa_send_message( (int) $member_id, $wa_number, $msg );
}

function niz_wa_mask_email( $email ) {
	$parts  = explode( '@', (string) $email );
	$name   = $parts[0];
	$domain = isset( $parts[1] ) ? $parts[1] : '';
	if ( strlen( $name ) <= 2 ) {
		$masked = substr( $name, 0, 1 ) . '***';
	} else {
		$masked = substr( $name, 0, 2 ) . str_repeat( '*', max( 1, strlen( $name ) - 2 ) );
	}
	return $masked . ( '' !== $domain ? '@' . $domain : '' );
}

function niz_wa_send_email_code( $email, $code ) {
	$subject = 'Your Masjid4All verification code';
	$body    = "Assalamualaikum,\n\n"
		. "Your Masjid4All verification code is: {$code}\n\n"
		. "Enter this code in WhatsApp to confirm your email address. It expires in 20 minutes.\n\n"
		. "If you didn't request this, you can safely ignore this email.\n\n"
		. "— Masjid4All";
	wp_mail( sanitize_email( $email ), $subject, $body );
}

/* ---------------- One-time magic-login link (WhatsApp -> web) -----------------
   AUTH-SENSITIVE. Logs the WhatsApp user into /member without a password:
   160-bit random token, stored only as a SHA-256 hash in a 20-minute
   transient, single-use (burned on redemption), a session (non-persistent)
   auth cookie, and a same-site redirect only. Delivered over WhatsApp to the
   Meta-verified number, whose only real risk is being forwarded within 20
   minutes. */

function niz_wa_magic_login_url( $user_id, $redirect_path = '/member/' ) {
	$token = bin2hex( random_bytes( 20 ) );
	set_transient(
		'niz_wa_magic_' . hash( 'sha256', $token ),
		array( 'user_id' => (int) $user_id, 'redirect' => $redirect_path ),
		20 * MINUTE_IN_SECONDS
	);
	return add_query_arg( 'niz_wa_login', $token, home_url( '/' ) );
}

add_action( 'init', 'niz_wa_magic_login_handler' );

function niz_wa_magic_login_handler() {
	if ( empty( $_GET['niz_wa_login'] ) ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_GET['niz_wa_login'] ) );
	if ( ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
		return;
	}
	$key  = 'niz_wa_magic_' . hash( 'sha256', $token );
	$data = get_transient( $key );
	delete_transient( $key );

	$user = ( is_array( $data ) && ! empty( $data['user_id'] ) ) ? get_userdata( (int) $data['user_id'] ) : false;
	if ( ! $user ) {
		wp_safe_redirect( home_url( '/member/' ) );
		exit;
	}
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );
	$redirect = ( is_array( $data ) && ! empty( $data['redirect'] ) ) ? $data['redirect'] : '/member/';
	wp_safe_redirect( home_url( $redirect ) );
	exit;
}

function niz_wa_action_membership_price( $user_id, $context ) {
	$url = home_url( '/member/premium/' );
	return "Membership starts from RM19.90 per year.\n\nFor the latest pricing and to upgrade, visit:\n{$url}";
}

/* niz_wa_action_advertise() moved to sofia-leads.php on 2026-08-19. It used
   to return a link to home_url( '/advertise' ) - a page that does not exist
   (404 on production), so every advertising enquiry was handed a dead link.
   It is now a real lead-capture conversation. */

/**
 * Personal referral link, same ?id={user_id} format the member dashboard's
 * "Invite a Friend" modal now shows (member-account-modals.php) - the
 * capture side (affiliateid cookie -> referrer_id) already runs sitewide
 * via enaizi-mfa's niz_mfa_location_init(), so this just needs to hand the
 * link to whoever asks for it over WhatsApp. Uses home_url() like every
 * other action reply in this file (2026-08-14 - previously these
 * hardcoded an absolute masjid4all.com URL; switched to home_url() so
 * replies generated on staging correctly point to staging and replies
 * generated on production point to production, instead of every
 * environment always hardcoding one specific domain).
 *
 * UX polish (2026-08-10): NWA_Sender::send_message() runs every outgoing
 * message through format_for_whatsapp() unconditionally (converts
 * Markdown bold markers to WhatsApp's single-asterisk bold, not just for AI
 * replies) - confirmed before relying on *bold* below actually rendering.
 * Also includes a ready-to-forward invite line (same copy as the
 * dashboard modal's wa.me share text) wrapped in the link itself, so a
 * WhatsApp user can act in one copy/forward instead of having to write
 * their own invite message around a bare link.
 */
function niz_wa_action_share( $user_id, $context ) {
	$link   = home_url( '/?id=' . $user_id );
	$status = get_user_meta( $user_id, 'user_status', true );

	$invite_text = "Assalamualaikum! I'd like to invite you to join Masjid4All, a Muslim community platform with mosque directories, prayer times, and more: {$link}";

	$message  = "Here's your personal Masjid4All referral link:\n\n*{$link}*\n\n";
	$message .= "Anyone who joins through it earns you Barakah points. Want to invite someone right now? Just forward this message to them:\n\n";
	$message .= "_{$invite_text}_";

	if ( ! in_array( $status, array( 'member', 'premium' ), true ) ) {
		$message .= "\n\nTip: reply *REGISTER* to become a member first so you can start earning Barakah points from your referrals.";
	}

	return $message;
}

/**
 * General inquiries/complaints/feedback, or anyone asking to reach a
 * human - points to the /contact-us/ form (2026-08-14), which now
 * writes into wp_jet_cct_contact_us and emails the sender a
 * confirmation (see mfa-core/includes/widgets/contact-form.php and
 * admin-inquiry-list.php/admin-inquiry-info.php for the admin side).
 * Uses home_url(), not a hardcoded domain - see niz_wa_action_share()'s
 * docblock above for why every action reply in this file now does this.
 *
 * AI-composed acknowledgment added 2026-08-14: a plain canned reply
 * ('For any inquiries... please visit: [link]') ignored what the user
 * actually asked - user feedback was 'answer what the user want' rather
 * than just dropping a link. NWA_Router now threads the original
 * message text through $context['message_text'] (see
 * class-nwa-router.php - start_or_run_action()/execute_action()) so
 * this callback can ask the AI (NWA_AI::call_ai(), made public for
 * this) for a two-line reply (acknowledgment + closing phrase, both
 * in the user's own language - the closing line was still hardcoded
 * English until a follow-up fix the same day), then appends the real
 * home_url()-based link itself on its own line rather than trusting
 * the AI to reproduce a URL correctly. Falls back to the original
 * canned reply if the AI call fails or no message text is available
 * (e.g. triggered some other way than normal routing).
 */
function niz_wa_action_inquiry( $user_id, $context ) {
	return niz_wa_contact_start( $user_id, $context );
}

/* ---------------- Contact-Us flow ----------------
   Sofia now takes the user's message to the team in-chat instead of handing
   out the /contact-us/ URL (changed 2026-08-15). Primary path (added
   2026-08-15) is a native WhatsApp Flow — MFA_CONTACT_FLOW_ID is Meta's
   published id for the "Masjid4All - Contact Us" flow (single screen, id
   CONTACT_FORM, fields name/email/subject/message) — so the user fills a
   real in-chat form instead of answering one question at a time. If sending
   the flow message fails for any reason (outside the 24h window, API error,
   flow not configured), niz_wa_contact_start() falls back to the older
   multi-step text conversation below (name -> email -> subject -> message ->
   review -> send), which stays fully intact as a safety net. Name/email are
   pre-offered from the member's record when known in the fallback path (a
   real email is anything not ending in @mfa.com, the WhatsApp placeholder
   domain). The fallback's in-progress step lives in niz-wa's own
   pending_action/pending_context columns (intent_key 'contact_flow');
   niz_wa_contact_route() claims every message while that session is live.
   Both paths end at the shared mfa_contact_us_store() (contact-form.php) so
   WhatsApp and the web form write identical wp_jet_cct_contact_us rows +
   notifications. Phone is not asked - it is the user's WhatsApp number. */

// Meta's published id for the single-screen "Masjid4All - Contact Us" flow
// (WABA 27070199045967929 - the one actually behind niz-wa's sending number,
// +60 18-989 7579 - screen id CONTACT_FORM). Not a secret, just this WABA's
// resource id - see the flow-message note above. A flow created against the
// wrong WABA (119349044605868, Meta's default "Test" WABA) sends but every
// send 400s with error 131009 ("flow_id is invalid... doesn't belong to your
// WhatsApp Business Account") - confirmed live 2026-08-15, don't reuse that
// id.
define( 'MFA_CONTACT_FLOW_ID', '1673813523692341' );

// Priority 5: runs before every other stateful session override, since its
// guard (message_text must decode to JSON containing an nfm_reply key) is
// narrow enough that it can never misfire on a plain-text reply meant for
// another flow.
add_filter( 'nwa_route_message_override', 'niz_wa_contact_flow_reply_route', 5, 5 );

/**
 * Claims the completed-form reply from the native WhatsApp Flow sent by
 * niz_wa_contact_start(). Meta delivers this as a normal inbound message
 * whose content (already JSON-encoded by NWA_Webhook::extract_content(),
 * since it isn't a button_reply/list_reply) is the raw 'interactive' object;
 * nfm_reply.response_json inside it holds the submitted field values. Only
 * one flow exists today, so no flow_token/name matching is needed to tell
 * flows apart - add that if a second Flow (e.g. Faraid) goes live.
 */
function niz_wa_contact_flow_reply_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override;
	}

	$interactive = json_decode( (string) $message_text, true );
	if ( ! is_array( $interactive ) || ! isset( $interactive['nfm_reply']['response_json'] ) ) {
		return $override;
	}

	$fields = json_decode( (string) $interactive['nfm_reply']['response_json'], true );
	$fields = is_array( $fields ) ? $fields : array();

	$name    = sanitize_text_field( $fields['name'] ?? '' );
	$email   = sanitize_email( $fields['email'] ?? '' );
	$subject = sanitize_text_field( $fields['subject'] ?? '' );
	$message = sanitize_textarea_field( $fields['message'] ?? '' );

	if ( '' === $name || '' === $email || ! is_email( $email ) || '' === $subject || '' === $message ) {
		return "Sorry, I couldn't read that submission properly. Please try again, or message *contact* to restart.";
	}

	$stored = function_exists( 'mfa_contact_us_store' )
		? mfa_contact_us_store( $name, $email, $wa_number, $subject, $message, (int) $user_id )
		: false;

	if ( $stored ) {
		return "✅ Sent! Our team has your message and will get back to you soon, In sha Allah.\n\nJazakAllah khair for reaching out. 🤲";
	}

	return "Sorry, something went wrong saving your message. Please try again later, or use " . home_url( '/contact-us/' ) . ".";
}

// Priority 25: runs after whatsapp-verify (10), account (15) and directory
// (20) overrides, so their sessions/codes always win if somehow both open.
add_filter( 'nwa_route_message_override', 'niz_wa_contact_route', 25, 5 );

/** A display name we can offer to reuse, or '' if we don't have a usable one. */
function niz_wa_contact_known_name( $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return '';
	}
	$name = trim( (string) $u->display_name );
	if ( '' === $name || 0 === strpos( $name, 'mfa_' ) ) {
		$name = trim( (string) $u->first_name );
	}
	// Reject empty or a bare phone number (WhatsApp default names are often the number).
	$digits_only = preg_replace( '/[\s+\-]/', '', $name );
	if ( '' === $name || ( '' !== $digits_only && ctype_digit( $digits_only ) ) ) {
		return '';
	}
	return $name;
}

/** A real (non-placeholder) email we can offer to reuse, or '' if none. */
function niz_wa_contact_known_email( $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return '';
	}
	$email = trim( (string) $u->user_email );
	if ( '' === $email || ! is_email( $email ) ) {
		return '';
	}
	// WhatsApp-created accounts get a <phone>@mfa.com placeholder they can't use.
	if ( preg_match( '/@mfa\.com$/i', $email ) ) {
		return '';
	}
	return $email;
}

/** Did the user reply with a short affirmative ("ok", "yes", "use it", ...)? */
function niz_wa_contact_is_affirmative( $text ) {
	$t = strtolower( trim( $text ) );
	return in_array( $t, array( 'ok', 'okay', 'yes', 'yes please', 'ya', 'yup', 'yep', 'y', 'betul', 'use it', 'ok use it' ), true );
}

/**
 * Entry point for the 'inquiry' action. Tries the native WhatsApp Flow form
 * first; if that send fails (outside the 24h window, API error, etc.) falls
 * back to the step-by-step text conversation below. Either way sends its own
 * message(s) and returns '' so NWA_Router sends nothing further.
 */
function niz_wa_contact_start( $user_id, $context ) {
	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation ) {
		// Fallback to the old URL reply if we somehow have no conversation row.
		return "I'd love to pass your message to our team. Please use our contact form:\n" . home_url( '/contact-us/' );
	}

	$wa_number = $conversation->wa_number;

	if ( function_exists( 'nwa_send_flow' ) ) {
		$sent = nwa_send_flow(
			$user_id,
			$wa_number,
			"I'd be glad to pass your message to the Masjid4All team. 📝\n\nJust fill in the short form below and tap Submit — you can cancel anytime.",
			MFA_CONTACT_FLOW_ID,
			'Contact Us',
			'CONTACT_FORM'
		);
		if ( ! empty( $sent['success'] ) ) {
			return '';
		}
	}

	$known_name = niz_wa_contact_known_name( $user_id );

	$intro = "I'd be glad to pass your message to the Masjid4All team. 📝\n\nLet's put it together — you can reply *stop* anytime to cancel.\n\n";

	if ( '' !== $known_name ) {
		nwa_send_message( $user_id, $wa_number,
			$intro . "First, your *name* — I have it as *{$known_name}*.\nReply *OK* to use it, or type a different name." );
	} else {
		nwa_send_message( $user_id, $wa_number, $intro . "First, what's your *name*?" );
	}

	NWA_DB::set_pending_action( $conversation->id, 'contact_flow', array( 'step' => 'name', 'known_name' => $known_name ), 30 );
	return '';
}

/**
 * Drives the Contact-Us steps while a 'contact_flow' session is active.
 * Returns '' to claim the message (we send our own replies), or the
 * unchanged $override so normal routing continues when no session is live.
 */
function niz_wa_contact_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override;
	}
	if ( ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}
	if ( 'contact_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		return $override;
	}

	$ctx  = json_decode( (string) $conversation->pending_context, true );
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$step = isset( $ctx['step'] ) ? $ctx['step'] : '';
	$text = trim( (string) $message_text );

	// Escape hatch at any step (exact match so a real subject/message never trips it).
	if ( in_array( strtolower( $text ), array( 'stop', 'cancel', 'exit', 'quit', 'batal' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "No problem, I've cancelled that. 👍\n\nMessage *contact* anytime to reach our team." );
		return '';
	}

	if ( 'name' === $step ) {
		$known = isset( $ctx['known_name'] ) ? (string) $ctx['known_name'] : '';
		$name  = ( '' !== $known && niz_wa_contact_is_affirmative( $text ) ) ? $known : sanitize_text_field( $text );
		if ( '' === $name ) {
			nwa_send_message( $user_id, $wa_number, "Please type your *name* so we know who to reply to." );
			return '';
		}

		$known_email = niz_wa_contact_known_email( $user_id );
		NWA_DB::set_pending_action( $conversation->id, 'contact_flow',
			array( 'step' => 'email', 'name' => $name, 'known_email' => $known_email ), 30 );

		if ( '' !== $known_email ) {
			nwa_send_message( $user_id, $wa_number,
				"Thanks, *{$name}*! What *email* should we reply to?\nI have *{$known_email}* — reply *OK* to use it, or type another." );
		} else {
			nwa_send_message( $user_id, $wa_number, "Thanks, *{$name}*! What's your *email address*?" );
		}
		return '';
	}

	if ( 'email' === $step ) {
		$known = isset( $ctx['known_email'] ) ? (string) $ctx['known_email'] : '';
		$email = ( '' !== $known && niz_wa_contact_is_affirmative( $text ) ) ? $known : sanitize_email( $text );
		if ( '' === $email || ! is_email( $email ) ) {
			nwa_send_message( $user_id, $wa_number, "That doesn't look like a valid email. Please type your *email address*." );
			return '';
		}

		$ctx['step']  = 'subject';
		$ctx['email'] = $email;
		unset( $ctx['known_email'] );
		NWA_DB::set_pending_action( $conversation->id, 'contact_flow', $ctx, 30 );
		nwa_send_message( $user_id, $wa_number, "Got it. What's the *subject*? (a few words on what it's about)" );
		return '';
	}

	if ( 'subject' === $step ) {
		$subject = sanitize_text_field( $text );
		if ( '' === $subject ) {
			nwa_send_message( $user_id, $wa_number, "Please type a short *subject* for your message." );
			return '';
		}
		$ctx['step']    = 'message';
		$ctx['subject'] = $subject;
		NWA_DB::set_pending_action( $conversation->id, 'contact_flow', $ctx, 30 );
		nwa_send_message( $user_id, $wa_number, "And finally, please type your *message*." );
		return '';
	}

	if ( 'message' === $step ) {
		$message = sanitize_textarea_field( $text );
		if ( '' === $message ) {
			nwa_send_message( $user_id, $wa_number, "Please type the *message* you'd like to send to our team." );
			return '';
		}
		$ctx['step']    = 'review';
		$ctx['message'] = $message;
		NWA_DB::set_pending_action( $conversation->id, 'contact_flow', $ctx, 30 );

		$summary = "Please review 👇\n\n"
			. "*Name:* {$ctx['name']}\n"
			. "*Email:* {$ctx['email']}\n"
			. "*Subject:* {$ctx['subject']}\n"
			. "*Message:* {$message}\n\n"
			. "Send this to our team?";
		nwa_send_buttons( $user_id, $wa_number, $summary, array(
			array( 'id' => 'contact_send',   'title' => 'Send' ),
			array( 'id' => 'contact_cancel', 'title' => 'Cancel' ),
		) );
		return '';
	}

	if ( 'review' === $step ) {
		$t = strtolower( $text );
		if ( false !== strpos( $t, 'cancel' ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number, "No problem, I've cancelled that. 👍\n\nMessage *contact* anytime to reach our team." );
			return '';
		}
		if ( false !== strpos( $t, 'send' ) ) {
			$name    = isset( $ctx['name'] ) ? $ctx['name'] : '';
			$email   = isset( $ctx['email'] ) ? $ctx['email'] : '';
			$subject = isset( $ctx['subject'] ) ? $ctx['subject'] : '';
			$message = isset( $ctx['message'] ) ? $ctx['message'] : '';

			$stored = function_exists( 'mfa_contact_us_store' )
				? mfa_contact_us_store( $name, $email, $wa_number, $subject, $message, (int) $user_id )
				: false;

			NWA_DB::set_pending_action( $conversation->id, null );

			if ( $stored ) {
				nwa_send_message( $user_id, $wa_number, "✅ Sent! Our team has your message and will get back to you soon, In sha Allah.\n\nJazakAllah khair for reaching out. 🤲" );
			} else {
				nwa_send_message( $user_id, $wa_number, "Sorry, something went wrong saving your message. Please try again later, or use " . home_url( '/contact-us/' ) . "." );
			}
			return '';
		}

		nwa_send_message( $user_id, $wa_number, "Please tap *Send* to submit, or *Cancel* to discard." );
		return '';
	}

	// Unknown step — reset gracefully so the next message routes normally.
	NWA_DB::set_pending_action( $conversation->id, null );
	return $override;
}

/* ---------------- Directory listing flow (multi-step) ----------------
   "Add my mosque/business/website" isn't single-shot like the actions above:
   it's a short conversation — an intro with three tappable buttons, then
   "paste the link", then an acknowledgment. The in-progress step lives in
   niz-wa's own pending_action/pending_context columns (intent_key
   'directory_flow'); niz_wa_directory_route() claims every message while the
   session is live, so it never reaches niz-wa's generic Yes/No pending
   handler. Entry is the ordinary seeded 'directory' action below.

   Phase 1 (this build) only captures the submitted link and acknowledges —
   actually creating the directory record from it is phase 2. */

// Priority 20: runs AFTER whatsapp-verify's override (priority 10) so a
// VERIFY-XXXX code always wins, even if a directory session is open.
add_filter( 'nwa_route_message_override', 'niz_wa_directory_route', 20, 5 );

/**
 * Entry point for the seeded 'directory' action. Sends the intro + three
 * reply buttons and opens the directory session. Buttons can't be expressed
 * as a plain return string, so this sends its own interactive message and
 * returns '' to tell NWA_Router there's nothing further to send.
 */
function niz_wa_action_directory( $user_id, $context ) {
	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation ) {
		return "You can add your mosque, business, or website to the Masjid4All directory for free. Please try again in a moment.";
	}

	// If the triggering message already names a type — "add mosque",
	// "claim my website", etc. (the directory pages' Add buttons deep-link to
	// WhatsApp with exactly these) — skip the menu and jump straight into that
	// branch's link prompt. Only an untyped entry ("directory", "add listing")
	// falls through to the three-button menu below.
	$message_text = isset( $context['message_text'] ) ? $context['message_text'] : '';
	$type         = niz_wa_dir_detect_choice( $message_text );

	if ( '' !== $type ) {
		return niz_wa_dir_start_branch( $user_id, $conversation->wa_number, $conversation, $type );
	}

	$body = "🕌 *Masjid4All Directory*\n\n"
		. "You can add any of these to Masjid4All for *free*:\n\n"
		. "🕌 *Mosque* — so the community can find its prayer times & location\n"
		. "🏪 *Business* — list your halal-friendly business for Muslims to discover\n"
		. "🌐 *Website* — share a useful Islamic website or resource\n\n"
		. "Which one would you like to add?";

	$buttons = array(
		array( 'id' => 'dir_mosque',   'title' => 'Add Mosque' ),
		array( 'id' => 'dir_business', 'title' => 'Add Business' ),
		array( 'id' => 'dir_website',  'title' => 'Add Website' ),
	);

	nwa_send_buttons( $user_id, $conversation->wa_number, $body, $buttons );
	NWA_DB::set_pending_action( $conversation->id, 'directory_flow', array( 'step' => 'await_choice' ), 30 );

	return '';
}

/**
 * Drives the remaining directory steps while a 'directory_flow' session is
 * active. Returns '' to claim the message (we send our own replies), or the
 * unchanged $override so normal routing continues when no session is live.
 */
function niz_wa_directory_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override; // another handler (e.g. WhatsApp verify) already claimed this.
	}

	if ( ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}

	// get_active_pending_action() auto-clears the session once its TTL passes.
	if ( 'directory_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		// Not mid-flow — but catch the "Add Another …" completion buttons.
		$another = niz_wa_dir_detect_another( $message_text );
		if ( '' !== $another ) {
			return niz_wa_dir_start_branch( $user_id, $wa_number, $conversation, $another );
		}
		return $override;
	}

	$ctx  = json_decode( (string) $conversation->pending_context, true );
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$step = isset( $ctx['step'] ) ? $ctx['step'] : '';
	$text = trim( (string) $message_text );

	// Escape hatch: let the user bail out of the flow at any step. Exact match
	// (not substring) so a real URL or listing name never trips it.
	if ( in_array( strtolower( $text ), array( 'stop', 'cancel', 'exit', 'quit', 'batal' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number,
			"No problem, I've cancelled that. 👍\n\nSend *directory* anytime to start again, or just tell me what you need." );
		return '';
	}

	if ( 'await_link' === $step ) {
		$type = isset( $ctx['type'] ) ? $ctx['type'] : 'listing';

		if ( ! niz_wa_dir_looks_like_link( $text ) ) {
			nwa_send_message( $user_id, $wa_number,
				"Hmm, that didn't look like a link. " . niz_wa_dir_link_prompt( $type ) );
			return '';
		}

		// Website branch: already listed -> show claim status; otherwise
		// create the listing + single post now and hand back the page link
		// (content is generated on that page, not in this webhook).
		if ( 'website' === $type ) {
			$match = niz_wa_web_find_by_url( $text );
			if ( $match ) {
				return niz_wa_web_present_listing( $user_id, $wa_number, $conversation, $match );
			}
			return niz_wa_web_add_new( $user_id, $wa_number, $conversation, $text );
		}

		// mosque/business: resolve the Google Maps link -> Serper -> confirm -> create.
		return niz_wa_place_add_from_link( $user_id, $wa_number, $conversation, $type, $text );
	}

	if ( 'website_listed' === $step ) {
		$url     = isset( $ctx['url'] ) ? $ctx['url'] : '';
		$name    = isset( $ctx['name'] ) ? $ctx['name'] : 'this website';
		$post_id = isset( $ctx['post_id'] ) ? (int) $ctx['post_id'] : 0;
		$t       = strtolower( $text );

		if ( false !== strpos( $t, 'visit' ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number, "🔗 " . $url );
			return '';
		}

		if ( false !== strpos( $t, 'claim' ) ) {
			$status = get_user_meta( $user_id, 'user_status', true );

			// Registered members claim immediately; a prospect / unregistered
			// contact must register first (the gate the user asked for).
			if ( in_array( $status, array( 'member', 'premium' ), true ) ) {
				$result = niz_wa_web_do_claim( $post_id, $user_id );
				NWA_DB::set_pending_action( $conversation->id, null );
				niz_wa_dir_send_done( $user_id, $wa_number, niz_wa_web_claim_result_message( $result, $name, $post_id ) );
				return '';
			}

			nwa_send_buttons( $user_id, $wa_number,
				"To claim *{$name}*, you'll need a free Masjid4All membership first.\n\nWould you like to register now?",
				array(
					array( 'id' => 'dir_web_register', 'title' => 'Register as member' ),
					array( 'id' => 'dir_web_cancel',   'title' => 'Cancel' ),
				) );
			NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
				array( 'step' => 'claim_need_register', 'url' => $url, 'name' => $name, 'post_id' => $post_id, 'dtype' => 'web' ), 30 );
			return '';
		}

		nwa_send_message( $user_id, $wa_number,
			"Please tap *Visit Website* or *Claim this website* to continue."
			. niz_wa_dir_stop_hint() );
		return '';
	}

	if ( 'claim_need_register' === $step ) {
		$name    = isset( $ctx['name'] ) ? $ctx['name'] : 'this listing';
		$post_id = isset( $ctx['post_id'] ) ? (int) $ctx['post_id'] : 0;
		$dtype   = isset( $ctx['dtype'] ) ? $ctx['dtype'] : 'web';
		$t       = strtolower( $text );

		// 'Cancel' is already handled by the global stop-word check above.
		if ( false !== strpos( $t, 'register' ) ) {
			// Hand off to the account-setup flow (email + code), carrying the
			// claim so it's applied automatically once they're a member.
			return niz_wa_account_start( $user_id, array(
				'claim' => array( 'post_id' => $post_id, 'dtype' => $dtype, 'name' => $name ),
			) );
		}

		nwa_send_message( $user_id, $wa_number,
			"Please tap *Register as member* to continue, or *Cancel* to stop." );
		return '';
	}

	if ( 'business_listed' === $step ) {
		$name    = isset( $ctx['name'] ) ? $ctx['name'] : 'this business';
		$post_id = isset( $ctx['post_id'] ) ? (int) $ctx['post_id'] : 0;
		$t       = strtolower( $text );

		if ( false !== strpos( $t, 'claim' ) ) {
			$status = get_user_meta( $user_id, 'user_status', true );

			if ( in_array( $status, array( 'member', 'premium' ), true ) ) {
				$result = niz_wa_web_do_claim( $post_id, $user_id, 'business' );
				NWA_DB::set_pending_action( $conversation->id, null );
				niz_wa_dir_send_done( $user_id, $wa_number, niz_wa_web_claim_result_message( $result, $name, $post_id ) );
				return '';
			}

			nwa_send_buttons( $user_id, $wa_number,
				"To claim *{$name}*, you'll need a free Masjid4All membership first.\n\nWould you like to register now?",
				array(
					array( 'id' => 'dir_biz_register', 'title' => 'Register as member' ),
					array( 'id' => 'dir_biz_cancel',   'title' => 'Cancel' ),
				) );
			NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
				array( 'step' => 'claim_need_register', 'name' => $name, 'post_id' => $post_id, 'dtype' => 'business' ), 30 );
			return '';
		}

		nwa_send_message( $user_id, $wa_number,
			"Please tap *Claim this business* to continue." . niz_wa_dir_stop_hint() );
		return '';
	}

	if ( 'await_place_confirm' === $step ) {
		$ptype = isset( $ctx['type'] ) ? $ctx['type'] : 'mosque';
		$place = ( isset( $ctx['place'] ) && is_array( $ctx['place'] ) ) ? $ctx['place'] : null;
		$name  = $place['title'] ?? 'this place';
		$t     = strtolower( $text );

		if ( false !== strpos( $t, 'yes' ) || false !== strpos( $t, 'add' ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );

			if ( ! $place || ! function_exists( 'mfa_geohash_upsert_place' ) ) {
				nwa_send_message( $user_id, $wa_number, "Sorry, something went wrong adding *{$name}*. Please try again." );
				return '';
			}

			$result = mfa_geohash_upsert_place( $ptype, $place );
			$link   = niz_wa_place_page_url( $ptype, isset( $place['placeId'] ) ? $place['placeId'] : '' );
			$label  = 'business' === $ptype ? 'business' : 'mosque';

			if ( in_array( $result, array( 'new', 'existing' ), true ) && $link ) {
				niz_wa_dir_send_done( $user_id, $wa_number,
					"✅ *{$name}* has been added to the Masjid4All {$label} directory!\n\n"
					. "Tap the link below to generate its full details and publish the listing:\n{$link}\n\n"
					. "Once it's live, you can claim it to manage and update the info." );
			} else {
				nwa_send_message( $user_id, $wa_number, "Sorry, I couldn't add *{$name}* right now. Please try again later." );
			}
			return '';
		}

		if ( false !== strpos( $t, 'no' ) ) {
			NWA_DB::set_pending_action( $conversation->id, 'directory_flow', array( 'step' => 'await_link', 'type' => $ptype ), 30 );
			nwa_send_message( $user_id, $wa_number,
				"No problem — paste a different Google Maps link, or type *stop* to cancel." );
			return '';
		}

		nwa_send_message( $user_id, $wa_number, "Please tap *Yes, add it* or *No*." . niz_wa_dir_stop_hint() );
		return '';
	}

	// Default: awaiting a button choice (or a typed equivalent).
	$choice = niz_wa_dir_detect_choice( $text );

	if ( ! $choice ) {
		nwa_send_message( $user_id, $wa_number,
			"Please tap one of the buttons above — *Add Mosque*, *Add Business*, or *Add Website* — to continue."
			. niz_wa_dir_stop_hint() );
		return '';
	}

	return niz_wa_dir_start_branch( $user_id, $wa_number, $conversation, $choice );
}

/* ---- Directory flow helpers ---- */

function niz_wa_dir_detect_choice( $text ) {
	$t = strtolower( $text );
	if ( false !== strpos( $t, 'mosque' ) || false !== strpos( $t, 'masjid' ) || false !== strpos( $t, 'surau' ) ) {
		return 'mosque';
	}
	if ( false !== strpos( $t, 'business' ) || false !== strpos( $t, 'bisnes' ) || false !== strpos( $t, 'kedai' ) ) {
		return 'business';
	}
	if ( false !== strpos( $t, 'website' ) || false !== strpos( $t, 'web' ) || false !== strpos( $t, 'url' ) || false !== strpos( $t, 'site' ) ) {
		return 'website';
	}
	return '';
}

function niz_wa_dir_stop_hint() {
	return "\n\n_Or type *stop* to cancel._";
}

/**
 * "Add Another …" buttons shown at the end of a completed flow to nudge the
 * user to list more. Titles are caught by niz_wa_dir_detect_another() in the
 * override handler, so they route even with no active session.
 */
function niz_wa_dir_another_buttons() {
	return array(
		array( 'id' => 'dir_more_mosque',   'title' => 'Add Another Mosque' ),
		array( 'id' => 'dir_more_business', 'title' => 'Add Another Business' ),
		array( 'id' => 'dir_more_website',  'title' => 'Add Another Website' ),
	);
}

/**
 * Sends a flow-completion message together with the "Add Another …" buttons.
 * Use this instead of nwa_send_message() at any terminal point of the flow.
 */
function niz_wa_dir_send_done( $user_id, $wa_number, $message ) {
	nwa_send_buttons( $user_id, $wa_number,
		$message . "\n\nWould you like to add another listing to Masjid4All?",
		niz_wa_dir_another_buttons() );
}

/**
 * Detects an "Add Another Mosque/Business/Website" tap -> its type, else ''.
 */
function niz_wa_dir_detect_another( $text ) {
	if ( false === strpos( strtolower( (string) $text ), 'another' ) ) {
		return '';
	}
	return niz_wa_dir_detect_choice( $text );
}

function niz_wa_dir_link_prompt( $type ) {
	if ( 'website' === $type ) {
		return "🌐 *Add Website*\n\nPlease send the *website URL* you'd like to list.\nExample: https://example.com"
			. niz_wa_dir_stop_hint();
	}

	$label = 'business' === $type ? 'business' : 'mosque';
	return "📍 *Add " . ucfirst( $label ) . "*\n\n"
		. "Please send the *Google Maps link* of your {$label}:\n\n"
		. "1. Open Google Maps\n"
		. "2. Search for your {$label}\n"
		. "3. Tap *Share* and copy the link\n"
		. "4. Paste the link here"
		. niz_wa_dir_stop_hint();
}

function niz_wa_dir_ack( $type ) {
	$label = 'website' === $type ? 'website' : ( 'business' === $type ? 'business' : 'mosque' );
	return "Thank you! ✅ We've received your {$label} details.\n\n"
		. "Please give us a little time — our team will review and list it on Masjid4All, and I'll update you here once it's done. 🤲";
}

/**
 * Loose sanity check so obvious non-links (a greeting, a name) re-prompt
 * instead of being stored as a "link". Accepts anything with an http(s)
 * scheme, or a bare domain / Maps short link pasted without one.
 */
function niz_wa_dir_looks_like_link( $text ) {
	if ( false !== stripos( $text, 'http' ) ) {
		return true;
	}
	return (bool) preg_match( '#[a-z0-9.\-]+\.[a-z]{2,}(/|$|\s)#i', $text );
}

/**
 * Looks up a website in the directory (wp_jet_cct_web) by normalised host
 * (scheme- and www-insensitive, reusing website-extract.php's normaliser).
 * Returns array( id, name, url, post_id ) on a host match, or null.
 */
function niz_wa_web_find_by_url( $url ) {
	global $wpdb;
	if ( ! function_exists( 'mfa_web_extract_normalize_host' ) ) {
		return null;
	}
	$host = mfa_web_extract_normalize_host( $url );
	if ( '' === $host ) {
		return null;
	}
	$w    = $wpdb->prefix . 'jet_cct_web';
	$like = '%' . $wpdb->esc_like( $host ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT _ID, name, url, cct_single_post_id, listing_status FROM {$w} WHERE url LIKE %s LIMIT 50",
		$like
	), ARRAY_A );
	foreach ( (array) $rows as $row ) {
		if ( mfa_web_extract_normalize_host( $row['url'] ) === $host ) {
			return array(
				'id'      => (int) $row['_ID'],
				'name'    => $row['name'] ? $row['name'] : $row['url'],
				'url'     => $row['url'],
				'post_id' => (int) $row['cct_single_post_id'],
				'status'  => (string) $row['listing_status'],
			);
		}
	}
	return null;
}

/**
 * user_id that owns/claimed a listing post (wp_jet_cct_listing_owner), or 0
 * if unclaimed. Same table + semantics as the single-post Claim Website
 * (mfa_claim_web_listing_shortcode / mfa_website_user_can_manage).
 */
function niz_wa_web_claim_owner( $post_id ) {
	global $wpdb;
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 0;
	}
	$o = $wpdb->prefix . 'jet_cct_listing_owner';
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$o} WHERE post_id = %d LIMIT 1", $post_id ) );
}

/**
 * Claims a website listing for a user by inserting into
 * wp_jet_cct_listing_owner (mirrors mfa_claim_web_listing_shortcode()'s
 * insert). Returns 'claimed', 'already_yours', 'taken', or 'error'.
 */
function niz_wa_web_do_claim( $post_id, $user_id, $post_type = 'web' ) {
	global $wpdb;
	$post_id = (int) $post_id;
	$user_id = (int) $user_id;
	if ( $post_id <= 0 || $user_id <= 0 ) {
		return 'error';
	}

	$owner = niz_wa_web_claim_owner( $post_id );
	if ( $owner > 0 ) {
		return $owner === $user_id ? 'already_yours' : 'taken';
	}

	$o = $wpdb->prefix . 'jet_cct_listing_owner';
	$wpdb->insert( $o, array(
		'post_type'   => ( 'business' === $post_type ? 'business' : 'web' ),
		'post_id'     => $post_id,
		'user_id'     => $user_id,
		'cct_created' => current_time( 'mysql' ),
	), array( '%s', '%d', '%d', '%s' ) );

	return $wpdb->insert_id ? 'claimed' : 'error';
}

/**
 * Manage-listing URL for a claimed listing: /member/business/?id=<post_id>
 * (the post_id member-listing-single.php reads from ?id). Uses home_url() so
 * it resolves to the current environment, not a hardcoded domain.
 */
function niz_wa_web_manage_url( $post_id ) {
	return add_query_arg( 'id', (int) $post_id, home_url( '/member/business/' ) );
}

/**
 * Human-facing reply for a niz_wa_web_do_claim() result.
 */
function niz_wa_web_claim_result_message( $result, $name, $post_id ) {
	$manage = niz_wa_web_manage_url( $post_id );
	switch ( $result ) {
		case 'claimed':
			return "🎉 Done! You've claimed *{$name}*.\n\nYou can manage its listing here:\n{$manage}";
		case 'already_yours':
			return "You've already claimed *{$name}*. 🙂\n\nManage it here:\n{$manage}";
		case 'taken':
			return "Sorry — *{$name}* was just claimed by someone else. If you believe that's a mistake, reply here and our team will help.";
		default:
			return "Something went wrong claiming *{$name}*. Please try again later.";
	}
}

/**
 * Presents an already-listed website: claim status decides the message and
 * whether a Claim button is offered, and sets/clears the session as needed.
 * Always returns '' — the override handler claims the message here.
 */
function niz_wa_web_present_listing( $user_id, $wa_number, $conversation, $match ) {
	// Rejected / errored / removed listings aren't claimable — point the user
	// to support instead of offering Visit / Claim.
	$status = isset( $match['status'] ) ? $match['status'] : '';
	if ( in_array( strtolower( $status ), array( 'rejected', 'error', 'deleted' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_dir_send_done( $user_id, $wa_number,
			"The website *{$match['name']}* is listed, but its status is *{$status}*.\n\n"
			. "If you think this is an error, please contact us:\n" . home_url( '/contact-us/' ) );
		return '';
	}

	$owner = niz_wa_web_claim_owner( $match['post_id'] );

	if ( $owner === (int) $user_id ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_dir_send_done( $user_id, $wa_number,
			"✅ *{$match['name']}* is already listed — and you've already claimed it. 🎉\n\n🔗 {$match['url']}\n\nManage it here:\n" . niz_wa_web_manage_url( $match['post_id'] ) );
		return '';
	}

	if ( $owner > 0 ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_dir_send_done( $user_id, $wa_number,
			"✅ *{$match['name']}* is already listed in the Masjid4All directory, and it has already been claimed by its owner.\n\n🔗 {$match['url']}\n\nIf you believe that's a mistake, reply here and our team will help." );
		return '';
	}

	// Unclaimed — offer Visit / Claim.
	nwa_send_buttons( $user_id, $wa_number,
		"✅ *{$match['name']}* is already listed in the Masjid4All directory.\n\nIs this your website?",
		array(
			array( 'id' => 'dir_web_visit', 'title' => 'Visit Website' ),
			array( 'id' => 'dir_web_claim', 'title' => 'Claim this website' ),
		) );
	NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
		array( 'step' => 'website_listed', 'url' => $match['url'], 'name' => $match['name'], 'post_id' => $match['post_id'] ), 30 );
	return '';
}

/**
 * Not-yet-listed website: create the CCT record + `web` single post right away
 * (status New, no AI in this webhook) and reply with the page link, where the
 * existing "Update Content" button generates the article. Reuses the web
 * form's own helpers (add-website.php) and attributes the submission to the
 * WhatsApp user. Ends the session. Always returns ''.
 */
function niz_wa_web_add_new( $user_id, $wa_number, $conversation, $raw_url ) {
	// Normalise to an absolute URL (add a scheme for a bare domain).
	$raw = trim( (string) $raw_url );
	if ( ! preg_match( '#^https?://#i', $raw ) ) {
		$raw = 'https://' . $raw;
	}
	$url  = esc_url_raw( $raw );
	$host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';

	// Structural validation: a real domain has a host with a dot. Reject
	// junk without ending the session, so the user can just resend a good URL.
	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! $host || false === strpos( $host, '.' ) ) {
		nwa_send_message( $user_id, $wa_number,
			"That doesn't look like a valid website address. Please send the full URL, e.g. https://example.com" . niz_wa_dir_stop_hint() );
		return '';
	}

	if ( ! function_exists( 'mfa_insert_web_cct' ) || ! function_exists( 'mfa_get_remote_website_title' ) ) {
		niz_wa_dir_store_submission( $user_id, 'website', $raw_url );
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, niz_wa_dir_ack( 'website' ) );
		return '';
	}

	$base = function_exists( 'cct_trim_url_to_base' ) ? cct_trim_url_to_base( rtrim( $url, '/' ) ) : rtrim( $url, '/' );

	// Reachability + name in one fetch. A DNS/connection failure comes back as
	// "Error: ..." — reject rather than create a bogus listing (the web form
	// aborts on a fetch error too; the old domain-name fallback here was the
	// bug that let invalid URLs through and produced junk AI content).
	$name = mfa_get_remote_website_title( $base );
	if ( '' === trim( (string) $name ) || 0 === strpos( (string) $name, 'Error:' ) ) {
		nwa_send_message( $user_id, $wa_number,
			"I couldn't reach that website — please check the address and send a working link." . niz_wa_dir_stop_hint() );
		return '';
	}

	// Valid + reachable: create the record, attributed to the WhatsApp user
	// (mfa_insert_web_cct() reads the current user for cct_author_id/post_author).
	NWA_DB::set_pending_action( $conversation->id, null );

	$prev = get_current_user_id();
	wp_set_current_user( $user_id );
	$result = mfa_insert_web_cct( $name, $base );
	wp_set_current_user( $prev );

	if ( is_wp_error( $result ) || empty( $result['post_id'] ) ) {
		error_log( 'niz_wa_web_add_new: insert failed for ' . $base . ' — '
			. ( is_wp_error( $result ) ? $result->get_error_message() : 'no post_id' ) );
		nwa_send_message( $user_id, $wa_number,
			"Sorry, something went wrong adding your website. Please try again in a moment." );
		return '';
	}

	$link = add_query_arg( 'added', '1', get_permalink( $result['post_id'] ) );

	niz_wa_dir_send_done( $user_id, $wa_number,
		"✅ *{$name}* has been added to the Masjid4All directory!\n\n"
		. "Tap the link below to generate its full details and publish the listing:\n{$link}\n\n"
		. "Once it's live, you can claim it to manage and update the info." );
	return '';
}

/**
 * Enters the chosen add-a-listing branch. Website is handled conversationally
 * (ask for the URL, then find-or-create). Mosque/business are handed off to
 * the site's own Google place-picker form (/add-mosque, /add-business) — which
 * is public, no login required — because a mosque/business record needs
 * place_id + coordinates that only that picker captures, which a pasted Maps
 * link can't be resolved to server-side.
 */
function niz_wa_dir_start_branch( $user_id, $wa_number, $conversation, $type ) {
	// All three branches ask for the link/URL: website -> the site's own URL;
	// mosque/business -> a Google Maps link, which Sofia resolves via Serper.
	nwa_send_message( $user_id, $wa_number, niz_wa_dir_link_prompt( $type ) );
	NWA_DB::set_pending_action( $conversation->id, 'directory_flow', array( 'step' => 'await_link', 'type' => $type ), 30 );
	return '';
}

/* ---------------- Mosque/business add from a Google Maps link ----------------
   A pasted Maps link is resolved to a Google place entirely via Serper (no
   Google Places API needed): expand the short link to its final URL, pull the
   FTID out of it, derive the numeric CID, and look the place up with Serper's
   {"cid": ...} — which returns the full place (placeId, name, address, phone,
   website, rating, hours). The record is then created with the crawler's own
   mfa_geohash_upsert_place(), so it's identical to a crawled listing. */

/**
 * Resolves a Google Maps link to array( cid, name, lat, lng ), or null.
 * Follows the maps.app.goo.gl redirect to the full /maps/place URL and parses
 * the FTID (!1s0x..:0x..) whose second half, as a decimal, is the CID.
 */
function niz_wa_maps_resolve( $raw_link ) {
	$link = trim( (string) $raw_link );
	if ( '' === $link ) {
		return null;
	}
	if ( ! preg_match( '#^https?://#i', $link ) ) {
		$link = 'https://' . $link;
	}

	$ua    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36';
	$url   = $link;
	$final = $link;
	for ( $i = 0; $i < 6; $i++ ) {
		$r = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'headers' => array( 'User-Agent' => $ua, 'Accept-Language' => 'en-US,en;q=0.9' ) ) );
		if ( is_wp_error( $r ) ) {
			break;
		}
		$code  = wp_remote_retrieve_response_code( $r );
		$loc   = wp_remote_retrieve_header( $r, 'location' );
		$final = $url;
		if ( $code >= 300 && $code < 400 && $loc ) {
			$url   = $loc;
			$final = $loc;
		} else {
			break;
		}
	}

	$cid = '';
	if ( preg_match( '/1s(0x[0-9a-f]+:0x[0-9a-f]+)/i', $final, $m ) ) {
		$parts = explode( ':', $m[1] );
		$cid   = niz_wa_hex_to_dec( end( $parts ) );
	}
	$name = preg_match( '#/place/([^/@?]+)#', $final, $n ) ? str_replace( '+', ' ', urldecode( $n[1] ) ) : '';
	$lat  = null;
	$lng  = null;
	if ( preg_match( '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $final, $c ) ) {
		$lat = $c[1];
		$lng = $c[2];
	} elseif ( preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $final, $c ) ) {
		$lat = $c[1];
		$lng = $c[2];
	}

	if ( '' === $cid && '' === $name ) {
		return null;
	}
	return array( 'cid' => $cid, 'name' => $name, 'lat' => $lat, 'lng' => $lng );
}

/**
 * Exact big-hex -> decimal (the CID overflows PHP's int, so hexdec() can't be
 * used). GMP preferred, BCMath fallback.
 */
function niz_wa_hex_to_dec( $hex ) {
	$hex = preg_replace( '/[^0-9a-f]/i', '', (string) $hex );
	if ( '' === $hex ) {
		return '';
	}
	if ( function_exists( 'gmp_init' ) ) {
		return gmp_strval( gmp_init( $hex, 16 ) );
	}
	if ( function_exists( 'bcadd' ) ) {
		$dec = '0';
		for ( $i = 0, $len = strlen( $hex ); $i < $len; $i++ ) {
			$dec = bcadd( bcmul( $dec, '16' ), (string) hexdec( $hex[ $i ] ) );
		}
		return $dec;
	}
	return '';
}

/**
 * Full place from Serper by CID (its /maps endpoint accepts {"cid": ...}).
 * Uses the site's configured MFA_SERPER_API_KEY via mfa_serper_key().
 */
function niz_wa_serper_by_cid( $cid ) {
	if ( ! function_exists( 'mfa_serper_key' ) || '' === (string) $cid ) {
		return null;
	}
	$key = mfa_serper_key();
	if ( ! $key ) {
		return null;
	}
	$r = wp_remote_post( 'https://google.serper.dev/maps', array(
		'timeout' => 20,
		'headers' => array( 'X-API-KEY' => $key, 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array( 'cid' => (string) $cid ) ),
	) );
	if ( is_wp_error( $r ) ) {
		return null;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( isset( $d['places'][0] ) ) {
		return $d['places'][0];
	}
	return isset( $d['place'] ) ? $d['place'] : null;
}

/**
 * Existing mosque/business row for a placeId -> array( post_id, url ) or null.
 */
function niz_wa_place_find_existing( $type, $place_id ) {
	global $wpdb;
	if ( '' === (string) $place_id ) {
		return null;
	}
	$table = $wpdb->prefix . ( 'mosque' === $type ? 'jet_cct_mosque' : 'jet_cct_business' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT cct_single_post_id, page_url FROM {$table} WHERE place_id = %s LIMIT 1", $place_id ), ARRAY_A );
	if ( ! $row ) {
		return null;
	}
	$post_id = (int) $row['cct_single_post_id'];
	$url     = ! empty( $row['page_url'] ) ? $row['page_url'] : ( $post_id ? get_permalink( $post_id ) : '' );
	return array( 'post_id' => $post_id, 'url' => $url );
}

/**
 * Listing page URL (?added=1) for a just-created mosque/business by placeId.
 */
function niz_wa_place_page_url( $type, $place_id ) {
	global $wpdb;
	if ( '' === (string) $place_id ) {
		return '';
	}
	$table   = $wpdb->prefix . ( 'mosque' === $type ? 'jet_cct_mosque' : 'jet_cct_business' );
	$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT cct_single_post_id FROM {$table} WHERE place_id = %s LIMIT 1", $place_id ) );
	if ( ! $post_id ) {
		return '';
	}
	return add_query_arg( 'added', '1', get_permalink( $post_id ) );
}

/**
 * Soft check that a Serper place looks like a mosque (name/type keywords).
 */
function niz_wa_looks_like_mosque( $place ) {
	$hay = strtolower(
		( $place['title'] ?? '' ) . ' ' . ( $place['type'] ?? '' ) . ' ' . implode( ' ', (array) ( $place['types'] ?? array() ) )
	);
	foreach ( array( 'mosque', 'masjid', 'surau', 'musolla', 'musalla', 'madrasah', 'islamic centre', 'islamic center' ) as $kw ) {
		if ( false !== strpos( $hay, $kw ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Already-listed business: mirror the website claim presenter. Owned by the
 * user -> "you've claimed it"; owned by someone else -> "already claimed";
 * unclaimed -> a Claim button (business_listed step). Claims write the same
 * wp_jet_cct_listing_owner table, keyed by post_id, post_type 'business'.
 */
function niz_wa_business_present_listing( $user_id, $wa_number, $conversation, $existing, $name ) {
	$post_id = (int) $existing['post_id'];
	$url     = (string) $existing['url'];
	$owner   = niz_wa_web_claim_owner( $post_id );

	if ( $owner === (int) $user_id ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_dir_send_done( $user_id, $wa_number,
			"✅ *{$name}* is already listed — and you've already claimed it. 🎉\n\n" . $url
			. "\n\nManage it here:\n" . niz_wa_web_manage_url( $post_id ) );
		return '';
	}

	if ( $owner > 0 ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		niz_wa_dir_send_done( $user_id, $wa_number,
			"✅ *{$name}* is already listed in the Masjid4All business directory, and it has already been claimed by its owner.\n\n" . $url
			. "\n\nIf you believe that's a mistake, reply here and our team will help." );
		return '';
	}

	nwa_send_buttons( $user_id, $wa_number,
		"✅ *{$name}* is already listed in the Masjid4All business directory.\n\n" . $url
		. "\n\nIs this your business? You can claim it to manage the listing.",
		array(
			array( 'id' => 'dir_biz_claim', 'title' => 'Claim this business' ),
		) );
	NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
		array( 'step' => 'business_listed', 'post_id' => $post_id, 'name' => $name, 'url' => $url ), 30 );
	return '';
}

/**
 * Resolves a pasted Google Maps link to a place, then either reports it's
 * already listed or asks the user to confirm before creating it. Keeps the
 * session on await_link when the link can't be resolved so the user can retry.
 */
function niz_wa_place_add_from_link( $user_id, $wa_number, $conversation, $type, $raw_link ) {
	$resolved = niz_wa_maps_resolve( $raw_link );

	if ( ! $resolved || ( '' === $resolved['cid'] && '' === (string) $resolved['name'] ) ) {
		nwa_send_message( $user_id, $wa_number,
			"That doesn't look like a Google Maps place link. Open the place in Google Maps, tap *Share* → *Copy link*, and paste it here." . niz_wa_dir_stop_hint() );
		return '';
	}

	// CID lookup is exact; fall back to a name+coords search only if the link
	// carried no FTID.
	$place = '' !== $resolved['cid'] ? niz_wa_serper_by_cid( $resolved['cid'] ) : null;
	if ( ( ! $place || empty( $place['placeId'] ) ) && '' !== (string) $resolved['name'] && $resolved['lat'] && function_exists( 'mfa_serper_maps' ) ) {
		$found = mfa_serper_maps( $resolved['name'], (float) $resolved['lat'], (float) $resolved['lng'], 17 );
		if ( ! is_wp_error( $found ) && ! empty( $found[0] ) ) {
			$place = $found[0];
		}
	}

	if ( ! $place || empty( $place['placeId'] ) ) {
		nwa_send_message( $user_id, $wa_number,
			"I couldn't find that place from the link. Please paste the link from the place's *Share* button." . niz_wa_dir_stop_hint() );
		return '';
	}

	// The place's actual nature decides the directory, not what was asked:
	// a mosque/surau/etc. only goes in the mosque directory, everything else
	// in the business directory. If that differs from what the user asked,
	// suggest the correct one instead of adding it to the wrong directory.
	$target   = niz_wa_looks_like_mosque( $place ) ? 'mosque' : 'business';
	$mismatch = ( $target !== $type );

	// Already in the (correct) directory?
	$existing = niz_wa_place_find_existing( $target, $place['placeId'] );
	if ( $existing ) {
		if ( 'business' === $target ) {
			return niz_wa_business_present_listing( $user_id, $wa_number, $conversation, $existing, (string) $place['title'] );
		}
		NWA_DB::set_pending_action( $conversation->id, null );
		$suffix = $existing['url'] ? "\n\n" . $existing['url'] : '';
		niz_wa_dir_send_done( $user_id, $wa_number,
			"✅ *{$place['title']}* is already listed in the Masjid4All mosque directory.{$suffix}" );
		return '';
	}

	// Confirm before creating (redirecting to the right directory if needed).
	$addr = isset( $place['address'] ) ? $place['address'] : '';
	if ( $mismatch ) {
		$target_label = 'business' === $target ? 'Business' : 'Mosque';
		$body = "I found:\n\n*{$place['title']}*\n{$addr}\n\nThis looks like a *{$target}*, not a *{$type}*. Would you like to add it to the *{$target_label} Directory* instead?";
		$btn  = 'business' === $target ? 'Add to Business' : 'Add to Mosque';
	} else {
		$label = 'business' === $target ? 'business' : 'mosque';
		$body  = "I found:\n\n*{$place['title']}*\n{$addr}\n\nAdd this to the Masjid4All {$label} directory?";
		$btn   = 'Yes, add it';
	}

	nwa_send_buttons( $user_id, $wa_number, $body,
		array(
			array( 'id' => 'dir_place_yes', 'title' => $btn ),
			array( 'id' => 'dir_place_no',  'title' => 'No' ),
		) );
	NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
		array( 'step' => 'await_place_confirm', 'type' => $target, 'place' => $place ), 30 );
	return '';
}

/**
 * Holds the latest submission on the user until phase 2 (turning the link
 * into an actual directory record) is built. Deliberately a single
 * "last submission" slot plus an error_log breadcrumb — not a schema.
 */
function niz_wa_dir_store_submission( $user_id, $type, $link ) {
	update_user_meta( $user_id, 'niz_wa_dir_last_submission', array(
		'type' => sanitize_key( $type ),
		'link' => sanitize_text_field( $link ),
		'time' => current_time( 'mysql' ),
	) );
	error_log( "niz-wa directory submission: user_id={$user_id} type={$type} link={$link}" );
}

/* ---------------- Action registry seeding ---------------- */

add_action( 'admin_init', 'niz_wa_seed_actions' );

function niz_wa_seed_actions() {
	if ( ! class_exists( 'NWA_DB' ) ) {
		return;
	}

	global $wpdb;
	$table = NWA_DB::actions_table();

	$actions = array(
		array(
			'intent_key'            => 'start',
			'keywords'              => 'start',
			'description'           => 'User sends a greeting or wants to begin',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_start',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'help',
			'keywords'              => 'help,bantuan,tolong,menu,panduan',
			'description'           => 'User asks for help, a menu, or what Sofia / Masjid4All can do',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_help',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'register',
			'keywords'              => 'register,signup,sign up,daftar,jadi ahli',
			'description'           => 'User wants to register as a new member or asks about signing up',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_register',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'reset_password',
			'keywords'              => 'password,forgot password,reset password,request password,kata laluan,tukar password,lupa password,change password,login,log in,cannot login,sign in',
			'description'           => "User can't log in, forgot their password, or wants to sign in / access their account",
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_reset_password',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'membership_price',
			'keywords'              => 'price,pricing,membership price,harga,yuran',
			'description'           => 'User is asking about membership pricing or fees',
			'requires_confirmation' => true,
			'confirm_message'       => 'Would you like details on our membership pricing?',
			'callback_function'     => 'niz_wa_action_membership_price',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'advertise',
			'keywords'              => 'advertise,advertising,iklan,promote',
			'description'           => 'User wants to advertise or promote their business with Masjid4All',
			'requires_confirmation' => true,
			'confirm_message'       => 'Are you interested in advertising with Masjid4All?',
			'callback_function'     => 'niz_wa_action_advertise',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'share',
			'keywords'              => 'share,invite,referral,referral link,my link,kongsi,ajak',
			'description'           => 'User wants to share/invite friends to Masjid4All or asks for their referral link',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_share',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'inquiry',
			'keywords'              => 'inquiry,enquiry,complaint,feedback,contact us,contact,speak to someone,talk to someone,human agent,customer service,aduan,soalan,maklum balas,hubungi kami',
			'description'           => 'User wants to submit an inquiry, complaint, or feedback, or reach a human at Masjid4All',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_inquiry',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'directory',
			'keywords'              => 'directory,add listing,add mosque,add business,add website,claim business,claim website,claim listing,claim my business,claim my website,direktori,tambah masjid,tambah bisnes,tambah laman web,tuntut bisnes',
			'description'           => 'User wants to add or claim a mosque, business, or website in the Masjid4All directory, or asks how to list one for free',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_directory',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'founding_member',
			'keywords'              => 'founding member,founding,waitlist,join waitlist,early access,premium,lifetime,ahli pengasas,senarai menunggu',
			'description'           => 'User is interested in becoming a Founding Member, joining the waitlist, or asks about premium/lifetime membership',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_founding_member',
			'enabled'               => true,
		),
	);

	foreach ( $actions as $action ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE intent_key = %s",
			$action['intent_key']
		) );

		if ( $exists ) {
			continue;
		}

		NWA_DB::save_action( $action );
	}

	// 'help'/'menu' moved from the 'start' action to the dedicated 'help'
	// action; narrow any existing start row so its keyword no longer wins the
	// 'help' match. Idempotent — only updates while it still needs fixing.
	$wpdb->query( "UPDATE {$table} SET keywords = 'start' WHERE intent_key = 'start' AND keywords <> 'start'" );

	// register / reset_password moved to the magic-login account flow: no more
	// Yes/No confirmation, and reset_password now means "log me in". Sync the
	// existing rows (seeding only inserts). Idempotent.
	$wpdb->query( "UPDATE {$table} SET requires_confirmation = 0, confirm_message = '' WHERE intent_key = 'register' AND requires_confirmation <> 0" );

	// 'advertise' became a lead-capture conversation whose own first step asks
	// 'Reply YES to continue' - the registry-level confirmation would ask the
	// same question twice. Seeding only inserts, so sync the existing row.
	// Idempotent. (2026-08-19)
	$wpdb->query( "UPDATE {$table} SET requires_confirmation = 0, confirm_message = '' WHERE intent_key = 'advertise' AND requires_confirmation <> 0" );
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET requires_confirmation = 0, confirm_message = '', keywords = %s, description = %s WHERE intent_key = 'reset_password' AND requires_confirmation <> 0",
		'password,forgot password,reset password,request password,kata laluan,tukar password,lupa password,change password,login,log in,cannot login,sign in',
		"User can't log in, forgot their password, or wants to sign in / access their account"
	) );
}

/* ---------------- Directory FAQ (knowledge base) ---------------- */
/* Grounds Sofia's open-ended Q&A (NWA_AI::answer_question searches
   wp_nwa_knowledge_base) so she can explain the directory/listing/claim
   features. Idempotent by title; source_type 'directory_faq' tags them. */

add_action( 'admin_init', 'niz_wa_seed_knowledge' );

function niz_wa_seed_knowledge() {
	if ( ! class_exists( 'NWA_DB' ) ) {
		return;
	}

	global $wpdb;
	$table = NWA_DB::knowledge_table();

	$faqs = array(
		array(
			'title'   => 'Masjid4All directories — what you can list',
			'content' => "Masjid4All has three free community directories you can add to: the Mosque Directory (mosques, suraus, musollas, madrasahs), the Business Directory (halal-friendly businesses), and the Website Directory (useful Islamic websites and resources). On WhatsApp, send \"directory\" to see the options, or just tell Sofia what you want to add — for example \"add mosque\", \"add business\", or \"add website\".",
		),
		array(
			'title'   => 'How to add a mosque to Masjid4All',
			'content' => "To add a mosque, surau, musolla or madrasah: send \"add mosque\" to Sofia on WhatsApp. She asks for the Google Maps link of the mosque; paste it and she finds the place and asks you to confirm before adding it to the Mosque Directory. Only Islamic places of worship belong in the Mosque Directory — if the place is actually a business, Sofia will suggest the Business Directory instead. New listings show the status \"New\" until their details are generated and reviewed.",
		),
		array(
			'title'   => 'How to add a business to Masjid4All',
			'content' => "To add your business: send \"add business\" to Sofia and paste the Google Maps link of your business. Sofia finds it and asks you to confirm before adding it to the Business Directory (for halal-friendly businesses). If the business is already listed, she offers to let you claim it. New listings show the status \"New\" until reviewed.",
		),
		array(
			'title'   => 'How to add a website to Masjid4All',
			'content' => "To add a website: send \"add website\" to Sofia and paste the website address (URL), for example https://example.com. If it is already listed you can visit or claim it; if it is new, Sofia adds it and gives you a link to generate its full details. Only real, reachable websites can be added — an invalid or unreachable address is rejected.",
		),
		array(
			'title'   => 'How to get a Google Maps link (Share link)',
			'content' => "To get the Google Maps share link for your mosque or business: 1) Open Google Maps. 2) Search for your mosque or business and open its place. 3) Tap Share and copy the link (it looks like https://maps.app.goo.gl/...). 4) Paste that link to Sofia. This link lets Sofia identify the exact place and pull its details (name, address, phone, and more) automatically.",
		),
		array(
			'title'   => 'How to claim your business on Masjid4All',
			'content' => "If your business is already listed, you can claim it to manage its details. Send \"add business\" and paste your business's Google Maps link; if it is already listed, Sofia shows a \"Claim this business\" button. Claiming requires a free Masjid4All membership — if you are not registered, Sofia registers you first and then claims it for you automatically. Once claimed, you manage the listing from your member area.",
		),
		array(
			'title'   => 'How to claim your website on Masjid4All',
			'content' => "If your website is already listed, send \"add website\" and paste its URL. Sofia shows a \"Claim this website\" button. Claiming requires a free Masjid4All membership; if you are not registered yet, Sofia registers you and claims it for you. After claiming, you can manage and update the listing.",
		),
		array(
			'title'   => 'Directory listing status and review',
			'content' => "New directory listings start with the status \"New\" and become fully visible after their details are generated and reviewed. Common statuses are New, Pending (under review), and Approved. A listing marked \"Rejected\" (or Error/Deleted) does not meet our directory guidelines — if you think that is a mistake, please contact us through the Contact Us page.",
		),
		array(
			'title'   => 'Do I need an account to add or claim a listing?',
			'content' => "Adding a mosque, business, or website through Sofia on WhatsApp does not require an account. Claiming an existing business or website — so that you can manage it — does require a free Masjid4All membership. If you are not registered, Sofia can register you and complete the claim in the same conversation.",
		),
		array(
			'title'   => 'Getting help from Sofia',
			'content' => "Sofia is the Masjid4All WhatsApp assistant. Send \"help\" (or \"bantuan\") anytime to see what she can do. You can add or claim mosques, businesses, and websites, and ask questions like \"how do I get a Google Maps link?\" or \"how do I claim my business?\". To start, send \"directory\", or \"add mosque\", \"add business\", or \"add website\".",
		),
	);

	foreach ( $faqs as $faq ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE title = %s", $faq['title'] ) );
		if ( $exists ) {
			continue;
		}
		NWA_DB::save_knowledge( array(
			'title'       => $faq['title'],
			'content'     => $faq['content'],
			'source_type' => 'directory_faq',
		) );
	}
}
