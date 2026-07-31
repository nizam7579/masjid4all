<?php

class Niz_WA_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu() {

        add_menu_page(
            'WA Settings',
            'WA Settings',
            'manage_options',
            'enaizi-wa-settings',
            [$this, 'render_settings_page'],
            'dashicons-format-chat'
        );
    
 
    }

    public function register_settings() {
        register_setting('niz_wa_settings_group', 'niz_wa_whatsapp_settings');
        register_setting('niz_wa_settings_group', 'niz_wa_ai_settings');

        add_settings_section(
            'niz_wa_whatsapp_section',
            'WhatsApp Cloud API',
            null,
            'enaizi-wa-settings'
        );

        add_settings_section(
            'niz_wa_ai_section',
            'AI Assistant',
            null,
            'enaizi-wa-settings'
        );

        $whatsapp_fields = [
            'phone_number_id' => 'Phone Number ID',
            'waba_id' => 'WABA ID',
            'access_token' => 'Access Token',
            'verify_token' => 'Verify Token',
            'app_secret' => 'App Secret'
        ];

        foreach ($whatsapp_fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, 'render_input'],
                'enaizi-wa-settings',
                'niz_wa_whatsapp_section',
                [
                    'name' => $field,
                    'option_group' => 'niz_wa_whatsapp_settings',
                    'type' => in_array($field, ['access_token', 'app_secret']) ? 'password' : 'text'
                ]
            );
        }

        $ai_fields = [
            'provider' => 'Provider',
            'api_key' => 'API Key',
            'model' => 'Model',
            'temperature' => 'Temperature',
            'max_tokens' => 'Max Tokens'
        ];

        foreach ($ai_fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, 'render_input'],
                'enaizi-wa-settings',
                'niz_wa_ai_section',
                [
                    'name' => $field,
                    'option_group' => 'niz_wa_ai_settings',
                    'type' => $field === 'api_key' ? 'password' : 'text'
                ]
            );
        }
    }

    public function render_input($args) {
        $options = get_option($args['option_group'], []);
        $value = $options[$args['name']] ?? '';
        $type = $args['type'] ?? 'text';

        echo "<input type='{$type}' name='{$args['option_group']}[{$args['name']}]' value='" . esc_attr($value) . "' class='regular-text' />";
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>ENAIZI WA Settings</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('niz_wa_settings_group');
                do_settings_sections('enaizi-wa-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
 
    
}