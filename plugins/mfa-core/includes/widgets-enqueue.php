<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional asset loading for the widget shortcodes moved from
 * enaizi-mfa/enaizi-user. Each CSS/JS pair only loads on pages that use
 * the matching shortcode, instead of the old sitewide-unconditional
 * enqueue. LiteSpeed exclusion is preserved for prayer-times/qibla since
 * their client-side hydration timing depends on not being combined/deferred.
 */
add_action( 'wp_enqueue_scripts', 'mfa_core_enqueue_widget_assets' );
function mfa_core_enqueue_widget_assets() {
	if ( is_admin() ) {
		return;
	}

	$post = get_post();
	$content = $post ? $post->post_content : '';

	$get_version = function ( $path ) {
		return file_exists( $path ) ? filemtime( $path ) : MFA_CORE_VERSION;
	};

	if ( has_shortcode( $content, 'niz_mfa_prayer_times' ) ) {
		$css = MFA_CORE_PATH . 'assets/css/prayer-times.css';
		$js  = MFA_CORE_PATH . 'assets/js/prayer-times.js';
		wp_enqueue_style( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/css/prayer-times.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/js/prayer-times.js', array(), $get_version( $js ), true );
	}

	if ( has_shortcode( $content, 'niz_mfa_qibla' ) ) {
		$css = MFA_CORE_PATH . 'assets/css/qibla.css';
		$js  = MFA_CORE_PATH . 'assets/js/qibla.js';
		wp_enqueue_style( 'mfa-core-qibla', MFA_CORE_URL . 'assets/css/qibla.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-qibla', MFA_CORE_URL . 'assets/js/qibla.js', array(), $get_version( $js ), true );
	}

	$is_quran_page = $post && ( 'quran' === $post->post_name || 'quran' === $post->post_type );
	if ( has_shortcode( $content, 'daily_quran' ) || has_shortcode( $content, 'quran_surah_selector' ) || $is_quran_page ) {
		$css = MFA_CORE_PATH . 'assets/css/quran.css';
		$js  = MFA_CORE_PATH . 'assets/js/quran.js';
		wp_enqueue_style( 'mfa-core-quran', MFA_CORE_URL . 'assets/css/quran.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-quran', MFA_CORE_URL . 'assets/js/quran.js', array(), $get_version( $js ), true );
	}

	if ( has_shortcode( $content, 'mfa_member_share' ) ) {
		$css = MFA_CORE_PATH . 'assets/css/member-share.css';
		$js  = MFA_CORE_PATH . 'assets/js/member-share.js';
		wp_enqueue_style( 'mfa-core-member-share', MFA_CORE_URL . 'assets/css/member-share.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-member-share', MFA_CORE_URL . 'assets/js/member-share.js', array(), $get_version( $js ), true );
	}

	// Reusable content/ad two-column layout utility — sitewide, not tied
	// to a specific page or shortcode. See page-layout-v2.css for usage.
	$page_layout_css = MFA_CORE_PATH . 'assets/css/page-layout-v2.css';
	wp_enqueue_style( 'mfa-core-page-layout', MFA_CORE_URL . 'assets/css/page-layout-v2.css', array(), $get_version( $page_layout_css ) );

	if ( $post && 'homepage' === $post->post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/homepage-v8.css';
		wp_enqueue_style( 'mfa-core-homepage', MFA_CORE_URL . 'assets/css/homepage-v8.css', array(), $get_version( $css ) );
	}

	if ( $is_quran_page ) {
		$css = MFA_CORE_PATH . 'assets/css/quran-page-v6.css';
		wp_enqueue_style( 'mfa-core-quran-page', MFA_CORE_URL . 'assets/css/quran-page-v6.css', array(), $get_version( $css ) );
	}
}

add_filter( 'litespeed_optimize_css_excludes', 'mfa_core_litespeed_css_excludes' );
function mfa_core_litespeed_css_excludes( $excludes ) {
	$excludes[] = 'mfa-core/assets/css/prayer-times.css';
	$excludes[] = 'mfa-core/assets/css/qibla.css';
	$excludes[] = 'mfa-core/assets/css/homepage-v8.css';
	$excludes[] = 'mfa-core/assets/css/page-layout-v2.css';
	$excludes[] = 'mfa-core/assets/css/quran-page-v6.css';
	return $excludes;
}