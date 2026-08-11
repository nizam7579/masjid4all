<?php
/**
 * Page template. Every public page is a single shortcode that owns its full
 * markup, so output the content directly — no title, no wrapper.
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
