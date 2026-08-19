<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_quran_page] - the entire /quran/ landing page content as one
 * shortcode, deliberately not built from Kadence blocks (see the CSS
 * file's own note on why: Kadence's per-block generated CSS repeatedly
 * fought hand-written overrides on this page's previous version).
 * Surah selector and ads are delegated to their own existing shortcodes.
 */
add_shortcode( 'mfa_quran_page', 'mfa_quran_page_shortcode' );
function mfa_quran_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-quran-page">
		<header class="mfa-quran-header">
			<h1>Daily Quran</h1>
			<p class="mfa-quran-tagline">Build a daily habit with the Quran — just one short Surah, five minutes a day. Here's how:</p>
			<p class="mfa-quran-steps"><strong>Recite, Listen and Understand.</strong></p>
		</header>

		<div class="mfa-row">
			<div class="mfa-row-main">
				<?php echo do_shortcode( '[quran_surah_selector]' ); ?>

				<article class="mfa-quran-article">
					<h2>Connect Beyond Recitation</h2>
					<p>Many non-Arabic speaking Muslims can read and recite the Quran beautifully, yet struggle to grasp its profound meaning. We bridge that gap.</p>
					<p>Our <strong>Daily Quran</strong> feature focuses on <strong>33 carefully selected short Surahs</strong>—the ones most frequently recited in daily prayers. For each Surah, you can:</p>
					<ul>
						<li><strong>Listen</strong> to clear, beautiful recitation.</li>
						<li><strong>Read</strong> the Arabic text alongside transliteration and translation.</li>
						<li><strong>Understand</strong> the meaning through simple, accessible explanations.</li>
					</ul>
					<p>We also provide <strong>key insights</strong> about each Surah—its background, themes, and lessons—so you connect with the Quran not just in words, but in heart and mind.</p>

					<hr>

					<h2>A Habit That Transforms</h2>
					<p>Just five minutes a day. One Surah at a time. Over days and weeks, you'll find yourself:</p>
					<ul>
						<li>Reciting with greater presence and focus.</li>
						<li>Understanding what Allah is saying to you.</li>
						<li>Feeling a deeper connection during your prayers.</li>
					</ul>
					<p>It's not about how much you read—it's about how deeply you connect.</p>

					<hr>

					<h2>Share the Blessing</h2>
					<p>The Quran is a gift meant to be shared. Start your daily journey, then invite your family and friends to join you.</p>
					<p>When you share, you're not just spreading words—you're spreading understanding, peace, and connection to Allah. Together, we can help more people build a daily bond with the Quran, one short Surah at a time.</p>

					<hr>

					<h2>Start Today</h2>
					<p>Pick your first Surah. Take five minutes. Begin a journey that will transform your days and your heart.</p>
					<p><strong>Because the Quran is not just to be read—it's to be lived.</strong></p>
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
