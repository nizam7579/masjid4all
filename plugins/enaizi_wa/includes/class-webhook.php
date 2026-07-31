<?php

class Niz_WA_Webhook {

    public function register_routes() {
        register_rest_route('niz_wa/v1', '/webhook', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle($request) {
        if ($request->get_method() === 'GET') {
            return $this->verify($request);
        }

        return $this->process($request);
    }

    private function verify($request) {
        $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
        $verify_token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

        $settings = get_option('niz_wa_whatsapp_settings', []);
        $stored_token = $settings['verify_token'] ?? '';

        if ($mode === 'subscribe' && $verify_token === $stored_token) {
            return new WP_REST_Response($challenge, 200);
        }

        return new WP_REST_Response([
            'code' => 'invalid_token',
            'message' => 'Token mismatch'
        ], 403);
    }
 
    
    
    private function process($request) {
        global $wpdb;
    
        $payload = json_decode($request->get_body(), true);
    
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
    
        if (!$value || empty($value['messages'])) {
            return new WP_REST_Response(['status' => 'ignored'], 200);
        }
    
        $contacts_meta = $value['contacts'] ?? [];
    
        $table_contacts = $wpdb->prefix . 'niz_wa_contacts';
        $table_conversations = $wpdb->prefix . 'niz_wa_conversations';
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
    
        foreach ($value['messages'] as $msg) {
            $from = $msg['from'] ?? '';
            $msg_id = $msg['id'] ?? '';
    
            if (!$from || !$msg_id) {
                continue;
            }
    
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_messages WHERE whatsapp_msg_id = %s",
                $msg_id
            ));
    
            if ($exists) {
                continue;
            }
    
            $content = $this->extract_content($msg);
            $type = $msg['type'] ?? 'text';
    
            $timestamp = isset($msg['timestamp'])
                ? date('Y-m-d H:i:s', intval($msg['timestamp']))
                : current_time('mysql');
    
            $clean_phone = preg_replace('/\D+/', '', $from);
    
            /*
            |--------------------------------------------------------------------------
            | Track 24-hour customer service window
            |--------------------------------------------------------------------------
            */
            
            if (function_exists('niz_user_check')) {
                $user_id = niz_user_check($clean_phone);
                if ($user_id) {
                    update_user_meta(
                        $user_id,
                        'last_whatsapp_inbound_at',
                        current_time('mysql')
                    );
                }
            }
    
            /*
            |--------------------------------------------------------------------------
            | Prospect Auto Capture
            |--------------------------------------------------------------------------
            */
    
            $wa_name = '';
    
            foreach ($contacts_meta as $contact) {
                if (($contact['wa_id'] ?? '') === $from) {
                    $wa_name = $contact['profile']['name'] ?? '';
                    break;
                }
            }
    
            if (function_exists('niz_user_create_prospect')) {
                niz_user_create_prospect($clean_phone, $wa_name);
            }
    
            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */
    
            $contact_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_contacts WHERE phone = %s",
                $from
            ));
    
            if (!$contact_id) {
                $user_id = function_exists('niz_user_check')
                    ? niz_user_check($clean_phone)
                    : 0;
                
                $wpdb->insert($table_contacts, [
                    'user_id' => $user_id ?: 0,
                    'wa_id' => $from,
                    'phone' => $from,
                    'name' => $wa_name,
                    'created_at' => current_time('mysql')
                ]);
   
                $contact_id = $wpdb->insert_id;
            }
    
            /*
            |--------------------------------------------------------------------------
            | Conversation
            |--------------------------------------------------------------------------
            */
    
            $conversation_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_conversations
                 WHERE contact_id = %d
                 ORDER BY id DESC
                 LIMIT 1",
                $contact_id
            ));
    
            if (!$conversation_id) {
                $wpdb->insert($table_conversations, [
                    'contact_id' => $contact_id,
                    'status' => 'open',
                    'unread_count' => 0,
                    'created_at' => current_time('mysql')
                ]);
    
                $conversation_id = $wpdb->insert_id;
            }
    
            /*
            |--------------------------------------------------------------------------
            | Save Message
            |--------------------------------------------------------------------------
            */
    
            $wpdb->insert($table_messages, [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id,
                'direction' => 'inbound',
                'message_type' => $type,
                'whatsapp_msg_id' => $msg_id,
                'content' => $content,
                'status' => 'received',
                'sent_at' => $timestamp,
                'created_at' => current_time('mysql')
            ]);
    
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_conversations
                 SET unread_count = unread_count + 1
                 WHERE id = %d",
                $conversation_id
            ));
    
            /*
            |--------------------------------------------------------------------------
            | AI Processing
            |--------------------------------------------------------------------------
            */
    
            if (!empty($content)) {
                try {
                    Niz_WA_Intent::process($clean_phone, $content);
    
                } catch (Throwable $e) {
                    error_log('PROCESS EXCEPTION: ' . $e->getMessage());
                }
            }
        }
    
        return new WP_REST_Response(['status' => 'processed'], 200);
    }

    private function extract_content($msg) {
        if (isset($msg['text']['body'])) {
            return $msg['text']['body'];
        }

        if (isset($msg['interactive']['button_reply']['id'])) {
            return $msg['interactive']['button_reply']['id'];
        }

        if (isset($msg['interactive']['button_reply']['title'])) {
            return $msg['interactive']['button_reply']['title'];
        }

        if (isset($msg['interactive']['list_reply']['id'])) {
            return $msg['interactive']['list_reply']['id'];
        }

        if (isset($msg['interactive']['list_reply']['title'])) {
            return $msg['interactive']['list_reply']['title'];
        }
 
        return '';
    }
}