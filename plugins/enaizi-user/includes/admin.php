<?php

// 1. Register the Shortcode
add_shortcode('wa_conversation', 'niz_display_wa_conversation');

function niz_display_wa_conversation() {
    global $wpdb;

    // Get and sanitize user_id from URL
    $user_id = isset($_GET['user_id']) ? sanitize_text_field($_GET['user_id']) : '';

    if (empty($user_id)) {
        return '<p style="color: red; text-align: center;">Error: No User ID specified in the URL.</p>';
    }

    // Enqueue styles and scripts inline for ease of installation
    ob_start();
    ?>
    <style>
        .wa-chat-container {
            max-width: 100%;
            margin: 20px auto;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background-color: #efeae2; /* WhatsApp background */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            height: 85vh;
        }
        .wa-chat-header {
            background-color: #008069;
            color: #fff;
            padding: 15px;
            font-weight: bold;
            font-size: 16px;
            border-top-left-radius: 9px;
            border-top-right-radius: 9px;
        }
        .wa-chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column-reverse; /* Keeps layout stable when loading older messages */
        }
        .wa-msg-wrapper {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .wa-message {
            max-width: 65%;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 7px;
            font-size: 14.5px;
            line-height: 1.4;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
        }
        /* Inbound / User */
        .wa-message.inbound {
            background-color: #ffffff;
            align-self: flex-start;
        }
        /* Outbound / Admin */
        .wa-message.outbound {
            background-color: #d9fdd3;
            align-self: flex-end;
        }
        .wa-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #667781;
            margin-top: 4px;
        }
        .wa-sender {
            font-weight: bold;
            margin-right: 10px;
        }
        .wa-load-more-container {
            text-align: center;
            padding: 10px;
            background: #f0f2f5;
            border-bottom: 1px solid #e0e0e0;
        }
        .wa-load-more-btn {
            background-color: #008069;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }
        .wa-load-more-btn:hover {
            background-color: #006653;
        }
        .wa-load-more-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
    </style>

    <div class="wa-chat-container">
 
        <div class="wa-load-more-container">
            <button id="wa-load-more" class="wa-load-more-btn" data-user="<?php echo esc_attr($user_id); ?>" data-offset="0">
                Load Older Messages
            </button>
        </div>

        <div id="wa-chat-body" class="wa-chat-body">
            <!-- Messages will be injected here via AJAX -->
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadMoreBtn = document.getElementById('wa-load-more');
        const chatBody = document.getElementById('wa-chat-body');
        
        function fetchMessages() {
            const userId = loadMoreBtn.getAttribute('data-user');
            const offset = parseInt(loadMoreBtn.getAttribute('data-offset'));

            loadMoreBtn.disabled = true;
            loadMoreBtn.innerText = 'Loading...';

            const formData = new FormData();
            formData.append('action', 'load_wa_messages');
            formData.append('user_id', userId);
            formData.append('offset', offset);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if(res.success && res.data.html) {
                    // Append HTML to the chat window
                    chatBody.insertAdjacentHTML('beforeend', res.data.html);
                    
                    // Update offset tracker
                    loadMoreBtn.setAttribute('data-offset', offset + res.data.count);
                    
                    // If less than 20 messages returned, we reached the end
                    if(res.data.count < 20) {
                        loadMoreBtn.innerText = 'No more messages';
                        loadMoreBtn.disabled = true;
                    } else {
                        loadMoreBtn.innerText = 'Load Older Messages';
                        loadMoreBtn.disabled = false;
                    }
                    
                    // On initial load, scroll to bottom (newest messages)
                    if(offset === 0) {
                        chatBody.scrollTop = chatBody.scrollHeight;
                    }
                } else {
                    loadMoreBtn.innerText = 'No more messages';
                    loadMoreBtn.disabled = true;
                }
            })
            .catch(err => {
                console.error(err);
                loadMoreBtn.innerText = 'Error loading messages';
                loadMoreBtn.disabled = false;
            });
        }

        // Trigger initial load
        fetchMessages();

        // Bind click event
        loadMoreBtn.addEventListener('click', fetchMessages);
    });
    </script>
    <?php
    return ob_get_clean();
}

// 2. AJAX Handler to Fetch Messages
add_action('wp_ajax_load_wa_messages', 'niz_load_wa_messages_callback');
add_action('wp_ajax_nopriv_load_wa_messages', 'niz_load_wa_messages_callback'); // Remove if only logged in users should see this

function niz_load_wa_messages_callback() {
    global $wpdb;

    $user_id = isset($_POST['user_id']) ? sanitize_text_field($_POST['user_id']) : '';
    $offset  = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit   = 20;

    if (empty($user_id)) {
        wp_send_json_error(['message' => 'Invalid User ID']);
    }

    $table_name = $wpdb->prefix . 'niz_wa_messages'; 
    // NOTE: If your table doesn't use the WP prefix, change the query below to target 'niz_wa_messages' directly.

    // Query to pull the data sorted from newest to oldest based on offset
    $messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT direction, content, status, created_at 
             FROM {$wpdb->prefix}niz_wa_messages 
             WHERE user_id = %s 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        )
    );

    if (empty($messages)) {
        wp_send_json_success(['html' => '', 'count' => 0]);
    }

    $html = '';
    foreach ($messages as $msg) {
        $is_inbound = (strtolower($msg->direction) === 'inbound');
        $class      = $is_inbound ? 'inbound' : 'outbound';
        $sender     = $is_inbound ? 'User' : 'Admin';
        
        // Format date and time
        $date_formatted = date('M d, Y g:i A', strtotime($msg->sent_at));
        
        // Status indicator text (sent/received)
        $status_text = $msg->status ? ' • ' . ucfirst(esc_html($msg->status)) : '';

        $html .= '<div class="wa-msg-wrapper">';
        $html .= '    <div class="wa-message ' . $class . '">';
        $html .= '        <div class="wa-content">' . nl2br(esc_html($msg->content)) . '</div>';
        $html .= '        <div class="wa-meta">';
        $html .= '            <span class="wa-sender">' . $sender . '</span>';
        $html .= '            <span class="wa-time">' . $date_formatted . esc_html($status_text) . '</span>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
    }

    wp_send_json_success([
        'html'  => $html,
        'count' => count($messages)
    ]);
}


/**
 * Display User Info.
 * [niz_user_info]
 */
add_shortcode('niz_user_admin_userinfo', 'niz_user_admin_userinfo_shortcode');
function niz_user_admin_userinfo_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $user_id = $_GET['user_id'] ;
    $name    = get_cct_member_data($user_id, 'name');
    $status  = get_cct_member_data($user_id, 'status');
    $rank    = get_cct_member_data($user_id, 'rank');
    $points  = get_cct_member_data($user_id, 'points');
    $country = get_cct_member_data($user_id, 'country');
    $phone   = get_cct_member_data($user_id, 'phone');
    
    ob_start();
    ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <b><?= esc_html(strtoupper($name)); ?></b>

    <table>
        <tr><td>Phone</td><td><b><?= esc_html($phone); ?></b></td></tr>
        <tr><td>Country</td><td><b><?= esc_html($country); ?></b></td></tr>
 
        <tr><td>Status</td><td><b><?= esc_html($status); ?></b></td></tr>
        <tr><td>Rank</td><td><b><?= esc_html($rank); ?></b></td></tr>
        <tr><td>Points</td><td><b><?= esc_html($points) . ' points'; ?></b></td></tr>
    </table>

    <?php
    return ob_get_clean();
}