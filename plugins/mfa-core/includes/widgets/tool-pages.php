<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_prayer_times_page] and [mfa_qibla_page] - the /prayer-times/ and
 * /qibla-finder/ landing pages, plain HTML (no Kadence blocks), following
 * the same template [mfa_quran_page] established: hero header + tool in
 * a content column, ad column alongside. Both existing tool widgets
 * ([niz_mfa_prayer_times] / [niz_mfa_qibla]) are also embedded on the
 * homepage, so their own markup/CSS stays shortcode-owned; this file only
 * wraps them in the page chrome.
 */

add_shortcode( 'mfa_prayer_times_page', 'mfa_prayer_times_page_shortcode' );
function mfa_prayer_times_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header">
			<h1>Prayer Times</h1>
			<p class="mfa-tool-page-tagline">Accurate, location-based prayer schedules to help you stay consistent throughout the day.</p>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<div class="mfa-tool-page-card mfa-tool-page-card--prayer">
					<?php echo do_shortcode( '[niz_mfa_prayer_times]' ); ?>
				</div>

				<?php
				// Directly under the times, where someone who has just checked
				// them is most likely to be thinking about a journey. Rendered
				// from inside this shortcode rather than added to the page
				// content, so the page still resolves to exactly one shortcode.
				echo do_shortcode( '[mfa_travel_cta source="prayer-times"]' );
				?>

				<div class="mfa-faq-list">
					<details open>
						<summary>How are these prayer times calculated?</summary>
						<p>We use your device's location together with the Aladhan API, applying the calculation method most commonly used in your country (for example, JAKIM's method for Malaysia) to work out Fajr, Dhuhr, Asr, Maghrib and Isha.</p>
					</details>
					<details>
						<summary>Do I need to allow location access?</summary>
						<p>Yes — accurate prayer times depend on your coordinates. If location access isn't available, we fall back to Kuala Lumpur, Malaysia as a default.</p>
					</details>
					<details>
						<summary>How often do the times update?</summary>
						<p>The countdown to the next prayer refreshes automatically every 30 seconds, and a fresh set of times is fetched each day.</p>
					</details>
				</div>
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

add_shortcode( 'mfa_qibla_page', 'mfa_qibla_page_shortcode' );
function mfa_qibla_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-tool-page">
		<header class="mfa-tool-page-header">
			<h1>Qibla Direction</h1>
			<p class="mfa-tool-page-tagline">Instant and reliable Qibla finder — point your phone toward the Kaaba, wherever you are.</p>
		</header>

		<div class="mfa-page-row">
			<div class="mfa-page-col-content">
				<div class="mfa-tool-page-card mfa-tool-page-card--qibla">
					<?php echo do_shortcode( '[niz_mfa_qibla]' ); ?>
				</div>

				<div class="mfa-faq-list">
					<details open>
						<summary>How does the Qibla finder work?</summary>
						<p>It combines your device's compass sensor with your GPS coordinates to calculate the bearing to the Kaaba in Makkah, then rotates the arrow to point you in that direction.</p>
					</details>
					<details>
						<summary>My phone doesn't support a compass — what happens?</summary>
						<p>If sensor data isn't available, the finder switches to manual mode: it shows the exact Qibla bearing in degrees and lets you drag the circle to align it yourself.</p>
					</details>
					<details>
						<summary>Why do I need to grant location and motion permissions?</summary>
						<p>Location gives us your coordinates for the bearing calculation, and motion/orientation access lets the compass track which way your phone is facing. Both are needed for the automatic mode to work.</p>
					</details>
				</div>
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
