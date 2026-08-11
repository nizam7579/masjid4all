<?php
/**
 * Single blog post. Kept simple and readable — the directory CPTs (masjid,
 * business, web, knowledge) have their own single-{type}.php templates.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="mfa-content-wrap">
		<h1 class="mfa-entry-title"><?php the_title(); ?></h1>
		<div class="mfa-entry-meta"><?php echo esc_html( get_the_date() ); ?></div>
		<div class="mfa-entry-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
