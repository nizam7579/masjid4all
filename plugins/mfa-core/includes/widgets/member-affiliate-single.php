<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_affiliate] - /member/affiliate/ (post 218138). MVP scope
 * (2026-08-09): if the user hasn't joined the affiliate program yet, show
 * the same join prompt as the dashboard's Affiliate Program card; once
 * joined, show their downline (jet_cct_member rows where referrer_id is
 * them) - name and when they joined. The old page had a "Your Affiliates"
 * JetEngine listing-grid here, plus long-form commission-structure and
 * promotional copy (5% standard / 20% Founding Member) - deliberately not
 * carried over yet; that content plus any real commission ledger/payout
 * waits on Founding Member + a payment gateway, per direction. Replaces
 * the page's previous Kadence-block content entirely (its real content
 * was hardcoded hideBlock:true, and its join button just opened a modal
 * saying "Coming Soon"), per the project's one-shortcode-per-page rule.
 *
 * Login is already enforced before this ever runs -
 * includes/member-access-control.php redirects logged-out visitors back
 * to /member/ on template_redirect.
 */
add_shortcode( 'mfa_member_affiliate', 'mfa_member_affiliate_shortcode' );
function mfa_member_affiliate_shortcode() {

	$user_id       = get_current_user_id();
	$has_affiliate = function_exists( 'niz_user_field_by_userid' ) && 'Yes' === niz_user_field_by_userid( $user_id, 'chk_affiliate' );

	ob_start();
	?>
	<div class="mfa-dash">
		<a href="/member/" class="mfa-dash-link-btn">&larr; Back to Dashboard</a>

		<?php if ( ! $has_affiliate ) : ?>
			<div class="mfa-card mfa-dash-points-card">
				<h1 class="mfa-h2">Join the Affiliate Program</h1>
				<p class="mfa-body-muted">Earn 100 Barakah points and start building your downline by inviting mosques, businesses, and members to join Masjid4All.</p>
				<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-join-affiliate="<?php echo esc_attr( wp_create_nonce( 'mfa_join_affiliate' ) ); ?>">Join Masjid4All Affiliate Program</button>
			</div>
		<?php else : ?>
			<div class="mfa-card mfa-dash-points-card">
				<h1 class="mfa-h2">My Affiliates</h1>
				<?php
				global $wpdb;
				$downline = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT name, cct_created FROM {$wpdb->prefix}jet_cct_member WHERE referrer_id = %d ORDER BY cct_created DESC",
						$user_id
					)
				);
				?>
				<?php if ( $downline ) : ?>
					<ul class="mfa-dash-simple-list">
						<?php foreach ( $downline as $person ) : ?>
							<li>
								<span><?php echo esc_html( $person->name ); ?></span>
								<span class="mfa-dash-card-note"><?php echo esc_html( $person->cct_created ? date_i18n( 'j M Y', strtotime( $person->cct_created ) ) : '' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="mfa-body-muted mfa-dash-locked">No one has joined through you yet - share your links from any page and they'll show up here.</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
