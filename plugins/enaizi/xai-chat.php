<?php

if (!defined('ABSPATH')) exit;

// 🟢 CHOOSE YOUR AI AGENT: 'gemini', 'openai', or 'deepseek'
define('AI_AGENT', 'openai'); // Change to 'gemini' or 'deepseek' as needed

// API KEYS: resolved in keys.php (wp-config constant, then DB option).
// The OpenAI and DeepSeek keys previously sitting here as commented-out
// literals have been removed - a commented secret is exactly as exposed as
// a live one. Both should be treated as leaked and rotated.

// Enqueue Scripts
function wp_ai_chat_scripts() {
    wp_enqueue_script('wp-ai-chat-js', plugin_dir_url(__FILE__) . 'chat.js', ['jquery'], null, true);
    wp_localize_script('wp-ai-chat-js', 'wpAiChat', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'is_logged_in' => is_user_logged_in() ? 'true' : 'false'
    ]);
    wp_enqueue_style('wp-ai-chat-css', plugin_dir_url(__FILE__) . 'chat.css');
}
add_action('wp_enqueue_scripts', 'wp_ai_chat_scripts');


// AI CHAT
function wp_ai_chat_shortcode() {
    ob_start();
    
    $is_logged_in = is_user_logged_in();
    ?>
    <div class="wp-ai-chat-container">
        <!-- Exit button for mobile -->
  
        <!-- Welcome Message (Only for Logged-Out Users) -->
        <?php if (!$is_logged_in): ?>
            <div class="wp-ai-chat-messages" id="chatMessages">
                <br><br>Assalamualaikum! I’m your AI Assistant, here to make it easy for you to find information—whether it’s about Islam, the Masjid4All project, or other useful knowledge. <br>Feel free to ask me anything! (in any language) 😊 
            </div>
        <?php endif; ?>
 
        <!-- Chat messages -->
        <div class="wp-ai-chat-messages" id="chatMessages"></div>

        <!-- Input box -->
        <div class="wp-ai-chat-input">
            <input type="text" id="chatInput" placeholder="Type a message..." />
            <button onclick="sendMessage()">➤</button>
        </div>
    </div>
    
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (wpAiChat.is_logged_in === 'true') {
                fetchChatHistory();
            }
        });

        function exitChat() {
            window.location.href = "<?= home_url(); ?>";
        }

        function sendMessage() {
            let inputBox = document.getElementById("chatInput");
            let chatMessages = document.getElementById("chatMessages");
            let userMessage = inputBox.value.trim();
            if (userMessage === "") return;

            let userMsgElement = document.createElement("div");
            userMsgElement.className = "user-message";
            userMsgElement.innerText = userMessage;
            chatMessages.appendChild(userMsgElement);

            inputBox.value = "";

            fetch(wpAiChat.ajax_url, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "action=wp_ai_chat&message=" + encodeURIComponent(userMessage)
            })
            .then(response => response.json())
            .then(data => {
                let aiMsgElement = document.createElement("div");
                aiMsgElement.className = "ai-message";
                aiMsgElement.innerText = data.response;
                chatMessages.appendChild(aiMsgElement);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }

        function fetchChatHistory() {
            fetch(wpAiChat.ajax_url + "?action=wp_ai_chat_history")
            .then(response => response.json())
            .then(data => {
                let chatMessages = document.getElementById("chatMessages");
                data.forEach(entry => {
                    let userMsg = document.createElement("div");
                    userMsg.className = "user-message";
                    userMsg.innerText = entry.user_message;
                    chatMessages.appendChild(userMsg);

                    let aiMsg = document.createElement("div");
                    aiMsg.className = "ai-message";
                    aiMsg.innerText = entry.ai_response;
                    chatMessages.appendChild(aiMsg);
                });
            });
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ai_chat', 'wp_ai_chat_shortcode');


// AI CHAT
function wp_ai_chat_ajax() {
    $user_id = get_current_user_id();
    $user_message = sanitize_text_field($_POST['message']);

    $api_url = '';
    $api_key = '';
    $post_data = [];

    switch (AI_AGENT) {
        case 'gemini':
            $api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=" . GEMINI_API_KEY;
            $post_data = json_encode(["contents" => [["parts" => [["text" => $user_message]]]]]);
            break;
        case 'openai':
            $api_url = "https://api.openai.com/v1/chat/completions";
            $api_key = OPENAI_API_KEY;
            $post_data = json_encode([
                "model" => "gpt-3.5-turbo",
                "messages" => [["role" => "user", "content" => $user_message]]
            ]);
            break;
        case 'deepseek':
            $api_url = "https://api.deepseek.com/v1/chat/completions";
            $api_key = DEEPSEEK_API_KEY;
            $post_data = json_encode([
                "model" => "deepseek-chat",
                "messages" => [["role" => "user", "content" => $user_message]]
            ]);
            break;
    }

    $response = wp_remote_post($api_url, [
        'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
        'body' => $post_data
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $ai_response = $body['choices'][0]['message']['content'] ?? "Error: Unexpected API response.";

    if ($user_id > 0) {
        global $wpdb;
        $wpdb->insert('wp_jet_cct_ai_chat', [
            'user_id' => $user_id,
            'user_message' => $user_message,
            'ai_response' => $ai_response,
            'timestamp' => current_time('mysql')
        ]);
    }

    echo json_encode(["response" => $ai_response]);
    wp_die();
}
add_action('wp_ajax_wp_ai_chat', 'wp_ai_chat_ajax');
add_action('wp_ajax_nopriv_wp_ai_chat', 'wp_ai_chat_ajax');


// CHAT HISTORY
function wp_ai_chat_history_ajax() {
    $user_id = get_current_user_id();
    if ($user_id > 0) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, ai_response FROM wp_jet_cct_ai_chat WHERE user_id = %d ORDER BY timestamp DESC LIMIT 10",
            $user_id
        ));
        echo json_encode($results);
    } else {
        echo json_encode([]);
    }
    wp_die();
}
add_action('wp_ajax_wp_ai_chat_history', 'wp_ai_chat_history_ajax');