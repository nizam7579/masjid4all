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
<body <?php body_class( 'mfa-member-area' ); ?>>
<?php wp_body_open(); ?>

<?php echo do_shortcode( '[mfa_member_header]' ); ?>

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
