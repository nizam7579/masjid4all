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
				<?php
				$mfa_nc_author = (int) get_post_field( 'post_author', get_the_ID() );
				$mfa_nc_photo  = $mfa_nc_author ? get_user_meta( $mfa_nc_author, 'niz_namecard_photo', true ) : '';
				$mfa_nc_banner = get_the_post_thumbnail_url( get_the_ID(), 'large' );
				?>
				<?php if ( $mfa_nc_banner ) : ?>
					<div class="mfa-namecard-banner"><img src="<?php echo esc_url( $mfa_nc_banner ); ?>" alt="" loading="lazy"></div>
				<?php endif; ?>
				<?php if ( $mfa_nc_photo ) : ?>
					<div class="mfa-namecard-avatar<?php echo $mfa_nc_banner ? ' mfa-namecard-avatar--over' : ''; ?>"><img src="<?php echo esc_url( $mfa_nc_photo ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"></div>
				<?php endif; ?>
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
