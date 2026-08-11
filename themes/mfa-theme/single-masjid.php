<?php
/**
 * Single mosque listing. Renders via the shared [mfa_directory_single]
 * component (mfa-core), which is configured for the masjid post type.
 * Replaces the Kadence Theme Builder "Mosque" element (post 875).
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
