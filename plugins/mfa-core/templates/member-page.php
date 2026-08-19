<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full HTML document for /member/* - loaded via the template_include filter
 * in includes/member-template.php. Deliberately does not call get_header()/
 * get_footer(), since Kadence Theme Builder's fixed elements (Header, Footer
 * New, Promo Header, Sidebar, AI Chatbot) hook into those theme template
 * files - skipping them is what "bypass Kadence entirely" means here.
 * wp_head()/wp_footer() still run so RankMath/Site Kit/LiteSpeed keep working.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( is_user_logged_in() ? 'mfa-member-area' : 'mfa-member-area mfa-site' ); ?>>
<?php wp_body_open(); ?>

<?php
// Logged-in members get the minimal member-area chrome; logged-out visitors
// (the marketing / login panel) get the full public site header instead, so
// they still have the main nav and Tools menu to explore the site. The
// [mfa_site_header] is position:fixed and relies on the .mfa-site body class
// above for its top padding (see site-chrome-v1.css).
if ( is_user_logged_in() ) {
	echo do_shortcode( '[mfa_member_header]' );
} else {
	echo do_shortcode( '[mfa_site_header]' );
}
?>

<main class="mfa-member-main">
<?php
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
?>
</main>

<?php echo do_shortcode( '[mfa_member_footer]' ); ?>

<?php wp_footer(); ?>
</body>
</html>
