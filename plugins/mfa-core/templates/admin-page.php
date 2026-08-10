<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full HTML document for /admin/* - loaded via the template_include filter
 * in includes/admin-template.php. Same "bypass Kadence entirely" approach
 * as templates/member-page.php (no get_header()/get_footer(), but
 * wp_head()/wp_footer() still run so SEO/analytics/cache plugins keep
 * working). Header is a horizontal top nav across the 7 sub-sections
 * (2026-08-10 - replaced the old left sidebar, which no longer exists);
 * some pages hide it entirely, see mfa_admin_page_hides_chrome().
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

<?php
$mfa_admin_hide_chrome = function_exists( 'mfa_admin_page_hides_chrome' ) && mfa_admin_page_hides_chrome();

if ( ! $mfa_admin_hide_chrome ) {
	echo do_shortcode( '[mfa_admin_header]' );
}
?>

<div class="mfa-admin-shell">
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
