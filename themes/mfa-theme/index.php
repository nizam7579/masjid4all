<?php
/**
 * Generic fallback template. Renders singular content directly (single
 * shortcode pages) or a simple post list for archives.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( is_singular() ) {
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
} else {
	?>
	<div class="mfa-archive-list">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				?>
				<div class="mfa-archive-item">
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
				</div>
				<?php
			endwhile;
		else :
			?>
			<p>Nothing found.</p>
			<?php
		endif;
		?>
	</div>
	<?php
}

get_footer();
