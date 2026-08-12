<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_homepage] - full front page (/homepage) markup.
 *
 * Replaces the previous Kadence column + wp:html block soup that lived in
 * the page's post_content. Content parity is exact: same sections, classes
 * and copy, styled by homepage-v15.css (enqueued on post_name 'homepage' in
 * widgets-enqueue.php, independent of this shortcode). Internal links are
 * relative (/member/, /masjid/, ...) instead of the hardcoded
 * https://staging.masjid4all.com/* URLs the block version carried, so they
 * resolve correctly on both staging and live.
 *
 * Nested tool shortcodes ([mfa_homepage_stats], [niz_mfa_prayer_times],
 * [niz_mfa_qibla], [daily_quran]) are resolved by running the whole output
 * through do_shortcode() before returning.
 */
add_shortcode( 'mfa_homepage', 'mfa_homepage_shortcode' );
function mfa_homepage_shortcode() {
	ob_start();
	?>
	<div class="mfa-home">

		<section class="mfa-hero">
			<div class="mfa-hero-inner">
				<span class="mfa-eyebrow">Global Muslim Apps</span>
				<h1>Your Local Mosque, <span class="mfa-text-gradient"><br>One Search Away</span></h1>
				<p class="mfa-hero-sub">Find mosques and prayer times near you, connect with your community, and support Muslim-friendly businesses — all in one place.</p>
				<div class="mfa-hero-ctas">
					<a href="/member/" class="mfa-btn mfa-btn-primary">Join Free Today</a>
					<a href="/masjid/" class="mfa-btn mfa-btn-ghost">Explore Mosque Directory</a>
				</div>
			</div>
		</section>
		[mfa_homepage_stats]

		<section class="mfa-section">
			<div class="mfa-section-inner">
				<div class="mfa-section-head">
					<h2>One Platform, Four Directories</h2>
					<p>Everything a Muslim community needs to discover, connect, and grow — built around the mosque.</p>
				</div>
				<div class="mfa-directory-grid">
					<div class="mfa-directory-card">
						<div class="mfa-directory-icon">🕌</div>
						<h3>Mosque Directory</h3>
						<p>Find nearby mosques with prayer facilities, contact details, directions, and community information.</p>
						<a href="/masjid/">Mosques Near You →</a>
					</div>
					<div class="mfa-directory-card">
						<div class="mfa-directory-icon">🏪</div>
						<h3>Business Directory</h3>
						<p>Discover Muslim-friendly businesses and services, and support local entrepreneurs in your community.</p>
						<a href="/business/">Businesses Near You →</a>
					</div>
					<div class="mfa-directory-card">
						<div class="mfa-directory-icon">🌐</div>
						<h3>Website Directory</h3>
						<p>Explore trusted Islamic and Muslim-friendly websites for learning, services, shopping, and daily needs.</p>
						<a href="/web/">Discover Websites →</a>
					</div>
					<div class="mfa-directory-card">
						<div class="mfa-directory-icon">📖</div>
						<h3>Knowledge Hub</h3>
						<p>Learn from authentic Islamic resources, practical guides, and inspiring content for daily life.</p>
						<a href="/knowledge-hub/">Explore Knowledge →</a>
					</div>
				</div>
			</div>
		</section>

		<section class="mfa-section">
			<div class="mfa-section-inner">
				<div class="mfa-section-head">
					<h2>Help Us Reach a Million Mosques</h2>
					<p>Masjid4All is built by its community. Every mosque, business, and website you add strengthens the platform for Muslims everywhere.</p>
				</div>
				<div class="mfa-impact-bento">
					<div class="mfa-impact-card mfa-impact-feature">
						<span class="mfa-impact-number">🕋</span>
						<h3>Every Mosque Deserves to Be Found</h3>
						<p>Masjid4All is built by its community. If a mosque near you isn't listed yet, adding it takes just a few minutes — and helps Muslims traveling or new to the area find a place to pray.</p>
						<a href="/add-mosque/" class="mfa-btn mfa-btn-primary">Add a Mosque</a>
					</div>
					<div class="mfa-impact-card">
						<span class="mfa-impact-icon">🤝</span>
						<h3>Join the Community</h3>
						<p>Create a free account and become part of a growing global network of Muslims.</p>
						<a href="/member/">Join as Member &rarr;</a>
					</div>
					<div class="mfa-impact-card">
						<span class="mfa-impact-icon">🏪</span>
						<h3>Add Businesses</h3>
						<p>Help Muslim-friendly businesses get discovered by the community.</p>
						<a href="/add-business/">Add your Business &rarr;</a>
					</div>
					<div class="mfa-impact-card">
						<span class="mfa-impact-icon">🌐</span>
						<h3>Add Websites</h3>
						<p>Share valuable Islamic websites, apps, and resources with everyone.</p>
						<a href="/add-website/">Add your Website &rarr;</a>
					</div>
					<div class="mfa-impact-card">
						<span class="mfa-impact-icon">🎁</span>
						<h3>Share &amp; Earn Rewards</h3>
						<p>Invite others and earn points, badges, and recognition as you contribute.</p>
					</div>
				</div>
			</div>
		</section>

		<section class="mfa-section mfa-tools-section">
			<div class="mfa-section-inner">
				<div class="mfa-section-head">
					<h2>Islamic Tools for Everyday Life</h2>
					<p>Free apps built into the platform — with more on the way.</p>
				</div>
				<div class="mfa-tools-grid">
					<div class="mfa-tool-card">
						<h3>⏰ Prayer Times</h3>
						<p>Accurate prayer times based on your live location, with a countdown to the next prayer.</p>
						[niz_mfa_prayer_times]
					</div>
					<div class="mfa-tool-card">
						<h3>🧭 Qibla Direction</h3>
						<p>Point your phone and find the exact direction of the Kaaba, wherever you are.</p>
						[niz_mfa_qibla]
					</div>
					<div class="mfa-tool-card mfa-tool-wide">
						<h3>📖 Daily Quran</h3>
						<p>Recite, listen, and reflect on one short surah every visit — in under five minutes.</p>
						[daily_quran]
						<div class="mfa-tool-cta"><a href="/quran" class="mfa-btn mfa-btn-primary">Explore Daily Quran &rarr;</a></div>
					</div>
				</div>
			</div>
		</section>

		<section class="mfa-section mfa-mission">
			<div class="mfa-section-inner mfa-section-narrow">
				<div class="mfa-section-head">
					<h2>One Ummah. One Platform.</h2>
				</div>
				<div class="mfa-mission-body">
					<p>Masjid4All is a community-driven platform dedicated to connecting Muslims worldwide. Starting from the mosque — the heart of every Muslim community — we bring together prayer tools, mosque directories, Muslim businesses, Islamic resources, and digital services to help Muslims discover, connect, and contribute.</p>
					<p><strong>Our mission is simple:</strong> use technology to strengthen the Ummah and make beneficial resources accessible to everyone, everywhere.</p>
				</div>
			</div>
		</section>

		<section class="mfa-section">
			<div class="mfa-section-inner mfa-section-narrow">
				<div class="mfa-cta-band">
					<h2>Be Part of a Global Muslim Community</h2>
					<p>Register free and connect with mosques, businesses, and resources near you.</p>
					<a href="/member/" class="mfa-btn mfa-btn-solid-dark">Join as a Member Now</a>
				</div>
			</div>
		</section>

		<section class="mfa-section">
			<div class="mfa-section-inner">
				<div class="mfa-section-head">
					<h2>Real Stories from Our Community</h2>
					<p>What our members experience using Masjid4All every day.</p>
				</div>
				<div class="mfa-testimonial-grid">
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"I travel often for work, and Masjid4All helps me quickly find nearby mosques, prayer times, Qibla direction, and halal places wherever I go."</p>
						<p class="mfa-attr">Ahmad Faiz Rahman – Malaysia</p>
					</div>
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"A customer discovered my business through Masjid4All. After claiming my listing, I started reaching more local customers."</p>
						<p class="mfa-attr">Omar Al-Hassan – United Kingdom</p>
					</div>
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"It helps me keep a daily Quran habit — listen, read, and reflect in just a few minutes each day. It brings real peace to my routine."</p>
						<p class="mfa-attr">Amina Yusuf – Nigeria</p>
					</div>
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"I found a halal caterer just minutes away from my home for my daughter’s wedding. It was exactly what I needed at the right time."</p>
						<p class="mfa-attr">Hafsa Karim – Pakistan</p>
					</div>
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"The Knowledge Hub gives me clear, structured answers when I need guidance. Everything is in one trusted place. Thank you Masjid4All."</p>
						<p class="mfa-attr">Bilal Ismail – South Africa</p>
					</div>
					<div class="mfa-testimonial-card">
						<p class="mfa-quote">"The AI assistant is incredibly helpful — answers are instant anytime I need them, like having a knowledgeable guide available 24/7."</p>
						<p class="mfa-attr">Nurul Huda Abdullah – Brunei</p>
					</div>
				</div>
			</div>
		</section>

		<section class="mfa-section mfa-section-narrow">
			<div class="mfa-section-inner">
				<div class="mfa-section-head">
					<h2>Frequently Asked Questions</h2>
				</div>
				<div class="mfa-faq-list">
					<details>
						<summary>What is Masjid4All?</summary>
						<p>Masjid4All is a global digital platform that connects Muslims with mosques, businesses, websites, and Islamic knowledge resources. Our mission is to make it easier for Muslims to discover, connect, and contribute to their communities through technology.</p>
					</details>
					<details>
						<summary>What can I find on Masjid4All?</summary>
						<p>A mosque directory, a halal business directory, a curated website directory, an Islamic knowledge hub, live prayer times, a Qibla finder, and a daily Quran reading tool — all free to use.</p>
					</details>
					<details>
						<summary>What is the Mosque Directory?</summary>
						<p>A community-built listing of mosques worldwide with prayer facilities, contact details, directions, and community information, growing toward a target of 1,000,000 mosques.</p>
					</details>
					<details>
						<summary>What is the Business Directory?</summary>
						<p>A directory of Muslim-friendly businesses and services. Business owners can claim their listing to get discovered by the local community.</p>
					</details>
					<details>
						<summary>What is the Knowledge Hub?</summary>
						<p>A collection of authentic Islamic resources, guides, and articles to help you learn and strengthen your daily practice.</p>
					</details>
					<details>
						<summary>How can I join the Masjid4All community?</summary>
						<p>Create a free account on our <a href="/member/">member page</a> using email, Google, or your WhatsApp number.</p>
					</details>
					<details>
						<summary>Is Masjid4All free to use?</summary>
						<p>Yes. Browsing the directories, prayer times, Qibla finder, and Daily Quran are free for everyone. Optional premium membership plans are available for members who want additional features.</p>
					</details>
				</div>
			</div>
		</section>

	</div>
	<?php
	return do_shortcode( ob_get_clean() );
}
