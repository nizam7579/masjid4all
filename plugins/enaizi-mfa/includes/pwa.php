<?php
if (!defined('ABSPATH')) exit;


/*

add_action('wp_enqueue_scripts', 'enaizi_pwa_enqueue_assets');

function enaizi_pwa_enqueue_assets() {
    // Enqueue CSS
    wp_enqueue_style(
        'enaizi-pwa-global-bar',
        ENAIZI_PWA_URL . 'assets/css/global-bar.css',
        [],
        ENAIZI_PWA_VERSION
    );
  
    // Enqueue JS
    wp_enqueue_script(
        'enaizi-pwa-global-bar',
        ENAIZI_PWA_URL . 'assets/js/global-bar.js',
        [], // no dependencies (plain JS)
        ENAIZI_PWA_VERSION,
        true // load in footer
    );

    // Pass dynamic data to JS
    wp_localize_script('enaizi-pwa-global-bar', 'enaiziPWA', [
        'iconUrl' => ENAIZI_PWA_URL . 'assets/icon-192.png',
        'isUserLoggedIn' => is_user_logged_in(),
        'loginUrl' => '/member/',
        'businessUrl' => '/business',
        'knowledgeUrl' => '/knowledge-hub/'
    ]);
}

class Enaizi_PWA_Analytics {

    private $ga_measurement_id;
    private $ga_api_secret;

    public function __construct() {
        // Check if constants are defined and not placeholders
        if (!defined('ENAIZI_PWA_GA_MEASUREMENT_ID') || 
            !defined('ENAIZI_PWA_GA_API_SECRET') ||
            ENAIZI_PWA_GA_MEASUREMENT_ID === 'G-5EJ88ZM6FR' ||
            ENAIZI_PWA_GA_API_SECRET === 'um8En1qKRVuhVBTv3xsvIA') {
            return; // Analytics disabled – placeholder values
        }
    
        $this->ga_measurement_id = ENAIZI_PWA_GA_MEASUREMENT_ID;
        $this->ga_api_secret = ENAIZI_PWA_GA_API_SECRET;
    
        add_action('wp_enqueue_scripts', [$this, 'enqueue_analytics_script']);
        add_action('rest_api_init', [$this, 'register_analytics_endpoint']);
    }

    public function enqueue_analytics_script() {
        if (is_admin()) return;

        wp_enqueue_script(
            'enaizi-pwa-analytics',
            ENAIZI_PWA_URL . 'assets/js/analytics.js',
            [],
            ENAIZI_PWA_VERSION,
            true
        );

        wp_localize_script('enaizi-pwa-analytics', 'enaiziPWAAnalytics', [
            'restUrl' => rest_url('enaizi-pwa/v1/analytics'),
            'nonce' => wp_create_nonce('wp_rest'),
            'measurementId' => $this->ga_measurement_id,
            'apiSecret' => $this->ga_api_secret
        ]);
    }

    public function register_analytics_endpoint() {
        register_rest_route('enaizi-pwa/v1', '/analytics', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_analytics_event'],
            'permission_callback' => [$this, 'check_analytics_nonce']
        ]);
    }

    public function check_analytics_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, 'wp_rest');
    }

    public function handle_analytics_event($request) {
        $params = $request->get_json_params();
        $event_name = sanitize_text_field($params['event_name']);
        $event_params = $params['event_params'] ?? [];

        // Forward to GA4 Measurement Protocol
        $url = 'https://www.google-analytics.com/mp/collect?measurement_id=' .
               $this->ga_measurement_id . '&api_secret=' . $this->ga_api_secret;

        $payload = [
            'client_id' => $this->get_client_id(),
            'events' => [[
                'name' => $event_name,
                'params' => $event_params
            ]]
        ];

        wp_remote_post($url, [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 2 // fire-and-forget
        ]);

        return ['success' => true];
    }

    private function get_client_id() {
        if (isset($_COOKIE['_ga'])) {
            return $_COOKIE['_ga'];
        }
        return wp_generate_uuid4();
    }
}

*/

class Enaizi_PWA_Handler {

    public function __construct() {
        add_action('wp_head', [$this, 'output_manifest_link']);
        add_action('wp_footer', [$this, 'register_service_worker']);
        add_action('wp_footer', [$this, 'add_install_prompt_script']);
        add_action('wp_ajax_niz_mfa_dismiss_install_prompt', [$this, 'dismiss_install_prompt']);
        add_action('wp_ajax_nopriv_niz_mfa_dismiss_install_prompt', [$this, 'dismiss_install_prompt']);
    }

    public function output_manifest_link() {
        $manifest_url = ENAIZI_PWA_URL . 'manifest.json';
        echo '<link rel="manifest" href="' . esc_url($manifest_url) . '">';
        echo '<meta name="theme-color" content="#0f766e">';
        echo '<link rel="apple-touch-icon" href="' . esc_url(ENAIZI_PWA_URL . 'assets/icon-192.png') . '">';
    }
    

    public function register_service_worker() {
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('SW registered', reg))
                .catch(err => console.warn('SW registration failed', err));
        }
        </script>
        <?php
    }

    /**
     * Add custom install prompt script (works on Android & iOS)
     */
    public function add_install_prompt_script() {
        ?>
        <script>
        (function() {
            // Store the deferred prompt globally
            window.deferredPrompt = null;
    
            window.addEventListener('beforeinstallprompt', (e) => {
                //e.preventDefault();
                window.deferredPrompt = e;
                console.log('Install prompt ready');
            });
    
            // Optional: clear prompt after use
            window.triggerInstallPrompt = async () => {
                if (window.deferredPrompt) {
                    window.deferredPrompt.prompt();
                    const { outcome } = await window.deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        console.log('User installed the app');
                    }
                    window.deferredPrompt = null;
                } else {
                    console.log('No install prompt available');
                    alert('Install prompt not available. Make sure you are using a supported browser (Chrome, Edge) and the site is served over HTTPS.');
                }
            };
        })();
        </script>
        <?php
    }
}