<?php
/**
 * Single post. Two cases:
 *  - Digital name card posts (category "Affiliate", id 39): content is
 *    [niz_user_namecard]; render it with the QR code and the "get your own"
 *    CTA, matching the old Kadence "Namecard" element (post 218194).
 *  - Regular blog-style posts: simple title + date + content.
 * The directory CPTs (masjid, business, web, knowledge, quran, blog) have
 * their own single-{type}.php templates.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	if ( has_category( 39 ) ) :
		?>
		<div class="mfa-namecard-single">
			<div class="mfa-namecard-card">
				<?php the_content(); ?>
				<?php echo do_shortcode( '[current_page_qr]' ); ?>
			</div>
			<div class="mfa-namecard-promo">
				<h3>Digital Namecard by Masjid4All</h3>
				<a href="/member" class="mfa-namecard-promo-link">Get Your FREE Namecard NOW</a>
			</div>
		</div>
		<?php
	else :
		?>
		<article class="mfa-content-wrap">
			<h1 class="mfa-entry-title"><?php the_title(); ?></h1>
			<div class="mfa-entry-meta"><?php echo esc_html( get_the_date() ); ?></div>
			<div class="mfa-entry-content"><?php the_content(); ?></div>
		</article>
		<?php
	endif;

endwhile;

get_footer();
