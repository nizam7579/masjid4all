<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_premium_page] - streamlined Founding Member page at /member/premium/.
 *
 * Replaces the previous ~70KB Kadence sales page. Member-area page, so it
 * renders inside templates/member-page.php (already Kadence-free). Presents
 * the core founding offer ($29.90 one-time, lifetime Premium, 100% back as
 * Platform Credit, Founding Member status) in a "launching soon" state -
 * founding membership is not purchasable yet, so there is no live buy CTA,
 * only a Back to Dashboard action. Styled by premium-page-v1.css using the
 * global-v3 design tokens.
 *
 * Still the target of the member dashboard "See Founding Member Details"
 * button and the niz-wa pricing reply (both link /member/premium/).
 */
add_shortcode( 'mfa_premium_page', 'mfa_premium_page_shortcode' );
function mfa_premium_page_shortcode() {
	$benefits = array(
		array( '♾️', 'Lifetime Premium Access', 'Pay once. No monthly fees, no annual renewals, no recurring subscriptions.' ),
		array( '💳', '100% Back as Platform Credit', 'Your $29.90 contribution returns to you as usable Platform Credit within the ecosystem.' ),
		array( '⭐', 'Founding Member Status', 'Permanent recognition as one of the first supporters who helped build Masjid4All.' ),
		array( '🤝', 'Supports the Mission', 'Helps fund the core infrastructure that keeps essential tools free for Muslims worldwide.' ),
	);

	ob_start();
	?>
	<div class="mfa-premium">
		<a href="/member/" class="mfa-premium-back">&larr; Back to Dashboard</a>

		<section class="mfa-premium-hero">
			<span class="mfa-eyebrow mfa-eyebrow-dark">Founding Member</span>
			<h1>Help Build the Future of the <span>Islamic Digital Ecosystem</span></h1>
			<p class="mfa-premium-sub">Become a Founding Member and secure lifetime Premium access while supporting a platform built for the Ummah.</p>
		</section>

		<div class="mfa-premium-offer">
			<span class="mfa-premium-badge">Launching soon</span>
			<div class="mfa-premium-price">
				<span class="mfa-premium-amount">$29.90</span>
				<span class="mfa-premium-term">One-time payment &middot; Lifetime Premium</span>
			</div>
			<p class="mfa-premium-note">Founding Membership is launching soon. This one-time founding offer will be limited to the first 3,000 members.</p>
			<a href="/member/" class="mfa-btn mfa-btn-primary">Back to Dashboard</a>
		</div>

		<div class="mfa-premium-benefits">
			<?php foreach ( $benefits as $b ) : ?>
				<div class="mfa-premium-benefit">
					<span><?php echo esc_html( $b[0] ); ?></span>
					<h3><?php echo esc_html( $b[1] ); ?></h3>
					<p><?php echo esc_html( $b[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
