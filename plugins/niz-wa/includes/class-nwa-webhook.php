<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_Webhook {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'nemkad-wa/v1', '/webhook', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'verify' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'handle' ), 'permission_callback' => '__return_true' ),
		) );

		// Kept registered for potential future use, but handle() no longer calls
		// this — fire-and-forget loopback requests get killed by this host
		// before slow outbound AI calls can finish, so processing now happens
		// synchronously inside handle() instead.
		register_rest_route( 'nemkad-wa/v1', '/process', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'process' ),
			'permission_callback' => array( __CLASS__, 'verify_internal_secret' ),
		) );
	}

	public static function verify( WP_REST_Request $request ) {
		$verify_token = NWA_Config::get( 'verify_token' );

		$mode      = $request->get_param( 'hub_mode' );
		$token     = $request->get_param( 'hub_verify_token' );
		$challenge = $request->get_param( 'hub_challenge' );

		if ( 'subscribe' === $mode && hash_equals( (string) $verify_token, (string) $token ) ) {
			return new WP_REST_Response( (int) $challenge, 200 );
		}

		return new WP_REST_Response( 'Verification failed', 403 );
	}

	/**
	 * Signature check, dedupe + raw insert, then processes each message
	 * synchronously before acking Meta. See note in register_routes() on why
	 * this isn't handed off to a background request on this host.
	 */
	public static function handle( WP_REST_Request $request ) {
		$raw_body = $request->get_body();

		if ( ! self::verify_signature( $raw_body, $request->get_header( 'x-hub-signature-256' ) ) ) {
			return new WP_REST_Response( 'Invalid signature', 401 );
		}

		$payload = json_decode( $raw_body, true );

		if ( empty( $payload['entry'] ) ) {
			return new WP_REST_Response( 'ok', 200 );
		}

		$message_ids_to_process = array();

		foreach ( $payload['entry'] as $entry ) {
			foreach ( (array) $entry['changes'] as $change ) {
				$value = $change['value'] ?? array();

				if ( ! empty( $value['statuses'] ) ) {
					foreach ( $value['statuses'] as $status ) {
						NWA_DB::update_message_status( $status['id'], $status['status'] );
					}
				}

				if ( ! empty( $value['messages'] ) ) {
					foreach ( $value['messages'] as $message ) {
						$timestamp = gmdate( 'Y-m-d H:i:s', (int) $message['timestamp'] );
						$id = NWA_DB::insert_raw_message( $message['from'], $message['id'], wp_json_encode( array(
							'message'  => $message,
							'contacts' => $value['contacts'] ?? array(),
						) ), $timestamp );

						if ( false !== $id ) {
							$message_ids_to_process[] = $id;
							// Show 'typing…' right away so the user knows a reply is coming,
							// not just silence — processing below can take a couple seconds.
							NWA_Sender::mark_read_with_typing( $message['id'] );
						}
					}
				}
			}
		}

		foreach ( $message_ids_to_process as $message_id ) {
			self::process_single_message( $message_id );
		}

		return new WP_REST_Response( 'ok', 200 );
	}

	private static function trigger_async_processing( $message_ids ) {
		wp_remote_post( get_rest_url( null, 'nemkad-wa/v1/process' ), array(
			'body'     => wp_json_encode( array(
				'ids'    => $message_ids,
				'secret' => self::internal_secret(),
			) ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'timeout'  => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		) );
	}

	/**
	 * Kept for compatibility with the /process route registration above.
	 * Not called from handle() anymore — see note there.
	 */
	public static function process( WP_REST_Request $request ) {
		$body = json_decode( $request->get_body(), true );
		$ids  = $body['ids'] ?? array();

		foreach ( $ids as $message_id ) {
			self::process_single_message( (int) $message_id );
		}

		return new WP_REST_Response( 'ok', 200 );
	}

	private static function process_single_message( $message_id ) {
		$row = NWA_DB::get_unprocessed_message( $message_id );
		if ( ! $row || (int) $row->processed === 1 ) {
			return;
		}

		$raw          = json_decode( $row->raw_payload, true );
		$message      = $raw['message'] ?? array();
		$contacts     = $raw['contacts'] ?? array();
		$contact_name = $contacts[0]['profile']['name'] ?? null;
		$wa_number    = $row->wa_number;
		$type         = $message['type'] ?? 'unknown';
		$content      = self::extract_content( $message, $type );

		$user_id = self::resolve_user_id( $wa_number, $contact_name );

		$conversation = NWA_DB::get_or_create_conversation( $user_id, $wa_number, $contact_name );
		NWA_DB::complete_message( $message_id, $user_id, $conversation->id, $type, $content );
		NWA_DB::touch_inbound( $conversation->id, $row->created_at );

		// Media is only a short-lived id on Meta's side (~30 days), so it is
		// pulled into the media library now rather than when someone looks.
		// Bounded and non-fatal by design - see class-nwa-media.php.
		if ( class_exists( 'NWA_Media' ) && in_array( $type, NWA_Media::types(), true ) ) {
			$media_id = $message[ $type ]['id'] ?? '';
			if ( $media_id ) {
				$attachment = NWA_Media::download( $media_id, array(
					'type'      => $type,
					'wa_number' => $wa_number,
					'user_id'   => $user_id,
				) );

				if ( is_wp_error( $attachment ) ) {
					// Logged, not raised: a reply still has to go out, and the
					// id stays in raw_payload so it can be fetched by hand.
					error_log( 'Niz WA: media download failed — media_id=' . $media_id
						. ' type=' . $type . ' error=' . $attachment->get_error_message() );
				} else {
					NWA_DB::set_message_attachment( $message_id, $attachment );
				}
			}
		}

		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		NWA_Router::handle_message( $user_id, $wa_number, $conversation, $content, $type, $message['id'] ?? null );
	}

	/**
	 * Resolves a WhatsApp number to the identity niz-wa should key
	 * conversations/messages/profiles on. A site plugin can hook
	 * 'nwa_resolve_user_id' to link this to its own user/CRM model (e.g.
	 * mfa-core links WhatsApp numbers to real WordPress users on
	 * Masjid4All). With no filter hooked, niz-wa creates a real WordPress
	 * user for this number itself (2026-08-09) - user_id is a genuine WP
	 * user ID either way, which is what makes niz-wa usable standalone on
	 * any site out of the box, not just ones running a companion identity
	 * plugin.
	 */
	private static function resolve_user_id( $wa_number, $contact_name ) {
		$user_id = apply_filters( 'nwa_resolve_user_id', null, $wa_number, $contact_name );

		if ( $user_id ) {
			return (int) $user_id;
		}

		return NWA_DB::get_or_create_wp_user( $wa_number, $contact_name );
	}

	private static function extract_content( $message, $type ) {
		switch ( $type ) {
			case 'text':
				return $message['text']['body'] ?? '';
			case 'button':
				return $message['button']['text'] ?? '';
			case 'interactive':
				if ( isset( $message['interactive']['button_reply'] ) ) {
					return $message['interactive']['button_reply']['title'] ?? '';
				}
				if ( isset( $message['interactive']['list_reply'] ) ) {
					return $message['interactive']['list_reply']['title'] ?? '';
				}
				return wp_json_encode( $message['interactive'] ?? array() );
			case 'image':
			case 'video':
			case 'document':
			case 'audio':
			case 'sticker':
				// The caption used to be thrown away and the bare media id kept,
				// which meant "here's my business licence" reached the router and
				// the AI as an opaque 32-character id. The caption is the part
				// with meaning; the id is preserved in raw_payload and the file
				// itself is downloaded to the media library, so nothing is lost
				// by not putting it here.
				$caption = trim( (string) ( $message[ $type ]['caption'] ?? '' ) );
				if ( '' !== $caption ) {
					return $caption;
				}

				$filename = trim( (string) ( $message[ $type ]['filename'] ?? '' ) );

				return $filename ? '[' . $type . ': ' . $filename . ']' : '[' . $type . ']';
			case 'location':
				return wp_json_encode( $message['location'] ?? array() );
			default:
				return wp_json_encode( $message );
		}
	}

	private static function verify_signature( $raw_body, $signature_header ) {
		$app_secret = NWA_Config::get( 'app_secret' );

		if ( empty( $app_secret ) || empty( $signature_header ) ) {
			return empty( $app_secret );
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $app_secret );
		return hash_equals( $expected, $signature_header );
	}

	private static function internal_secret() {
		return wp_hash( 'nemkad-wa-internal-process' );
	}

	public static function verify_internal_secret( WP_REST_Request $request ) {
		$body = json_decode( $request->get_body(), true );
		return isset( $body['secret'] ) && hash_equals( self::internal_secret(), $body['secret'] );
	}
}
