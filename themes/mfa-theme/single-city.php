<?php
/**
 * Single city page (city CPT, e.g. /city/seattle/). Renders via the shared
 * [mfa_directory_single] component (configured for the city post type).
 * Replaces the Kadence "City" Theme Builder element (post 225556).
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
