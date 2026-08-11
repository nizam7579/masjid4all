<?php
/**
 * Single Knowledge post (knowledge CPT). Replaces the Kadence "Knowledge"
 * Theme Builder element (post 193845): a left-golden two-column layout —
 * featured image + title + article body on the left, the knowledge directory
 * on the right. The admin-only "Upload Image" modal ([cpt_image_manager]) and
 * the hidden [niz_mfa_business_info] block from the original are omitted.
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
			<?php echo do_shortcode( '[niz_mfa_knowledge_directory columns="1"]' ); ?>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
