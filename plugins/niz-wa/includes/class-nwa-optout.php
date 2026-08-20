<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opt-out handling: STOP actually stops something.
 *
 * Until now "stop" only cancelled an in-progress flow. Someone replying
 * STOP to a template - with no flow running - fell through to the AI, which
 * answered conversationally, recorded nothing, and left them receiving
 * templates. A footer promising "reply STOP to opt out" would therefore
 * have been a promise the system did not keep, and the practical result is
 * worse than rude: people who cannot opt out **block the number instead**,
 * which is what actually damages the quality rating and can get a sender
 * restricted.
 *
 * ## What opting out does and does not block
 *
 * Templates are business-INITIATED - they are how marketing reaches
 * somebody who is not in a conversation - so an opt-out blocks them
 * outright, in the sender, not merely in the UI.
 *
 * Replies inside the 24-hour window are NOT blocked. That window only
 * exists because the person messaged us; refusing to answer someone who
 * has just written in would be a worse experience than the marketing they
 * opted out of, and it is not what opting out means. Staff see the opt-out
 * on the member page and decide.
 *
 * ## Why STOP is only global outside a flow
 *
 * Inside a flow, "stop" already means "cancel this" - the prompts say so.
 * Reading it as "never message me again" there would opt people out for
 * abandoning a form. With no flow running there is nothing to cancel, so
 * the plain meaning is the right one.
 */
class NWA_OptOut {

	const META_KEY = 'nwa_opted_out';

	public static function init() {
		// After every flow route (4-25) so an in-flow "stop" still cancels the
		// flow, and before the AI fallback so STOP never becomes small talk.
		add_filter( 'nwa_route_message_override', array( __CLASS__, 'route' ), 30, 5 );
	}

	/** Exact matches only - "stop" inside a sentence is not an opt-out. */
	public static function stop_words() {
		return apply_filters( 'nwa_optout_stop_words', array(
			'stop', 'unsubscribe', 'opt out', 'optout', 'berhenti', 'henti',
		) );
	}

	public static function start_words() {
		return apply_filters( 'nwa_optout_start_words', array(
			'start', 'unstop', 'subscribe', 'resume', 'mula', 'sambung',
		) );
	}

	public static function is_opted_out( $user_id ) {
		return '' !== (string) get_user_meta( (int) $user_id, self::META_KEY, true );
	}

	/** @return string|'' The GMT timestamp they opted out, or '' if they have not. */
	public static function opted_out_at( $user_id ) {
		return (string) get_user_meta( (int) $user_id, self::META_KEY, true );
	}

	public static function opt_out( $user_id ) {
		update_user_meta( (int) $user_id, self::META_KEY, gmdate( 'Y-m-d H:i:s' ) );
		do_action( 'nwa_user_opted_out', (int) $user_id );
	}

	public static function opt_in( $user_id ) {
		delete_user_meta( (int) $user_id, self::META_KEY );
		do_action( 'nwa_user_opted_in', (int) $user_id );
	}

	/**
	 * Catches a bare STOP / START when nothing else claimed the message.
	 */
	public static function route( $override, $user_id, $wa_number, $message_text, $conversation ) {
		if ( null !== $override ) {
			return $override;
		}

		$text = strtolower( trim( (string) $message_text ) );
		$text = trim( $text, ".!" );

		if ( in_array( $text, self::stop_words(), true ) ) {
			self::opt_out( $user_id );

			// Sent before the flag can matter: this is a reply to their own
			// message, inside their own window, and confirming is the whole
			// point of honouring the request.
			nwa_send_message( $user_id, $wa_number,
				"You've been unsubscribed. 👍\n\nWe won't send you any more messages.\n\nIf you change your mind, just reply *START* and we'll turn them back on." );

			return '';
		}

		if ( in_array( $text, self::start_words(), true ) && self::is_opted_out( $user_id ) ) {
			self::opt_in( $user_id );
			nwa_send_message( $user_id, $wa_number,
				"Welcome back — you're subscribed again. 🎉\n\nYou can reply *STOP* at any time." );

			return '';
		}

		return $override;
	}
}

NWA_OptOut::init();

/** Convenience wrapper, matching the plugin's nwa_* function style. */
function nwa_is_opted_out( $user_id ) {
	return NWA_OptOut::is_opted_out( $user_id );
}
