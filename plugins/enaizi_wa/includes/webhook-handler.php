<?php

class Niz_WA_Webhook_Handler {
    
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    public function register_routes() {
        register_rest_route('niz_wa/v1', '/webhook', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_request'],
            'permission_callback' => '__return_true',
        ]);
    }
    public function handle_request($request) {
        if ($request->get_method() === 'GET') return $this->verify($request);
        return $this->process($request);
    }
    
    private function verify($request) {
        $verify_token = trim($request->get_param('hub_verify_token') ?? '');
        $challenge = $request->get_param('hub_challenge');
        $settings = get_option('niz_wa_whatsapp_settings', []);
        $stored_token = $settings['verify_token'] ?? '';
        if ($verify_token === $stored_token) {
            status_header(200);
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }
        return new WP_Error('invalid_token', 'Token mismatch', ['status' => 403]);
    }
    
    private function process($request) {
        $payload = json_decode($request->get_body(), true);
        error_log('NIZ_WA: Webhook payload received: ' . print_r($payload, true)); // ADD THIS
    
        if (!$payload) return new WP_REST_Response(['status' => 'invalid'], 200);
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (!$value) return new WP_REST_Response(['status' => 'no_value'], 200);
        if (isset($value['messages'])) {
            $this->handle_messages($value['messages'], $value['contacts'] ?? []);
        } elseif (isset($value['statuses'])) {
            $this->handle_statuses($value['statuses']);
        }
        return new WP_REST_Response(['status' => 'processed'], 200);
    }
    private function handle_messages($messages, $contacts_meta) {
        wp_die('HANDLE_MESSAGES HIT');
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'niz_wa_contacts';
        $table_conversations = $wpdb->prefix . 'niz_wa_conversations';
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $msg_id = $msg['id'] ?? '';
            $timestamp = isset($msg['timestamp']) ? date('Y-m-d H:i:s', $msg['timestamp']) : current_time('mysql');
            $type = $this->detect_type($msg);
            $content = $this->extract_content($msg);
            error_log('FROM=' . $from);
            error_log('TYPE=' . $type);
            error_log('CONTENT=' . $content);
            
            $contact_id = $this->upsert_contact($from, $contacts_meta);
            if (!$contact_id) continue;
            $conv_id = $this->get_or_create_conversation($contact_id);
            if (!$conv_id) continue;
            $wpdb->insert($table_messages, [
                'conversation_id' => $conv_id,
                'direction' => 'inbound',
                'message_type' => $type,
                'whatsapp_msg_id' => $msg_id,
                'content' => $content,
                'status' => 'delivered',
                'sent_at' => $timestamp,
                'created_at' => current_time('mysql')
            ]);
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_conversations SET unread_count = unread_count + 1, window_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = %d",
                $conv_id
            ));
            // ✅ Trigger intent processing (call your existing functions)
            $this->trigger_intent($from, $content);
        }
    }
    private function handle_statuses($statuses) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
        foreach ($statuses as $status) {
            $msg_id = $status['id'] ?? '';
            $status_type = $status['status'] ?? '';
            if ($msg_id) {
                $wpdb->update($table_messages, ['status' => $status_type], ['whatsapp_msg_id' => $msg_id]);
            }
        }
    }
    private function detect_type($msg) {
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
        if (isset($msg['location'])) return json_encode($msg['location']);
        return '';
    }
    private function upsert_contact($phone, $contacts_meta) {
        global $wpdb;
        $table = $wpdb->prefix . 'niz_wa_contacts';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE phone = %s", $phone));
        if ($existing) {
            // Check if user_id is already set
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $existing));
            if (!$user_id) {
                // Try to find WordPress user by phone
                $wp_user = $this->find_user_by_phone($phone);
                if ($wp_user) {
                    $wpdb->update($table, ['user_id' => $wp_user->ID], ['id' => $existing]);
                }
            }
            return $existing;
        }
        $name = '';
        foreach ($contacts_meta as $c) {
            if (($c['wa_id'] ?? '') === $phone) {
                $name = $c['profile']['name'] ?? '';
                break;
            }
        }
        $wpdb->insert($table, [
            'wa_id' => $phone,
            'phone' => $phone,
            'name'  => $name,
            'created_at' => current_time('mysql')
        ]);
        $contact_id = $wpdb->insert_id;
        // After insert, try to link to existing WordPress user
        $wp_user = $this->find_user_by_phone($phone);
        if ($wp_user) {
            $wpdb->update($table, ['user_id' => $wp_user->ID], ['id' => $contact_id]);
        }
        return $contact_id;
    }
    
    private function find_user_by_phone($phone) {
        $users = get_users([
            'meta_key' => 'user_phone',
            'meta_value' => $phone,
            'number' => 1,
            'fields' => 'ID'
        ]);
        if (empty($users)) return false;
        return new WP_User($users[0]);
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
    
    private function trigger_intent($phone, $message) {
        //Niz_WA_WhatsApp::send_text($phone, 'TEST AUTO REPLY');
        $clean = preg_replace('/\D+/', '', $phone);
        Niz_WA_WhatsApp::send_text($clean, 'TEST AUTO REPLY');
    }

   

    /*
    private function trigger_intent($phone, $message) {
        $msg_upper = strtoupper(trim($message));
        // Direct keyword matches – call your existing functions
        if (strpos($msg_upper, 'START') === 0 || strpos($msg_upper, 'HELP') === 0) {
            if (function_exists('wapi_start')) wapi_start($phone);
        } elseif (strpos($msg_upper, 'REGISTER') === 0) {
            if (function_exists('wapi_register')) wapi_register($phone);
        } elseif (strpos($msg_upper, 'PASSWORD') === 0 || strpos($msg_upper, 'FORGOT') !== false) {
            if (function_exists('wapi_password')) wapi_password($phone);
        } else {
            // Optional: AI fallback
            do_action('niz_wa_ai_response', $phone, $message);
        }
    }
    */
    
}