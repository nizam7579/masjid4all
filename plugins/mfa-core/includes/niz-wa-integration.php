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

function niz_wa_action_register( $user_id, $context ) {
	$status    = get_user_meta( $user_id, 'user_status', true );
	$login_url = home_url( '/member' );

	if ( in_array( $status, array( 'member', 'premium' ), true ) ) {
		return "You're already a registered member. Log in here:\n{$login_url}";
	}

	$user  = get_userdata( $user_id );
	$phone = get_user_meta( $user_id, 'user_phone', true );

	if ( ! $user || empty( $phone ) ) {
		return "Registration failed — we couldn't find your WhatsApp number on file. Please try again later.";
	}

	$name = trim( (string) $user->first_name );
	if ( ! $name || 0 === strpos( $name, 'Prospect ' ) ) {
		$name = 'Member';
	}

	if ( function_exists( 'niz_user_complete_registration' ) ) {
		// Creates the jet_cct_member row, syncs name, marks user_status
		// 'member', awards the Welcome Bonus — same shared step every
		// other registration path uses (see identity-registration.php).
		niz_user_complete_registration( $user_id, array( 'name' => $name ) );
	} else {
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => $name,
		) );
		update_user_meta( $user_id, 'user_status', 'member' );
	}

	$temp_pass = (string) random_int( 100000, 999999 );
	wp_set_password( $temp_pass, $user_id );
	update_user_meta( $user_id, 'change_password', 'yes' );

	return "Registration successful, {$name}!\n\nYour temporary password is: {$temp_pass}\n\nLogin here:\n{$login_url}\n\nPlease change your password after login.";
}

function niz_wa_action_reset_password( $user_id, $context ) {
	if ( ! function_exists( 'niz_user_password' ) ) {
		return "Password reset is temporarily unavailable. Please try again later.";
	}

	$status = get_user_meta( $user_id, 'user_status', true );
	$phone  = get_user_meta( $user_id, 'user_phone', true );

	if ( empty( $status ) || 'prospect' === $status ) {
		return "You don't have an active membership yet. Reply REGISTER to sign up as a free member first.";
	}

	if ( empty( $phone ) ) {
		return "We couldn't find your WhatsApp number on file. Please try again later.";
	}

	$password = niz_user_password( $phone );

	if ( is_wp_error( $password ) || ! $password ) {
		return "We couldn't reset your password right now. Please try again later.";
	}

	return "Your temporary password is: {$password}\n\nLogin here:\n" . home_url( '/member' ) . "\n\nPlease change your password after login.";
}

function niz_wa_action_membership_price( $user_id, $context ) {
	$url = home_url( '/member/premium/' );
	return "Membership starts from RM19.90 per year.\n\nFor the latest pricing and to upgrade, visit:\n{$url}";
}

function niz_wa_action_advertise( $user_id, $context ) {
	$url = home_url( '/advertise' );
	return "Interested in advertising with Masjid4All? Learn more here:\n{$url}\n\nOr reply here and our team will reach out.";
}

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
	$url      = home_url( '/contact-us/' );
	$fallback = "For any inquiries, feedback, or to reach our team directly, please visit:\n{$url}\n\nWe'll get back to you as soon as possible.";

	$message_text = isset( $context['message_text'] ) ? trim( (string) $context['message_text'] ) : '';

	if ( '' === $message_text || ! class_exists( 'NWA_AI' ) || ! method_exists( 'NWA_AI', 'call_ai' ) ) {
		return $fallback;
	}

	$persona = method_exists( 'NWA_AI', 'default_persona' ) ? NWA_AI::default_persona() : '';
	$system  = trim( $persona . "\n\nThe user just sent a message that is an inquiry, complaint, piece of feedback, or a request to reach the team - for example a business collaboration, partnership, or advertising proposal, or a general question that needs a human. Reply with exactly two short lines, both written in the SAME language as the user's message:\nLine 1: ONE short, warm sentence that specifically acknowledges what THEY asked about - do not be generic or vague, do not ask them further questions.\nLine 2: a short closing phrase inviting them to reach the team through the contact link below (the equivalent of 'You can reach our team here:') - do NOT include any URL or link yourself, a real link will be appended automatically right after your reply.\nOutput ONLY those two lines, nothing else." );

	$ai_reply = NWA_AI::call_ai( $system, $message_text );

	if ( ! is_string( $ai_reply ) || '' === trim( $ai_reply ) ) {
		return $fallback;
	}

	return trim( $ai_reply ) . "\n{$url}";
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
		nwa_send_message( $user_id, $conversation->wa_number, niz_wa_dir_link_prompt( $type ) );
		NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
			array( 'step' => 'await_link', 'type' => $type ), 30 );
		return '';
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

		// mosque/business (record creation not built yet): capture + ack.
		niz_wa_dir_store_submission( $user_id, $type, $text );
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, niz_wa_dir_ack( $type ) );
		return '';
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
				nwa_send_message( $user_id, $wa_number, niz_wa_web_claim_result_message( $result, $name, $post_id ) );
				return '';
			}

			nwa_send_buttons( $user_id, $wa_number,
				"To claim *{$name}*, you'll need a free Masjid4All membership first.\n\nWould you like to register now?",
				array(
					array( 'id' => 'dir_web_register', 'title' => 'Register as member' ),
					array( 'id' => 'dir_web_cancel',   'title' => 'Cancel' ),
				) );
			NWA_DB::set_pending_action( $conversation->id, 'directory_flow',
				array( 'step' => 'claim_need_register', 'url' => $url, 'name' => $name, 'post_id' => $post_id ), 30 );
			return '';
		}

		nwa_send_message( $user_id, $wa_number,
			"Please tap *Visit Website* or *Claim this website* to continue."
			. niz_wa_dir_stop_hint() );
		return '';
	}

	if ( 'claim_need_register' === $step ) {
		$name    = isset( $ctx['name'] ) ? $ctx['name'] : 'this website';
		$post_id = isset( $ctx['post_id'] ) ? (int) $ctx['post_id'] : 0;
		$t       = strtolower( $text );

		// 'Cancel' is already handled by the global stop-word check above.
		if ( false !== strpos( $t, 'register' ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );

			// Register the member (same path as replying REGISTER), then
			// auto-claim the website they were trying to claim.
			$reg_message = function_exists( 'niz_wa_action_register' )
				? (string) niz_wa_action_register( $user_id, array() )
				: '';
			$result  = niz_wa_web_do_claim( $post_id, $user_id );

			$message = trim( $reg_message );
			if ( in_array( $result, array( 'claimed', 'already_yours' ), true ) ) {
				$message .= ( '' !== $message ? "\n\n" : '' )
					. "🎉 I've also claimed *{$name}* for you.\n\nManage it here:\n" . niz_wa_web_manage_url( $post_id );
			}

			nwa_send_message( $user_id, $wa_number,
				'' !== $message ? $message : "You're registered! You can now claim *{$name}*." );
			return '';
		}

		nwa_send_message( $user_id, $wa_number,
			"Please tap *Register as member* to continue, or *Cancel* to stop." );
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

	NWA_DB::set_pending_action( $conversation->id, 'directory_flow', array( 'step' => 'await_link', 'type' => $choice ), 30 );
	nwa_send_message( $user_id, $wa_number, niz_wa_dir_link_prompt( $choice ) );
	return '';
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

function niz_wa_dir_link_prompt( $type ) {
	if ( 'website' === $type ) {
		return "🌐 *Add Website*\n\nPlease send the *website URL* you'd like to list.\nExample: https://example.com"
			. niz_wa_dir_stop_hint();
	}

	$label = 'business' === $type ? 'business' : 'mosque';
	return "📍 *Add " . ucfirst( $label ) . "*\n\n"
		. "Please send the *Google Maps link* of your {$label}.\n\n"
		. "_Tip: open it in Google Maps → tap Share → Copy link → paste it here._"
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
		"SELECT _ID, name, url, cct_single_post_id FROM {$w} WHERE url LIKE %s LIMIT 50",
		$like
	), ARRAY_A );
	foreach ( (array) $rows as $row ) {
		if ( mfa_web_extract_normalize_host( $row['url'] ) === $host ) {
			return array(
				'id'      => (int) $row['_ID'],
				'name'    => $row['name'] ? $row['name'] : $row['url'],
				'url'     => $row['url'],
				'post_id' => (int) $row['cct_single_post_id'],
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
function niz_wa_web_do_claim( $post_id, $user_id ) {
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
		'post_type'   => 'web',
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
	$owner = niz_wa_web_claim_owner( $match['post_id'] );

	if ( $owner === (int) $user_id ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number,
			"✅ *{$match['name']}* is already listed — and you've already claimed it. 🎉\n\n🔗 {$match['url']}\n\nManage it here:\n" . niz_wa_web_manage_url( $match['post_id'] ) );
		return '';
	}

	if ( $owner > 0 ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number,
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
	NWA_DB::set_pending_action( $conversation->id, null );

	// Normalise to a valid absolute URL (add a scheme for a bare domain).
	$raw = trim( (string) $raw_url );
	if ( ! preg_match( '#^https?://#i', $raw ) ) {
		$raw = 'https://' . $raw;
	}
	$url = esc_url_raw( $raw );

	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL )
		|| ! function_exists( 'mfa_insert_web_cct' ) || ! function_exists( 'mfa_get_remote_website_title' ) ) {
		// Can't create it cleanly — fall back to the phase-1 acknowledgment.
		niz_wa_dir_store_submission( $user_id, 'website', $raw_url );
		nwa_send_message( $user_id, $wa_number, niz_wa_dir_ack( 'website' ) );
		return '';
	}

	$base = function_exists( 'cct_trim_url_to_base' ) ? cct_trim_url_to_base( rtrim( $url, '/' ) ) : rtrim( $url, '/' );

	// A readable name from the site itself, with a domain-derived fallback so a
	// slow/blocked fetch never stops us from adding the listing.
	$name = mfa_get_remote_website_title( $base );
	if ( '' === $name || 0 === strpos( $name, 'Error:' ) ) {
		$host = wp_parse_url( $base, PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', (string) $host );
		$name = $host ? ucwords( str_replace( array( '-', '_', '.' ), ' ', $host ) ) : $base;
	}

	// Attribute the submission to the WhatsApp user (mfa_insert_web_cct() reads
	// the current user for cct_author_id / post_author).
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

	nwa_send_message( $user_id, $wa_number,
		"✅ *{$name}* has been added to the Masjid4All directory!\n\n"
		. "Tap the link below to generate its full details and publish the listing:\n{$link}\n\n"
		. "Once it's live, you can claim it to manage and update the info." );
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
			'keywords'              => 'start,help,menu',
			'description'           => 'User wants a welcome/help message or main menu',
			'requires_confirmation' => false,
			'confirm_message'       => '',
			'callback_function'     => 'niz_wa_action_start',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'register',
			'keywords'              => 'register,signup,sign up,daftar,jadi ahli',
			'description'           => 'User wants to register as a new member or asks about signing up',
			'requires_confirmation' => true,
			'confirm_message'       => 'Would you like to register as a free Masjid4All member?',
			'callback_function'     => 'niz_wa_action_register',
			'enabled'               => true,
		),
		array(
			'intent_key'            => 'reset_password',
			'keywords'              => 'password,forgot password,reset password,request password,kata laluan,tukar password,lupa password,change password',
			'description'           => "User forgot their password or wants to reset/change their account password",
			'requires_confirmation' => true,
			'confirm_message'       => 'Do you want to reset your password?',
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
}
