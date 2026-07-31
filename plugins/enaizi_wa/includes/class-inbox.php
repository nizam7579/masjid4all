<?php
class Niz_WA_Inbox {
     
    public function __construct() {
        add_shortcode('whatsapp_inbox', [$this, 'render']);
    
        add_action('wp_ajax_niz_wa_get_conversations', [$this, 'ajax_get_conversations']);
        add_action('wp_ajax_niz_wa_get_messages', [$this, 'ajax_get_messages']);
        add_action('wp_ajax_niz_wa_send_reply', [$this, 'ajax_send_reply']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        add_action('wp_ajax_niz_wa_get_messages_by_user', [$this, 'ajax_get_messages_by_user']); 
        add_action('wp_ajax_niz_wa_get_window_status', [$this, 'ajax_get_window_status']); 
        add_action('wp_ajax_niz_wa_send_template', [$this, 'ajax_send_template']);
        
    }
    
 
    public function enqueue_assets() {
        global $post;
    
        if (
            is_a($post, 'WP_Post') &&
            (
                has_shortcode($post->post_content, 'niz_wa_inbox')
            )
        ) {
            wp_enqueue_style(
                'niz-wa-inbox',
                NIZ_WA_PLUGIN_URL . 'assets/css/inbox.css',
                [],
                NIZ_WA_VERSION
            );
    
            wp_enqueue_script(
                'niz-wa-inbox',
                NIZ_WA_PLUGIN_URL . 'assets/js/inbox.js',
                ['jquery'],
                NIZ_WA_VERSION,
                true
            );
    
            wp_localize_script('niz-wa-inbox', 'niz_wa_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('niz_wa_inbox')
            ]);
        }
    }

    public function render() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return '<p>You do not have permission to view the inbox.</p>';
        }
        ob_start();
        ?>
        <div class="niz-wa-inbox">
            <div class="niz-wa-conversations" id="niz-wa-conversations">Loading...</div>
            <div class="niz-wa-messages-area">
                <div class="niz-wa-messages" id="niz-wa-messages">
                    <div style="text-align:center; color:#999;">Select a conversation</div>
                </div>
                <div class="niz-wa-reply-area" style="display:none;">
                    <textarea id="niz-wa-reply-text" rows="2" placeholder="Type a reply..."></textarea>
                    <button id="niz-wa-send-reply">Send</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

public function ajax_get_conversations() {
    $this->verify_request();
    global $wpdb;

    $user_id = absint($_REQUEST['user_id'] ?? 0);

    if (!$user_id) {
        wp_send_json_success([]);
    }

    error_log('AJAX USER ID: ' . $user_id);

    $contacts = $wpdb->prefix . 'niz_wa_contacts';
    $conversations = $wpdb->prefix . 'niz_wa_conversations';
    $messages = $wpdb->prefix . 'niz_wa_messages';

    $sql = $wpdb->prepare(
        "SELECT c.id, cnt.user_id, cnt.phone, cnt.name as contact_name, c.unread_count,
            (SELECT content FROM $messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM $messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_time
        FROM $conversations c
        JOIN $contacts cnt ON c.contact_id = cnt.id
        WHERE cnt.user_id = %d",
        $user_id
    );

    $results = $wpdb->get_results($sql);

    error_log('SQL: ' . $sql);
    error_log('RESULTS: ' . print_r($results, true));

    wp_send_json_success($results);
}

    public function ajax_get_conversationsxx() {
        $this->verify_request();
        global $wpdb; 
        $user_id = absint($_REQUEST['user_id'] ?? 0);
        $contacts = $wpdb->prefix . 'niz_wa_contacts';
        $conversations = $wpdb->prefix . 'niz_wa_conversations';
        $messages = $wpdb->prefix . 'niz_wa_messages';
        $sql = $wpdb->prepare(
            "SELECT c.id, cnt.phone, cnt.name as contact_name, c.unread_count,
                (SELECT content FROM $messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM $messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_time
            FROM $conversations c
            JOIN $contacts cnt ON c.contact_id = cnt.id
            WHERE cnt.user_id = %d
            ORDER BY COALESCE(last_time, c.created_at) DESC",
            $user_id
        );
        wp_send_json_success($wpdb->get_results($sql));
    }

    public function ajax_get_messages() {
        $this->verify_request();
        $conv_id = intval($_POST['conversation_id']);
        global $wpdb;
        $table_messages = $wpdb->prefix . 'niz_wa_messages';
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_messages WHERE conversation_id = %d ORDER BY created_at ASC",
            $conv_id
        ));
        wp_send_json_success($messages);
    }

    public function ajax_send_reply() {
        $this->verify_request();
    
        $user_id = absint($_POST['user_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
    
        if (!$user_id || !$message) {
            wp_send_json_error('Missing data');
        }
    
        if (!niz_wa_is_window_active($user_id)) {
            wp_send_json_error('Outside 24-hour window');
        }
    
        $phone = niz_wa_get_user_phone($user_id);
    
        if (!$phone) {
            wp_send_json_error('No phone found');
        }
    
        $result = Niz_WA_WhatsApp::send_text(
            $phone,
            $message
        );
    
        if (!$result) {
            wp_send_json_error('Send failed');
        }
    
        global $wpdb;
    
        $table = $wpdb->prefix . 'niz_wa_messages';
    
        $wa_id = $result['messages'][0]['id'] ?? '';
    
        $wpdb->update(
            $table,
            [
                'agent_id' => get_current_user_id()
            ],
            [
                'whatsapp_msg_id' => $wa_id
            ]
        );
    
        wp_send_json_success();
    }


    private function verify_request() {
        if (
            !wp_verify_nonce($_REQUEST['nonce'] ?? '', 'niz_wa_inbox')
            || !is_user_logged_in()
        ) {
            wp_send_json_error('Permission denied');
        }
    }
    
    public function ajax_get_messages_by_user() {
        $this->verify_request();
    
        global $wpdb;
    
        $user_id = absint($_REQUEST['user_id'] ?? 0);
    
        if (!$user_id) {
            wp_send_json_success([]);
        }
    
        $table = $wpdb->prefix . 'niz_wa_messages';
    
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM $table
             WHERE user_id = %d
             ORDER BY created_at ASC",
            $user_id
        ));
    
        foreach ($messages as &$msg) {
            if ($msg->direction === 'inbound') {
                $msg->sender_name = 'User';
            } elseif ((int)$msg->agent_id === NIZ_WA_BOT_USER_ID) {
                $msg->sender_name = 'Sofia';
            } else {
                $agent = get_userdata($msg->agent_id);
                $msg->sender_name = $agent ? $agent->display_name : 'Agent';
            }
        }
    
        wp_send_json_success($messages);
    }
    
    public function ajax_get_window_status() {
        //$this->verify_request();
    
        $user_id = absint($_REQUEST['user_id'] ?? 0);
    
        if (!$user_id) {
            wp_send_json_error('Invalid user');
        }
    
        $last = get_user_meta($user_id, 'last_whatsapp_inbound_at', true);
    
        $active = niz_wa_is_window_active($user_id);
    
        $expires = $last
            ? date('Y-m-d H:i:s', strtotime($last . ' +24 hours'))
            : '';
    
        wp_send_json_success([
            'active' => $active,
            'last_inbound' => $last,
            'expires_at' => $expires
        ]);
    }
    
    
    
    public function ajax_send_template() {
        $this->verify_request();
    
        $user_id = absint($_POST['user_id'] ?? 0);
        $template_key = sanitize_key($_POST['template_key'] ?? '');
        $variables = $_POST['variables'] ?? [];
        $media = $_POST['media'] ?? [];
    
        if (!$user_id || !$template_key) {
            wp_send_json_error('Missing data');
        }
    
        $phone = niz_wa_get_user_phone($user_id);
    
        if (!$phone) {
            wp_send_json_error('No phone found');
        }
    
        $result = Niz_WA_WhatsApp::send_template_dynamic(
            $phone,
            $template_key,
            $variables,
            $media,
            get_current_user_id()
        );
    
        if (!$result) {
            wp_send_json_error('Template send failed');
        }
    
        wp_send_json_success();
    }
    
}