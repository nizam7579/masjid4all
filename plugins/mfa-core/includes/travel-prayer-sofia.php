<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sofia's side of the travel prayer planner: the WhatsApp conversation that
 * collects a journey, and the button on the site that starts it.
 *
 * travel-prayer.php holds the computation; this file only gathers the five
 * things it needs - from, to, when, how, and how long - then hands over.
 *
 * Built as a step-by-step text conversation rather than a native WhatsApp Flow
 * for the same reason Contact-Us was (text first in b05a490, Flow added later
 * in 748f059): a Flow has to be created and published in Meta's dashboard and
 * fails with error 131009 if it belongs to another WABA, so it cannot ship
 * from here. The steps below are shaped so a Flow can replace the intake later
 * without touching the planner.
 */

/**
 * Registers the planner as a measurable interest signal.
 *
 * Declared here rather than inside mfa_lead_types() so the planner owns its
 * own tracking, and via the filter so registering it cannot disturb the two
 * capture flows. 'signal' => true means it is counted but never captured:
 * there is no wa_keyword, no fields and no CTA, so none of the lead
 * machinery can enter it - see mfa_lead_record_signal().
 */
add_filter( 'mfa_lead_types', 'mfa_travel_register_lead_type' );
function mfa_travel_register_lead_type( $types ) {
	$types['travel_planner'] = array(
		'label'  => 'Solat for travellers',
		'emoji'  => '✈️',
		'signal' => true,
	);

	return $types;
}

/** The business number behind Sofia, matching the other deep links on the site. */
function mfa_travel_wa_number() {
	return apply_filters( 'mfa_travel_wa_number', '60189897579' );
}

/**
 * The phrase the deep link pre-fills. Kept in one place because it has to
 * match the action's keywords for Sofia to recognise it.
 */
function mfa_travel_wa_trigger() {
	return 'Plan my solat for travel';
}

/**
 * Deep link that opens WhatsApp with the trigger phrase ready to send.
 *
 * @param string $source Which page sent them - appended so we can tell what
 *                       actually converts, something we are blind on today.
 */
function mfa_travel_wa_link( $source = '' ) {
	$text = mfa_travel_wa_trigger();

	if ( '' !== $source ) {
		$text .= ' [' . $source . ']';
	}

	return 'https://wa.me/' . mfa_travel_wa_number() . '?text=' . rawurlencode( $text );
}

/* -------------------------------------------------------------------------
 * Web entry point
 * ---------------------------------------------------------------------- */

/**
 * [mfa_travel_cta source="prayer-times"] - the button that starts the whole
 * thing. Deliberately states the pain it solves rather than naming the
 * feature; "plan my prayers" means nothing to someone who has not yet
 * realised the time zone is going to catch them out.
 */
add_shortcode( 'mfa_travel_cta', 'mfa_travel_cta_shortcode' );
function mfa_travel_cta_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'source' => '',
			'title'  => 'Travelling soon?',
			'text'   => 'Crossing time zones changes when each solat falls, and a long journey may allow you to shorten or combine them. Tell Sofia your journey on WhatsApp and get a prayer plan for it.',
			'button' => 'Plan my solat on WhatsApp',
		),
		$atts,
		'mfa_travel_cta'
	);

	$link = mfa_travel_wa_link( sanitize_key( $atts['source'] ) );

	ob_start();
	?>
	<div class="mfa-band mfa-band--tinted">
		<h2 class="mfa-band-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<p class="mfa-band-text"><?php echo esc_html( $atts['text'] ); ?></p>
		<a class="mfa-btn mfa-btn-primary" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">
			<?php echo esc_html( $atts['button'] ); ?>
		</a>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Sofia conversation
 * ---------------------------------------------------------------------- */

/**
 * Entry point for the 'travel_prayer' action. Sends its own message and
 * returns '' so NWA_Router adds nothing after it.
 */
function niz_wa_action_travel_prayer( $user_id, $context = array() ) {
	if ( ! class_exists( 'NWA_DB' ) ) {
		return "Sorry, I can't plan journeys right now. Please try again shortly.";
	}

	$conversation = NWA_DB::get_conversation_by_user( $user_id );

	if ( ! $conversation ) {
		return "Sorry, I can't plan journeys right now. Please try again shortly.";
	}

	nwa_send_message(
		$user_id,
		$conversation->wa_number,
		"Assalamualaikum! 🕋\n\nLet's plan your solat for the journey. I'll ask five short questions — reply *stop* anytime to cancel.\n\nFirst, which *city* are you travelling *from*?"
	);

	NWA_DB::set_pending_action( $conversation->id, 'travel_flow', array( 'step' => 'from' ), 30 );

	return '';
}

// Priority 22: after whatsapp-verify (10), account (15) and directory (20),
// before contact (25) - the same ordering rule the other sessions follow.
add_filter( 'nwa_route_message_override', 'niz_wa_travel_route', 22, 5 );

/**
 * Drives the travel steps while a 'travel_flow' session is open.
 */
function niz_wa_travel_route( $override, $user_id, $wa_number, $message_text, $conversation ) {
	if ( null !== $override ) {
		return $override;
	}

	if ( ! class_exists( 'NWA_DB' ) ) {
		return $override;
	}

	if ( 'travel_flow' !== NWA_DB::get_active_pending_action( $conversation ) ) {
		return $override;
	}

	$ctx  = json_decode( (string) $conversation->pending_context, true );
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$step = isset( $ctx['step'] ) ? $ctx['step'] : '';
	$text = trim( (string) $message_text );

	if ( in_array( strtolower( $text ), array( 'stop', 'cancel', 'exit', 'quit', 'batal' ), true ) ) {
		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "No problem, I've cancelled that. 👍\n\nMessage *travel* anytime to plan a journey." );
		return '';
	}

	if ( 'from' === $step ) {
		if ( mb_strlen( $text ) < 2 ) {
			nwa_send_message( $user_id, $wa_number, "Please type the *city* you're travelling from — for example *Kuala Lumpur*." );
			return '';
		}

		$ctx = array( 'step' => 'to', 'from' => sanitize_text_field( $text ) );
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );
		nwa_send_message( $user_id, $wa_number, "Got it — *{$ctx['from']}*.\n\nAnd which *city* are you travelling *to*?" );
		return '';
	}

	if ( 'to' === $step ) {
		if ( mb_strlen( $text ) < 2 ) {
			nwa_send_message( $user_id, $wa_number, "Please type the *city* you're travelling to." );
			return '';
		}

		$ctx['to']   = sanitize_text_field( $text );
		$ctx['step'] = 'when';
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );
		nwa_send_message( $user_id, $wa_number, "*{$ctx['from']}* ➡️ *{$ctx['to']}*.\n\nWhen do you *depart*? Please give the date and time — for example *10 Sep 2026 23:30*." );
		return '';
	}

	if ( 'when' === $step ) {
		$when = mfa_travel_parse_when( $text );

		if ( ! $when ) {
			nwa_send_message( $user_id, $wa_number, "I couldn't read that as a date and time. Please try like *10 Sep 2026 23:30*, or *tomorrow 9pm*." );
			return '';
		}

		$ctx['date'] = $when['date'];
		$ctx['time'] = $when['time'];
		$ctx['step'] = 'mode';
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );

		// Echo the parsed date back - cheap confirmation that costs no extra
		// step, and a misread date would quietly wreck the whole plan.
		nwa_send_message(
			$user_id,
			$wa_number,
			"Departing *" . $when['label'] . "*.\n\nHow are you travelling? Reply *flight*, *car*, *bus* or *train*."
		);
		return '';
	}

	if ( 'mode' === $step ) {
		$mode = mfa_travel_parse_mode( $text );

		if ( '' === $mode ) {
			nwa_send_message( $user_id, $wa_number, "Please reply *flight*, *car*, *bus* or *train*." );
			return '';
		}

		$ctx['mode'] = $mode;
		$ctx['step'] = 'duration';
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );
		nwa_send_message( $user_id, $wa_number, "By *{$mode}*, noted.\n\nRoughly how *long* is the journey? For example *9.5 hours*, *45 minutes*, or *2h30*." );
		return '';
	}

	if ( 'duration' === $step ) {
		$hours = mfa_travel_parse_duration( $text );

		if ( null === $hours ) {
			nwa_send_message( $user_id, $wa_number, "I couldn't read that as a length of time. Please try like *9.5 hours*, *45 minutes*, or *2h30*." );
			return '';
		}

		nwa_send_message( $user_id, $wa_number, "Give me a moment — working out your prayer times… 🕋" );

		$layover_from = isset( $ctx['landed_at'] ) ? (string) $ctx['landed_at'] : '';
		$plan         = mfa_travel_plan( $ctx['from'], $ctx['to'], $ctx['date'], $ctx['time'], $hours, $ctx['mode'], $layover_from );

		if ( is_wp_error( $plan ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number, $plan->get_error_message() . "\n\nMessage *travel* to start again." );
			return '';
		}

		nwa_send_message( $user_id, $wa_number, mfa_travel_format_reply( $plan ) );

		// Counted only once a plan has actually been produced and sent.
		// Starting the flow is curiosity; receiving a plan is use, and use is
		// what the dashboard's Interest panel is meant to measure.
		if ( function_exists( 'mfa_lead_record_signal' ) ) {
			mfa_lead_record_signal( $user_id, 'travel_planner', $ctx['from'] . ' to ' . $ctx['to'] );
		}

		// Keep the session open for a connecting flight. Planning each leg in
		// turn beats asking for a whole itinerary up front: the traveller
		// answers the same short questions again, and the wait between legs
		// becomes something we can rule on rather than a gap in a single long
		// journey - eight hours in Dubai is time to pray properly on the
		// ground, not a reason to pray in a seat.
		$next = array(
			'step'      => 'next_leg',
			'mode'      => $ctx['mode'],
			'at_city'   => $ctx['to'],
			'landed_at' => $plan['arrive_iso'],
			'leg'       => isset( $ctx['leg'] ) ? (int) $ctx['leg'] + 1 : 2,
		);

		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $next, 30 );

		nwa_send_message(
			$user_id,
			$wa_number,
			"Is *{$ctx['to']}* your final destination?\n\nReply *done* if so — or tell me your *next destination* and I'll plan the onward leg, including your wait at the airport."
		);

		return '';
	}

	if ( 'next_leg' === $step ) {
		if ( in_array( strtolower( $text ), array( 'done', 'no', 'final', 'finish', 'that\'s all', 'thats all', 'selesai', 'tamat' ), true ) ) {
			NWA_DB::set_pending_action( $conversation->id, null );
			nwa_send_message( $user_id, $wa_number, "Safe travels, and may Allah accept your prayers. 🤲\n\nMessage *travel* anytime to plan another journey." );
			return '';
		}

		if ( mb_strlen( $text ) < 2 ) {
			nwa_send_message( $user_id, $wa_number, "Please type your *next destination*, or reply *done* if you've arrived." );
			return '';
		}

		$ctx['to']   = sanitize_text_field( $text );
		$ctx['from'] = $ctx['at_city'];
		$ctx['step'] = 'next_when';
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );

		$landed = mfa_travel_friendly_landing( $ctx['landed_at'] );

		nwa_send_message(
			$user_id,
			$wa_number,
			"*{$ctx['from']}* ➡️ *{$ctx['to']}*.\n\nYou land in {$ctx['from']} at *{$landed}* local time. What time does your onward flight *depart*? Give the time in {$ctx['from']}'s local time — for example *12:15*, or *tomorrow 08:00*."
		);
		return '';
	}

	if ( 'next_when' === $step ) {
		// Interpreted against the landing moment, so "08:00" means the next
		// 08:00 after arriving rather than 08:00 today wherever the server is.
		$when = mfa_travel_parse_when( $text, $ctx['landed_at'] );

		if ( ! $when ) {
			nwa_send_message( $user_id, $wa_number, "I couldn't read that as a time. Please try like *12:15*, or *tomorrow 08:00*." );
			return '';
		}

		$ctx['date'] = $when['date'];
		$ctx['time'] = $when['time'];
		$ctx['step'] = 'duration';
		NWA_DB::set_pending_action( $conversation->id, 'travel_flow', $ctx, 30 );

		nwa_send_message( $user_id, $wa_number, "Departing *" . $when['label'] . "*.\n\nAnd roughly how *long* is that flight? For example *7 hours* or *6h45*." );
		return '';
	}

	return $override;
}

/** "Fri, 11 Sep 04:00" from the stored ISO landing moment. */
function mfa_travel_friendly_landing( $iso ) {
	$dt = date_create_immutable_from_format( 'Y-m-d H:i', (string) $iso );

	return $dt ? $dt->format( 'D, j M H:i' ) : (string) $iso;
}

/* -------------------------------------------------------------------------
 * Parsers - people type times the way they say them, not the way a field
 * wants them.
 * ---------------------------------------------------------------------- */

/**
 * Read a departure date and time from free text.
 *
 * @return array|null date (Y-m-d), time (H:i), label (what we echo back).
 */
function mfa_travel_parse_when( $text, $base_iso = '' ) {
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return null;
	}

	// strtotime reads 10/09/2026 as 9 October - the American order. Our
	// travellers mean 10 September, and a silently shifted month would produce
	// a confident plan for the wrong day. Rewrite d/m/Y to an unambiguous form
	// before parsing.
	$text = preg_replace_callback(
		'#\b(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})\b#',
		function ( $m ) {
			$day   = (int) $m[1];
			$month = (int) $m[2];

			// Only reorder when it is genuinely d/m - if the first number is
			// above 12 it can only be a day, and if the second is above 12 the
			// text was already m/d and is left alone.
			if ( $month > 12 ) {
				return $m[0];
			}

			return sprintf( '%04d-%02d-%02d', (int) $m[3], $month, $day );
		},
		$text
	);

	// On a connecting leg the clock that matters is the one at the airport
	// they are sitting in, so relative answers resolve against the landing
	// moment: "08:00" means the next 08:00 after arriving, not 08:00 today
	// wherever the server happens to be.
	// Both branches must live in the same frame as the gmdate() calls below.
	// current_time('timestamp') is already offset so gmdate reads back as site
	// local; pinning the landing string to UTC makes the connecting base
	// round-trip identically instead of shifting by the server's offset.
	$connecting = ( '' !== $base_iso );
	$base       = $connecting ? strtotime( (string) $base_iso . ' UTC' ) : current_time( 'timestamp' );

	if ( false === $base ) {
		$base = current_time( 'timestamp' );
	}

	$ts = strtotime( $text, $base );

	if ( false === $ts ) {
		return null;
	}

	if ( $connecting ) {
		// A bare "08:00" resolves to the landing date; if that has already
		// passed by the time they land, they mean the following morning.
		if ( $ts < $base ) {
			$ts += DAY_IN_SECONDS;
		}
	} elseif ( $ts < $base - ( 6 * HOUR_IN_SECONDS ) ) {
		// Refuse a departure in the past, with a few hours' grace for someone
		// already under way - but "yesterday" is a typo, not a journey.
		return null;
	}

	return array(
		'date'  => gmdate( 'Y-m-d', $ts ),
		'time'  => gmdate( 'H:i', $ts ),
		'label' => gmdate( 'D, j M Y H:i', $ts ),
	);
}

/** Normalise the transport answer, in English or Malay. */
function mfa_travel_parse_mode( $text ) {
	$t = strtolower( trim( (string) $text ) );

	$modes = array(
		'flight' => array( 'flight', 'fly', 'plane', 'aeroplane', 'airplane', 'kapal terbang', 'penerbangan' ),
		'car'    => array( 'car', 'drive', 'driving', 'kereta', 'memandu' ),
		'bus'    => array( 'bus', 'coach', 'bas' ),
		'train'  => array( 'train', 'rail', 'ktm', 'ets', 'keretapi' ),
	);

	foreach ( $modes as $mode => $words ) {
		foreach ( $words as $word ) {
			if ( false !== strpos( $t, $word ) ) {
				return $mode;
			}
		}
	}

	return '';
}

/**
 * Read a journey length in hours from free text: "9.5", "9.5 hours",
 * "45 minutes", "2h30", "1 jam 30 minit".
 *
 * @return float|null
 */
function mfa_travel_parse_duration( $text ) {
	$t = strtolower( trim( (string) $text ) );

	if ( '' === $t ) {
		return null;
	}

	// Hours *and* minutes together: "2h30", "2h 30m", "1 jam 30 minit".
	// Both parts are required here so that "9.5 hours" cannot fall through to
	// it - the old single pattern skipped past "9." and matched "5 hours",
	// turning a nine-and-a-half hour flight into five.
	if ( preg_match( '/(\d+)\s*(?:h|hr|hrs|hour|hours|jam)\s*(\d+)\s*(?:m|min|mins|minute|minutes|minit)?\b/u', $t, $m ) ) {
		$hours = (float) $m[1] + ( (float) $m[2] / 60 );

		return $hours > 0 ? $hours : null;
	}

	// Decimal or whole hours: "9.5 hours", "2 jam".
	if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:h|hr|hrs|hour|hours|jam)\b/u', $t, $m ) ) {
		$hours = (float) str_replace( ',', '.', $m[1] );

		return $hours > 0 ? $hours : null;
	}

	// "45 minutes"
	if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:m|min|mins|minute|minutes|minit)\b/u', $t, $m ) ) {
		$hours = (float) str_replace( ',', '.', $m[1] ) / 60;

		return $hours > 0 ? $hours : null;
	}

	// A bare number is hours - the question asked for hours.
	if ( preg_match( '/(\d+(?:[.,]\d+)?)/u', $t, $m ) ) {
		$hours = (float) str_replace( ',', '.', $m[1] );

		return $hours > 0 ? $hours : null;
	}

	return null;
}
