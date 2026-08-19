<?php
/**
 * Sofia lead-capture flows — "conversation as the funnel".
 *
 * The conversion pattern the site is moving to: a public page offers
 * something, the CTA hands the visitor to Sofia on WhatsApp, and Sofia
 * captures the few fields we need in-chat. No landing page, no web form,
 * no login wall in front of the interest.
 *
 * Two lead types launch here (2026-08-19):
 *   - founding_member : waitlist for the Founding Member offer, which is
 *                       NOT purchasable yet (premium-page.php is
 *                       deliberately "launching soon"). Sofia says so
 *                       plainly rather than implying they can buy today.
 *   - advertise       : interest in advertising, replacing a placeholder
 *                       reply in niz-wa-integration.php that pointed at
 *                       home_url('/advertise') — a page that 404s on
 *                       production, so every advertising enquiry to date
 *                       was handed a dead link.
 *
 * One step machine drives both. Each type declares an ordered field list
 * (mfa_lead_types()), so adding the remaining ideas — mosque community,
 * matrimony, faraid — is a copy block plus a field list, not new routing.
 *
 * Captured leads land in FluentCRM (activated 2026-08-19 for exactly this)
 * as a subscriber tagged per type, which is what the follow-up funnels
 * trigger on. Follow-ups are email-led by design: WhatsApp only permits
 * free-form replies inside the 24-hour customer service window, and
 * niz-wa has no template management, so nothing here schedules a WhatsApp
 * message for later.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead type registry. Copy + captured fields + where it lands in FluentCRM.
 *
 * 'fields' is the ordered capture sequence. Supported: name, email, detail.
 * 'detail' is the one free-text field whose prompt/label varies per type.
 */
function mfa_lead_types() {
	$types = array(
		'founding_member' => array(
			'label'        => 'Founding Member waitlist',
			'emoji'        => '⭐',
			'cta_label'    => 'Join the Waitlist',
			'cta_title'    => 'Become a Founding Member',
			'cta_text'     => 'Sofia will take your details on WhatsApp and add you to the waitlist — it takes a minute.',
			'wa_keyword'   => 'founding member',
			'fields'       => array( 'name', 'email' ),
			'tag_title'    => 'Founding Member Waitlist',
			'tag_slug'     => 'founding-member-waitlist',
			'source'       => 'sofia-founding-member',
			'intro'        => "*Founding Member* — for the people who back Masjid4All from the start. ⭐\n\n"
				. "The plan: a one-time joining fee, lifetime Premium access, the full amount returned to you as Platform Credit, and permanent Founding Member status.\n\n"
				. "It's *not on sale yet* — I'm building the waitlist now, and you'll be the first told when it opens. No payment, no commitment.\n\n"
				. "Shall I add you? Reply *YES* to join, or *STOP* to skip.",
			'done'         => "You're on the Founding Member waitlist. ⭐\n\nI'll message you the moment it opens — you'll get first access before it's announced publicly.",
		),
		'advertise'       => array(
			'label'        => 'Advertising enquiry',
			'emoji'        => '📣',
			'cta_label'    => 'Feature My Business',
			'cta_title'    => 'Advertise on Masjid4All',
			'cta_text'     => 'Reach Muslim families searching for mosques and halal businesses. Sofia will take your details on WhatsApp.',
			'wa_keyword'   => 'advertise',
			'fields'       => array( 'name', 'email' ),
			'tag_title'    => 'Advertiser Prospect',
			'tag_slug'     => 'advertiser-prospect',
			'source'       => 'sofia-advertise',
			// Opens with native reply buttons rather than asking the user to
			// type YES. WhatsApp caps titles at 20 chars and delivers a tap
			// back as the title text, so the titles double as match tokens.
			'buttons'      => array(
				'yes' => 'Yes, tell me more',
				'no'  => 'Not now',
			),
			// Native in-chat form, same mechanism as Contact Us. Used only
			// when the constant is defined AND the send succeeds; otherwise
			// the text conversation runs instead.
			'flow_const'   => 'MFA_ADVERTISE_FLOW_ID',
			'flow_cta'     => 'Send My Details',
			'flow_screen'  => 'ADVERTISE_FORM',
			'flow_token'   => 'advertise',
			'flow_intro'   => "Great! 📣 Just fill in the short form below and tap Submit — it only takes a moment.",
			'intro'        => "Advertise with Masjid4All 📣\n\n"
				. "Your ad can appear alongside our mosque and halal business directories — in front of people already searching for exactly that.\n\n"
				. "Are you interested in advertising with Masjid4All?",
			'decline'      => "No problem at all. 👍\n\nIf you change your mind, just message me *advertise* anytime.",
			// No rates quoted on purpose: the rate card is not written yet and
			// global-vs-per-country pricing is undecided (2026-08-19), so
			// Sofia must not invent numbers. The lead is captured as a
			// prospect and the team follows up manually.
			'done'         => "Thank you — I've passed your details to our team. 📣\n\nThey'll be in touch shortly with our rate card, ad placements and examples, so you can see exactly what's on offer before deciding anything.",
		),
	);

	/**
	 * Filter the Sofia lead types.
	 *
	 * @param array $types Keyed by intent key.
	 */
	return apply_filters( 'mfa_lead_types', $types );
}

/** One lead type, or null if the key isn't registered. */
function mfa_lead_type( $key ) {
	$types = mfa_lead_types();
	return isset( $types[ $key ] ) ? $types[ $key ] : null;
}

/* ---------------- FluentCRM capture ---------------- */

/**
 * Ensure a tag exists and return its id, or 0 if FluentCRM isn't available.
 *
 * FluentCrmApi('tags')->createOrUpdate() returns null in 3.1.10 (verified
 * live before relying on it), so this uses the Tag model's firstOrCreate,
 * which does attach correctly through the pivot.
 */
function mfa_lead_tag_id( $slug, $title ) {
	if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
		return 0;
	}
	try {
		$tag = \FluentCrm\App\Models\Tag::firstOrCreate(
			array( 'slug' => sanitize_title( $slug ) ),
			array( 'title' => sanitize_text_field( $title ) )
		);
		return $tag ? (int) $tag->id : 0;
	} catch ( \Throwable $e ) {
		error_log( 'mfa_lead_tag_id: ' . $e->getMessage() );
		return 0;
	}
}

/**
 * Record a captured lead.
 *
 * Writes three places, deliberately:
 *   1. FluentCRM  — what the follow-up funnels trigger on.
 *   2. User meta  — so the lead survives even if FluentCRM is unavailable,
 *                   and so /admin/ can read it without a CRM query.
 *   3. error_log  — same breadcrumb convention as
 *                   niz_wa_dir_store_submission().
 *
 * FluentCRM failure is never allowed to break the WhatsApp reply: the user
 * has already given us their details and must still get a confirmation.
 *
 * @param int    $user_id WP user behind the WhatsApp conversation.
 * @param string $type    Lead type key.
 * @param array  $data    name / email / detail / phone.
 * @return bool Whether the CRM write succeeded.
 */
function mfa_lead_capture( $user_id, $type, $data ) {
	$cfg = mfa_lead_type( $type );
	if ( ! $cfg ) {
		return false;
	}

	$name   = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
	$email  = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$detail = isset( $data['detail'] ) ? sanitize_text_field( $data['detail'] ) : '';
	$phone  = isset( $data['phone'] ) ? preg_replace( '/[^0-9]/', '', (string) $data['phone'] ) : '';

	// 2. Local record first — cheap, and the one that must not be lost.
	$existing = get_user_meta( $user_id, 'mfa_sofia_leads', true );
	$existing = is_array( $existing ) ? $existing : array();
	$existing[ $type ] = array(
		'name'   => $name,
		'email'  => $email,
		'detail' => $detail,
		'phone'  => $phone,
		'time'   => current_time( 'mysql' ),
	);
	update_user_meta( $user_id, 'mfa_sofia_leads', $existing );

	error_log( "mfa sofia lead: user_id={$user_id} type={$type} email={$email}" );

	// 1. FluentCRM.
	if ( ! function_exists( 'FluentCrmApi' ) || ! is_email( $email ) ) {
		return false;
	}

	try {
		$parts      = preg_split( '/\s+/', trim( $name ), 2 );
		$first_name = isset( $parts[0] ) ? $parts[0] : '';
		$last_name  = isset( $parts[1] ) ? $parts[1] : '';

		$fields = array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			// They asked us for this in a live conversation, so subscribed
			// is the honest status - this is not a scraped or imported list.
			'status'     => 'subscribed',
			'source'     => $cfg['source'],
		);
		if ( '' !== $phone ) {
			$fields['phone'] = $phone;
		}
		if ( $user_id ) {
			$fields['user_id'] = (int) $user_id;
		}

		$tag_id = mfa_lead_tag_id( $cfg['tag_slug'], $cfg['tag_title'] );
		if ( $tag_id ) {
			$fields['tags'] = array( $tag_id );
		}

		$contact = FluentCrmApi( 'contacts' )->createOrUpdate( $fields );
		if ( ! $contact ) {
			return false;
		}

		// The free-text detail has nowhere native to live on a subscriber,
		// so it goes on the contact as a note - visible in the CRM next to
		// the contact rather than only in user meta.
		//
		// Subscriber has no addNote() in 3.1.10, only the notes() relation.
		// An earlier method_exists( $contact, 'addNote' ) guard here meant every
		// note was silently skipped - the detail still reached user meta, but
		// never the CRM. Verified against wp_fc_subscriber_notes.
		if ( '' !== $detail && method_exists( $contact, 'notes' ) ) {
			$contact->notes()->create( array(
				'title'       => $cfg['label'],
				'description' => $detail,
				'type'        => 'note',
				'created_by'  => 0,
			) );
		}

		return true;
	} catch ( \Throwable $e ) {
		error_log( 'mfa_lead_capture: ' . $e->getMessage() );
		return false;
	}
}

/* ---------------- WhatsApp flow ----------------
   One step machine for every lead type. State lives in niz-wa's own
   pending_action/pending_context columns under the intent key 'lead_flow',
   the same mechanism the contact and directory flows use, so a lead
   capture in progress can't be crossed with those. */

/** Start a lead flow. Sends its own message and returns '' so the router adds nothing. */
function mfa_lead_start( $user_id, $type ) {
	$cfg = mfa_lead_type( $type );
	if ( ! $cfg || ! class_exists( 'NWA_DB' ) ) {
		return '';
	}

	$conversation = NWA_DB::get_conversation_by_user( $user_id );
	if ( ! $conversation ) {
		return $cfg['intro'];
	}

	$ctx = array( 'type' => $type, 'step' => 'confirm' );

	// Native reply buttons where the type asks for them, so the opening
	// question is a tap rather than typing YES. If the send fails for any
	// reason the plain-text intro still goes out and the typed path works,
	// so the flow is never left without a prompt.
	$sent_buttons = false;
	if ( ! empty( $cfg['buttons'] ) && function_exists( 'nwa_send_buttons' ) ) {
		$res = nwa_send_buttons( $user_id, $conversation->wa_number, $cfg['intro'], array(
			array( 'id' => 'lead_yes', 'title' => $cfg['buttons']['yes'] ),
			array( 'id' => 'lead_no',  'title' => $cfg['buttons']['no'] ),
		) );
		$sent_buttons = ! empty( $res['success'] );
	}

	if ( ! $sent_buttons ) {
		$text = $cfg['intro'];
		if ( ! empty( $cfg['buttons'] ) ) {
			// Buttons unavailable - spell out the typed equivalent.
			$text .= "\n\nReply *YES* to continue, or *STOP* to skip.";
		}
		nwa_send_message( $user_id, $conversation->wa_number, $text );
	}

	NWA_DB::set_pending_action( $conversation->id, 'lead_flow', $ctx, 30 );

	return '';
}

/**
 * Begin collecting fields once interest is confirmed.
 *
 * Prefers a native in-chat Meta Flow form (one screen, submit once) and
 * falls back to asking one question at a time. Same two-path shape as the
 * Contact Us flow, and for the same reason: the Flow can fail to send
 * (outside the 24h window, API error, not configured yet) and the
 * conversation must continue regardless.
 *
 * The advertise Flow is NOT published in Meta yet, so in practice the text
 * path is what runs today. Define MFA_ADVERTISE_FLOW_ID against the WABA
 * that actually sends (27070199045967929) to switch it on - a Flow built
 * against the wrong WABA sends but 400s with error 131009.
 */
function mfa_lead_begin_capture( $user_id, $wa_number, $conversation, $cfg, $ctx ) {
	$flow_id = ( ! empty( $cfg['flow_const'] ) && defined( $cfg['flow_const'] ) )
		? constant( $cfg['flow_const'] )
		: '';

	if ( '' !== $flow_id && function_exists( 'nwa_send_flow' ) ) {
		$sent = nwa_send_flow(
			$user_id,
			$wa_number,
			isset( $cfg['flow_intro'] ) ? $cfg['flow_intro'] : 'Please fill in the short form below.',
			$flow_id,
			isset( $cfg['flow_cta'] ) ? $cfg['flow_cta'] : 'Continue',
			isset( $cfg['flow_screen'] ) ? $cfg['flow_screen'] : '',
			isset( $cfg['flow_token'] ) ? $cfg['flow_token'] : null
		);
		if ( ! empty( $sent['success'] ) ) {
			// Park the session so the Flow reply can be matched back to this
			// type - see mfa_lead_flow_reply_route().
			$ctx['step'] = 'awaiting_flow';
			NWA_DB::set_pending_action( $conversation->id, 'lead_flow', $ctx, 30 );
			return;
		}
	}

	$next = mfa_lead_next_field( $cfg, $ctx );
	if ( '' === $next ) {
		mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx );
		return;
	}
	mfa_lead_ask( $user_id, $wa_number, $conversation, $cfg, $ctx, $next );
}

function niz_wa_action_founding_member( $user_id, $context ) {
	return mfa_lead_start( $user_id, 'founding_member' );
}

function niz_wa_action_advertise( $user_id, $context ) {
	return mfa_lead_start( $user_id, 'advertise' );
}

/**
 * Which field do we ask for next, given what's already captured?
 * Returns '' when every field for the type is filled.
 */
function mfa_lead_next_field( $cfg, $ctx ) {
	foreach ( $cfg['fields'] as $field ) {
		if ( empty( $ctx[ $field ] ) ) {
			return $field;
		}
	}
	return '';
}

/**
 * Ask for one field, pre-offering what we already know where we can.
 * Mirrors the contact flow's "reply OK to use it" convention so the two
 * feel like the same assistant.
 */
function mfa_lead_ask( $user_id, $wa_number, $conversation, $cfg, $ctx, $field ) {
	$known = '';
	if ( 'name' === $field ) {
		$known = niz_wa_contact_known_name( $user_id );
	} elseif ( 'email' === $field ) {
		$known = niz_wa_contact_known_email( $user_id );
	}

	$ctx['step']  = $field;
	$ctx['known'] = $known;
	NWA_DB::set_pending_action( $conversation->id, 'lead_flow', $ctx, 30 );

	if ( 'name' === $field ) {
		$msg = ( '' !== $known )
			? "First, your *name* — I have it as *{$known}*.\nReply *OK* to use it, or type a different name."
			: "First, what's your *name*?";
	} elseif ( 'email' === $field ) {
		$msg = ( '' !== $known )
			? "And your *email* — I have *{$known}*.\nReply *OK* to use it, or type another."
			: "What's your *email address*? That's how we'll reach you.";
	} else {
		$msg = isset( $cfg['detail_ask'] ) ? $cfg['detail_ask'] : 'Tell me a little more.';
	}

	nwa_send_message( $user_id, $wa_number, $msg );
}

/** Capture is complete: store it and confirm. */
function mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx ) {
	NWA_DB::set_pending_action( $conversation->id, null );

	mfa_lead_capture( $user_id, $ctx['type'], array(
		'name'   => isset( $ctx['name'] ) ? $ctx['name'] : '',
		'email'  => isset( $ctx['email'] ) ? $ctx['email'] : '',
		'detail' => isset( $ctx['detail'] ) ? $ctx['detail'] : '',
		'phone'  => $wa_number,
	) );

	$message = $cfg['done'];

	// Everything captured here is a warm lead who may still not have an
	// account. Nudge once, and only when they actually need it.
	if ( ! niz_wa_is_member( $user_id ) ) {
		$message .= "\n\nWhile you wait — reply *REGISTER* to set up your free Masjid4All account and start earning Barakah points.";
	}

	nwa_send_message( $user_id, $wa_number, $message );
}


/* ---------------- Native Flow reply ----------------
   Priority 4: ahead of niz_wa_contact_flow_reply_route() at 5, which claims
   ANY inbound message carrying an nfm_reply and would otherwise try to read
   an advertise submission as a Contact Us one and answer "Sorry, I couldn't
   read that submission properly."

   Guarded twice so it can never steal a genuine Contact Us submission: a
   'lead_flow' session must be open AND parked at the awaiting_flow step. The
   flow_token is checked too when Meta echoes one back. */

add_filter( 'nwa_route_message_override', 'mfa_lead_flow_reply_route', 4, 5 );

function mfa_lead_flow_reply_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override || ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}
	if ( 'lead_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		return $override;
	}

	$interactive = json_decode( (string) $message_text, true );
	if ( ! is_array( $interactive ) || ! isset( $interactive['nfm_reply']['response_json'] ) ) {
		return $override;
	}

	$ctx = json_decode( (string) $conversation->pending_context, true );
	$ctx = is_array( $ctx ) ? $ctx : array();
	if ( ! isset( $ctx['step'] ) || 'awaiting_flow' !== $ctx['step'] ) {
		return $override;
	}

	$cfg = mfa_lead_type( isset( $ctx['type'] ) ? $ctx['type'] : '' );
	if ( ! $cfg ) {
		return $override;
	}

	$fields = json_decode( (string) $interactive['nfm_reply']['response_json'], true );
	$fields = is_array( $fields ) ? $fields : array();

	// When Meta echoes the token we sent, insist it matches this type.
	if ( ! empty( $cfg['flow_token'] ) && ! empty( $fields['flow_token'] )
		&& $fields['flow_token'] !== $cfg['flow_token'] ) {
		return $override;
	}

	$name  = sanitize_text_field( isset( $fields['name'] ) ? $fields['name'] : '' );
	$email = sanitize_email( isset( $fields['email'] ) ? $fields['email'] : '' );

	if ( '' === $name || '' === $email || ! is_email( $email ) ) {
		// Don't lose the lead over a malformed submission - fall back to
		// asking for whatever is still missing, one question at a time.
		if ( '' !== $name ) {
			$ctx['name'] = $name;
		}
		$next = mfa_lead_next_field( $cfg, $ctx );
		if ( '' === $next ) {
			mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx );
			return '';
		}
		nwa_send_message( $user_id, $wa_number, "Sorry, I couldn't read that form properly — let me just ask directly." );
		mfa_lead_ask( $user_id, $wa_number, $conversation, $cfg, $ctx, $next );
		return '';
	}

	$ctx['name']  = $name;
	$ctx['email'] = $email;
	if ( ! empty( $fields['detail'] ) ) {
		$ctx['detail'] = sanitize_text_field( $fields['detail'] );
	}

	mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx );
	return '';
}
// Priority 23: after the travel planner (22), before the contact flow (25).
// Ordering is mostly cosmetic since every handler guards on its own pending
// action key, but keeping them in a stable sequence makes the chain readable.
add_filter( 'nwa_route_message_override', 'mfa_lead_route', 23, 5 );

/**
 * Drives an active lead capture. Returns '' to claim the message (we send
 * our own replies), or the unchanged $override when no session is live.
 */
function mfa_lead_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override;
	}
	if ( ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}
	if ( 'lead_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		return $override;
	}

	$ctx  = json_decode( (string) $conversation->pending_context, true );
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$cfg  = mfa_lead_type( isset( $ctx['type'] ) ? $ctx['type'] : '' );
	$text = trim( (string) $message_text );

	if ( ! $cfg ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		return $override;
	}

	// Exact match only, so a real name or business description can't trip it.
	if ( in_array( strtolower( $text ), array( 'stop', 'cancel', 'exit', 'quit', 'batal', 'no' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "No problem, I've cancelled that. 👍\n\nJust message me anytime if you change your mind." );
		return '';
	}

	$step = isset( $ctx['step'] ) ? $ctx['step'] : '';

	if ( 'confirm' === $step ) {
		// A button tap arrives as the button's own title text (see
		// NWA_Webhook's interactive handling), so the configured titles are
		// what we match on, alongside the typed equivalents.
		$yes = isset( $cfg['buttons']['yes'] ) ? strtolower( $cfg['buttons']['yes'] ) : '';
		$no  = isset( $cfg['buttons']['no'] ) ? strtolower( $cfg['buttons']['no'] ) : '';
		$low = strtolower( $text );

		if ( '' !== $no && $low === $no ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number,
				isset( $cfg['decline'] ) ? $cfg['decline'] : "No problem. 👍" );
			return '';
		}

		$said_yes = ( '' !== $yes && $low === $yes ) || niz_wa_contact_is_affirmative( $text );
		if ( ! $said_yes ) {
			$prompt = ! empty( $cfg['buttons'] )
				? "Just tap *{$cfg['buttons']['yes']}* or *{$cfg['buttons']['no']}* above — or type *stop* to cancel."
				: "Reply *YES* to continue, or *STOP* if you'd rather not.";
			nwa_send_message( $user_id, $wa_number, $prompt );
			return '';
		}

		mfa_lead_begin_capture( $user_id, $wa_number, $conversation, $cfg, $ctx );
		return '';
	}

	// The user was sent a native Flow form but typed a message instead of
	// submitting it. Drop to the text path rather than leaving them stuck
	// waiting on a form they may have dismissed.
	if ( 'awaiting_flow' === $step ) {
		$next = mfa_lead_next_field( $cfg, $ctx );
		if ( '' === $next ) {
			mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx );
			return '';
		}
		mfa_lead_ask( $user_id, $wa_number, $conversation, $cfg, $ctx, $next );
		return '';
	}

	if ( in_array( $step, array( 'name', 'email', 'detail' ), true ) ) {
		$known = isset( $ctx['known'] ) ? (string) $ctx['known'] : '';
		$value = ( '' !== $known && niz_wa_contact_is_affirmative( $text ) ) ? $known : $text;

		if ( 'email' === $step ) {
			$value = sanitize_email( $value );
			if ( '' === $value || ! is_email( $value ) ) {
				nwa_send_message( $user_id, $wa_number, "That doesn't look like a valid email. Please type your *email address*." );
				return '';
			}
		} else {
			$value = sanitize_text_field( $value );
			if ( '' === $value ) {
				$retry = ( 'detail' === $step && isset( $cfg['detail_retry'] ) )
					? $cfg['detail_retry']
					: "Please type your *name*.";
				nwa_send_message( $user_id, $wa_number, $retry );
				return '';
			}
		}

		$ctx[ $step ] = $value;
		unset( $ctx['known'] );

		$next = mfa_lead_next_field( $cfg, $ctx );
		if ( '' === $next ) {
			mfa_lead_finish( $user_id, $wa_number, $conversation, $cfg, $ctx );
			return '';
		}
		mfa_lead_ask( $user_id, $wa_number, $conversation, $cfg, $ctx, $next );
		return '';
	}

	// Unrecognised step — drop the session rather than trapping the user.
	NWA_DB::set_pending_action( $conversation->id, null );
	return $override;
}

/* ---------------- Front-end CTA ----------------
   Same .mfa-assist-* modal component the directory "Add Your Mosque/Business/
   Website" CTAs use (dir-assist-v1.css / dir-assist-v1.js) — deliberately not
   a new component. The pattern is proven and the visitor already recognises
   it: a button opens a short explanation, and the only action in it is
   "Continue on WhatsApp". */

/**
 * [mfa_lead_cta type="founding_member|advertise" style="card|inline"]
 *
 * style="card" wraps the button in a tinted card with its own heading, for
 * dropping into a sidebar column. style="inline" is the bare button, for
 * places that already have their own surrounding copy.
 */
add_shortcode( 'mfa_lead_cta', 'mfa_lead_cta_shortcode' );
function mfa_lead_cta_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'type'  => '',
		'style' => 'inline',
		'class' => 'mfa-btn mfa-btn-primary',
	), $atts, 'mfa_lead_cta' );

	$cfg = mfa_lead_type( $atts['type'] );
	if ( ! $cfg ) {
		return '';
	}

	$modal_id = 'mfa-lead-' . sanitize_html_class( $atts['type'] );
	$wa_link  = 'https://wa.me/60189897579?text=' . rawurlencode( $cfg['wa_keyword'] );

	// Same inline WhatsApp glyph the directory CTA uses; kept inline rather
	// than added to a sprite so the component stays self-contained.
	$wa_icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.43.06-.66.31-.23.24-.86.84-.86 2.06 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z"/></svg>';

	ob_start();

	if ( 'ad' === $atts['style'] ) {
		// Sized and shaped like an ad slot (full width, 8px radius, same
		// gap) so it reads as part of the ads block rather than a banner
		// bolted above it - see .enaizi-ads-container in ads.php.
		?>
		<div class="mfa-ad-promo">
			<p class="mfa-ad-promo-text">You can have your business featured here.</p>
			<button type="button" class="mfa-btn mfa-btn-primary mfa-ad-promo-btn mfa-assist-open" data-target="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>"><?php echo esc_html( $cfg['cta_label'] ); ?></button>
		</div>
		<?php
	} elseif ( 'card' === $atts['style'] ) {
		?>
		<div class="mfa-card mfa-card--tinted mfa-lead-card">
			<div class="mfa-lead-card-emoji"><?php echo $cfg['emoji']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<h3 class="mfa-lead-card-title"><?php echo esc_html( $cfg['cta_title'] ); ?></h3>
			<p class="mfa-lead-card-text"><?php echo esc_html( $cfg['cta_text'] ); ?></p>
			<button type="button" class="mfa-btn mfa-btn-primary mfa-btn--block mfa-assist-open" data-target="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>"><?php echo esc_html( $cfg['cta_label'] ); ?></button>
		</div>
		<?php
	} else {
		?>
		<button type="button" class="<?php echo esc_attr( $atts['class'] ); ?> mfa-assist-open" data-target="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>"><?php echo esc_html( $cfg['cta_label'] ); ?></button>
		<?php
	}

	// style="ad" returns the trigger only. Its modal is emitted by ads.php
	// AFTER the ads container closes: a full-screen overlay has no business
	// living inside a narrow sidebar element. It works today because the
	// overlay is position:fixed, but a single transform/filter/contain on
	// .enaizi-ads-container would make fixed resolve against that container
	// instead of the viewport and trap the dialog in the column.
	if ( 'ad' !== $atts['style'] ) {
		echo mfa_lead_modal_html( $atts['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	return ob_get_clean();
}

/**
 * The dialog markup on its own, so a caller can place it outside whatever
 * container the trigger sits in. Safe to call once per type per page.
 */
function mfa_lead_modal_html( $type ) {
	$cfg = mfa_lead_type( $type );
	if ( ! $cfg ) {
		return '';
	}

	$modal_id = 'mfa-lead-' . sanitize_html_class( $type );
	$wa_link  = 'https://wa.me/60189897579?text=' . rawurlencode( $cfg['wa_keyword'] );
	$wa_icon  = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.43.06-.66.31-.23.24-.86.84-.86 2.06 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z"/></svg>';

	ob_start();
	?>
	<div class="mfa-assist-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo esc_attr( $cfg['cta_title'] ); ?>">
		<div class="mfa-assist-modal">
			<button type="button" class="mfa-assist-close" aria-label="Close">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
			<div class="mfa-assist-emoji"><?php echo $cfg['emoji']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<h3 class="mfa-assist-title"><?php echo esc_html( $cfg['cta_title'] ); ?></h3>
			<p class="mfa-assist-text"><?php echo esc_html( $cfg['cta_text'] ); ?></p>
			<a class="mfa-assist-cta" href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Continue on WhatsApp</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
