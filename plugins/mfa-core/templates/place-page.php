<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full HTML document for /places/... hubs - loaded via the template_include
 * filter in includes/widgets/place-hub.php. Same reasoning as
 * templates/member-page.php: no get_header()/get_footer(), so no theme
 * builder chrome, but wp_head()/wp_footer() still fire so RankMath, Site Kit
 * and LiteSpeed keep working.
 *
 * The body is one shortcode, per the project's standing rule. The place post's
 * own editor content is rendered inside that shortcode as the intro, not here.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mfa-site mfa-place-area' ); ?>>
<?php wp_body_open(); ?>

<?php echo do_shortcode( '[mfa_site_header]' ); ?>

<main class="mfa-place-main">
<?php echo do_shortcode( '[mfa_place_hub]' ); ?>
</main>

<?php echo do_shortcode( '[mfa_site_footer]' ); ?>

<?php wp_footer(); ?>
</body>
</html>
