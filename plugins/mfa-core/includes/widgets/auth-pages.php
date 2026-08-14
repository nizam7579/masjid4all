<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth / confirmation utility-page wrappers.
 *
 * These pages (email-verified, forgot-password, reset-password,
 * payment-success, payment-failed) previously rendered their shortcode plus
 * a Kadence "Go to Member's Page" button (kadence/advancedbtn) inside a
 * Kadence column. To take them off Kadence blocks without touching the
 * auth logic itself (the niz_* shortcodes live in enaizi-identity /
 * mfa-core and are intentionally left alone), each page now renders through
 * one wrapper shortcode here: the original shortcode via do_shortcode()
 * plus the shared member-return button.
 *
 * Styling: button comes from .mfa-btn in global-v2.css (sitewide); the
 * centering wrapper and payment-failed message come from auth-return-v1.css
 * (enqueued by post_name in widgets-enqueue.php).
 */

/**
 * Shared "Go to Member's Page" button, matching the Kadence singlebtn these
 * pages used (label + /member/ target).
 */
function mfa_member_return_button( $label = "Go to Member's Page" ) {
	return '<div class="mfa-auth-return"><a href="/member/" class="mfa-btn mfa-btn-primary">' . esc_html( $label ) . '</a></div>';
}

add_shortcode( 'mfa_email_verified_page', 'mfa_email_verified_page_shortcode' );
function mfa_email_verified_page_shortcode() {
	return do_shortcode( '[niz_email_verified]' ) . mfa_member_return_button();
}

add_shortcode( 'mfa_reset_password_page', 'mfa_reset_password_page_shortcode' );
function mfa_reset_password_page_shortcode() {
	return do_shortcode( '[niz_reset_password]' ) . mfa_member_return_button();
}

/**
 * Forgot Password: the button was shown only to logged-in visitors on the
 * original page (the logged-out view is the request form itself), so keep
 * that role gate here.
 */
add_shortcode( 'mfa_forgot_password_page', 'mfa_forgot_password_page_shortcode' );
function mfa_forgot_password_page_shortcode() {
	$out = do_shortcode( '[niz_forgot_password]' );

	// Accounts created via WhatsApp have no password to reset — offer the
	// Sofia magic-login instead, alongside the email reset above.
	if ( function_exists( 'mfa_assist_popup' ) ) {
		$login = 'https://wa.me/60189897579?text=' . rawurlencode( 'login' );
		$out  .= '<div class="mfa-assist-links"><button type="button" class="mfa-assist-link mfa-assist-open" data-target="mfa-auth-login">Registered on WhatsApp? <strong>Log in with WhatsApp</strong> — no reset needed</button></div>';
		$out  .= mfa_assist_popup( 'mfa-auth-login', '💬', 'Log in with WhatsApp',
			'Registered via WhatsApp? Tap below and <strong>Sofia</strong> sends you a one-tap login link — no password needed.',
			'Log in on WhatsApp', $login );
	}

	if ( is_user_logged_in() ) {
		$out .= mfa_member_return_button();
	}
	return $out;
}

add_shortcode( 'mfa_payment_success_page', 'mfa_payment_success_page_shortcode' );
function mfa_payment_success_page_shortcode() {
	return do_shortcode( '[mfa_payment_success_details]' ) . mfa_member_return_button();
}

/**
 * Payment Failed: the original was only a bare "Payment Failed" heading.
 * Give it a short explanatory message plus the member-return button.
 */
add_shortcode( 'mfa_payment_failed_page', 'mfa_payment_failed_page_shortcode' );
function mfa_payment_failed_page_shortcode() {
	ob_start();
	?>
	<div class="mfa-auth-msg">
		<h1>Payment Failed</h1>
		<p>Your payment could not be completed and no charge was made. You can try again from your member page.</p>
		<a href="/member/" class="mfa-btn mfa-btn-primary">Go to Member's Page</a>
	</div>
	<?php
	return ob_get_clean();
}
