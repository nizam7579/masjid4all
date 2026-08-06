<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_logged_out] - replaces the "Not LoggedIn" Kadence section on
 * the /member/ page (post 70180) for logged-out visitors. Plain HTML, no
 * Kadence blocks - drops the [niz_login] form (public login/registration is
 * being closed off site-wide) and keeps the "how you can contribute"
 * marketing panel that sat next to it. The much larger logged-in member
 * dashboard (Membership/eWallet/DinarX/Transactions/Affiliate, etc.) is
 * untouched - this only covers the visitor-facing marketing panel.
 *
 * The "Join the Community" card's copy is trimmed to drop "create your free
 * account" since that's no longer an available action once registration is
 * closed - everything else is copied verbatim from the original content.
 */
add_shortcode( 'mfa_member_logged_out', 'mfa_member_logged_out_shortcode' );
function mfa_member_logged_out_shortcode() {
	ob_start();
	?>
	<div class="mfa-member-out">
		<p class="mfa-member-out-notice">Registration and login will be available soon.</p>

		<h2 class="mfa-member-out-title">One Ummah. One Platform. Endless Possibilities.</h2>
		<p class="mfa-member-out-lead">Masjid4All is powered by the community. Every mosque you add, every business you list, every website you recommend, and every friend you invite helps create a trusted platform that benefits Muslims around the world.</p>

		<h3 class="mfa-member-out-subhead">How You Can Make an Impact</h3>

		<div class="mfa-member-out-cards">
			<div class="mfa-member-out-card">
				<p>🤝 <strong>Join the Community</strong><br>Become part of a growing global network of Muslims.</p>
			</div>
			<div class="mfa-member-out-card">
				<p>🕌 <strong>Add Mosques</strong><br>Help us build the world&rsquo;s largest mosque directory. Over <strong>100,000 already listed</strong>, with a target of <strong>1 million</strong> worldwide.</p>
			</div>
			<div class="mfa-member-out-card">
				<p>🏪 <strong>Add Businesses</strong><br>Support Muslim-friendly businesses by helping them get discovered by the community.</p>
			</div>
			<div class="mfa-member-out-card">
				<p>🌐 <strong>Add Websites</strong><br>Share valuable Islamic websites, apps, and online resources with the community.</p>
			</div>
			<div class="mfa-member-out-card">
				<p>📢 <strong>Share Masjid4All</strong><br>Invite your friends, family, mosque members, and community to join. Together, we can reach Muslims around the world.</p>
			</div>
			<div class="mfa-member-out-card">
				<p>🎁 <strong>Earn Rewards</strong><br>Receive points, badges, exclusive recognition, and future member benefits as you contribute and help grow the platform.</p>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
