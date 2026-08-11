<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Masjid4All theme — lightweight public-site theme replacing Kadence.
 *
 * Member and admin areas render through mfa-core's own document templates
 * (templates/member-page.php, templates/admin-page.php) via a template_include
 * filter in mfa-core, so this theme's templates apply to public pages only.
 * The page chrome is provided by mfa-core shortcodes: [mfa_site_header] in
 * header.php and [mfa_site_footer] in footer.php.
 */

add_action( 'after_setup_theme', 'mfa_theme_setup' );
function mfa_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
}

add_action( 'wp_enqueue_scripts', 'mfa_theme_assets', 20 );
function mfa_theme_assets() {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	$ver = static function ( $rel ) use ( $dir ) {
		$path = $dir . $rel;
		return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
	};

	// Base stylesheet — depends on mfa-core's global tokens when available.
	$deps = wp_style_is( 'mfa-core-global', 'registered' ) ? array( 'mfa-core-global' ) : array();
	wp_enqueue_style( 'mfa-theme', get_stylesheet_uri(), $deps, $ver( '/style.css' ) );

	// Mobile bottom-nav rendered by [mfa_site_footer].
	wp_enqueue_style( 'mfa-theme-footer-nav', $uri . '/assets/css/footer-nav.css', array( 'mfa-theme' ), $ver( '/assets/css/footer-nav.css' ) );
	wp_enqueue_script( 'mfa-theme-footer-nav', $uri . '/assets/js/footer-nav.js', array(), $ver( '/assets/js/footer-nav.js' ), true );
}
