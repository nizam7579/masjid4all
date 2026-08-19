<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_about_page] and [mfa_contact_page] - the /about-us/ and
 * /contact-us/ pages, plain HTML (no Kadence blocks), following the same
 * template as [mfa_quran_page]/[mfa_prayer_times_page]. Copy, images,
 * registration number and address are carried over verbatim from the old
 * Kadence content — only the markup/layout changed. The legacy "Footer"
 * reusable block (wp_block 66762) both pages used to end with is dropped:
 * it's dead pre-Kadence-Theme-Builder cruft (its [cookies_set_cookies]
 * shortcode doesn't even match a registered shortcode name — the real,
 * working geolocation prompt lives in the header menu blocks instead),
 * and the real sitewide footer already renders on every page.
 */

add_shortcode( 'mfa_about_page', 'mfa_about_page_shortcode' );
function mfa_about_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-brand-page">
		<header class="mfa-hero mfa-hero--brand mfa-hero--bleed">
			<div class="mfa-hero-inner">
				<h1 class="mfa-hero-title">About Us</h1>
				<p class="mfa-hero-tagline">The company and mission behind Masjid4All.</p>
			</div>
		</header>

		<div class="mfa-brand-page-body">
			<div class="mfa-about-intro">
				<img class="mfa-about-logo" src="https://staging.masjid4all.com/wp-content/uploads/2025/01/Pewarisan.my-Logo-1.png" alt="Pewarisan Sdn Bhd logo" loading="lazy">

				<div class="mfa-about-body">
					<p><strong>Pewarisan Sdn Bhd</strong> is a Malaysian-based Islamic Tech company specializing in community and inheritance solutions. Established in 2020, Pewarisan has achieved significant milestones, including receiving the prestigious Malaysian Digital (MD) Status, securing investments from three venture capital firms, and winning multiple hackathons and accelerator programs.</p>
					<p>Our latest solution, <strong>Masjid4ALL.com</strong>, aims to empower the Muslim community through a Halal Business directory and AI-powered community engagement tools.</p>
					<p>Masjid4All.com offers free services for Halal businesses to claim and update their profiles, and also provides AI support for Islamic-related inquiries and projects under the Masjid4All initiative.</p>
					<p>We are now focusing on AI technology, developing AI-driven agents for community engagement and planning platforms. With a strong commitment to innovation, we are expanding into the global market, bringing our cutting-edge solutions to a broader audience.</p>
				</div>

				<img class="mfa-about-partner-strip" src="https://staging.masjid4all.com/wp-content/uploads/2025/03/pewarisan-partner.webp" alt="Pewarisan Sdn Bhd partners" loading="lazy">
			</div>

			<div class="mfa-about-recognition">
				<div class="mfa-recognition-block">
					<h2>Venture Capital Partners</h2>
					<img src="https://staging.masjid4all.com/wp-content/uploads/2025/03/pewarisan-vc-1024x141.webp" alt="Logos of the three venture capital firms that invested in Pewarisan Sdn Bhd" loading="lazy">
				</div>

				<div class="mfa-recognition-block">
					<h2>Awards &amp; Recognition</h2>
					<div class="mfa-award-grid">
						<img src="https://staging.masjid4all.com/wp-content/uploads/2025/03/pewarisan-award-4.webp" alt="Hackathon and accelerator award received by Pewarisan Sdn Bhd" loading="lazy">
						<img src="https://staging.masjid4all.com/wp-content/uploads/2025/03/pewarisan-award-2-1024x115.webp" alt="Hackathon and accelerator award received by Pewarisan Sdn Bhd" loading="lazy">
						<img src="https://staging.masjid4all.com/wp-content/uploads/2025/03/pewarisan-award-3.webp" alt="Hackathon and accelerator award received by Pewarisan Sdn Bhd" loading="lazy">
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_contact_page', 'mfa_contact_page_shortcode' );
function mfa_contact_page_shortcode() {
	$address = '22-2, Jalan Prima Setapak 3, Taman Setapak, 53300 Kuala Lumpur, Malaysia';
	$map_src = 'https://www.google.com/maps?q=' . rawurlencode( 'Pewarisan Sdn Bhd, ' . $address ) . '&output=embed';

	ob_start();
	?>
	<div class="mfa-brand-page">
		<header class="mfa-hero mfa-hero--brand mfa-hero--bleed">
			<div class="mfa-hero-inner">
				<h1 class="mfa-hero-title">Contact Us</h1>
				<p class="mfa-hero-tagline">We'd love to hear from you.</p>
			</div>
		</header>

		<div class="mfa-brand-page-body">
			<div class="mfa-contact-info">
				<img class="mfa-about-logo" src="https://staging.masjid4all.com/wp-content/uploads/2025/01/Pewarisan.my-Logo-1.png" alt="Pewarisan Sdn Bhd logo" loading="lazy">
				<h2>Pewarisan Sdn Bhd</h2>
				<p class="mfa-contact-regno">Reg. No. 202001033735 (1390056-P)</p>
				<p class="mfa-contact-address"><?php echo esc_html( $address ); ?></p>
			</div>

			<div class="mfa-contact-grid">
				<div class="mfa-contact-form-col">
					<?php echo do_shortcode( '[mfa_contact_form]' ); ?>
				</div>
				<div class="mfa-contact-map-col">
					<iframe
						src="<?php echo esc_url( $map_src ); ?>"
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"
						title="Map to Pewarisan Sdn Bhd"></iframe>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
