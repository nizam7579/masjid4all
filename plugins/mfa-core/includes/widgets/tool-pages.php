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
				// The two follow-ons a person who has just checked the times
				// actually wants, nearest need first: which way to face, then
				// what happens to these times on a journey. Both render from
				// inside this shortcode rather than being added to the page
				// content, so the page still resolves to exactly one shortcode.
				?>
				<?php echo do_shortcode( '[mfa_tool_cta tool="qibla"]' ); ?>

				<?php echo do_shortcode( '[mfa_travel_cta source="prayer-times"]' ); ?>

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
						<summary>Can I shorten or combine my prayers while travelling?</summary>
						<p>On a journey beyond roughly 90 km — two marhalah — you may pray qasar, shortening Zuhr, Asr and Isha to two raka'at, and you may combine Zuhr with Asr and Maghrib with Isha, either at the earlier prayer's time (jamak taqdim) or the later one's (jamak ta'khir). Fajr is never combined. Schools of fiqh differ on the exact distance, so for a journey close to that threshold please confirm with your local religious authority.</p>
					</details>
					<details>
						<summary>What if a prayer time passes while I'm on the plane?</summary>
						<p>Where you can, pray before you board or once you land — combining the pair is usually the simplest way to manage it. Sometimes neither works: on an overnight flight, Fajr's whole time can fall between take-off and landing, and Fajr cannot be combined with anything. Then pray on board — seated if you cannot stand safely, facing the qiblah as you begin, and using tayammum if no water is available.</p>
					</details>
					<details>
						<summary>How do prayer times work across a time zone change?</summary>
						<p>Prayer times follow the place you are in, not the clock you set out with, so a long flight can shift them by hours and even land you on a different calendar day. Tell Sofia your journey on WhatsApp and she will work out each leg for you — including a long stopover, where prayers falling during the wait can be performed properly at the airport rather than in the air.</p>
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

				<?php echo do_shortcode( '[mfa_tool_cta tool="prayer-times"]' ); ?>

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

/**
 * [mfa_tool_cta tool="prayer-times|qibla"] - a cross-link between the tools.
 *
 * One definition rather than the same markup written out on each page: these
 * appear on /prayer-times/, /qibla-finder/ and every mosque listing, and copy
 * that has to be edited in three places drifts apart in two of them.
 *
 * Renders nothing when it would point at the page you are already reading.
 */
add_shortcode( 'mfa_tool_cta', 'mfa_tool_cta_shortcode' );
function mfa_tool_cta_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'tool' => 'prayer-times' ), $atts, 'mfa_tool_cta' );

	$tools = array(
		'prayer-times' => array(
			'slug'   => 'prayer-times',
			'title'  => 'Prayer times where you are',
			'text'   => 'Fajr to Isha for your own location, with a live countdown to the next prayer.',
			'button' => 'Open Prayer Times',
		),
		'qibla'        => array(
			'slug'   => 'qibla-finder',
			'title'  => 'Which way is the qiblah?',
			'text'   => 'Point your phone and face the Kaaba from wherever you are — no account, no setup.',
			'button' => 'Open the Qibla Finder',
		),
	);

	$key = isset( $tools[ $atts['tool'] ] ) ? $atts['tool'] : 'prayer-times';
	$cta = $tools[ $key ];

	// A link back to the page you are on is noise.
	$post = get_post();

	if ( $post && $cta['slug'] === $post->post_name ) {
		return '';
	}

	ob_start();
	?>
	<div class="mfa-tool-cta">
		<h2 class="mfa-tool-cta-title"><?php echo esc_html( $cta['title'] ); ?></h2>
		<p class="mfa-tool-cta-text"><?php echo esc_html( $cta['text'] ); ?></p>
		<a class="mfa-btn mfa-btn-primary mfa-tool-cta-btn" href="<?php echo esc_url( home_url( '/' . $cta['slug'] . '/' ) ); ?>">
			<?php echo esc_html( $cta['button'] ); ?>
		</a>
	</div>
	<?php
	return ob_get_clean();
}
