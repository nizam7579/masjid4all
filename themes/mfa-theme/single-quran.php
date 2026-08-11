<?php
/**
 * Single Quran post (quran CPT, e.g. /quran/al-ikhlas/). Replaces the Kadence
 * "Quran" Theme Builder element (post 225214), which was just [mfa_quran_single].
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	echo do_shortcode( '[mfa_quran_single]' );
endwhile;

get_footer();
