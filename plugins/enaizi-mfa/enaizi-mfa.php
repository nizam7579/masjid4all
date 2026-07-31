<?php
/**
 * Plugin Name: Enaizi MFA - Muslim Friendly App
 * Plugin URI: https://staging.masjid4all.com
 * Description: Muslim-friendly features including prayer times, Qibla finder, location, date, and PWA support. Optimized for LiteSpeed & Cloudflare edge caching.
 * Version: 1.0.1
 * Author: Masjid4All
 * Text Domain: enaizi-mfa
 */

// 1. PREVENT DIRECT ACCESS
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly to prevent path disclosure
}

// =============================================
// 2. CONSTANTS
// =============================================
define('NIZ_MFA_VERSION', '1.0.4');
define('NIZ_MFA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NIZ_MFA_PLUGIN_URL', plugin_dir_url(__FILE__));

// PWA & Analytics Constants
define('ENAIZI_PWA_VERSION', NIZ_MFA_VERSION);
define('ENAIZI_PWA_URL', NIZ_MFA_PLUGIN_URL);
define('ENAIZI_PWA_GA_MEASUREMENT_ID', 'G-XXXXXXXXXX'); // TODO: Update with your GA4 ID
define('ENAIZI_PWA_GA_API_SECRET', 'your_api_secret_here'); // TODO: Update with your GA4 API secret

// =============================================
// 3. LOAD SHORTCODES
// =============================================
$mfa_shortcode_files = [
    'mosque.php',
    'business.php',
    'web.php',
    'knowledge.php',
    'stripe.php',
    'add-business.php',
    'add-mosque.php',
    'add-website.php',
    'directory.php',
    'website.php',
    'barakah.php',
    'review.php', 
    'upload-image.php',
    'image-upload.php',
    'image-upload-r2.php',
    'location.php',
    'date.php',
    'prayer-times.php', 
    'qibla.php',
    'set-cookies.php',  
    'user-web-process.php',
    'quran.php',
    'community.php',
    'register.php',
    'admin.php',
    'member.php',
    'install-button.php'   
];

foreach ($mfa_shortcode_files as $file) {
    $file_path = NIZ_MFA_PLUGIN_DIR . 'shortcodes/' . $file;
    if (file_exists($file_path) && is_readable($file_path)) {
        require_once $file_path;
    }
}

add_filter('render_block', function($block_content, $block) {
    if (strpos($block_content, 'kb-section-link-overlay') !== false) {
        $block_content = str_replace(
            'class="kb-section-link-overlay"',
            'class="kb-section-link-overlay" aria-label="Learn more"',
            $block_content
        );
    }
    return $block_content;
}, 10, 2);

// =============================================
// 4. LOAD CORE INCLUDES & HELPERS
// =============================================
$mfa_core_files = [
    'stripe.php',
    'pwa.php',
    'helpers.php',
    'business.php',
    'web.php',
];

foreach ($mfa_core_files as $file) {
    $file_path = NIZ_MFA_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($file_path) && is_readable($file_path)) {
        require_once $file_path;
    }
}

// Initialize PWA classes conditionally if they exist
if (class_exists('Enaizi_PWA_Handler')) {
    new Enaizi_PWA_Handler();
}
if (class_exists('Enaizi_PWA_Analytics')) {
    new Enaizi_PWA_Analytics();
}



// =============================================
// 5. ENQUEUE GLOBAL ASSETS (With Cache Busting)
// =============================================

add_action('wp_enqueue_scripts', function(){
    wp_enqueue_script(
        'mfa-website-update',
        plugin_dir_url(__FILE__) . 'assets/js/website-update.js',
        array(),
        '1.0',
        true
    );

}); 

add_action('wp_enqueue_scripts', 'niz_mfa_enqueue_plugin_assets');
function niz_mfa_enqueue_plugin_assets() {
    $get_version = function($path) {
        return file_exists($path) ? filemtime($path) : NIZ_MFA_VERSION;
    };

    // Global CSS
    $global_css_path = NIZ_MFA_PLUGIN_DIR . 'assets/css/global.css';
    if (file_exists($global_css_path)) {
        wp_enqueue_style('niz-global', NIZ_MFA_PLUGIN_URL . 'assets/css/global.css', array(), $get_version($global_css_path));
    }

    // Prayer Times Assets
    // $prayer_css_path = NIZ_MFA_PLUGIN_DIR . 'assets/css/prayer-times.css';
    $prayer_js_path  = NIZ_MFA_PLUGIN_DIR . 'assets/js/prayer-times.js';

    if (file_exists($prayer_css_path)) {
        wp_enqueue_style('niz-prayer-times', NIZ_MFA_PLUGIN_URL . 'assets/css/prayer-times.css', array(), $get_version($prayer_css_path));
    }
    if (file_exists($prayer_js_path)) {
        wp_enqueue_script('niz-prayer-times', NIZ_MFA_PLUGIN_URL . 'assets/js/prayer-times.js', array(), $get_version($prayer_js_path), true);
    }

    // Qibla Finder Assets
    // $qibla_css_path = NIZ_MFA_PLUGIN_DIR . 'assets/css/qibla.css';
    $qibla_js_path  = NIZ_MFA_PLUGIN_DIR . 'assets/js/qibla.js';

    // Force LiteSpeed to ignore critical plugin CSS
    add_filter( 'litespeed_optimize_css_excludes', function( $excludes ) {
        $excludes[] = 'prayer-times.css';
        $excludes[] = 'mosque.css';
        $excludes[] = 'business.css';
        $excludes[] = 'knowledge.css';
        $excludes[] = 'web.css';
        $excludes[] = 'qibla.css';
        $excludes[] = 'global.css';
        return $excludes;
    } );

    if (file_exists($qibla_css_path)) {
        wp_enqueue_style('niz-qibla-finder', NIZ_MFA_PLUGIN_URL . 'assets/css/qibla.css', array(), $get_version($qibla_css_path));
    }
    if (file_exists($qibla_js_path)) {
        wp_enqueue_script('niz-qibla-finder', NIZ_MFA_PLUGIN_URL . 'assets/js/qibla.js', array(), $get_version($qibla_js_path), true);
    }
}

// =============================================
// 6. PWA REWRITE RULES & ENDPOINTS
// =============================================
add_action('init', 'niz_mfa_add_rewrite_rules');
function niz_mfa_add_rewrite_rules() {
    add_rewrite_rule('^manifest\.json$', 'index.php?niz_mfa_manifest=1', 'top');
    add_rewrite_rule('^sw\.js$', 'index.php?niz_mfa_sw=1', 'top');
}

add_filter('query_vars', 'niz_mfa_register_query_vars');
function niz_mfa_register_query_vars($vars) {
    $vars[] = 'niz_mfa_manifest';
    $vars[] = 'niz_mfa_sw';
    return $vars;
}

add_action('template_redirect', 'niz_mfa_pwa_endpoints_handler');
function niz_mfa_pwa_endpoints_handler() {
    if (get_query_var('niz_mfa_manifest')) {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $manifest = [
            'name'             => 'Masjid4All',
            'short_name'       => 'Masjid4All',
            'start_url'        => '/',
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => '#0f766e',
            'icons'            => [
                [
                    'src'   => NIZ_MFA_PLUGIN_URL . 'assets/icon-192.png',
                    'sizes' => '192x192',
                    'type'  => 'image/png'
                ],
                [
                    'src'   => NIZ_MFA_PLUGIN_URL . 'assets/icon-512.png',
                    'sizes' => '512x512',
                    'type'  => 'image/png'
                ]
            ]
        ];
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    if (get_query_var('niz_mfa_sw')) {
        header('Content-Type: application/javascript; charset=utf-8');
        $sw_file = NIZ_MFA_PLUGIN_DIR . 'sw.php';
        if (file_exists($sw_file)) {
            include $sw_file;
        } else {
            status_header(404);
            echo '// Service worker file (sw.php) not found.';
        }
        exit;
    }
}

// =============================================
// 7. ACTIVATION / DEACTIVATION HOOKS
// =============================================
register_activation_hook(__FILE__, 'niz_mfa_activate');
function niz_mfa_activate() {
    niz_mfa_add_rewrite_rules();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'niz_mfa_deactivate');
function niz_mfa_deactivate() {
    flush_rewrite_rules();
    flush_rewrite_rules();
}

