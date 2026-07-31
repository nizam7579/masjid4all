<?php
/**
 * Plugin Name: Enaizi User
 * Description: User login, registration, forgot password, and profile management.
 * Version: 1.1.0
 * Author: Nizam Mustapha
 * Text Domain: enaizi-user
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Global Definitions
define( 'NIZ_USER_VERSION', '1.1.0' );
define( 'NIZ_USER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NIZ_USER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// =============================================
// LOAD SHORTCODES
// =============================================
$user_shortcode_files = [
    'user.php',
    'member.php',
    'barakah.php',
    'credit.php',
    'namecard.php',
    'affiliate.php'
];

foreach ( $user_shortcode_files as $file ) {
    $file_path = NIZ_USER_PLUGIN_DIR . 'shortcodes/' . $file;
    if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
        require_once $file_path;
    }
}

// =============================================
// LOAD INCLUDES & HELPERS
// =============================================
$user_core_files = [
    'user.php',
    'ajax-handlers.php',
    'fluentform.php',
    'member.php',
    'admin.php',
];

foreach ( $user_core_files as $file ) {
    // Aligned to look inside your unified /includes/ directory
    $file_path = NIZ_USER_PLUGIN_DIR . 'includes/' . $file;
    if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
        require_once $file_path;
    }
}

// =============================================
// ASSET MANAGEMENT & ENQUEUES
// =============================================
add_action( 'wp_enqueue_scripts', 'niz_user_enqueue_assets' );

function niz_user_enqueue_assets() {
    $style_path  = NIZ_USER_PLUGIN_DIR . 'assets/css/style.css';
    $card_path   = NIZ_USER_PLUGIN_DIR . 'assets/css/card.css';
    $script_path = NIZ_USER_PLUGIN_DIR . 'assets/js/niz-user.js';

    // Dynamic fallback versioning to prevent site crashes if files are missing
    $style_ver  = file_exists( $style_path )  ? filemtime( $style_path )  : NIZ_USER_VERSION;
    $card_ver   = file_exists( $card_path )   ? filemtime( $card_path )   : NIZ_USER_VERSION;
    $script_ver = file_exists( $script_path ) ? filemtime( $script_path ) : NIZ_USER_VERSION;

    // Enqueue Stylesheets
    wp_enqueue_style( 'niz-user-style', NIZ_USER_PLUGIN_URL . 'assets/css/style.css', [], $style_ver );
    wp_enqueue_style( 'niz-user-card', NIZ_USER_PLUGIN_URL . 'assets/css/card.css', [], $card_ver );
    wp_enqueue_style( 'font-awesome-local', NIZ_USER_PLUGIN_URL . 'assets/css/all.min.css', [], '6.5.1' );

    // Enqueue Javascript Scripts
    wp_enqueue_script( 'niz-user-script', NIZ_USER_PLUGIN_URL . 'assets/js/niz-user.js', [], $script_ver, true );

    // Localize dynamic Ajax script variables
    wp_localize_script( 'niz-user-script', 'niz_user_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'niz_user_ajax_nonce' ) // Added security token for your AJAX calls
    ] );
}

// =============================================
// THIRD-PARTY OPTIMIZATIONS
// =============================================
/**
 * Prevent RankMath from running its heavy analytics engines on custom card layout pages
 */
add_action( 'wp_enqueue_scripts', 'niz_dequeue_rankmath_analytics', 99 );

function niz_dequeue_rankmath_analytics() {
    if ( is_singular( 'card' ) || isset( $_GET['user_id'] ) ) {
        wp_dequeue_script( 'rank-math-analyzer' );
        wp_dequeue_script( 'rank-math-app' );
    }
}
