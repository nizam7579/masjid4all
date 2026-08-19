<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_quran_single] - the Kadence Theme Builder "Single Post" template
 * content for the `quran` CPT (post 225214). Reuses the /quran/ page's
 * header + row/col layout and quran-page CSS, but the header title
 * resolves to the currently viewed surah post via get_the_title() (no
 * ID arg - Theme Builder swaps the global $post to the real singular
 * post during render), and the article body is replaced by [daily_quran],
 * which auto-detects the viewed post's `surah` meta.
 */
add_shortcode( 'mfa_quran_single', 'mfa_quran_single_shortcode' );
function mfa_quran_single_shortcode() {
	ob_start();
	?>
	<div class="mfa-quran-page">
		<header class="mfa-quran-header">
			<h2><?php echo esc_html( get_the_title() ); ?></h2>
			<p class="mfa-quran-tagline">Build a daily habit with the Quran — just one short Surah, five minutes a day. Here's how:</p>
			<p class="mfa-quran-steps"><strong>Recite, Listen and Understand.</strong></p>
		</header>

		<div class="mfa-row">
			<div class="mfa-row-main">
				<?php echo do_shortcode( '[quran_surah_selector]' ); ?>
				<?php echo do_shortcode( '[daily_quran]' ); ?>

				<article class="mfa-quran-article mfa-quran-article-post">
					<?php echo apply_filters( 'the_content', get_the_content() ); ?>
				</article>
			</div>

			<div class="mfa-row-side">
				<h3 class="mfa-quran-ad-heading">Featured Business</h3>
				<?php echo do_shortcode( '[enaizi_ads count="4" layout="vertical"]' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
