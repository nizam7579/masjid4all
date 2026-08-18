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

/**
 * "Add Your X" CTA that opens a Sofia-assist popup instead of linking to the
 * old /add-X page. The popup explains Sofia will help on WhatsApp and its CTA
 * deep-links to wa.me with the matching directory keyword (add mosque /
 * add business / add website), so the bot drops the user straight into that
 * branch — see niz-wa-integration.php's niz_wa_action_directory(). Styling/
 * behaviour: dir-assist-v1.css / dir-assist-v1.js (enqueued in
 * widgets-enqueue.php on the masjid/business/web pages).
 */
function mfa_dir_assist_cta( $type ) {
	$map = array(
		'mosque'   => array( 'emoji' => '🕌', 'label' => 'Add Your Mosque',   'title' => 'Add your mosque to Masjid4All',   'text' => 'add mosque',   'lead' => 'guide you through listing your mosque' ),
		'business' => array( 'emoji' => '🏪', 'label' => 'Add Your Business', 'title' => 'List your business on Masjid4All', 'text' => 'add business', 'lead' => 'help you add or claim your business' ),
		'website'  => array( 'emoji' => '🌐', 'label' => 'Add Your Website',  'title' => 'Add your website to Masjid4All',  'text' => 'add website',  'lead' => 'help you add or claim your website' ),
	);

	if ( ! isset( $map[ $type ] ) ) {
		return '';
	}

	$c        = $map[ $type ];
	$modal_id = 'mfa-assist-' . $type;
	$wa_link  = 'https://wa.me/60189897579?text=' . rawurlencode( $c['text'] );

	$wa_icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.43.06-.66.31-.23.24-.86.84-.86 2.06 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z"/></svg>';

	ob_start();
	?>
	<button type="button" class="mfa-tool-page-header-cta-btn mfa-assist-open" data-target="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>"><?php echo esc_html( $c['label'] ); ?></button>

	<div class="mfa-assist-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo esc_attr( $c['title'] ); ?>">
		<div class="mfa-assist-modal">
			<button type="button" class="mfa-assist-close" aria-label="Close">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
			<div class="mfa-assist-emoji"><?php echo $c['emoji']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<h3 class="mfa-assist-title"><?php echo esc_html( $c['title'] ); ?></h3>
			<p class="mfa-assist-text">Our AI assistant <strong>Sofia</strong> will <?php echo esc_html( $c['lead'] ); ?> on WhatsApp &mdash; it&rsquo;s quick and free.</p>
			<a class="mfa-assist-cta" href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Continue on WhatsApp</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

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
					<?php echo mfa_dir_assist_cta( 'mosque' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<?php echo do_shortcode( '[niz_mfa_nearest_mosque]' ); ?>
			</div>

			<div class="mfa-page-col-ad">
				<?php echo mfa_place_links_or_ads(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
		<header class="mfa-tool-page-header mfa-tool-page-header--split">
			<div class="mfa-tool-page-header-inner">
				<div class="mfa-tool-page-header-main">
					<h1>Business Directory</h1>
					<p class="mfa-tool-page-tagline">Discover trusted local services and explore.</p>
				</div>
				<div class="mfa-tool-page-header-cta">
					<h2>Your business is not listed?</h2>
					<?php echo mfa_dir_assist_cta( 'business' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
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
		<header class="mfa-tool-page-header mfa-tool-page-header--split">
			<div class="mfa-tool-page-header-inner">
				<div class="mfa-tool-page-header-main">
					<h1>Website Directory</h1>
					<p class="mfa-tool-page-tagline">Discover trusted online resources.</p>
				</div>
				<div class="mfa-tool-page-header-cta">
					<h2>Your website is not listed?</h2>
					<?php echo mfa_dir_assist_cta( 'website' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
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
