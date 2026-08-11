<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mfa-site' ); ?>>
<?php wp_body_open(); ?>

<?php echo do_shortcode( '[mfa_site_header]' ); ?>

<main class="mfa-site-main">
