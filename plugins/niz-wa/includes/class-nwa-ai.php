<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_AI {

	const CONFIDENCE_THRESHOLD      = 0.6;
	const PROFILE_UPDATE_EVERY_N    = 8;
	const HISTORY_MESSAGE_LIMIT     = 6;
	const HISTORY_MINUTES_CUTOFF    = 45;

	/**
	 * Classify a message against the currently enabled action registry.
	 * Returns array( 'intent' => string|null, 'confidence' => float ).
	 */
	public static function classify_intent( $message_text, $context_messages = array() ) {
		$actions = NWA_DB::get_enabled_actions();

		// 'start' already has full keyword coverage (start/help/menu) and is
		// checked before AI classification ever runs — excluding it here stops
		// generic "what is X" questions from being misread as greetings.
		$actions = array_values( array_filter( $actions, function( $a ) {
			return 'start' !== $a->intent_key;
		} ) );

		if ( empty( $actions ) ) {
			return array( 'intent' => null, 'confidence' => 0 );
		}

		$intent_list = '';
		foreach ( $actions as $action ) {
			$intent_list .= "- {$action->intent_key}: {$action->description}\n";
		}

		$system = "You are an intent classifier for a WhatsApp assistant.\n"
			. "Given the user's message, match it to ONE of the following intents, "
			. "or return \"none\" if nothing matches with reasonable confidence.\n\n"
			. "Intents:\n{$intent_list}\n"
			. "Respond ONLY with JSON, no other text:\n"
			. '{"intent": "<intent_key or none>", "confidence": <0.0 to 1.0>}';

		$history_text = self::format_history( $context_messages );
		$user_prompt   = $history_text ? "{$history_text}\n\nCurrent message: {$message_text}" : $message_text;

		$raw = self::call_ai( $system, $user_prompt );
		$parsed = json_decode( self::strip_json_fences( $raw ), true );

		if ( ! is_array( $parsed ) || empty( $parsed['intent'] ) || 'none' === $parsed['intent'] ) {
			return array( 'intent' => null, 'confidence' => 0 );
		}

		return array(
			'intent'     => sanitize_key( $parsed['intent'] ),
			'confidence' => (float) ( $parsed['confidence'] ?? 0 ),
		);
	}

	/**
	 * Fallback Q&A: grounded on the knowledge base, with conversation history
	 * and (if available) the user's profile summary as context.
	 */
	public static function answer_question( $message_text, $context_messages = array(), $profile_summary = array() ) {
		$kb_results = NWA_DB::search_knowledge( $message_text, 5 );

		$kb_text = '';
		foreach ( $kb_results as $row ) {
			$kb_text .= "### {$row->title}\n{$row->content}\n\n";
		}

		$profile_line = self::format_profile_line( $profile_summary );
		$history_text = self::format_history( $context_messages );

		if ( ! empty( $kb_text ) ) {
			$system = "Answer using ONLY the information provided below. If the answer isn't "
				. "contained in this information, say you don't have that detail and offer to "
				. "connect them with a human — do not guess or use outside knowledge for "
				. "company/product specific questions.\n\n"
				. "Reference information:\n{$kb_text}";
		} else {
			$system = "Answer the user's question clearly and concisely. If you're not confident "
				. "in the answer, say so rather than guessing.";
		}

		if ( $profile_line ) {
			$system .= "\n\nWhat you know about this user: {$profile_line}";
		}

		$user_prompt = $history_text ? "{$history_text}\n\nCurrent question: {$message_text}" : $message_text;

		return self::call_ai( $system, $user_prompt );
	}

	/**
	 * Checks whether enough new messages have accumulated to justify a
	 * profile refresh, and runs it if so. Safe to call after every message —
	 * it's a cheap count check unless the threshold is actually hit.
	 */
	public static function maybe_update_profile( $conversation_id, $user_id ) {
		$since = NWA_DB::messages_since_last_profile_update( $conversation_id, $user_id );

		if ( $since < self::PROFILE_UPDATE_EVERY_N ) {
			return;
		}

		$profile        = NWA_DB::get_profile( $user_id );
		$recent_messages = NWA_DB::get_recent_context( $conversation_id, 20, 24 * 60 );
		$history_text    = self::format_history( $recent_messages );

		$system = "Update this user's profile based on the recent conversation. "
			. "Merge with the existing profile — don't discard facts unless the user has "
			. "explicitly contradicted them.\n\n"
			. "Only include things the user has clearly stated. Do not guess, infer, or "
			. "assume anything (age, job, location, etc.) that wasn't directly said.\n\n"
			. 'Existing profile (JSON): ' . wp_json_encode( $profile['summary'] ) . "\n\n"
			. "Return ONLY the updated profile as a flat JSON object of facts, e.g. "
			. '{"name": "Ahmad", "interests": ["faraid"]}' . ". If there is nothing to record, "
			. "return {} — do not wrap the result in any other key, no other text.";

		$raw    = self::call_ai( $system, $history_text );
		$parsed = json_decode( self::strip_json_fences( $raw ), true );

		if ( is_array( $parsed ) ) {
			$total = NWA_DB::count_messages_for_conversation( $conversation_id );
			NWA_DB::save_profile( $user_id, $parsed, $total );
		}
	}

	/* ---------------- helpers ---------------- */

	private static function format_history( $messages ) {
		if ( empty( $messages ) ) {
			return '';
		}
		$lines = array();
		foreach ( $messages as $m ) {
			$speaker = 'inbound' === $m->direction ? 'User' : 'Assistant';
			$lines[] = "{$speaker}: {$m->content}";
		}
		return implode( "\n", $lines );
	}

	private static function format_profile_line( $summary ) {
		if ( empty( $summary ) || ! is_array( $summary ) ) {
			return '';
		}
		$parts = array();
		foreach ( $summary as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			$parts[] = ucfirst( $key ) . ': ' . $value;
		}
		return implode( '. ', $parts );
	}

	private static function strip_json_fences( $text ) {
		return trim( preg_replace( '/```json|```/', '', (string) $text ) );
	}

	/**
	 * Low-level call to the configured AI provider.
	 */
	private static function call_ai( $system_prompt, $user_message ) {
		$provider = NWA_Config::get( 'ai_provider' ) ?: 'anthropic';

		if ( 'deepseek' === $provider ) {
			return self::call_deepseek( $system_prompt, $user_message );
		}

		if ( 'openrouter' === $provider ) {
			return self::call_openrouter( $system_prompt, $user_message );
		}

		return self::call_anthropic( $system_prompt, $user_message );
	}

	/**
	 * Anthropic Messages API.
	 */
	private static function call_anthropic( $system_prompt, $user_message ) {
		$api_key = NWA_Config::get( 'ai_api_key' );
		$model   = NWA_Config::get( 'ai_model' );

		if ( empty( $api_key ) || empty( $model ) ) {
			error_log( 'Niz WA AI: Anthropic call skipped — missing api key or model.' );
			return '';
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => 1000,
				'system'     => $system_prompt,
				'messages'   => array( array( 'role' => 'user', 'content' => $user_message ) ),
			) ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Niz WA AI: Anthropic WP_Error: ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $data['content'] ) ) {
			error_log( 'Niz WA AI: Anthropic HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			return '';
		}

		$text = '';
		foreach ( $data['content'] as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'];
			}
		}

		return $text;
	}

	/**
	 * DeepSeek chat completions API (OpenAI-compatible format). Reuses the
	 * DEEPSEEK_API_KEY constant already defined for Enaizi WA rather than a
	 * separate key — set NWA_AI_PROVIDER to 'deepseek' and NWA_AI_MODEL to a
	 * DeepSeek model (e.g. 'deepseek-chat') to use this path.
	 */
	private static function call_deepseek( $system_prompt, $user_message ) {
		$api_key = defined( 'DEEPSEEK_API_KEY' ) ? DEEPSEEK_API_KEY : NWA_Config::get( 'ai_api_key' );
		$model   = NWA_Config::get( 'ai_model' ) ?: 'deepseek-chat';

		if ( empty( $api_key ) ) {
			error_log( 'Niz WA AI: DeepSeek call skipped — missing api key.' );
			return '';
		}

		$response = wp_remote_post( 'https://api.deepseek.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system_prompt ),
					array( 'role' => 'user', 'content' => $user_message ),
				),
				'temperature' => 0.7,
				'max_tokens'  => 1000,
			) ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Niz WA AI: DeepSeek WP_Error: ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			error_log( 'Niz WA AI: DeepSeek HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			return '';
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * OpenRouter — unified gateway to many providers/models via one API key,
	 * OpenAI-compatible chat completions format. Model names use OpenRouter's
	 * "provider/model" convention, e.g. "anthropic/claude-3.5-sonnet",
	 * "openai/gpt-4o", "deepseek/deepseek-chat", "google/gemini-2.0-flash".
	 * Set NWA_AI_PROVIDER to 'openrouter' and change NWA_AI_MODEL to switch
	 * models — same key, no other config changes needed.
	 */
	private static function call_openrouter( $system_prompt, $user_message ) {
		$api_key = NWA_Config::get( 'ai_api_key' );
		$model   = NWA_Config::get( 'ai_model' ) ?: 'openai/gpt-4o-mini';

		if ( empty( $api_key ) ) {
			error_log( 'Niz WA AI: OpenRouter call skipped — missing api key.' );
			return '';
		}

		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system_prompt ),
					array( 'role' => 'user', 'content' => $user_message ),
				),
				'temperature' => 0.7,
				'max_tokens'  => 1000,
			) ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Niz WA AI: OpenRouter WP_Error: ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			error_log( 'Niz WA AI: OpenRouter HTTP ' . $code . ' (model=' . $model . '): ' . wp_remote_retrieve_body( $response ) );
			return '';
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}
}
