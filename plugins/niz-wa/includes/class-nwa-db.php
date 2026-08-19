<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_DB {

	public static function conversations_table() { global $wpdb; return $wpdb->prefix . 'nwa_conversations'; }
	public static function messages_table()      { global $wpdb; return $wpdb->prefix . 'nwa_messages'; }
	public static function actions_table()       { global $wpdb; return $wpdb->prefix . 'nwa_actions'; }
	public static function knowledge_table()     { global $wpdb; return $wpdb->prefix . 'nwa_knowledge_base'; }
	public static function profiles_table()      { global $wpdb; return $wpdb->prefix . 'nwa_user_profiles'; }
	public static function contacts_table()      { global $wpdb; return $wpdb->prefix . 'nwa_contacts'; }

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$cc            = $wpdb->get_charset_collate();
		$conversations = self::conversations_table();
		$messages      = self::messages_table();
		$actions       = self::actions_table();
		$knowledge     = self::knowledge_table();
		$profiles      = self::profiles_table();
		$contacts      = self::contacts_table();

		dbDelta( "CREATE TABLE {$conversations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			wa_number VARCHAR(32) NOT NULL,
			contact_name VARCHAR(191) DEFAULT NULL,
			pending_action VARCHAR(100) DEFAULT NULL,
			pending_context LONGTEXT DEFAULT NULL,
			pending_expires_at DATETIME DEFAULT NULL,
			last_message_at DATETIME DEFAULT NULL,
			window_expires_at DATETIME DEFAULT NULL,
			unread_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY last_message_at (last_message_at)
		) {$cc};" );

		dbDelta( "CREATE TABLE {$messages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			conversation_id BIGINT UNSIGNED DEFAULT NULL,
			wa_number VARCHAR(32) NOT NULL,
			direction ENUM('inbound','outbound') NOT NULL,
			msg_type VARCHAR(32) DEFAULT NULL,
			content LONGTEXT DEFAULT NULL,
			status VARCHAR(20) DEFAULT NULL,
			meta_message_id VARCHAR(191) DEFAULT NULL,
			raw_payload LONGTEXT,
			processed TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY meta_message_id (meta_message_id),
			KEY user_id (user_id),
			KEY conversation_id (conversation_id),
			KEY processed (processed)
		) {$cc};" );

		dbDelta( "CREATE TABLE {$actions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			intent_key VARCHAR(100) NOT NULL,
			keywords VARCHAR(255) DEFAULT NULL,
			description TEXT,
			requires_confirmation TINYINT(1) NOT NULL DEFAULT 1,
			confirm_message TEXT,
			callback_function VARCHAR(191) NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY intent_key (intent_key)
		) {$cc};" );

		dbDelta( "CREATE TABLE {$knowledge} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(191) NOT NULL,
			content LONGTEXT NOT NULL,
			source_type VARCHAR(20) NOT NULL DEFAULT 'manual',
			source_post_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			FULLTEXT KEY content_search (title, content)
		) {$cc};" );

		dbDelta( "CREATE TABLE {$profiles} (
			user_id BIGINT UNSIGNED NOT NULL,
			summary LONGTEXT,
			message_count_at_last_update INT UNSIGNED NOT NULL DEFAULT 0,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (user_id)
		) {$cc};" );

		dbDelta( "CREATE TABLE {$contacts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wa_number VARCHAR(32) NOT NULL,
			contact_name VARCHAR(191) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wa_number (wa_number)
		) {$cc};" );
	}

	/* ---------------- Conversations ---------------- */

	public static function get_or_create_conversation( $user_id, $wa_number, $contact_name = null ) {
		global $wpdb;
		$table = self::conversations_table();

		$conversation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
		if ( $conversation ) {
			return $conversation;
		}

		$wpdb->insert(
			$table,
			array( 'user_id' => $user_id, 'wa_number' => $wa_number, 'contact_name' => $contact_name, 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%s', '%s', '%s' )
		);

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ) );
	}

	public static function get_conversation_by_user( $user_id ) {
		global $wpdb;
		$table = self::conversations_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	public static function touch_inbound( $conversation_id, $timestamp ) {
		global $wpdb;
		$table      = self::conversations_table();
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( $timestamp . ' +24 hours' ) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET last_message_at = %s, window_expires_at = %s, unread_count = unread_count + 1 WHERE id = %d",
			$timestamp, $expires_at, $conversation_id
		) );
	}

	public static function touch_outbound( $conversation_id, $timestamp ) {
		global $wpdb;
		$table = self::conversations_table();
		$wpdb->update(
			$table,
			array( 'last_message_at' => $timestamp, 'unread_count' => 0 ),
			array( 'id' => $conversation_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}

	public static function is_within_window( $conversation ) {
		if ( empty( $conversation->window_expires_at ) ) {
			return false;
		}
		return strtotime( $conversation->window_expires_at ) > current_time( 'timestamp', true );
	}

	public static function set_pending_action( $conversation_id, $intent_key, $context = array(), $ttl_minutes = 10 ) {
		global $wpdb;
		$table = self::conversations_table();

		if ( null === $intent_key ) {
			$wpdb->update(
				$table,
				array( 'pending_action' => null, 'pending_context' => null, 'pending_expires_at' => null ),
				array( 'id' => $conversation_id )
			);
			return;
		}

		$wpdb->update(
			$table,
			array(
				'pending_action'     => $intent_key,
				'pending_context'    => wp_json_encode( $context ),
				'pending_expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( "+{$ttl_minutes} minutes" ) ),
			),
			array( 'id' => $conversation_id )
		);
	}

	public static function get_active_pending_action( $conversation ) {
		if ( empty( $conversation->pending_action ) ) {
			return null;
		}
		if ( ! empty( $conversation->pending_expires_at ) &&
			strtotime( $conversation->pending_expires_at ) < current_time( 'timestamp', true ) ) {
			self::set_pending_action( $conversation->id, null );
			return null;
		}
		return $conversation->pending_action;
	}

	/* ---------------- Messages ---------------- */

	public static function insert_raw_message( $wa_number, $meta_message_id, $raw_payload, $timestamp ) {
		global $wpdb;
		$table = self::messages_table();

		if ( ! empty( $meta_message_id ) ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE meta_message_id = %s", $meta_message_id ) );
			if ( $existing ) {
				return false;
			}
		}

		$wpdb->insert(
			$table,
			array(
				'wa_number'       => $wa_number,
				'direction'       => 'inbound',
				'meta_message_id' => $meta_message_id,
				'raw_payload'     => $raw_payload,
				'processed'       => 0,
				'created_at'      => $timestamp,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function complete_message( $message_id, $user_id, $conversation_id, $msg_type, $content ) {
		global $wpdb;
		$table = self::messages_table();
		$wpdb->update(
			$table,
			array( 'user_id' => $user_id, 'conversation_id' => $conversation_id, 'msg_type' => $msg_type, 'content' => $content, 'processed' => 1 ),
			array( 'id' => $message_id ),
			array( '%d', '%d', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	public static function insert_outbound_message( $args ) {
		global $wpdb;
		$table    = self::messages_table();
		$defaults = array(
			'user_id' => null, 'conversation_id' => 0, 'wa_number' => '', 'msg_type' => 'text',
			'content' => '', 'status' => 'sent', 'meta_message_id' => null, 'processed' => 1,
				// GMT, matching the inbound path. Inbound stores Meta's own unix
			// timestamp via gmdate(), and touch_inbound() derives
			// window_expires_at from it, which is_within_window() then compares
			// against current_time('timestamp', true). The whole 24-hour window
			// is therefore GMT; outbound was the one path writing local time,
			// which put replies 8 hours ahead of the messages they answered.
			'created_at' => current_time( 'mysql', true ),
		);
		$args              = wp_parse_args( $args, $defaults );
		$args['direction'] = 'outbound';

		$wpdb->insert( $table, $args );
		return $wpdb->insert_id;
	}

	public static function update_message_status( $meta_message_id, $status ) {
		global $wpdb;
		$table = self::messages_table();
		$wpdb->update( $table, array( 'status' => $status ), array( 'meta_message_id' => $meta_message_id ) );
	}

	public static function get_unprocessed_message( $message_id ) {
		global $wpdb;
		$table = self::messages_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );
	}

	public static function get_messages( $conversation_id, $limit = 50 ) {
		global $wpdb;
		$table = self::messages_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE conversation_id = %d AND processed = 1 ORDER BY created_at DESC LIMIT %d",
			$conversation_id, $limit
		) );
	}

	/**
	 * Recent context for AI calls: last $limit messages, but only those
	 * within $minutes of "now" — whichever cutoff hits first. Returns
	 * oldest-first (natural reading order for a prompt).
	 */
	public static function get_recent_context( $conversation_id, $limit = 6, $minutes = 45 ) {
		global $wpdb;
		$table  = self::messages_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$minutes} minutes" ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT direction, msg_type, content, created_at FROM {$table}
			 WHERE conversation_id = %d AND processed = 1 AND created_at >= %s
			 ORDER BY created_at DESC LIMIT %d",
			$conversation_id, $cutoff, $limit
		) );

		return array_reverse( $rows );
	}

	public static function get_conversations( $limit = 50 ) {
		global $wpdb;
		$table = self::conversations_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_message_at DESC LIMIT %d", $limit ) );
	}

	public static function get_stuck_messages( $older_than_minutes = 5, $limit = 50 ) {
		global $wpdb;
		$table  = self::messages_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_minutes} minutes" ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE processed = 0 AND created_at < %s ORDER BY created_at ASC LIMIT %d",
			$cutoff, $limit
		) );
	}

	public static function count_messages_for_conversation( $conversation_id ) {
		global $wpdb;
		$table = self::messages_table();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE conversation_id = %d AND processed = 1", $conversation_id
		) );
	}

	/* ---------------- Action registry ---------------- */

	public static function get_enabled_actions() {
		global $wpdb;
		$table = self::actions_table();
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE enabled = 1" );
	}

	public static function get_all_actions() {
		global $wpdb;
		$table = self::actions_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	public static function get_action_by_intent( $intent_key ) {
		global $wpdb;
		$table = self::actions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE intent_key = %s AND enabled = 1", $intent_key ) );
	}

	public static function get_action_by_keyword( $message_text ) {
		global $wpdb;
		$table   = self::actions_table();
		$needle  = strtolower( trim( $message_text ) );
		$actions = $wpdb->get_results( "SELECT * FROM {$table} WHERE enabled = 1 AND keywords IS NOT NULL AND keywords != ''" );

		foreach ( $actions as $action ) {
			$keywords = array_map( 'trim', explode( ',', strtolower( $action->keywords ) ) );
			if ( in_array( $needle, $keywords, true ) ) {
				return $action;
			}
		}
		return null;
	}

	public static function save_action( $data, $id = null ) {
		global $wpdb;
		$table  = self::actions_table();
		$fields = array(
			'intent_key'            => sanitize_key( $data['intent_key'] ),
			'keywords'              => sanitize_text_field( $data['keywords'] ?? '' ),
			'description'           => sanitize_textarea_field( $data['description'] ?? '' ),
			'requires_confirmation' => empty( $data['requires_confirmation'] ) ? 0 : 1,
			'confirm_message'       => sanitize_textarea_field( $data['confirm_message'] ?? '' ),
			'callback_function'     => sanitize_text_field( $data['callback_function'] ),
			'enabled'               => empty( $data['enabled'] ) ? 0 : 1,
		);

		if ( $id ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ) );
			return $id;
		}

		$fields['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $fields );
		return $wpdb->insert_id;
	}

	public static function delete_action( $id ) {
		global $wpdb;
		$wpdb->delete( self::actions_table(), array( 'id' => $id ) );
	}

	/* ---------------- Knowledge base ---------------- */

	public static function search_knowledge( $query, $limit = 5 ) {
		global $wpdb;
		$table = self::knowledge_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT title, content, MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE) AS relevance
			 FROM {$table}
			 WHERE MATCH(title, content) AGAINST (%s IN NATURAL LANGUAGE MODE)
			 ORDER BY relevance DESC LIMIT %d",
			$query, $query, $limit
		) );
	}

	public static function get_all_knowledge() {
		global $wpdb;
		$table = self::knowledge_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC" );
	}

	public static function save_knowledge( $data, $id = null ) {
		global $wpdb;
		$table  = self::knowledge_table();
		$fields = array(
			'title'          => sanitize_text_field( $data['title'] ),
			'content'        => wp_kses_post( $data['content'] ),
			'source_type'    => sanitize_text_field( $data['source_type'] ?? 'manual' ),
			'source_post_id' => ! empty( $data['source_post_id'] ) ? (int) $data['source_post_id'] : null,
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $id ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ) );
			return $id;
		}

		$fields['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $fields );
		return $wpdb->insert_id;
	}

	public static function delete_knowledge( $id ) {
		global $wpdb;
		$wpdb->delete( self::knowledge_table(), array( 'id' => $id ) );
	}

	/* ---------------- User profile (memory) ---------------- */

	public static function get_profile( $user_id ) {
		global $wpdb;
		$table = self::profiles_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );

		if ( ! $row ) {
			return array( 'summary' => array(), 'message_count_at_last_update' => 0 );
		}

		return array(
			'summary'                      => json_decode( $row->summary, true ) ?: array(),
			'message_count_at_last_update' => (int) $row->message_count_at_last_update,
		);
	}

	public static function save_profile( $user_id, $summary, $message_count ) {
		global $wpdb;
		$table = self::profiles_table();
		$wpdb->replace(
			$table,
			array(
				'user_id'                      => $user_id,
				'summary'                      => wp_json_encode( $summary ),
				'message_count_at_last_update' => $message_count,
				'updated_at'                   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	public static function clear_profile( $user_id ) {
		global $wpdb;
		$wpdb->delete( self::profiles_table(), array( 'user_id' => $user_id ) );
	}

	/**
	 * How many messages have accumulated since the profile was last updated —
	 * used to decide whether a profile refresh is due.
	 */
	public static function messages_since_last_profile_update( $conversation_id, $user_id ) {
		$total   = self::count_messages_for_conversation( $conversation_id );
		$profile = self::get_profile( $user_id );
		return $total - $profile['message_count_at_last_update'];
	}

	/* ---------------- Standalone identity (used only when no
	   nwa_resolve_user_id filter is hooked by a site plugin) ----------------
	   Creates a real WordPress user for this number (2026-08-09 - previously
	   created a row in niz-wa's own now-unused wp_nwa_contacts table
	   instead, so user_id was never a real WP user in pure standalone mode).
	   Only ever sets user_phone meta plus a display name - a site's own
	   integration (via the filter) owns everything else about "who this
	   user is"; that's what a masjid4all-specific fork of this function
	   would do differently (e.g. also creating a CCT member record). */

	public static function get_or_create_wp_user( $wa_number, $contact_name = null ) {
		$existing = get_users( array(
			'meta_key'   => 'user_phone',
			'meta_value' => $wa_number,
			'number'     => 1,
			'fields'     => 'ID',
		) );
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$domain   = wp_parse_url( home_url(), PHP_URL_HOST );
		$username = 'nwa_' . $wa_number;
		$email    = $wa_number . '@' . $domain;

		// Guard against a very unlikely username/email collision (e.g. a
		// manually created account) rather than letting wp_insert_user()
		// fail silently on the first attempt.
		$suffix = 1;
		while ( username_exists( $username ) || email_exists( $email ) ) {
			$username = 'nwa_' . $wa_number . '_' . $suffix;
			$email    = $wa_number . '+' . $suffix . '@' . $domain;
			$suffix++;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_pass'    => wp_generate_password( 20, true ),
			'user_email'   => $email,
			'display_name' => $contact_name ? sanitize_text_field( $contact_name ) : $wa_number,
		) );

		if ( is_wp_error( $user_id ) ) {
			error_log( 'NWA_DB::get_or_create_wp_user: wp_insert_user failed - ' . $user_id->get_error_message() );
			return 0;
		}

		update_user_meta( $user_id, 'user_phone', $wa_number );

		return (int) $user_id;
	}
}
