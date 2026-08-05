<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_Router {

	private static $yes_words = array( 'yes', 'y', 'ya', 'yep', 'ok', 'okay', 'sure', 'boleh' );
	private static $no_words  = array( 'no', 'n', 'nope', 'tak', 'tidak', 'jangan' );

	public static function handle_message( $user_id, $wa_number, $conversation, $message_text, $msg_type, $wa_message_id = null ) {
		if ( 'text' !== $msg_type || '' === trim( (string) $message_text ) ) {
			return;
		}

		/**
		 * Lets a site plugin intercept a message by custom pattern (e.g. a
		 * dynamic "VERIFY-XXXX" code) instead of niz-wa's exact-keyword
		 * action matching, and short-circuit normal routing entirely.
		 * Return null to leave the message for normal routing, or a string
		 * (possibly '') to claim it — the string is sent as the reply.
		 */
		$override = apply_filters( 'nwa_route_message_override', null, $user_id, $wa_number, $message_text, $conversation );

		if ( null !== $override ) {
			if ( '' !== $override ) {
				nwa_send_message( $user_id, $wa_number, $override );
			}
			self::maybe_refresh_profile( $conversation, $user_id );
			return;
		}

		$pending = NWA_DB::get_active_pending_action( $conversation );

		if ( $pending ) {
			self::handle_pending_confirmation( $user_id, $wa_number, $conversation, $pending, $message_text );
			self::maybe_refresh_profile( $conversation, $user_id );
			return;
		}

		$action = NWA_DB::get_action_by_keyword( $message_text );

		if ( ! $action ) {
			$context = NWA_DB::get_recent_context( $conversation->id, NWA_AI::HISTORY_MESSAGE_LIMIT, NWA_AI::HISTORY_MINUTES_CUTOFF );
			$result  = NWA_AI::classify_intent( $message_text, $context );

			if ( $result['intent'] && $result['confidence'] >= NWA_AI::CONFIDENCE_THRESHOLD ) {
				$action = NWA_DB::get_action_by_intent( $result['intent'] );
			}
		}

		if ( $action ) {
			self::start_or_run_action( $user_id, $wa_number, $conversation, $action );
			self::maybe_refresh_profile( $conversation, $user_id );
			return;
		}

		$context = NWA_DB::get_recent_context( $conversation->id, NWA_AI::HISTORY_MESSAGE_LIMIT, NWA_AI::HISTORY_MINUTES_CUTOFF );
		$profile = NWA_DB::get_profile( $user_id );

		// Re-trigger the typing indicator right before the slowest step — open-ended
		// Q&A generation — since it resets WhatsApp's ~25s auto-clear window and the
		// first trigger (on message receipt, before intent classification) may otherwise
		// expire before this reply is ready.
		if ( $wa_message_id ) {
			NWA_Sender::mark_read_with_typing( $wa_message_id );
		}

		$answer  = NWA_AI::answer_question( $message_text, $context, $profile['summary'] );

		if ( $answer ) {
			nwa_send_message( $user_id, $wa_number, $answer );
		}

		self::maybe_refresh_profile( $conversation, $user_id );
	}

	private static function start_or_run_action( $user_id, $wa_number, $conversation, $action ) {
		if ( $action->requires_confirmation ) {
			NWA_DB::set_pending_action( $conversation->id, $action->intent_key, array() );
			nwa_send_message( $user_id, $wa_number, $action->confirm_message );
			return;
		}

		self::execute_action( $user_id, $wa_number, $action, array() );
	}

	private static function handle_pending_confirmation( $user_id, $wa_number, $conversation, $pending_intent_key, $message_text ) {
		$normalized = strtolower( trim( $message_text ) );
		$is_yes     = in_array( $normalized, self::$yes_words, true );
		$is_no      = in_array( $normalized, self::$no_words, true );

		if ( ! $is_yes && ! $is_no ) {
			nwa_send_message( $user_id, $wa_number, "Sorry, please reply Yes or No." );
			return;
		}

		$action = NWA_DB::get_action_by_intent( $pending_intent_key );
		NWA_DB::set_pending_action( $conversation->id, null );

		if ( ! $action ) {
			return;
		}

		if ( $is_no ) {
			nwa_send_message( $user_id, $wa_number, "Okay, cancelled." );
			return;
		}

		$context = ! empty( $conversation->pending_context ) ? json_decode( $conversation->pending_context, true ) : array();
		self::execute_action( $user_id, $wa_number, $action, $context ?: array() );
	}

	private static function execute_action( $user_id, $wa_number, $action, $context ) {
		$callback = $action->callback_function;

		if ( ! function_exists( $callback ) ) {
			nwa_send_message( $user_id, $wa_number, "Sorry, something went wrong on our end. Please try again later." );
			error_log( "Nemkad WA: registered action '{$action->intent_key}' points to missing function '{$callback}'." );
			return;
		}

		$reply = call_user_func( $callback, $user_id, $context );

		if ( is_string( $reply ) && '' !== $reply ) {
			nwa_send_message( $user_id, $wa_number, $reply );
		}
	}

	private static function maybe_refresh_profile( $conversation, $user_id ) {
		NWA_AI::maybe_update_profile( $conversation->id, $user_id );
	}
}
