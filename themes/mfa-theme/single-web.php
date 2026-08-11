<?php
/**
 * Single website listing. Renders via the shared [mfa_directory_single]
 * component (mfa-core). Replaces the Kadence "Web" element (post 220902).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	echo do_shortcode( '[mfa_directory_single]' );
endwhile;

get_footer();
