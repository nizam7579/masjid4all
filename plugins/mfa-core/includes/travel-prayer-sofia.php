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
	<div class="mfa-travel-cta">
		<h2 class="mfa-travel-cta-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<p class="mfa-travel-cta-text"><?php echo esc_html( $atts['text'] ); ?></p>
		<a class="mfa-btn mfa-btn-primary mfa-travel-cta-btn" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">
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

		NWA_DB::set_pending_action( $conversation->id, null );
		nwa_send_message( $user_id, $wa_number, "Give me a moment — working out your prayer times… 🕋" );

		$plan = mfa_travel_plan( $ctx['from'], $ctx['to'], $ctx['date'], $ctx['time'], $hours, $ctx['mode'] );

		if ( is_wp_error( $plan ) ) {
			nwa_send_message( $user_id, $wa_number, $plan->get_error_message() . "\n\nMessage *travel* to start again." );
			return '';
		}

		nwa_send_message( $user_id, $wa_number, mfa_travel_format_reply( $plan ) );
		return '';
	}

	return $override;
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
function mfa_travel_parse_when( $text ) {
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

	// Resolve relative words ("tomorrow 9pm") against site time rather than
	// UTC, so "tomorrow" means what the traveller means.
	$base = current_time( 'timestamp' );
	$ts   = strtotime( $text, $base );

	if ( false === $ts ) {
		return null;
	}

	// Refuse a departure in the past. A bare time ("23:30") resolves to today
	// and may just have passed, so a few hours' grace is allowed for someone
	// already on the way - but "yesterday" is a typo, not a journey.
	if ( $ts < $base - ( 6 * HOUR_IN_SECONDS ) ) {
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
