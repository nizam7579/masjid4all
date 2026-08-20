<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads inbound media off Meta and into the WordPress media library.
 *
 * ## Why this has to happen at receipt
 *
 * An inbound image/video/document/audio arrives as nothing but a media id.
 * That id is only resolvable for about 30 days, after which the file is
 * gone from Meta and unrecoverable. Fetching it lazily - when somebody
 * opens the inbox - would therefore lose anything nobody looked at in a
 * month, which is most of it. So the download runs as part of processing
 * the message.
 *
 * ## Why it is bounded
 *
 * NWA_Webhook::handle() processes each message synchronously before acking
 * Meta (this host kills the fire-and-forget loopback the async design
 * needed - see CLAUDE.md). Anything added here is time Meta waits, and a
 * slow ack earns a retry. A photo is a fraction of a second; a 16MB video
 * is not. So there is a size cap, checked from the metadata BEFORE the
 * bytes are fetched, and a short timeout. Over the cap the media id is
 * left in place and logged: the message still processes, and the file is
 * still retrievable by hand for the next 30 days.
 *
 * ## Why failures are never fatal
 *
 * A download problem must not stop a reply going out. Every path returns a
 * WP_Error the caller ignores rather than throwing.
 */
class NWA_Media {

	/** Media types WhatsApp can deliver. */
	public static function types() {
		return array( 'image', 'video', 'document', 'audio', 'sticker' );
	}

	/**
	 * Largest inbound file to pull, in bytes. Filterable, because the right
	 * answer depends on the host's patience more than on Meta's limits.
	 */
	public static function max_bytes() {
		return (int) apply_filters( 'nwa_media_max_bytes', 8 * MB_IN_BYTES );
	}

	/**
	 * Fetches one media id and returns a WP attachment id.
	 *
	 * Two round trips, which is Meta's design: the id resolves to a
	 * short-lived signed URL, and that URL still needs the access token.
	 *
	 * @return int|WP_Error Attachment id.
	 */
	public static function download( $media_id, $context = array() ) {
		$media_id = trim( (string) $media_id );
		if ( '' === $media_id ) {
			return new WP_Error( 'nwa_media_no_id', 'No media id.' );
		}

		$token = NWA_Config::get( 'access_token' );
		if ( empty( $token ) ) {
			return new WP_Error( 'nwa_media_no_token', 'No access token configured.' );
		}

		$api_version = NWA_Config::get( 'api_version' ) ?: 'v21.0';
		$auth        = array( 'Authorization' => 'Bearer ' . $token );

		// 1. Resolve the id to a URL, and learn the size before committing to
		//    downloading it.
		$meta_response = wp_remote_get(
			"https://graph.facebook.com/{$api_version}/{$media_id}",
			array( 'headers' => $auth, 'timeout' => 15 )
		);

		if ( is_wp_error( $meta_response ) ) {
			return $meta_response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $meta_response ) ) {
			return new WP_Error( 'nwa_media_lookup', 'Media lookup returned HTTP ' . wp_remote_retrieve_response_code( $meta_response ) );
		}

		$meta = json_decode( wp_remote_retrieve_body( $meta_response ), true );
		$url  = $meta['url'] ?? '';
		$mime = $meta['mime_type'] ?? '';
		$size = (int) ( $meta['file_size'] ?? 0 );

		if ( '' === $url ) {
			return new WP_Error( 'nwa_media_no_url', 'Media metadata carried no url.' );
		}

		$cap = self::max_bytes();
		if ( $size > 0 && $size > $cap ) {
			return new WP_Error(
				'nwa_media_too_large',
				sprintf( 'Media is %d bytes, over the %d byte cap - left on Meta.', $size, $cap )
			);
		}

		// 2. Fetch the bytes. The signed URL still requires the token.
		$file_response = wp_remote_get( $url, array( 'headers' => $auth, 'timeout' => 25 ) );

		if ( is_wp_error( $file_response ) ) {
			return $file_response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $file_response ) ) {
			return new WP_Error( 'nwa_media_fetch', 'Media fetch returned HTTP ' . wp_remote_retrieve_response_code( $file_response ) );
		}

		$bytes = wp_remote_retrieve_body( $file_response );
		if ( '' === $bytes ) {
			return new WP_Error( 'nwa_media_empty', 'Media body was empty.' );
		}

		// A file_size of 0 in the metadata is possible; re-check what actually
		// arrived so the cap cannot be bypassed by a missing header.
		if ( strlen( $bytes ) > $cap ) {
			return new WP_Error( 'nwa_media_too_large', 'Downloaded body exceeded the cap.' );
		}

		return self::store( $bytes, $mime, $media_id, $context );
	}

	/**
	 * Writes the bytes into the uploads folder and registers an attachment.
	 *
	 * The filename is built from the media id and an extension derived from
	 * Meta's reported MIME type - never from anything the sender supplies.
	 * wp_upload_bits() then refuses any extension the site does not allow,
	 * so a hostile "document" cannot land as something executable.
	 */
	private static function store( $bytes, $mime, $media_id, $context = array() ) {
		$ext = self::extension_for_mime( $mime );
		if ( '' === $ext ) {
			return new WP_Error( 'nwa_media_mime', 'Unsupported media type: ' . $mime );
		}

		$filename = 'wa-' . preg_replace( '/[^A-Za-z0-9]/', '', $media_id ) . '.' . $ext;

		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'nwa_media_upload', $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sprintf(
					'WhatsApp %s from %s',
					isset( $context['type'] ) ? $context['type'] : 'media',
					isset( $context['wa_number'] ) ? $context['wa_number'] : 'unknown'
				),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return is_wp_error( $attachment_id ) ? $attachment_id : new WP_Error( 'nwa_media_attach', 'Could not register the attachment.' );
		}

		// Thumbnails for images; harmless for everything else.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		// Kept so a file can be traced back to the conversation it came from
		// without joining through the messages table.
		update_post_meta( $attachment_id, '_nwa_media_id', $media_id );
		if ( ! empty( $context['user_id'] ) ) {
			update_post_meta( $attachment_id, '_nwa_user_id', (int) $context['user_id'] );
		}

		return (int) $attachment_id;
	}

	/**
	 * Extension for a MIME type, from an allow-list.
	 *
	 * Deliberately not wp_get_default_extension_for_mime_type(): this decides
	 * what the plugin is willing to store at all, and an allow-list is the
	 * safe direction when the input is whatever a stranger sent to a
	 * WhatsApp number.
	 */
	private static function extension_for_mime( $mime ) {
		$mime = strtolower( trim( (string) $mime ) );

		// Meta appends codec parameters to some types, e.g. "audio/ogg; codecs=opus".
		if ( false !== strpos( $mime, ';' ) ) {
			$mime = trim( strtok( $mime, ';' ) );
		}

		$map = array(
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/webp'      => 'webp',
			'video/mp4'       => 'mp4',
			'video/3gpp'      => '3gp',
			'audio/aac'       => 'aac',
			'audio/mpeg'      => 'mp3',
			'audio/mp4'       => 'm4a',
			'audio/amr'       => 'amr',
			'audio/ogg'       => 'ogg',
			'application/pdf' => 'pdf',
			'application/msword' => 'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.ms-excel' => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'text/plain'      => 'txt',
		);

		return isset( $map[ $mime ] ) ? apply_filters( 'nwa_media_extension', $map[ $mime ], $mime ) : '';
	}
}
