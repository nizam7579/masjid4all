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

	// Lato — the site's default font, previously provided by the Kadence
	// theme. Google Fonts OFL (weights used across the pages: 400/700/900).
	wp_enqueue_style( 'mfa-theme-fonts', 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap', array(), null );

	// Base stylesheet — depends on mfa-core's global tokens and the font.
	$deps = array( 'mfa-theme-fonts' );
	if ( wp_style_is( 'mfa-core-global', 'registered' ) ) {
		$deps[] = 'mfa-core-global';
	}
	wp_enqueue_style( 'mfa-theme', get_stylesheet_uri(), $deps, $ver( '/style.css' ) );

	// Mobile bottom-nav rendered by [mfa_site_footer].
	wp_enqueue_style( 'mfa-theme-footer-nav', $uri . '/assets/css/footer-nav.css', array( 'mfa-theme' ), $ver( '/assets/css/footer-nav.css' ) );
	wp_enqueue_script( 'mfa-theme-footer-nav', $uri . '/assets/js/footer-nav.js', array(), $ver( '/assets/js/footer-nav.js' ), true );
}

/**
 * Tag digital name-card pages (category "Affiliate", id 39) with a body class
 * so style.css can present them chrome-reduced: header hidden on all viewports,
 * mobile bottom-nav hidden on small screens. Matches single.php's own
 * has_category( 39 ) branch.
 */
add_filter( 'body_class', 'mfa_theme_namecard_body_class' );
function mfa_theme_namecard_body_class( $classes ) {
	if ( is_singular( 'post' ) && has_category( 39, get_queried_object_id() ) ) {
		$classes[] = 'mfa-namecard-page';
	}
	return $classes;
}

add_filter( 'wp_resource_hints', 'mfa_theme_resource_hints', 10, 2 );
function mfa_theme_resource_hints( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}
