<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_Sender {

	/**
	 * Free-form text reply. Only valid inside the 24h window.
	 */
	public static function send_message( $user_id, $to, $text ) {
		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
			error_log( 'Niz WA: send_message blocked — user_id=' . $user_id . ' to=' . $to
				. ' conversation_found=' . ( $conversation ? 'yes' : 'no' )
				. ' window_expires_at=' . ( $conversation->window_expires_at ?? 'n/a' )
				. ' now_gmt=' . current_time( 'mysql', true ) );
			return array( 'success' => false, 'error' => 'outside_window', 'message_id' => null );
		}

		$text = self::format_for_whatsapp( $text );

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'text',
			'text'              => array( 'body' => $text ),
		);

		$response = self::api_request( $body );

		if ( $response['success'] ) {
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => 'text',
				'content'         => $text,
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Template message. Works outside the 24h window (or to start a new
	 * conversation). $to must be provided directly since there may not be
	 * an existing conversation yet.
	 */
	public static function send_template( $to, $template_name, $lang_code = '', $components = array(), $user_id = null ) {
		// Enforced here rather than only in the UI: a template is the
		// business-INITIATED channel, so this is the one send that must not
		// happen after somebody opts out, whoever calls it and from wherever.
		if ( $user_id && class_exists( 'NWA_OptOut' ) && NWA_OptOut::is_opted_out( $user_id ) ) {
			error_log( 'Niz WA: template blocked — user_id=' . $user_id . ' opted out at ' . NWA_OptOut::opted_out_at( $user_id ) );

			return array( 'success' => false, 'error' => 'opted_out', 'message_id' => null );
		}

		// One source of truth for the template language. Our templates are
		// approved in Meta as plain "English" (code `en`); sending `en_US`
		// against them fails with 132001 - the template exists, the call just
		// does not match a translation of it. A caller may still pass an
		// explicit code, and the filter lets a Malay template switch to `ms`
		// without touching a single call site.
		if ( '' === $lang_code ) {
			$lang_code = apply_filters( 'nwa_template_language', 'en', $template_name );
		}

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => array( 'name' => $template_name, 'language' => array( 'code' => $lang_code ) ),
		);
		if ( ! empty( $components ) ) {
			$body['template']['components'] = $components;
		}

		$response = self::api_request( $body );

		if ( $response['success'] && $user_id ) {
			$conversation = NWA_DB::get_or_create_conversation( $user_id, $to );
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => 'template',
				'content'         => wp_json_encode( array( 'template' => $template_name, 'components' => $components ) ),
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Interactive reply-buttons message (max 3). Like send_message(), only
	 * valid inside the 24h window. $buttons is a list of
	 * array( 'id' => ..., 'title' => ... ); titles are capped at WhatsApp's
	 * 20-char limit and the list at 3. The user's tap comes back as an
	 * 'interactive' inbound message whose button_reply title/id niz-wa's
	 * webhook reduces to plain text for routing.
	 */
	public static function send_buttons( $user_id, $to, $body_text, $buttons ) {
		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
			error_log( 'Niz WA: send_buttons blocked — user_id=' . $user_id . ' to=' . $to
				. ' conversation_found=' . ( $conversation ? 'yes' : 'no' ) );
			return array( 'success' => false, 'error' => 'outside_window', 'message_id' => null );
		}

		$reply_buttons = array();
		foreach ( array_slice( array_values( $buttons ), 0, 3 ) as $button ) {
			$reply_buttons[] = array(
				'type'  => 'reply',
				'reply' => array(
					'id'    => substr( (string) $button['id'], 0, 256 ),
					'title' => mb_substr( (string) $button['title'], 0, 20 ),
				),
			);
		}

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'interactive',
			'interactive'       => array(
				'type'   => 'button',
				'body'   => array( 'text' => $body_text ),
				'action' => array( 'buttons' => $reply_buttons ),
			),
		);

		$response = self::api_request( $body );

		if ( $response['success'] ) {
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => 'interactive',
				'content'         => $body_text,
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Media message — image, video, document or audio. Inside the 24h window
	 * only, same as the other free-form types.
	 *
	 * $source is either a publicly reachable URL or a Meta media id. It is
	 * told apart by looking for a scheme, so a caller never has to say which
	 * it has. A URL is the usual case here: everything we send (a rate card,
	 * a promo image) already lives in wp-content/uploads and is public, and
	 * Meta fetches it itself. Uploading a private file to Meta's /media
	 * endpoint to obtain an id is NOT implemented; if that is ever needed,
	 * it is a separate multipart request, and this method already accepts
	 * the id it would return.
	 *
	 * Meta's own limits, worth knowing because it rejects rather than
	 * truncates: image 5MB (jpeg/png), video 16MB (mp4/3gpp), audio 16MB,
	 * document 100MB. A caption is allowed on image, video and document but
	 * NOT audio, and `filename` applies only to a document - passing either
	 * where it doesn't belong is an error, so both are dropped rather than
	 * forwarded blindly.
	 *
	 * @param string $type image|video|document|audio
	 */
	public static function send_media( $user_id, $to, $type, $source, $caption = '', $filename = '' ) {
		$allowed = array( 'image', 'video', 'document', 'audio' );
		$type    = strtolower( (string) $type );

		if ( ! in_array( $type, $allowed, true ) ) {
			return array( 'success' => false, 'error' => 'unsupported_media_type', 'message_id' => null );
		}

		$source = trim( (string) $source );
		if ( '' === $source ) {
			return array( 'success' => false, 'error' => 'missing_media_source', 'message_id' => null );
		}

		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
			error_log( 'Niz WA: send_media blocked — user_id=' . $user_id . ' to=' . $to
				. ' type=' . $type
				. ' conversation_found=' . ( $conversation ? 'yes' : 'no' )
				. ' window_expires_at=' . ( $conversation->window_expires_at ?? 'n/a' )
				. ' now_gmt=' . current_time( 'mysql', true ) );
			return array( 'success' => false, 'error' => 'outside_window', 'message_id' => null );
		}

		// A URL goes as `link`, anything else is treated as an already-uploaded
		// media id. wp_http_validate_url() also rejects a local/private host,
		// which matters because Meta has to be able to fetch it.
		$media = ( 0 === strpos( $source, 'http' ) )
			? array( 'link' => $source )
			: array( 'id' => $source );

		if ( isset( $media['link'] ) && ! wp_http_validate_url( $source ) ) {
			return array( 'success' => false, 'error' => 'invalid_media_url', 'message_id' => null );
		}

		$caption = trim( (string) $caption );
		if ( '' !== $caption && 'audio' !== $type ) {
			// Meta caps captions at 1024 characters.
			$media['caption'] = mb_substr( $caption, 0, 1024 );
		}

		if ( 'document' === $type && '' !== trim( (string) $filename ) ) {
			$media['filename'] = sanitize_file_name( $filename );
		}

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => $type,
			$type               => $media,
		);

		$response = self::api_request( $body );

		if ( $response['success'] ) {
			// The thread stores what was sent, not the bytes: the caption if
			// there is one, otherwise the source, so the inbox row is
			// recognisable rather than blank.
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => $type,
				'content'         => '' !== $caption ? $caption : $source,
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Interactive list message — a tappable menu of up to 10 rows. Inside the
	 * 24h window only.
	 *
	 * Use this instead of send_buttons() when there are more than three
	 * choices; buttons cap at three, a list at ten (in total, across all
	 * sections).
	 *
	 * $sections is an array of:
	 *   array( 'title' => 'Section name', 'rows' => array(
	 *       array( 'id' => 'x', 'title' => 'Row', 'description' => 'optional' ),
	 *   ) )
	 *
	 * Meta's limits are enforced here by truncation rather than left to the
	 * API, which rejects the whole message instead: button 20 chars, section
	 * title 24, row title 24, row description 72, row id 200, 10 rows total.
	 *
	 * Note how the reply comes back: NWA_Webhook reads `list_reply` and
	 * returns the row's TITLE, exactly as it does for buttons - so a router
	 * matching on the tap should compare titles, not ids.
	 */
	public static function send_list( $user_id, $to, $body_text, $button_label, $sections, $header = '', $footer = '' ) {
		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
			error_log( 'Niz WA: send_list blocked — user_id=' . $user_id . ' to=' . $to
				. ' conversation_found=' . ( $conversation ? 'yes' : 'no' )
				. ' window_expires_at=' . ( $conversation->window_expires_at ?? 'n/a' )
				. ' now_gmt=' . current_time( 'mysql', true ) );
			return array( 'success' => false, 'error' => 'outside_window', 'message_id' => null );
		}

		$clean_sections = array();
		$row_budget     = 10;

		foreach ( (array) $sections as $section ) {
			if ( $row_budget < 1 ) {
				break;
			}

			$rows = array();
			foreach ( (array) ( $section['rows'] ?? array() ) as $row ) {
				if ( $row_budget < 1 ) {
					break;
				}

				$title = trim( (string) ( $row['title'] ?? '' ) );
				if ( '' === $title ) {
					continue; // A row with no title is not tappable.
				}

				$entry = array(
					'id'    => substr( (string) ( $row['id'] ?? $title ), 0, 200 ),
					'title' => mb_substr( $title, 0, 24 ),
				);

				$description = trim( (string) ( $row['description'] ?? '' ) );
				if ( '' !== $description ) {
					$entry['description'] = mb_substr( $description, 0, 72 );
				}

				$rows[] = $entry;
				$row_budget--;
			}

			if ( ! $rows ) {
				continue; // Meta rejects an empty section.
			}

			$clean_sections[] = array(
				'title' => mb_substr( (string) ( $section['title'] ?? '' ), 0, 24 ),
				'rows'  => $rows,
			);
		}

		if ( ! $clean_sections ) {
			return array( 'success' => false, 'error' => 'no_list_rows', 'message_id' => null );
		}

		$interactive = array(
			'type'   => 'list',
			'body'   => array( 'text' => mb_substr( self::format_for_whatsapp( $body_text ), 0, 1024 ) ),
			'action' => array(
				'button'   => mb_substr( (string) $button_label, 0, 20 ),
				'sections' => $clean_sections,
			),
		);

		if ( '' !== trim( (string) $header ) ) {
			$interactive['header'] = array( 'type' => 'text', 'text' => mb_substr( $header, 0, 60 ) );
		}
		if ( '' !== trim( (string) $footer ) ) {
			$interactive['footer'] = array( 'text' => mb_substr( $footer, 0, 60 ) );
		}

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'interactive',
			'interactive'       => $interactive,
		);

		$response = self::api_request( $body );

		if ( $response['success'] ) {
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => 'interactive',
				'content'         => $body_text,
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Interactive WhatsApp Flow message — opens a native in-chat form. Like
	 * send_message()/send_buttons(), only valid inside the 24h window. The
	 * flow must already be published in Meta's WhatsApp Manager; $screen_id
	 * is that flow's first screen id. $flow_token isn't needed back to
	 * correlate the eventual nfm_reply (it arrives on the same conversation
	 * thread like any other inbound message) — it only shows up in Meta's
	 * own flow analytics, so a generated one is fine when the caller has
	 * nothing more meaningful to pass.
	 */
	public static function send_flow( $user_id, $to, $body_text, $flow_id, $flow_cta, $screen_id, $flow_token = null ) {
		$conversation = NWA_DB::get_conversation_by_user( $user_id );

		if ( ! $conversation || ! NWA_DB::is_within_window( $conversation ) ) {
			error_log( 'Niz WA: send_flow blocked — user_id=' . $user_id . ' to=' . $to
				. ' conversation_found=' . ( $conversation ? 'yes' : 'no' ) );
			return array( 'success' => false, 'error' => 'outside_window', 'message_id' => null );
		}

		$flow_token = $flow_token ?: ( 'nwa_flow_' . $conversation->id . '_' . time() );

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'interactive',
			'interactive'       => array(
				'type'   => 'flow',
				'body'   => array( 'text' => $body_text ),
				'action' => array(
					'name'       => 'flow',
					'parameters' => array(
						'flow_message_version' => '3',
						'flow_token'            => $flow_token,
						'flow_id'               => $flow_id,
						'flow_cta'              => $flow_cta,
						'flow_action'           => 'navigate',
						'flow_action_payload'   => array( 'screen' => $screen_id ),
					),
				),
			),
		);

		$response = self::api_request( $body );

		if ( $response['success'] ) {
			NWA_DB::insert_outbound_message( array(
				'user_id'         => $user_id,
				'conversation_id' => $conversation->id,
				'wa_number'       => $to,
				'msg_type'        => 'interactive',
				'content'         => $body_text,
				'meta_message_id' => $response['message_id'],
			) );
			NWA_DB::touch_outbound( $conversation->id, current_time( 'mysql', true ) );
		}

		return $response;
	}

	/**
	 * Marks an inbound message as read and shows the 'typing…' indicator in
	 * the user's chat while a reply is being generated. WhatsApp clears the
	 * indicator automatically after ~25s or as soon as the next message is
	 * sent — no explicit 'stop typing' call exists or is needed.
	 */
	public static function mark_read_with_typing( $inbound_message_id ) {
		$phone_number_id = NWA_Config::get( 'phone_number_id' );
		$access_token    = NWA_Config::get( 'access_token' );
		$api_version     = NWA_Config::get( 'api_version' ) ?: 'v21.0';

		if ( empty( $phone_number_id ) || empty( $access_token ) ) {
			return false;
		}

		$url = "https://graph.facebook.com/{$api_version}/{$phone_number_id}/messages";

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'messaging_product' => 'whatsapp',
				'status'            => 'read',
				'message_id'        => $inbound_message_id,
				'typing_indicator'  => array( 'type' => 'text' ),
			) ),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Niz WA: mark_read_with_typing WP_Error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			error_log( 'Niz WA: mark_read_with_typing HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}

	/**
	 * Converts common Markdown (as returned by the AI providers) into
	 * WhatsApp's own formatting syntax, so replies don't show literal
	 * '**', '###', etc. Headers are stripped rather than bolded to avoid
	 * awkward nested-asterisk output when a header also contains bold text.
	 */
	private static function format_for_whatsapp( $text ) {
		$text = preg_replace( '/^#{1,6}\s+/m', '', $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '*$1*', $text );
		$text = preg_replace( '/__(.+?)__/s', '*$1*', $text );
		$text = preg_replace( '/~~(.+?)~~/s', '~$1~', $text );

		return $text;
	}

	private static function api_request( $body ) {
		$phone_number_id = NWA_Config::get( 'phone_number_id' );
		$access_token    = NWA_Config::get( 'access_token' );
		$api_version     = NWA_Config::get( 'api_version' ) ?: 'v21.0';

		if ( empty( $phone_number_id ) || empty( $access_token ) ) {
			error_log( 'Niz WA: send failed — missing WhatsApp credentials.' );
			return array( 'success' => false, 'error' => 'missing_credentials', 'message_id' => null );
		}

		$url = "https://graph.facebook.com/{$api_version}/{$phone_number_id}/messages";

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Niz WA: send failed — WP_Error: ' . $response->get_error_message() );
			return array( 'success' => false, 'error' => $response->get_error_message(), 'message_id' => null );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['messages'][0]['id'] ) ) {
			return array( 'success' => true, 'error' => null, 'message_id' => $data['messages'][0]['id'] );
		}

		error_log( 'Niz WA: send failed — HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		return array( 'success' => false, 'error' => $data['error']['message'] ?? 'unknown_error', 'message_id' => null );
	}
}

function nwa_send_message( $user_id, $to, $text ) {
	return NWA_Sender::send_message( $user_id, $to, $text );
}

function nwa_send_template( $to, $template_name, $lang_code = '', $components = array(), $user_id = null ) {
	return NWA_Sender::send_template( $to, $template_name, $lang_code, $components, $user_id );
}

function nwa_send_buttons( $user_id, $to, $body_text, $buttons ) {
	return NWA_Sender::send_buttons( $user_id, $to, $body_text, $buttons );
}

function nwa_send_media( $user_id, $to, $type, $source, $caption = '', $filename = '' ) {
	return NWA_Sender::send_media( $user_id, $to, $type, $source, $caption, $filename );
}

function nwa_send_list( $user_id, $to, $body_text, $button_label, $sections, $header = '', $footer = '' ) {
	return NWA_Sender::send_list( $user_id, $to, $body_text, $button_label, $sections, $header, $footer );
}

function nwa_send_flow( $user_id, $to, $body_text, $flow_id, $flow_cta, $screen_id, $flow_token = null ) {
	return NWA_Sender::send_flow( $user_id, $to, $body_text, $flow_id, $flow_cta, $screen_id, $flow_token );
}
