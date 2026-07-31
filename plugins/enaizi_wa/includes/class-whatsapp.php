<?php

class Niz_WA_WhatsApp {

    private static function get_settings() {
        return get_option('niz_wa_whatsapp_settings', []);
    }
 
    // Send temporary password using meta template
    public static function send_temp_password($to, $password) {
        $settings = self::get_settings();
    
        $access_token = $settings['access_token'] ?? '';
        $phone_number_id = $settings['phone_number_id'] ?? '';
    
        if (!$access_token || !$phone_number_id) {
            return false;
        }
    
        $clean_to = preg_replace('/\D+/', '', $to);
     
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $clean_to,
            'type' => 'template',
            'template' => [
                'name' => 'temporary_password',
                'language' => [
                    'code' => 'en_US'
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $password
                            ]
                        ]
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $password
                            ]
                        ]
                    ]
                ]
            ]
        ];
     
        //return self::send_request($phone_number_id, $access_token, $payload);
        $result = self::send_request($phone_number_id, $access_token, $payload);
        error_log('TEMP PASSWORD RESULT: ' . print_r($result, true));
        return $result;

    }
    public static function send_text($to, $message) {
        $settings = self::get_settings();

        $access_token = $settings['access_token'] ?? '';
        $phone_number_id = $settings['phone_number_id'] ?? '';

        if (!$access_token || !$phone_number_id) {
            error_log('NIZ_WA missing WhatsApp credentials');
            return false;
        }

        $clean_to = preg_replace('/\D+/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $clean_to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => (string) $message
            ]
        ];

        $result = self::send_request($phone_number_id, $access_token, $payload);

        if ($result) {
            self::log_outbound_message(
                $clean_to,
                'text',
                $message,
                $result,
                NIZ_WA_BOT_USER_ID
            );
        }

        return $result;
    }
 
    public static function send_buttons($to, $body_text, $buttons = []) {
        $settings = self::get_settings();

        $access_token = $settings['access_token'] ?? '';
        $phone_number_id = $settings['phone_number_id'] ?? '';

        if (!$access_token || !$phone_number_id) {
            error_log('NIZ_WA missing WhatsApp credentials');
            return false;
        }

        $clean_to = preg_replace('/\D+/', '', $to);

        $formatted_buttons = [];

        foreach ($buttons as $btn) {
            $formatted_buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'],
                    'title' => $btn['title']
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $clean_to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => (string) $body_text
                ],
                'action' => [
                    'buttons' => $formatted_buttons
                ]
            ]
        ];

        $result = self::send_request($phone_number_id, $access_token, $payload);

        if ($result) {
            self::log_outbound_message(
                $clean_to,
                'interactive',
                $body_text,
                $result,
                NIZ_WA_BOT_USER_ID
            );
        }

        return $result;
    }

    private static function send_request($phone_number_id, $access_token, $payload) {
        $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 20
        ]);

        if (is_wp_error($response)) {
            error_log('NIZ_WA WP Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['error'])) {
            error_log('NIZ_WA FULL RESPONSE: ' . wp_json_encode($body));
            return false;
        }

        return $body;
    }

    private static function log_outbound_message(
        $phone,
        $type,
        $content,
        $api_result,
        $agent_id = null
    ) {
        global $wpdb;
    
        $table_contacts = $wpdb->prefix . 'niz_wa_contacts';
        $table_conversations = $wpdb->prefix . 'niz_wa_conversations';
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
    
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id
             FROM $table_contacts
             WHERE phone = %s",
            $phone
        ));
    
        if (!$contact) {
            return;
        }
    
        $conversation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id
             FROM $table_conversations
             WHERE contact_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $contact->id
        ));
    
        if (!$conversation_id) {
            return;
        }
    
        $wa_msg_id = $api_result['messages'][0]['id'] ?? '';
    
        $wpdb->insert($table_messages, [
            'user_id' => $contact->user_id,
            'conversation_id' => $conversation_id,
            'agent_id' => $agent_id,
            'direction' => 'outbound',
            'message_type' => $type,
            'whatsapp_msg_id' => $wa_msg_id,
            'content' => $content,
            'status' => 'sent',
            'sent_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ]);
    }
    
    private static function get_templates() {
        return [
            'welcome' => [
                'label' => 'Welcome',
                'name' => 'welcome_template',
                'type' => 'text',
                'variables' => []
            ],
    
            'membership_invite' => [
                'label' => 'Membership Invite',
                'name' => 'membership_invite',
                'type' => 'text',
                'variables' => ['name']
            ],
    
            'claim_business' => [
                'label' => 'Claim Business',
                'name' => 'claim_business',
                'type' => 'text',
                'variables' => []
            ],
    
            'advertise' => [
                'label' => 'Advertise',
                'name' => 'advertise',
                'type' => 'text',
                'variables' => []
            ],
    
            'premium_upgrade' => [
                'label' => 'Premium Upgrade',
                'name' => 'premium_upgrade',
                'type' => 'text',
                'variables' => []
            ]
        ];
    }
    
    public static function send_template_dynamic(
        $to,
        $template_key,
        $variables = [],
        $media = [],
        $agent_id = null
    ) {
        $templates = self::get_templates();
    
        if (empty($templates[$template_key])) {
            return false;
        }
    
        $template = $templates[$template_key];
    
        $settings = self::get_settings();
    
        $access_token = $settings['access_token'] ?? '';
        $phone_number_id = $settings['phone_number_id'] ?? '';
    
        if (!$access_token || !$phone_number_id) {
            return false;
        }
    
        $clean_to = preg_replace('/\D+/', '', $to);
    
        $body_params = [];
    
        foreach ($variables as $value) {
            $body_params[] = [
                'type' => 'text',
                'text' => (string) $value
            ];
        }
    
        $components = [];
    
        if (!empty($body_params)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $body_params
            ];
        }
    
        if ($template['type'] === 'image' && !empty($media['image'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $media['image']
                        ]
                    ]
                ]
            ];
        }
    
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $clean_to,
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => [
                    'code' => 'en_US'
                ],
                'components' => $components
            ]
        ];
    
        $result = self::send_request($phone_number_id, $access_token, $payload);
    
        if ($result) {
            self::log_outbound_message(
                $clean_to,
                'template',
                wp_json_encode([
                    'template' => $template_key,
                    'variables' => $variables,
                    'media' => $media
                ]),
                $result,
                $agent_id
            );
        }
    
        return $result;
    }
    
    
}