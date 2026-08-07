<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_logged_out] - replaces the "Not LoggedIn" Kadence section on
 * the /member/ page (post 70180) for logged-out visitors. Plain HTML, no
 * Kadence blocks - the "how you can contribute" marketing panel below is
 * kept as-is; the login/register forms above it were reopened 2026-08-08
 * (scoped to just this page, for testing the new registration/login flow -
 * /register/ and the other add-mosque/add-business/claim pages stay closed
 * per the earlier 2026-08-07 decision, that's a separate call). Reuses the existing
 * [niz_login]/[niz_register] shortcodes verbatim (native, no 3rd-party
 * form plugin) behind a tab toggle so both fit on one page without a
 * redirect - see member-auth-toggle-v1.js for the tab-switch + internal
 * cross-link interception (login's "Register" link / register's "Login"
 * link would otherwise navigate to /register/, which is still closed).
 */
add_shortcode( 'mfa_member_logged_out', 'mfa_member_logged_out_shortcode' );
function mfa_member_logged_out_shortcode() {
	ob_start();
	?>
	<div class="mfa-member-auth" id="mfa-member-auth">
		<div class="mfa-member-auth-tabs" role="tablist">
			<button type="button" class="mfa-member-auth-tab is-active" data-mfa-auth-tab="login" role="tab" aria-selected="true">Login</button>
			<button type="button" class="mfa-member-auth-tab" data-mfa-auth-tab="register" role="tab" aria-selected="false">Register</button>
		</div>
		<div class="mfa-member-auth-panel" data-mfa-auth-panel="login">
			<?php echo do_shortcode( '[niz_login]' ); ?>
		</div>
		<div class="mfa-member-auth-panel" data-mfa-auth-panel="register" hidden>
			<?php echo do_shortcode( '[niz_register]' ); ?>
		</div>
	</div>

	<div class="mfa-member-out">
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
