<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contribute-flow page wrappers: Add Mosque, Add Business, Add Website.
 *
 * These pages (linked from the homepage "Add a Mosque/Business/Website"
 * CTAs) previously rendered a Kadence heading + two role-gated Kadence
 * columns (logged-in => the niz_mfa_add_* form, logged-out => mfa_auth_tabs)
 * plus a boilerplate info column. To take them off Kadence blocks each page
 * now renders through one wrapper shortcode here:
 *   - page heading (H1 + subheading)
 *   - the add form (logged in) OR the auth tabs (logged out), via a PHP
 *     is_user_logged_in() check that replaces the Kadence blockVisibility
 *     role gate
 *   - Add Website only: the boilerplate info section, which was visible on
 *     that page. The equivalent section on Add Mosque/Add Business was
 *     hidden (Kadence hideBlock:true), so it is intentionally dropped there.
 *
 * The add forms (niz_mfa_add_*) and mfa_auth_tabs bring their own styling;
 * contribute-page-v1.css only styles the heading + info section.
 */

/**
 * Shared page heading.
 */
function mfa_contribute_head( $title, $subtitle ) {
	return '<div class="mfa-contribute-head"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $subtitle ) . '</p></div>';
}

/**
 * Logged-in add form, or logged-out auth tabs — matching the original
 * role-gated columns.
 */
function mfa_contribute_form( $add_shortcode ) {
	if ( is_user_logged_in() ) {
		return do_shortcode( '[' . $add_shortcode . ']' );
	}
	return do_shortcode( '[mfa_auth_tabs]' );
}

add_shortcode( 'mfa_add_mosque_page', 'mfa_add_mosque_page_shortcode' );
function mfa_add_mosque_page_shortcode() {
	$out  = '<div class="mfa-contribute">';
	$out .= mfa_contribute_head( 'Add Mosque', 'Your mosque is not listed? Please search and add to our directory' );
	$out .= mfa_contribute_form( 'niz_mfa_add_mosque' );
	$out .= '</div>';
	return $out;
}

add_shortcode( 'mfa_add_business_page', 'mfa_add_business_page_shortcode' );
function mfa_add_business_page_shortcode() {
	$out  = '<div class="mfa-contribute">';
	$out .= mfa_contribute_head( 'Own a Business or Want to Recommend One?', 'Submit your business or recommend a local business to be listed in our Business Directory.' );
	$out .= mfa_contribute_form( 'niz_mfa_add_business' );
	$out .= '</div>';
	return $out;
}

add_shortcode( 'mfa_add_website_page', 'mfa_add_website_page_shortcode' );
function mfa_add_website_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-contribute">
		<?php echo mfa_contribute_head( 'Muslim Web Directory', 'Help us build the largest, most trusted collection of Islamic websites — one valuable resource at a time.' ); ?>
		<?php echo mfa_contribute_form( 'niz_add_website' ); ?>

		<div class="mfa-contribute-info">
			<h2>Our Mission</h2>
			<p>We aim to build a trusted global Muslim-friendly directory that helps connect the Ummah with ethical, Shariah-compliant, and value-aligned businesses and services. Whether it is finding a halal place to eat, accessing professional services, or discovering community resources, our goal is to make it easier for Muslims to support businesses that reflect Islamic principles and integrity.</p>

			<h2>How You Can Contribute</h2>
			<p>You can play an important role in strengthening this ecosystem by submitting your own business or recommending trusted services in your area. Every contribution helps expand a reliable network that benefits Muslims worldwide and supports local communities.</p>

			<h2>Rewards &amp; Recognition</h2>
			<p>As a token of appreciation for contributing to the growth of the directory, you will earn <strong>10 Barakah Points</strong> for every business submission that is successfully reviewed and approved by our team.</p>

			<h2>Types of Businesses We Accept</h2>
			<p>We welcome listings that are beneficial, ethical, and aligned with Shariah principles. Categories include:</p>
			<ul>
				<li>Food &amp; beverage businesses (restaurants, cafés, bakeries, catering)</li>
				<li>Halal grocery stores and food suppliers</li>
				<li>Islamic education, tuition, and training centres</li>
				<li>Healthcare and wellness providers</li>
				<li>Professional services (legal, accounting, consulting, design, etc.)</li>
				<li>Home services (cleaning, maintenance, renovation, plumbing, electrical, landscaping)</li>
				<li>Retail stores and online shops offering permissible products</li>
				<li>Technology, software, and digital service providers</li>
				<li>Automotive sales and services</li>
				<li>Travel and tourism services</li>
				<li>Property and real estate services</li>
				<li>Financial and business services that comply with Islamic principles</li>
				<li>Non-profit organisations, community groups, and social initiatives</li>
			</ul>

			<h2>Listings Not Accepted</h2>
			<p>To maintain the integrity and trust of the platform, we do not accept listings related to:</p>
			<ul>
				<li>Alcohol-related businesses</li>
				<li>Gambling or betting services</li>
				<li>Conventional interest-based financial services</li>
				<li>Adult entertainment or immoral content</li>
				<li>Tobacco, vaping, and related products</li>
				<li>Sale or promotion of clearly prohibited (haram) goods or services</li>
				<li>Any business that conflicts with Islamic values or ethical standards</li>
			</ul>

			<p>We reserve the right to review, approve, or reject any submission to ensure consistency with our guidelines and community standards.</p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
