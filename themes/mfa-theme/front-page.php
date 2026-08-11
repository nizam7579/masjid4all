<?php
/**
 * Front page. The homepage is a single shortcode ([mfa_homepage]); output
 * its content directly with no title or wrapper.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	the_content();
endwhile;

get_footer();
