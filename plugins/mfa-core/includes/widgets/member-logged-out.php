<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_auth_tabs] - the logged-out auth block. Registration now happens via
 * Sofia on WhatsApp (email verified + magic-login, no password), so the old
 * web register tab is gone: this shows the Login form (email/password +
 * Google) plus two Sofia popups - "Register with Sofia" and "Log in with
 * WhatsApp" (for accounts created via WhatsApp, which have no password and so
 * can only get in via the magic link). Reuses the .mfa-assist-* popup
 * component (dir-assist-v1.css/js, enqueued on this page in widgets-enqueue).
 */
add_shortcode( 'mfa_auth_tabs', 'mfa_auth_tabs_shortcode' );
function mfa_auth_tabs_shortcode() {
	$wa    = '60189897579';
	$reg   = 'https://wa.me/' . $wa . '?text=' . rawurlencode( 'register' );
	$login = 'https://wa.me/' . $wa . '?text=' . rawurlencode( 'login' );

	ob_start();
	?>
	<div class="mfa-member-auth" id="mfa-member-auth">
		<div class="mfa-member-auth-panel" data-mfa-auth-panel="login">
			<?php echo do_shortcode( '[niz_login]' ); ?>
		</div>

		<div class="mfa-assist-links">
			<button type="button" class="mfa-assist-link mfa-assist-open" data-target="mfa-auth-register">New to Masjid4All? <strong>Register with Sofia</strong></button>
			<button type="button" class="mfa-assist-link mfa-assist-open" data-target="mfa-auth-login">Registered on WhatsApp? <strong>Log in with WhatsApp</strong></button>
		</div>
	</div>

	<?php
	echo mfa_assist_popup( 'mfa-auth-register', '🕌', 'Join Masjid4All — free',
		'Our AI assistant <strong>Sofia</strong> sets up your account on WhatsApp in under a minute — email verified, no password to remember.',
		'Register on WhatsApp', $reg );
	echo mfa_assist_popup( 'mfa-auth-login', '💬', 'Log in with WhatsApp',
		'Registered via WhatsApp? Tap below and <strong>Sofia</strong> sends you a one-tap login link — no password needed.',
		'Log in on WhatsApp', $login );
	?>
	<?php
	return ob_get_clean();
}

/**
 * Renders a Sofia-assist popup (the .mfa-assist-* modal) with a WhatsApp CTA.
 * Shared by the login page and the forgot-password page.
 */
function mfa_assist_popup( $id, $emoji, $title, $text_html, $btn, $href ) {
	$wa_icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.43.06-.66.31-.23.24-.86.84-.86 2.06 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z"/></svg>';
	ob_start();
	?>
	<div class="mfa-assist-overlay" id="<?php echo esc_attr( $id ); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo esc_attr( $title ); ?>">
		<div class="mfa-assist-modal">
			<button type="button" class="mfa-assist-close" aria-label="Close">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
			<div class="mfa-assist-emoji"><?php echo $emoji; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<h3 class="mfa-assist-title"><?php echo esc_html( $title ); ?></h3>
			<p class="mfa-assist-text"><?php echo wp_kses_post( $text_html ); ?></p>
			<a class="mfa-assist-cta" href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $btn ); ?></a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [mfa_member_logged_out] - replaces the "Not LoggedIn" Kadence section on
 * the /member/ page (post 70180) for logged-out visitors. Plain HTML, no
 * Kadence blocks - the "how you can contribute" marketing panel below is
 * kept as-is; the login/register forms above it were reopened 2026-08-08
 * (scoped to just this page, for testing the new registration/login flow -
 * /register/ stays closed per the earlier 2026-08-07 decision, that's a
 * separate call; add-mosque/add-business/add-website reopened separately
 * 2026-08-09 via [mfa_auth_tabs] above). Reuses the existing
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
	<?php echo do_shortcode( '[mfa_auth_tabs]' ); ?>

	<div class="mfa-member-out">
		<h1 class="mfa-member-out-title">One Ummah. One Platform. Endless Possibilities.</h1>
		<p class="mfa-member-out-lead">Masjid4All is powered by the community. Every mosque you add, every business you list, every website you recommend, and every friend you invite helps create a trusted platform that benefits Muslims around the world.</p>

		<h2 class="mfa-member-out-subhead">How You Can Make an Impact</h2>

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
