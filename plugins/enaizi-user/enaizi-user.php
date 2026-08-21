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
    // 'user.php' removed — moved to mfa-core/includes/identity-core.php.
    // niz_user_check/register/create_prospect/etc. now live there.
    // 'ajax-handlers.php' removed 2026-08-21 — its four unauthenticated
    // endpoints were deleted as a security fix, and the one live handler
    // (logout) is gone too: [niz_user_logout] in mfa-core is now a plain
    // wp_logout_url() link, so it needs no AJAX and no JavaScript.
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
    // 2026-08-21: style.css and niz-user.js are gone.
    //
    // niz-user.js drove the phone login/register form, whose AJAX endpoints
    // were removed as a security fix, and the logout button - now a plain
    // wp_logout_url() link in mfa-core needing no JS. style.css styled only
    // that form plus a generic .niz-user-logout-btn rule that the three
    // shells (site-chrome-v1.css, member-shell-v1.css, admin-shell-v3.css)
    // already override with scoped rules of their own.
    //
    // card.css and FontAwesome DO stay, and are still sitewide: the namecard
    // ([niz_user_namecard], rendered by the theme's single.php for category
    // 39 posts) owns every .niz-namecard-*/.niz-btn* rule in card.css and
    // uses FontAwesome brand icons for its contact buttons. FontAwesome is
    // additionally used by the "Finding businesses near ..." spinners in
    // enaizi-mfa's directory shortcodes, which are live on every mosque and
    // business page - so it cannot be scoped to namecard pages until those
    // spinners are replaced. See the note in CLAUDE.md before attempting it.
    $card_path = NIZ_USER_PLUGIN_DIR . 'assets/css/card.css';
    $card_ver  = file_exists( $card_path ) ? filemtime( $card_path ) : NIZ_USER_VERSION;

    wp_enqueue_style( 'niz-user-card', NIZ_USER_PLUGIN_URL . 'assets/css/card.css', [], $card_ver );
    wp_enqueue_style( 'font-awesome-local', NIZ_USER_PLUGIN_URL . 'assets/css/all.min.css', [], '6.5.1' );
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
