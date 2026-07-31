<?php
class Niz_WA_AI {
    private static $settings;
    private static $system_prompt = "You are Sofia, a helpful assistant for Masjid4All, a platform for mosques and Islamic services. Answer concisely and helpfully. If unsure, suggest contacting support.";

    private static function get_settings() {
        if (!self::$settings) {
            self::$settings = get_option('niz_wa_ai_settings', []);
        }
        return self::$settings;
    }

    public static function ask($question) {
        $log_file = WP_CONTENT_DIR . '/uploads/niz_ai_debug.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - AI ask: $question\n", FILE_APPEND);
        
        $settings = self::get_settings();
        $provider = $settings['provider'] ?? 'deepseek';
        $api_key = $settings['api_key'] ?? '';
        $model = $settings['model'] ?? ($provider === 'deepseek' ? 'deepseek-chat' : 'gpt-3.5-turbo');
        $temperature = floatval($settings['temperature'] ?? 0.7);
        $max_tokens = intval($settings['max_tokens'] ?? 500);

        if (empty($api_key)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - API key missing\n", FILE_APPEND);
            return null;
        }

        if ($provider === 'deepseek') {
            return self::call_deepseek($api_key, $model, $question, $temperature, $max_tokens);
        } elseif ($provider === 'openai') {
            return self::call_openai($api_key, $model, $question, $temperature, $max_tokens);
        }
        return null;
    }

    private static function call_deepseek($api_key, $model, $question, $temperature, $max_tokens) {
        $log_file = WP_CONTENT_DIR . '/uploads/niz_ai_debug.log';
        $url = 'https://api.deepseek.com/v1/chat/completions';
        $messages = [
            ['role' => 'system', 'content' => self::$system_prompt],
            ['role' => 'user', 'content' => $question]
        ];
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens
        ];
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        if (is_wp_error($response)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - DeepSeek WP_Error: " . $response->get_error_message() . "\n", FILE_APPEND);
            return null;
        }
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status !== 200) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - DeepSeek HTTP $status: $body\n", FILE_APPEND);
            return null;
        }
        $data = json_decode($body, true);
        $reply = $data['choices'][0]['message']['content'] ?? null;
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - DeepSeek reply: " . substr($reply, 0, 200) . "\n", FILE_APPEND);
        return $reply;
    }

    private static function call_openai($api_key, $model, $question, $temperature, $max_tokens) {
        $log_file = WP_CONTENT_DIR . '/uploads/niz_ai_debug.log';
        $url = 'https://api.openai.com/v1/chat/completions';
        $messages = [
            ['role' => 'system', 'content' => self::$system_prompt],
            ['role' => 'user', 'content' => $question]
        ];
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens
        ];
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        if (is_wp_error($response)) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - OpenAI WP_Error: " . $response->get_error_message() . "\n", FILE_APPEND);
            return null;
        }
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status !== 200) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - OpenAI HTTP $status: $body\n", FILE_APPEND);
            return null;
        }
        $data = json_decode($body, true);
        $reply = $data['choices'][0]['message']['content'] ?? null;
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - OpenAI reply: " . substr($reply, 0, 200) . "\n", FILE_APPEND);
        return $reply;
    }
}