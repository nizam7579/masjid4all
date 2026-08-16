<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_privacy_page] and [mfa_terms_page] - the /privacy-policy/ and
 * /terms-of-service/ pages, plain HTML (no Kadence blocks), following the
 * same header template as the other rebuilt pages but with no ad column -
 * legal documents should read as focused, uncluttered text. The legal
 * text itself is carried over verbatim from the old Kadence content
 * (heading levels renumbered h3/h4 -> h2/h3 now that the page's own h1 is
 * the title). Two small pre-existing content bugs are fixed along the
 * way: the "admin@pewarisan.com"/mailto links had no href at all (dead
 * text, not actual links), and the Terms page had an unfilled template
 * placeholder ("[link to Privacy Policy]") instead of a real link. Both
 * pages also used to end with the same dead legacy "Footer" reusable
 * block dropped from About/Contact — see brand-pages.php for why.
 */

add_shortcode( 'mfa_privacy_page', 'mfa_privacy_page_shortcode' );
function mfa_privacy_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-legal-page">
		<header class="mfa-tool-page-header">
			<h1>Privacy Policy</h1>
			<p class="mfa-tool-page-tagline">How Masjid4ALL collects, uses and protects your information.</p>
		</header>

		<div class="mfa-legal-page-body">
			<p class="mfa-legal-meta">Effective Date: 1st March 2025</p>

			<article class="mfa-legal-article">
				<p>Masjid4ALL (hereinafter referred to as "we," "our," or "us") is committed to protecting and respecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your personal information when you visit our website, masjid4all.com (the "Site"). By accessing or using the Site, you agree to the terms of this Privacy Policy.</p>

				<h2>1. Information We Collect</h2>
				<p>We collect various types of information to provide you with a better experience while using our Site. The information we collect may include:</p>

				<h3>a. Personal Information</h3>
				<ul>
					<li><strong>Contact Information</strong>: Name, email address, phone number, or any other information you voluntarily provide when contacting us or registering on the site.</li>
					<li><strong>Location Information</strong>: Geographical data that may be used to recommend nearby mosques based on your location.</li>
				</ul>

				<h3>b. Non-Personal Information</h3>
				<ul>
					<li><strong>Usage Data</strong>: Information about how you access and use the Site, including IP address, browser type, operating system, pages visited, and other technical data.</li>
					<li><strong>Cookies</strong>: We may use cookies and similar technologies to enhance your experience, analyze usage patterns, and track advertising performance. You can control the use of cookies through your browser settings.</li>
				</ul>

				<h2>2. How We Use Your Information</h2>
				<p>We use the information we collect for the following purposes:</p>
				<ul>
					<li>To provide and improve our services and features on the Site.</li>
					<li>To respond to inquiries and provide customer support.</li>
					<li>To send promotional or informational emails, provided you have opted-in to receive them.</li>
					<li>To personalize your experience based on your location or preferences.</li>
					<li>To comply with legal obligations or enforce our terms and policies.</li>
				</ul>

				<h2>3. Sharing Your Information</h2>
				<p>We do not sell, trade, or rent your personal information to third parties. However, we may share your information in the following situations:</p>
				<ul>
					<li><strong>Service Providers</strong>: We may engage third-party companies to assist in operating our Site or providing services to you (e.g., hosting providers, analytics services). These service providers are only permitted to use your information to perform services on our behalf.</li>
					<li><strong>Legal Requirements</strong>: We may disclose your information if required to do so by law or in response to legal process, such as a court order or subpoena.</li>
				</ul>

				<h2>4. Data Security</h2>
				<p>We employ appropriate technical and organizational measures to safeguard your personal information from unauthorized access, use, alteration, or disclosure. However, no method of transmission over the Internet or electronic storage is completely secure, and we cannot guarantee absolute security.</p>

				<h2>5. Your Rights</h2>
				<p>You have the right to:</p>
				<ul>
					<li>Access the personal information we hold about you.</li>
					<li>Correct any inaccurate or incomplete information.</li>
					<li>Request the deletion of your personal information, subject to certain legal restrictions.</li>
					<li>Opt-out of receiving marketing communications by following the unsubscribe instructions in emails or contacting us directly at <a href="mailto:admin@pewarisan.com">admin@pewarisan.com</a>.</li>
				</ul>

				<h2>6. Third-Party Links</h2>
				<p>Our Site may contain links to third-party websites that are not operated by us. We are not responsible for the privacy practices or content of these third-party sites. We encourage you to review the privacy policies of any linked sites you visit.</p>

				<h2>7. Changes to This Privacy Policy</h2>
				<p>We may update this Privacy Policy from time to time. When we do, we will post the revised version on this page with a new effective date. We encourage you to periodically review this Privacy Policy to stay informed about how we are protecting your information.</p>

				<h2>8. Contact Us</h2>
				<p>If you have any questions or concerns about this Privacy Policy or our data practices, please visit our <a href="https://masjid4all.com/contact-us">Contact Us page</a>.</p>

				<p><strong>Masjid4ALL.com<br>Pewarisan Sdn Bhd</strong><br>22-2, Jalan Prima Setapak 3<br>Taman Setapak, 50300 Kuala Lumpur, Malaysia</p>
			</article>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_terms_page', 'mfa_terms_page_shortcode' );
function mfa_terms_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-legal-page">
		<header class="mfa-tool-page-header">
			<h1>Terms of Service</h1>
			<p class="mfa-tool-page-tagline">The terms that govern your use of Masjid4ALL.</p>
		</header>

		<div class="mfa-legal-page-body">
			<p class="mfa-legal-meta">Effective Date: 1st March 2025</p>

			<article class="mfa-legal-article">
				<p>Welcome to <strong>Masjid4ALL</strong>, a service developed by Pewarisan Sdn Bhd (hereinafter referred to as "Masjid4ALL", "we," "our," or "us"). These Terms of Service ("Terms") govern your access to and use of our website and services, including any content, functionality, and services offered on or through the website masjid4all.com (the "Site"). By accessing or using the Site, you agree to comply with and be bound by these Terms. If you do not agree to these Terms, do not use the Site.</p>

				<h2>1. Acceptance of Terms</h2>
				<p>By accessing or using the Site, you agree to comply with and be legally bound by these Terms. You must be at least 18 years old or the age of majority in your jurisdiction to use the Site. If you are under the age of majority, you must have the consent of a parent or legal guardian to use the Site.</p>

				<h2>2. Services Provided</h2>
				<p>Masjid4ALL provides a global directory of mosques that allows users to find mosques and related services worldwide. We may also provide additional services or features through the Site, including the ability to search for mosques, obtain information about specific locations, and more.</p>

				<h2>3. User Account and Responsibilities</h2>
				<ul>
					<li>To use certain features of the Site, you may be required to create an account. When creating an account, you agree to provide accurate, complete, and up-to-date information.</li>
					<li>You are solely responsible for maintaining the confidentiality of your account credentials (username and password). You agree to notify us immediately if you suspect any unauthorized use of your account.</li>
					<li>You agree not to misuse or interfere with the Site or its services, and not to engage in any activity that could damage, disable, or impair the functionality of the Site.</li>
				</ul>

				<h2>4. Use of the Site</h2>
				<ul>
					<li>You agree to use the Site solely for lawful purposes and in accordance with these Terms.</li>
					<li>You must not engage in any conduct that could damage, disable, or impair the operation of the Site or interfere with the use of the Site by other users.</li>
					<li>You are prohibited from using the Site for any illegal or unauthorized purposes.</li>
				</ul>

				<h2>5. User-Generated Content</h2>
				<ul>
					<li>By submitting or uploading any content to the Site, including reviews, comments, or suggestions, you grant Masjid4ALL a worldwide, royalty-free, non-exclusive license to use, display, and distribute that content.</li>
					<li>You represent and warrant that you own or have the necessary rights to any content you submit and that the content does not infringe any third-party rights.</li>
				</ul>

				<h2>6. Privacy and Data Collection</h2>
				<p>Your use of the Site is also governed by our <a href="https://masjid4all.com/privacy-policy/">Privacy Policy</a>. By using the Site, you consent to our collection and use of your information as outlined in our Privacy Policy.</p>

				<h2>7. Intellectual Property</h2>
				<ul>
					<li>All content on the Site, including text, graphics, logos, images, and software, is the property of Masjid4ALL or its licensors and is protected by copyright and other intellectual property laws.</li>
					<li>You may not copy, reproduce, distribute, transmit, display, perform, or create derivative works of any content on the Site without the prior written permission of Masjid4ALL.</li>
				</ul>

				<h2>8. Third-Party Links</h2>
				<p>The Site may contain links to third-party websites that are not operated or controlled by Masjid4ALL. We are not responsible for the content or practices of any third-party websites. By using the Site, you acknowledge and agree that Masjid4ALL is not responsible for any damages or losses arising from your use of third-party websites.</p>

				<h2>9. Disclaimer of Warranties</h2>
				<ul>
					<li>The Site and all content and services provided are on an "as-is" and "as-available" basis. We make no representations or warranties of any kind, express or implied, regarding the operation of the Site or the accuracy, reliability, or completeness of any content or services provided.</li>
					<li>To the fullest extent permitted by law, Masjid4ALL disclaims all warranties, whether express or implied, including but not limited to the implied warranties of merchantability, fitness for a particular purpose, and non-infringement.</li>
				</ul>

				<h2>10. Limitation of Liability</h2>
				<ul>
					<li>To the fullest extent permitted by law, Masjid4ALL will not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenue, arising out of or in connection with your use of the Site.</li>
					<li>In no event will Masjid4ALL's total liability to you exceed the amount you paid, if any, to access the Site or use its services.</li>
				</ul>

				<h2>11. Indemnification</h2>
				<p>You agree to indemnify and hold harmless Masjid4ALL, its affiliates, officers, directors, employees, and agents from and against any claims, damages, losses, liabilities, costs, and expenses, including reasonable attorneys' fees, arising out of or in connection with your use of the Site or violation of these Terms.</p>

				<h2>12. Termination</h2>
				<p>We may suspend or terminate your access to the Site at any time, without notice, for conduct that we believe violates these Terms or is harmful to other users or to the Site.</p>

				<h2>13. Changes to the Terms</h2>
				<p>Masjid4ALL reserves the right to update or modify these Terms at any time, without prior notice. Any changes to the Terms will be effective upon posting on this page with the updated effective date. Your continued use of the Site after the posting of revised Terms constitutes your acceptance of those changes.</p>

				<h2>14. Governing Law</h2>
				<p>These Terms are governed by and construed in accordance with the laws of Malaysia. Any disputes arising out of or in connection with these Terms will be subject to the exclusive jurisdiction of the courts located in Kuala Lumpur, Malaysia.</p>

				<h2>15. Contact Information</h2>
				<p>If you have any questions about these Terms, please contact us at:</p>
				<p><strong>Masjid4ALL<br>Pewarisan Sdn Bhd</strong><br>22-2, Jalan Prima Setapak 3<br>Taman Setapak, 50300 Kuala Lumpur, Malaysia<br>Email: <a href="mailto:admin@pewarisan.com">admin@pewarisan.com</a></p>
			</article>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
