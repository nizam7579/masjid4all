<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_masjid_page], [mfa_business_page], [mfa_web_page], [mfa_knowledge_page]
 * - the /masjid/, /business/, /web/, /knowledge-hub/ directory pages,
 * plain HTML (no Kadence blocks), following the same [mfa_prayer_times_page]
 * template: hero header + directory shortcode in a content column, ad
 * column alongside. Each directory shortcode (niz_mfa_nearest_mosque,
 * niz_mfa_nearest_business, niz_mfa_web_directory, niz_mfa_knowledge_directory,
 * all in enaizi-mfa) is already a fully self-contained AJAX search/filter/
 * grid widget - this file only wraps them in the page chrome. Unlike the
 * small prayer-times/qibla tool card, these are full-width directory
 * grids, so the shortcode sits directly in the content column instead of
 * a narrow .mfa-tool-page-card.
 *
 * Each of these pages previously carried dead, already-hidden Kadence
 * content left over from earlier iterations (a disabled legacy "Share"
 * reusable block on masjid/business/knowledge-hub; a whole disabled
 * JetEngine listing-grid + JetSmartFilters section on web, and a second
 * hidden copy of the knowledge directory + hidden "Featured Articles"
 * section on knowledge-hub) - all `blockVisibility.hideBlock:true`, so
 * none of it was ever rendering. None of it is carried over here.
 */

add_shortcode( 'mfa_masjid_page', 'mfa_masjid_page_shortcode' );
function mfa_masjid_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header mfa-tool-page-header--split">
			<div class="mfa-tool-page-header-inner">
				<div class="mfa-tool-page-header-main">
					<h1>Mosque Directory</h1>
					<p class="mfa-tool-page-tagline">Find your nearest mosque and explore.</p>
				</div>
				<div class="mfa-tool-page-header-cta">
					<h2>Your mosque is not listed?</h2>
					<p>Add your mosque now</p>
					<a href="/add-mosque" class="mfa-tool-page-header-cta-btn">Add Mosque</a>
				</div>
			</div>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<?php echo do_shortcode( '[niz_mfa_nearest_mosque]' ); ?>
			</div>

			<div class="mfa-page-col-ad">
				<h3 class="mfa-tool-page-ad-heading">Recommended Products/Services</h3>
				<?php echo do_shortcode( '[enaizi_ads count="4" layout="vertical"]' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_business_page', 'mfa_business_page_shortcode' );
function mfa_business_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header">
			<h1>Business Directory</h1>
			<p class="mfa-tool-page-tagline">Discover trusted local services and explore.</p>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<?php echo do_shortcode( '[niz_mfa_nearest_business]' ); ?>
			</div>

			<div class="mfa-page-col-ad">
				<h3 class="mfa-tool-page-ad-heading">Recommended Products/Services</h3>
				<?php echo do_shortcode( '[enaizi_ads count="4" layout="vertical"]' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_web_page', 'mfa_web_page_shortcode' );
function mfa_web_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header">
			<h1>Website Directory</h1>
			<p class="mfa-tool-page-tagline">Discover trusted online resources.</p>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<?php echo do_shortcode( '[niz_mfa_web_directory]' ); ?>
			</div>

			<div class="mfa-page-col-ad">
				<h3 class="mfa-tool-page-ad-heading">Recommended Products/Services</h3>
				<?php echo do_shortcode( '[enaizi_ads count="4" layout="vertical"]' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_knowledge_page', 'mfa_knowledge_page_shortcode' );
function mfa_knowledge_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header">
			<h1>Knowledge Hub</h1>
			<p class="mfa-tool-page-tagline">Islamic knowledge and resources.</p>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<?php echo do_shortcode( '[niz_mfa_knowledge_directory]' ); ?>
			</div>

			<div class="mfa-page-col-ad">
				<h3 class="mfa-tool-page-ad-heading">Recommended Products/Services</h3>
				<?php echo do_shortcode( '[enaizi_ads count="4" layout="vertical"]' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
