<?php
/**
 * Single Blog post (blog CPT). Replaces the Kadence "Blog" Theme Builder
 * element (post 65786): featured image + title + body on the left, a list of
 * recent blog posts on the right (the original used a Kadence post-grid).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="mfa-cpt-layout">
		<article class="mfa-cpt-main">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="mfa-cpt-featured"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>
			<h1 class="mfa-cpt-title"><?php the_title(); ?></h1>
			<div class="mfa-cpt-content"><?php the_content(); ?></div>
		</article>
		<aside class="mfa-cpt-sidebar">
			<h2 class="mfa-cpt-sidebar-title">Blogs</h2>
			<ul class="mfa-cpt-postlist">
				<?php
				$recent = new WP_Query( array(
					'post_type'           => 'blog',
					'posts_per_page'      => 10,
					'post__not_in'        => array( get_the_ID() ),
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				) );
				if ( $recent->have_posts() ) :
					while ( $recent->have_posts() ) :
						$recent->the_post();
						?>
						<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
						<?php
					endwhile;
					wp_reset_postdata();
				endif;
				?>
			</ul>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
