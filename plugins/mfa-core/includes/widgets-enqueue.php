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
	$post_name = $post ? $post->post_name : '';

	$get_version = function ( $path ) {
		return file_exists( $path ) ? filemtime( $path ) : MFA_CORE_VERSION;
	};

	// The post_content check alone misses pages that embed the widget via
	// a PHP-wrapped page shortcode (e.g. [mfa_prayer_times_page]) rather
	// than the raw [niz_mfa_prayer_times] text — has_shortcode() only sees
	// literal post_content, not shortcodes nested inside another
	// shortcode's PHP callback. Same class of bug fixed for /quran/ earlier.
	if ( has_shortcode( $content, 'niz_mfa_prayer_times' ) || 'prayer-times' === $post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/prayer-times-v2.css';
		$js  = MFA_CORE_PATH . 'assets/js/prayer-times.js';
		wp_enqueue_style( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/css/prayer-times-v2.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/js/prayer-times.js', array(), $get_version( $js ), true );
	}

	if ( has_shortcode( $content, 'niz_mfa_qibla' ) || 'qibla-finder' === $post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/qibla-v2.css';
		$js  = MFA_CORE_PATH . 'assets/js/qibla-v2.js';
		wp_enqueue_style( 'mfa-core-qibla', MFA_CORE_URL . 'assets/css/qibla-v2.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-qibla', MFA_CORE_URL . 'assets/js/qibla-v2.js', array(), $get_version( $js ), true );
	}

	$is_quran_page = $post && ( 'quran' === $post->post_name || 'quran' === $post->post_type );
	if ( has_shortcode( $content, 'daily_quran' ) || has_shortcode( $content, 'quran_surah_selector' ) || $is_quran_page ) {
		$css = MFA_CORE_PATH . 'assets/css/quran.css';
		$js  = MFA_CORE_PATH . 'assets/js/quran.js';
		wp_enqueue_style( 'mfa-core-quran', MFA_CORE_URL . 'assets/css/quran.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-quran', MFA_CORE_URL . 'assets/js/quran.js', array(), $get_version( $js ), true );
	}

	// Floating footer share button — sitewide, since the Kadence Theme
	// Builder footer isn't part of any specific page's post_content.
	$share_btn_css = MFA_CORE_PATH . 'assets/css/share-button-v12.css';
	$share_btn_js  = MFA_CORE_PATH . 'assets/js/share-button-v12.js';
	wp_enqueue_style( 'mfa-core-share-button', MFA_CORE_URL . 'assets/css/share-button-v12.css', array(), $get_version( $share_btn_css ) );
	wp_enqueue_script( 'mfa-core-share-button', MFA_CORE_URL . 'assets/js/share-button-v12.js', array(), $get_version( $share_btn_js ), true );

	// Reusable content/ad two-column layout utility — sitewide, not tied
	// to a specific page or shortcode. See page-layout-v2.css for usage.
	$page_layout_css = MFA_CORE_PATH . 'assets/css/page-layout-v2.css';
	wp_enqueue_style( 'mfa-core-page-layout', MFA_CORE_URL . 'assets/css/page-layout-v2.css', array(), $get_version( $page_layout_css ) );

	if ( $post && 'homepage' === $post->post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/homepage-v8.css';
		wp_enqueue_style( 'mfa-core-homepage', MFA_CORE_URL . 'assets/css/homepage-v8.css', array(), $get_version( $css ) );
	}

	if ( $is_quran_page ) {
		$css = MFA_CORE_PATH . 'assets/css/quran-page-v7.css';
		wp_enqueue_style( 'mfa-core-quran-page', MFA_CORE_URL . 'assets/css/quran-page-v7.css', array(), $get_version( $css ) );
	}

	$is_tool_page = $post && in_array( $post->post_name, array( 'prayer-times', 'qibla-finder' ), true );
	if ( $is_tool_page ) {
		$css = MFA_CORE_PATH . 'assets/css/tool-page-v2.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v2.css', array(), $get_version( $css ) );
	}

	$is_brand_page = $post && in_array( $post->post_name, array( 'about-us', 'contact-us' ), true );
	if ( $is_brand_page ) {
		$header_css = MFA_CORE_PATH . 'assets/css/tool-page-v2.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v2.css', array(), $get_version( $header_css ) );
		$css = MFA_CORE_PATH . 'assets/css/brand-page-v3.css';
		wp_enqueue_style( 'mfa-core-brand-page', MFA_CORE_URL . 'assets/css/brand-page-v3.css', array(), $get_version( $css ) );
	}

	$is_legal_page = $post && in_array( $post->post_name, array( 'privacy-policy', 'terms-of-service' ), true );
	if ( $is_legal_page ) {
		$header_css = MFA_CORE_PATH . 'assets/css/tool-page-v2.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v2.css', array(), $get_version( $header_css ) );
		$css = MFA_CORE_PATH . 'assets/css/legal-page-v2.css';
		wp_enqueue_style( 'mfa-core-legal-page', MFA_CORE_URL . 'assets/css/legal-page-v2.css', array(), $get_version( $css ) );
	}
}

add_filter( 'litespeed_optimize_css_excludes', 'mfa_core_litespeed_css_excludes' );
function mfa_core_litespeed_css_excludes( $excludes ) {
	$excludes[] = 'mfa-core/assets/css/prayer-times-v2.css';
	$excludes[] = 'mfa-core/assets/css/qibla-v2.css';
	$excludes[] = 'mfa-core/assets/css/homepage-v8.css';
	$excludes[] = 'mfa-core/assets/css/page-layout-v2.css';
	$excludes[] = 'mfa-core/assets/css/quran-page-v7.css';
	$excludes[] = 'mfa-core/assets/css/tool-page-v2.css';
	$excludes[] = 'mfa-core/assets/css/brand-page-v3.css';
	$excludes[] = 'mfa-core/assets/css/legal-page-v2.css';
	$excludes[] = 'mfa-core/assets/css/share-button-v12.css';
	return $excludes;
}
