<?php
/**
 * Webhook Receiver – Simplified version (no signature validation for testing)
 */

/* 

class Niz_Wapi_Webhook_Receiver {
    private $table_webhook_events;
    private $table_logs;

    public function __construct() {
        global $wpdb;
        $this->table_webhook_events = $wpdb->prefix . 'niz_wa_webhook_events';
        $this->table_logs = $wpdb->prefix . 'niz_wa_logs';
    }

    public function register_routes() {
        register_rest_route('enaizi_wapi/v1', '/webhook', [
            'methods'             => ['GET', 'POST'],
            'callback'            => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_webhook($request) {
        if ($request->get_method() === 'GET') {
            return $this->verify_webhook($request);
        }
        return $this->process_incoming($request);
    }

    private function verify_webhook($request) {
        $verify_token = trim($request->get_param('hub_verify_token') ?? '');
        $challenge    = $request->get_param('hub_challenge') ?? '';
        $settings = get_option('niz_wapi_whatsapp_settings', []);
        $stored_token = isset($settings['verify_token']) ? $settings['verify_token'] : '';

        if ($verify_token === $stored_token) {
            status_header(200);
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }
        return new WP_Error('invalid_token', 'Verification token mismatch', ['status' => 403]);
    }

    private function process_incoming($request) {
        $raw_payload = $request->get_body();
        $payload = json_decode($raw_payload, true);

        // Log the payload to debug.log for inspection
        error_log('Webhook received: ' . print_r($payload, true));

        if (empty($payload)) {
            return new WP_REST_Response(['status' => 'empty'], 200);
        }

        // Extract webhook ID for idempotency
        $webhook_id = $this->get_webhook_id($payload);
        if ($webhook_id && $this->is_duplicate($webhook_id)) {
            return new WP_REST_Response(['status' => 'duplicate'], 200);
        }

        if ($webhook_id) {
            $this->store_webhook_event($webhook_id, $payload);
        }

        // Process messages and statuses directly (no queue for now)
        $this->process_payload($payload);

        return new WP_REST_Response(['status' => 'processed'], 200);
    }

    private function process_payload($payload) {
        // Check for status updates (delivery receipts)
        if (isset($payload['entry'][0]['changes'][0]['value']['statuses'])) {
            $this->handle_statuses($payload['entry'][0]['changes'][0]['value']['statuses']);
        }

        // Check for incoming messages
        if (isset($payload['entry'][0]['changes'][0]['value']['messages'])) {
            $value = $payload['entry'][0]['changes'][0]['value'];
            $this->handle_messages($value['messages'], $value['contacts'] ?? []);
        }
    }

    private function handle_statuses($statuses) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
        foreach ($statuses as $status) {
            $whatsapp_msg_id = $status['id'] ?? '';
            $status_type = $status['status'] ?? '';
            if ($whatsapp_msg_id) {
                $wpdb->update($table_messages, ['status' => $status_type], ['whatsapp_msg_id' => $whatsapp_msg_id]);
            }
        }
    }

    private function handle_messages($messages, $contacts) {
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'niz_wa_contacts';
        $table_conversations = $wpdb->prefix . 'niz_wa_conversations';
        $table_messages = $wpdb->prefix . 'niz_wa_messages';

        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $msg_id = $msg['id'] ?? '';
            $timestamp = isset($msg['timestamp']) ? date('Y-m-d H:i:s', $msg['timestamp']) : current_time('mysql');
            $message_type = $this->detect_message_type($msg);
            $content = $this->extract_content($msg);

            // Find or create contact
            $contact_id = $this->upsert_contact($from, $contacts);
            if (!$contact_id) continue;

            // Find or create conversation
            $conversation_id = $this->get_or_create_conversation($contact_id);

            // Store message
            $wpdb->insert($table_messages, [
                'conversation_id' => $conversation_id,
                'direction' => 'inbound',
                'message_type' => $message_type,
                'whatsapp_msg_id' => $msg_id,
                'content' => $content,
                'status' => 'delivered',
                'sent_at' => $timestamp,
                'created_at' => current_time('mysql')
            ]);

            // Update conversation: unread count, window expiry
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_conversations SET unread_count = unread_count + 1,
                 window_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                 updated_at = NOW() WHERE id = %d",
                $conversation_id
            ));

            // Trigger AI or response (you can add later)
            // For now, just log
            error_log("Message from $from: $content");
        }
    }

    private function detect_message_type($msg) {
        if (isset($msg['text'])) return 'text';
        if (isset($msg['image'])) return 'image';
        if (isset($msg['audio'])) return 'audio';
        if (isset($msg['video'])) return 'video';
        if (isset($msg['document'])) return 'document';
        if (isset($msg['location'])) return 'location';
        return 'unknown';
    }

    private function extract_content($msg) {
        if (isset($msg['text']['body'])) return $msg['text']['body'];
        if (isset($msg['interactive']['button_reply']['title'])) return $msg['interactive']['button_reply']['title'];
        if (isset($msg['interactive']['list_reply']['title'])) return $msg['interactive']['list_reply']['title'];
        return '';
    }

    private function upsert_contact($phone, $contacts_meta) {
        global $wpdb;
        $table = $wpdb->prefix . 'niz_wa_contacts';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE phone = %s", $phone));
        if ($existing) return $existing;

        $name = '';
        foreach ($contacts_meta as $contact) {
            if ($contact['wa_id'] === $phone) {
                $name = $contact['profile']['name'] ?? '';
                break;
            }
        }
        $wpdb->insert($table, [
            'wa_id' => $phone,
            'phone' => $phone,
            'name' => $name,
            'created_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }

    private function get_or_create_conversation($contact_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'niz_wa_conversations';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE contact_id = %d AND status NOT IN ('resolved') ORDER BY id DESC LIMIT 1",
            $contact_id
        ));
        if ($existing) return $existing;

        $wpdb->insert($table, [
            'contact_id' => $contact_id,
            'status' => 'open',
            'unread_count' => 0,
            'window_expires_at' => date('Y-m-d H:i:s', time() + 86400),
            'created_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }

    private function get_webhook_id($payload) {
        if (isset($payload['entry'][0]['changes'][0]['value']['messages'][0]['id'])) {
            return $payload['entry'][0]['changes'][0]['value']['messages'][0]['id'];
        }
        if (isset($payload['entry'][0]['changes'][0]['value']['statuses'][0]['id'])) {
            return $payload['entry'][0]['changes'][0]['value']['statuses'][0]['id'];
        }
        return null;
    }

    private function is_duplicate($webhook_id) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_webhook_events} WHERE webhook_id = %s AND processed = 1",
            $webhook_id
        ));
        return !empty($exists);
    }

    private function store_webhook_event($webhook_id, $payload) {
        global $wpdb;
        $wpdb->insert($this->table_webhook_events, [
            'webhook_id' => $webhook_id,
            'payload' => json_encode($payload),
            'processed' => 0,
            'created_at' => current_time('mysql')
        ]);
    }
}

*/
