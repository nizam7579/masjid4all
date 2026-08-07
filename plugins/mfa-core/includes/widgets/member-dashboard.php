<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_dashboard] - the logged-in /member/ experience (post 70180),
 * replacing the legacy Kadence-block dashboard (Membership/eWallet/DinarX/
 * Matrimony/etc) per the 2026-08-07 member-dashboard redesign. Logged-out
 * visitors still get the existing [mfa_member_logged_out] marketing panel
 * unchanged - reopening login/registration is a separate, not-yet-made
 * decision, not something this rebuild touches.
 *
 * Deliberately built only around features with a real, working backend
 * (per the "only show what's real" decision): Barakah points ledger,
 * email/WhatsApp verification, a native Edit Profile / Change Password
 * popup (member-account-modals.php - replaced the earlier Fluent Form
 * embed 2026-08-08, avoiding a 3rd-party form-plugin dependency),
 * listing ownership, and the live Stripe Founding Member checkout. No
 * affiliate/referral UI here - that backend (outbound referral links,
 * commission ledger) doesn't exist yet (Phase 4).
 */

add_shortcode( 'mfa_member_dashboard', 'mfa_member_dashboard_shortcode' );
function mfa_member_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		return do_shortcode( '[mfa_member_logged_out]' );
	}

	$user    = wp_get_current_user();
	$user_id = $user->ID;

	$email_verified = get_user_meta( $user_id, 'niz_email_verified', true ) === 'Yes';
	$wa_verified     = get_user_meta( $user_id, 'niz_whatsapp_verified', true ) === 'Yes';

	$country          = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'country' ) : '';
	$profile_complete = ! empty( $country );

	$is_premium = function_exists( 'niz_user_field_by_userid' ) && 'Yes' === niz_user_field_by_userid( $user_id, 'chk_premium' );

	$points = function_exists( 'mfa_get_barakah_points' ) ? mfa_get_barakah_points( $user_id ) : 0;
	$rank   = function_exists( 'mfa_get_barakah_rank' ) ? mfa_get_barakah_rank( $points ) : array( 'rank' => 'Bronze', 'next_rank' => 'Silver', 'next_at' => 500, 'points_to_next' => 500 );

	$has_joined_award  = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Welcome Bonus' );
	$has_email_award   = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Verify Email' );
	$has_wa_award      = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Verify WhatsApp' );
	$has_profile_award = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Complete Profile' );

	global $wpdb;
	$listing_count = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_listing_owner WHERE user_id = %d", $user_id )
	);

	$initial      = strtoupper( mb_substr( $user->display_name ? $user->display_name : $user->user_login, 0, 1 ) );
	$display_name = $user->display_name ? $user->display_name : $user->user_login;

	// Single most urgent onboarding action - deliberately one at a time
	// rather than a checklist of demands, per "focus user to start and
	// enjoy the free services" (downplay everything except the one next step).
	if ( ! $email_verified ) {
		$next_step = array(
			'title' => 'Verify your email address',
			'body'  => 'Confirm your email to secure your account and earn 25 Barakah points.',
			'cta'   => 'Verify Email',
			'href'  => '#mfa-dash-email',
		);
	} elseif ( ! $wa_verified ) {
		$next_step = array(
			'title' => 'Verify your WhatsApp number',
			'body'  => 'Link your WhatsApp so Sofia can reach you directly, and earn 25 Barakah points.',
			'cta'   => 'Verify WhatsApp',
			'href'  => '#mfa-dash-whatsapp',
		);
	} elseif ( ! $profile_complete ) {
		$next_step = array(
			'title' => 'Complete your profile',
			'body'  => 'A few quick details help us personalize your experience - earn 50 Barakah points.',
			'cta'   => 'Complete Profile',
			'modal' => 'mfa-edit-profile-modal',
		);
	} else {
		$next_step = null;
	}

	ob_start();
	?>
	<div class="mfa-dash">

		<section class="mfa-dash-profile-header">
			<span class="mfa-dash-avatar-lg" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
			<div>
				<h1 class="mfa-dash-name"><?php echo esc_html( $display_name ); ?></h1>
				<span class="mfa-dash-pill <?php echo $is_premium ? 'mfa-dash-pill-gold' : ''; ?>">
					<?php echo $is_premium ? 'Founding Member' : 'Free Member'; ?>
				</span>
			</div>
			<div class="mfa-dash-profile-actions">
				<button type="button" class="mfa-dash-link-btn" data-mfa-modal-open="mfa-edit-profile-modal">Edit Profile</button>
				<button type="button" class="mfa-dash-link-btn" data-mfa-modal-open="mfa-change-password-modal">Change Password</button>
			</div>
		</section>

		<?php if ( $next_step ) : ?>
		<section class="mfa-dash-next-step">
			<div>
				<span class="mfa-label" style="color: rgba(255,255,255,0.7);">Your Next Step</span>
				<h2 class="mfa-dash-next-step-title"><?php echo esc_html( $next_step['title'] ); ?></h2>
				<p class="mfa-dash-next-step-body"><?php echo esc_html( $next_step['body'] ); ?></p>
			</div>
			<?php if ( ! empty( $next_step['modal'] ) ) : ?>
				<button type="button" class="mfa-btn mfa-btn-primary" data-mfa-modal-open="<?php echo esc_attr( $next_step['modal'] ); ?>"><?php echo esc_html( $next_step['cta'] ); ?></button>
			<?php else : ?>
				<a href="<?php echo esc_url( $next_step['href'] ); ?>" class="mfa-btn mfa-btn-primary"><?php echo esc_html( $next_step['cta'] ); ?></a>
			<?php endif; ?>
		</section>
		<?php else : ?>
		<section class="mfa-dash-next-step mfa-dash-next-step-done">
			<div>
				<span class="mfa-label" style="color: rgba(255,255,255,0.7);">You're All Set</span>
				<h2 class="mfa-dash-next-step-title">Great work, <?php echo esc_html( $display_name ); ?>!</h2>
				<p class="mfa-dash-next-step-body">You've completed every onboarding step. Explore the tools below or consider becoming a Founding Member.</p>
			</div>
		</section>
		<?php endif; ?>

		<section class="mfa-dash-points-card">
			<div class="mfa-dash-points-total">
				<span class="mfa-dash-points-number"><?php echo esc_html( number_format_i18n( $points ) ); ?></span>
				<span class="mfa-dash-points-label">Barakah Points</span>
				<span class="mfa-dash-rank-badge"><?php echo esc_html( $rank['rank'] ); ?></span>
			</div>
			<?php if ( $rank['next_rank'] ) : ?>
				<p class="mfa-body-muted mfa-dash-points-next"><?php echo esc_html( number_format_i18n( $rank['points_to_next'] ) ); ?> points to <?php echo esc_html( $rank['next_rank'] ); ?></p>
			<?php else : ?>
				<p class="mfa-body-muted mfa-dash-points-next">You've reached the highest rank</p>
			<?php endif; ?>

			<ul class="mfa-dash-checklist">
				<li class="<?php echo $has_joined_award ? 'is-done' : ''; ?>">
					<span class="mfa-dash-check"><?php echo $has_joined_award ? '&#10003;' : ''; ?></span>
					<span>Welcome Bonus</span>
					<span class="mfa-dash-check-pts">+50</span>
				</li>
				<li class="<?php echo $has_email_award ? 'is-done' : ''; ?>">
					<span class="mfa-dash-check"><?php echo $has_email_award ? '&#10003;' : ''; ?></span>
					<span>Verify email</span>
					<span class="mfa-dash-check-pts">+25</span>
				</li>
				<li class="<?php echo $has_wa_award ? 'is-done' : ''; ?>">
					<span class="mfa-dash-check"><?php echo $has_wa_award ? '&#10003;' : ''; ?></span>
					<span>Verify WhatsApp</span>
					<span class="mfa-dash-check-pts">+25</span>
				</li>
				<li class="<?php echo $has_profile_award ? 'is-done' : ''; ?>">
					<span class="mfa-dash-check"><?php echo $has_profile_award ? '&#10003;' : ''; ?></span>
					<span>Complete profile</span>
					<span class="mfa-dash-check-pts">+50</span>
				</li>
			</ul>
		</section>

		<section class="mfa-dash-security-row">
			<div class="mfa-card mfa-dash-security-card" id="mfa-dash-email">
				<h3 class="mfa-h3">Email</h3>
				<?php if ( $email_verified ) : ?>
					<p class="mfa-dash-verified">&#10003; Verified</p>
				<?php else : ?>
					<p class="mfa-body-muted">Not verified yet.</p>
					<button type="button" class="niz-resend-email mfa-btn mfa-btn-primary mfa-dash-btn-sm">Resend Verification Email</button>
					<span id="niz-email-message" class="mfa-dash-inline-msg"></span>
				<?php endif; ?>
			</div>

			<div class="mfa-card mfa-dash-security-card" id="mfa-dash-whatsapp">
				<h3 class="mfa-h3">WhatsApp</h3>
				<?php if ( $wa_verified ) : ?>
					<p class="mfa-dash-verified">&#10003; Verified</p>
				<?php else : ?>
					<p class="mfa-body-muted">Not verified yet.</p>
					<?php
					$wa_link = function_exists( 'niz_wa_generate_verify_link' ) ? niz_wa_generate_verify_link( $user_id ) : null;
					if ( $wa_link && ! is_wp_error( $wa_link ) ) :
						?>
						<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Verify via WhatsApp</a>
					<?php else : ?>
						<p class="mfa-body-muted">WhatsApp verification is temporarily unavailable. Please try again shortly.</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</section>

		<section class="mfa-dash-tools">
			<h2 class="mfa-h2">Explore Masjid4All</h2>
			<div class="mfa-dash-tools-grid">
				<a href="/prayer-times/" class="mfa-card mfa-dash-tool-card">
					<span class="mfa-h3">Prayer Times</span>
					<span class="mfa-body-muted">Accurate prayer times wherever you are.</span>
				</a>
				<a href="/qibla-finder/" class="mfa-card mfa-dash-tool-card">
					<span class="mfa-h3">Qibla Finder</span>
					<span class="mfa-body-muted">Find the direction of the Qibla.</span>
				</a>
				<a href="/quran" class="mfa-card mfa-dash-tool-card">
					<span class="mfa-h3">Daily Quran</span>
					<span class="mfa-body-muted">A new verse and reflection every day.</span>
				</a>
				<a href="/member/business/" class="mfa-card mfa-dash-tool-card">
					<span class="mfa-h3">My Listings <?php if ( $listing_count > 0 ) : ?><span class="mfa-dash-count-badge"><?php echo esc_html( $listing_count ); ?></span><?php endif; ?></span>
					<span class="mfa-body-muted">Manage the businesses and websites you've claimed.</span>
				</a>
			</div>
		</section>

		<?php if ( $is_premium ) : ?>
		<section class="mfa-dash-premium-card mfa-dash-premium-card-active">
			<h3 class="mfa-h3">You're a Founding Member</h3>
			<p class="mfa-body-muted">Thank you for supporting Masjid4All from the start. Your Founding Member benefits are active on your account.</p>
		</section>
		<?php else : ?>
		<section class="mfa-dash-premium-card">
			<h3 class="mfa-h3">Become a Founding Member</h3>
			<p class="mfa-body">One-time RM29.90 - Tier 1 pricing, limited spots left.</p>
			<ul class="mfa-dash-premium-list">
				<li>1,000 Barakah points added instantly</li>
				<li>Get your RM29.90 back as platform credit - spend it on a premium business or website listing, banner ads, and more</li>
				<li>Priority support and early access to new features</li>
			</ul>
			<a href="/member/premium/" class="mfa-btn mfa-btn-primary">See Founding Member Details</a>
		</section>
		<?php endif; ?>

		<section class="mfa-dash-summary">
			<div>
				<span class="mfa-dash-summary-number"><?php echo esc_html( number_format_i18n( $points ) ); ?></span>
				<span class="mfa-label">Points</span>
			</div>
			<div>
				<span class="mfa-dash-summary-number"><?php echo esc_html( $rank['rank'] ); ?></span>
				<span class="mfa-label">Rank</span>
			</div>
			<div>
				<span class="mfa-dash-summary-number"><?php echo esc_html( $listing_count ); ?></span>
				<span class="mfa-label">Listings</span>
			</div>
		</section>

		<?php echo do_shortcode( '[mfa_member_account_modals]' ); ?>

	</div>
	<?php
	return ob_get_clean();
}
