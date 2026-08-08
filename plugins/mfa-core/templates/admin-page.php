<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full HTML document for /admin/* - loaded via the template_include filter
 * in includes/admin-template.php. Same "bypass Kadence entirely" approach
 * as templates/member-page.php (no get_header()/get_footer(), but
 * wp_head()/wp_footer() still run so SEO/analytics/cache plugins keep
 * working) plus a persistent sidebar nav across the 6 sub-sections, which
 * /member/'s chrome deliberately doesn't have.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mfa-admin-area' ); ?>>
<?php wp_body_open(); ?>

<?php echo do_shortcode( '[mfa_admin_header]' ); ?>

<div class="mfa-admin-shell">
<?php echo do_shortcode( '[mfa_admin_sidebar]' ); ?>

<main class="mfa-admin-main">
<?php
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
?>
</main>
</div>

<?php echo do_shortcode( '[mfa_admin_footer]' ); ?>

<?php wp_footer(); ?>
</body>
</html>
