<?php
/**
 * Archive listing (blog categories, tags, dates). The directory CPTs use
 * their own directory shortcode pages, not this template.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<h1 class="mfa-archive-title"><?php the_archive_title(); ?></h1>
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
		the_posts_pagination();
	else :
		?>
		<p>Nothing found.</p>
		<?php
	endif;
	?>
</div>
<?php
get_footer();
