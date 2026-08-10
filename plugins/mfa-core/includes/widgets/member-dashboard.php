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
 * listing ownership, and the live Stripe Founding Member checkout.
 *
 * 2026-08-09 layout pass: profile header now shows email/WhatsApp number
 * and a real cct status label; the "next step" banner and the email/
 * WhatsApp/profile cards became a 2-column row (was 2 stacked full-width
 * sections); Barakah points card gained a "Help us build Masjid4All"
 * 2x2 stat-card grid alongside it. Share/business/website counts there
 * are real queries - mosque submissions have no ownership tracking yet
 * (add-mosque.php never records who submitted a mosque), so that one
 * card is a literal 0 placeholder until that gap gets closed. The
 * "Start Share Now" link is a generic wa.me invite text, not a tracked
 * referral link - Phase 4 (real referral links, commission ledger)
 * still hasn't started.
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
	$wa_number       = get_user_meta( $user_id, 'user_phone', true );

	$country          = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'country' ) : '';
	$profile_complete = ! empty( $country );

	$is_premium   = function_exists( 'niz_user_field_by_userid' ) && 'Yes' === niz_user_field_by_userid( $user_id, 'chk_premium' );
	$has_affiliate = function_exists( 'niz_user_field_by_userid' ) && 'Yes' === niz_user_field_by_userid( $user_id, 'chk_affiliate' );
	$cct_status   = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'status' ) : '';
	$status_label = $cct_status ? $cct_status : 'Free Member';

	$points = function_exists( 'mfa_get_barakah_points' ) ? mfa_get_barakah_points( $user_id ) : 0;
	$rank   = function_exists( 'mfa_get_barakah_rank' ) ? mfa_get_barakah_rank( $points ) : array( 'rank' => 'Bronze', 'next_rank' => 'Silver', 'next_at' => 500, 'points_to_next' => 500 );

	$has_joined_award  = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Welcome Bonus' );
	$has_email_award   = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Verify Email' );
	$has_wa_award      = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Verify WhatsApp' );
	$has_profile_award = function_exists( 'mfa_has_barakah_award' ) && mfa_has_barakah_award( $user_id, 'Complete Profile' );

	$namecard_slug = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'namecard' ) : '';

	global $wpdb;
	$listing_count = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_listing_owner WHERE user_id = %d", $user_id )
	);
	// "Businesses/Websites you've added" (earn-grid below): distinct from
	// $listing_count above - that's listings the user CLAIMED ownership of
	// (jet_cct_listing_owner), this is listings the user actually SUBMITTED
	// via /add-business//add-website/ (wp_posts.post_author, set at
	// wp_insert_post() time in enaizi-mfa/shortcodes/add-business.php and
	// add-website.php) and that have since been approved (listing_status -
	// same 'Approved' check the Barakah-point-award code already uses in
	// business.php/web.php). Found 2026-08-09: this previously queried
	// jet_cct_listing_owner too, wrongly counting claims as additions.
	$business_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_business b
			JOIN {$wpdb->posts} p ON b.cct_single_post_id = p.ID
			WHERE p.post_author = %d AND b.listing_status = 'Approved'",
			$user_id
		)
	);
	$website_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_web w
			JOIN {$wpdb->posts} p ON w.cct_single_post_id = p.ID
			WHERE p.post_author = %d AND w.listing_status = 'Approved'",
			$user_id
		)
	);
	// "My Business" section (below): real data, combines both business and
	// website listings the user owns - the /member/business/?id= single-item
	// view it links to doesn't exist yet (Phase 4), same as the plain
	// /member/business/ link on the "My Listings" tool card below.
	$my_listings = $wpdb->get_results(
		$wpdb->prepare( "SELECT post_id, post_type FROM {$wpdb->prefix}jet_cct_listing_owner WHERE user_id = %d ORDER BY cct_created DESC", $user_id )
	);
	// "My Community" section (below): wp_jet_cct_community rows created by
	// the [mosque_community] join form (enaizi-mfa/shortcodes/community.php)
	// - found 2026-08-09 to already exist and work, it was just gated
	// admin-only by mistake on the mosque single template (fixed separately
	// in post 875's content). cct_single_post_id resolves the CCT mosque
	// row back to its WP post ID, same as $my_listings uses get_the_title()
	// below rather than trusting the CCT-mirrored name field. Capped at 10
	// for display; the join flow itself doesn't yet enforce a 10-mosque-
	// per-user limit.
	$joined_communities = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.cct_single_post_id AS id
			FROM {$wpdb->prefix}jet_cct_community c
			JOIN {$wpdb->prefix}jet_cct_mosque m ON c.mosque_id = m._ID
			WHERE c.user_id = %d AND m.cct_single_post_id > 0
			ORDER BY c.cct_created DESC
			LIMIT 10",
			$user_id
		),
		ARRAY_A
	);
	// referrer_id on jet_cct_member stores the referring member's WP user_id
	// (confirmed against live data), so this is a real "how many people
	// registered under you" count even without a referral-link UI yet.
	$referral_count = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_member WHERE referrer_id = %d", $user_id )
	);
	// No ownership tracking exists yet for mosque submissions - add-mosque.php
	// inserts the masjid CPT without recording a user_id anywhere. Placeholder
	// until that gap is closed.
	$mosque_count = 0;

	$barakah_history = $wpdb->get_results(
		$wpdb->prepare( "SELECT description, points, cct_created FROM {$wpdb->prefix}jet_cct_barakah WHERE user_id = %d ORDER BY cct_created DESC LIMIT 50", $user_id )
	);

	$initial      = strtoupper( mb_substr( $user->display_name ? $user->display_name : $user->user_login, 0, 1 ) );
	$display_name = $user->display_name ? $user->display_name : $user->user_login;

	// Email verification is a mandatory gate (2026-08-08 decision): until
	// it's done, it's the only active next step - Edit Profile and WhatsApp
	// verification are locked below, so every member ends up with a
	// verified email. Order after that: profile -> WhatsApp.
	if ( ! $email_verified ) {
		$next_step = array(
			'intro' => "Congratulations! You've earned 50 Barakah points for joining Masjid4All as a member.",
			'title' => 'Verify your email address',
			'body'  => 'Confirm your email to secure your account and earn 25 Barakah points.',
			'cta'   => 'Verify Email',
			'href'  => '#mfa-dash-email',
		);
	} elseif ( ! $profile_complete ) {
		$next_step = array(
			'title' => 'Update your profile',
			'body'  => 'Complete your profile to earn another 50 Barakah points.',
			'cta'   => 'Update Profile',
			'modal' => 'mfa-edit-profile-modal',
		);
	} elseif ( ! $wa_verified ) {
		$next_step = array(
			'title' => 'Verify your WhatsApp number',
			'body'  => "Confirm your WhatsApp and earn 25 Barakah points - we'll use the number you verify with, no need to type it in separately.",
			'cta'   => 'Verify WhatsApp',
			'href'  => '#mfa-dash-whatsapp',
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
				<p class="mfa-dash-contact-line"><?php echo esc_html( $user->user_email ); ?></p>
				<?php if ( $wa_verified && $wa_number ) : ?>
					<p class="mfa-dash-contact-line">+<?php echo esc_html( $wa_number ); ?></p>
				<?php endif; ?>
				<span class="mfa-dash-pill <?php echo $is_premium ? 'mfa-dash-pill-gold' : ''; ?>">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</div>
			<div class="mfa-dash-profile-actions">
				<button type="button" class="mfa-dash-link-btn" data-mfa-modal-open="mfa-change-password-modal">Change Password</button>
			</div>
		</section>

		<section class="mfa-dash-next-step-row">
			<?php if ( $next_step ) : ?>
			<div class="mfa-dash-next-step">
				<div>
					<?php if ( ! empty( $next_step['intro'] ) ) : ?>
						<p class="mfa-dash-next-step-intro"><?php echo esc_html( $next_step['intro'] ); ?></p>
					<?php endif; ?>
					<span class="mfa-label" style="color: rgba(255,255,255,0.7);">Your Next Step</span>
					<h2 class="mfa-dash-next-step-title"><?php echo esc_html( $next_step['title'] ); ?></h2>
					<p class="mfa-dash-next-step-body"><?php echo esc_html( $next_step['body'] ); ?></p>
				</div>
				<?php if ( ! empty( $next_step['modal'] ) ) : ?>
					<button type="button" class="mfa-btn mfa-btn-primary" data-mfa-modal-open="<?php echo esc_attr( $next_step['modal'] ); ?>"><?php echo esc_html( $next_step['cta'] ); ?></button>
				<?php else : ?>
					<a href="<?php echo esc_url( $next_step['href'] ); ?>" class="mfa-btn mfa-btn-primary"><?php echo esc_html( $next_step['cta'] ); ?></a>
				<?php endif; ?>
			</div>
			<?php else : ?>
			<div class="mfa-dash-next-step mfa-dash-next-step-done">
				<div>
					<span class="mfa-label" style="color: rgba(255,255,255,0.7);">You're All Set</span>
					<h2 class="mfa-dash-next-step-title">Great work, <?php echo esc_html( $display_name ); ?>!</h2>
					<p class="mfa-dash-next-step-body">You've completed every onboarding step. Explore the tools below or consider becoming a Founding Member.</p>
				</div>
			</div>
			<?php endif; ?>

			<div class="mfa-dash-action-cards">
				<div class="mfa-card mfa-dash-security-card" id="mfa-dash-email">
					<h3 class="mfa-h3">Email</h3>
					<?php if ( $email_verified ) : ?>
						<div class="mfa-dash-card-row">
							<span class="mfa-dash-verified">&#10003; Verified</span>
							<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-modal-open="mfa-update-email-modal">Change Email</button>
						</div>
						<p class="mfa-dash-card-note">Changing your email will require verifying it again.</p>
					<?php else : ?>
						<div class="mfa-dash-card-row">
							<span class="mfa-body-muted">Not verified yet</span>
							<button type="button" class="niz-resend-email mfa-btn mfa-btn-primary mfa-dash-btn-sm">Resend Email</button>
						</div>
						<span id="niz-email-message" class="mfa-dash-inline-msg"></span>
						<button type="button" class="mfa-dash-link-btn mfa-dash-change-email-btn" data-mfa-modal-open="mfa-update-email-modal">Wrong email? Update it</button>
					<?php endif; ?>
				</div>

				<div class="mfa-card mfa-dash-security-card" id="mfa-dash-whatsapp">
					<h3 class="mfa-h3">WhatsApp</h3>
					<?php if ( $wa_verified ) : ?>
						<div class="mfa-dash-card-row">
							<span class="mfa-dash-verified">&#10003; Verified</span>
						</div>
					<?php elseif ( ! $email_verified ) : ?>
						<p class="mfa-body-muted mfa-dash-locked">Verify your email first to unlock WhatsApp verification.</p>
					<?php else : ?>
						<?php
						$wa_link = function_exists( 'niz_wa_generate_verify_link' ) ? niz_wa_generate_verify_link( $user_id ) : null;
						if ( $wa_link && ! is_wp_error( $wa_link ) ) :
							?>
							<div class="mfa-dash-card-row">
								<span class="mfa-body-muted">Not verified yet</span>
								<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Verify WhatsApp</a>
							</div>
						<?php else : ?>
							<p class="mfa-body-muted">WhatsApp verification is temporarily unavailable. Please try again shortly.</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="mfa-card mfa-dash-security-card" id="mfa-dash-profile">
					<h3 class="mfa-h3">Profile</h3>
					<?php if ( ! $email_verified ) : ?>
						<p class="mfa-body-muted mfa-dash-locked">Verify your email first to unlock your profile.</p>
					<?php else : ?>
						<div class="mfa-dash-card-row">
							<span class="<?php echo $profile_complete ? 'mfa-dash-verified' : 'mfa-body-muted'; ?>"><?php echo $profile_complete ? '&#10003; Completed' : 'Not completed yet'; ?></span>
							<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-modal-open="mfa-edit-profile-modal">Update Profile</button>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="mfa-dash-namecard-section mfa-dash-section-divided">
			<div class="mfa-card mfa-dash-namecard-card">
				<h3 class="mfa-h3">Digital Name Card</h3>
				<?php if ( empty( $namecard_slug ) ) : ?>
					<p class="mfa-body-muted">Create a shareable digital business card with your own link and QR code.</p>
					<a href="/member/digital-card/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Create My Digital Name Card</a>
				<?php else : ?>
					<?php $namecard_url = function_exists( 'mfa_get_member_namecard_url' ) ? mfa_get_member_namecard_url( $user_id ) : home_url( '/' . $namecard_slug . '/' ); ?>
					<p class="mfa-body-muted">Your card is live at <?php echo esc_html( $namecard_url ); ?></p>
					<div class="mfa-dash-btn-group">
						<a href="/member/digital-card/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Update Name Card</a>
						<a href="<?php echo esc_url( $namecard_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">View Name Card</a>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="mfa-dash-points-section mfa-dash-section-divided">
			<h2 class="mfa-h2 mfa-dash-section-title-centered">Help us build Masjid4All and earn Barakah points</h2>
			<div class="mfa-dash-points-row">
			<div class="mfa-dash-points-card">
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
					<li class="<?php echo $has_profile_award ? 'is-done' : ''; ?>">
						<span class="mfa-dash-check"><?php echo $has_profile_award ? '&#10003;' : ''; ?></span>
						<span>Complete profile</span>
						<span class="mfa-dash-check-pts">+50</span>
					</li>
					<li class="<?php echo $has_wa_award ? 'is-done' : ''; ?>">
						<span class="mfa-dash-check"><?php echo $has_wa_award ? '&#10003;' : ''; ?></span>
						<span>Verify WhatsApp</span>
						<span class="mfa-dash-check-pts">+25</span>
					</li>
				</ul>

				<div class="mfa-dash-points-actions">
					<button type="button" class="mfa-dash-link-btn" data-mfa-modal-open="mfa-barakah-history-modal">View My Barakah Points</button>
					<button type="button" class="mfa-dash-link-btn" data-mfa-modal-open="mfa-barakah-info-modal">About Barakah Points</button>
				</div>
			</div>

			<div class="mfa-dash-earn-card">
				<div class="mfa-dash-earn-grid">
					<div class="mfa-card mfa-dash-earn-item">
						<span class="mfa-dash-earn-count"><?php echo esc_html( number_format_i18n( $referral_count ) ); ?></span>
						<p class="mfa-body-muted">Members who joined under you</p>
						<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-modal-open="mfa-share-modal">Start Share Now</button>
					</div>
					<div class="mfa-card mfa-dash-earn-item">
						<span class="mfa-dash-earn-count"><?php echo esc_html( number_format_i18n( $mosque_count ) ); ?></span>
						<p class="mfa-body-muted">Mosques you've added</p>
						<a href="/add-mosque/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Add Mosque</a>
					</div>
					<div class="mfa-card mfa-dash-earn-item">
						<span class="mfa-dash-earn-count"><?php echo esc_html( number_format_i18n( $business_count ) ); ?></span>
						<p class="mfa-body-muted">Businesses you've added</p>
						<a href="/add-business/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Add Business</a>
					</div>
					<div class="mfa-card mfa-dash-earn-item">
						<span class="mfa-dash-earn-count"><?php echo esc_html( number_format_i18n( $website_count ) ); ?></span>
						<p class="mfa-body-muted">Websites you've added</p>
						<a href="/add-website/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Add Website</a>
					</div>
				</div>
			</div>
			</div>
		</section>

		<section class="mfa-dash-points-section mfa-dash-section-divided">
			<h2 class="mfa-h2 mfa-dash-section-title-centered">My Community &amp; Business</h2>
			<div class="mfa-dash-points-row">
				<div class="mfa-dash-earn-card">
					<h3 class="mfa-h3 mfa-dash-col-title">My Community</h3>
					<p class="mfa-body-muted mfa-dash-col-intro">Join mosque communities to connect, share updates, and stay involved - up to 10 at a time.</p>
					<?php if ( $joined_communities ) : ?>
						<ul class="mfa-dash-simple-list">
							<?php foreach ( $joined_communities as $community ) : ?>
								<li>
									<span><?php echo esc_html( get_the_title( $community['id'] ) ); ?></span>
									<a href="<?php echo esc_url( home_url( '/member/community/?id=' . $community['id'] ) ); ?>" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">View</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="mfa-body-muted mfa-dash-locked">You haven't joined any mosque communities yet.</p>
						<a href="/masjid/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Browse Mosques</a>
					<?php endif; ?>
				</div>

				<div class="mfa-dash-earn-card">
					<h3 class="mfa-h3 mfa-dash-col-title">My Business</h3>
					<p class="mfa-body-muted mfa-dash-col-intro">Businesses and websites you've claimed appear here.</p>
					<?php if ( $my_listings ) : ?>
						<ul class="mfa-dash-simple-list">
							<?php foreach ( $my_listings as $listing ) : ?>
								<li>
									<span class="mfa-dash-listing-name">
										<span class="mfa-dash-type-pill mfa-dash-type-pill-<?php echo esc_attr( $listing->post_type ); ?>"><?php echo esc_html( 'business' === $listing->post_type ? 'Business' : 'Website' ); ?></span>
										<?php echo esc_html( get_the_title( $listing->post_id ) ); ?>
									</span>
									<a href="<?php echo esc_url( home_url( '/member/business/?id=' . $listing->post_id ) ); ?>" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">View</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="mfa-body-muted mfa-dash-locked">You haven't claimed any business or website listings yet.</p>
						<div class="mfa-dash-btn-group">
							<a href="/business/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Business Directory</a>
							<a href="/web/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">Website Directory</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="mfa-dash-tools mfa-dash-section-divided">
			<h2 class="mfa-h2 mfa-dash-section-title-centered">Explore Masjid4All</h2>
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
				<a href="/knowledge-hub/" class="mfa-card mfa-dash-tool-card">
					<span class="mfa-h3">Knowledge Hub</span>
					<span class="mfa-body-muted">Islamic knowledge and resources.</span>
				</a>
			</div>
		</section>

		<section class="mfa-dash-points-section mfa-dash-section-divided">
			<h2 class="mfa-h2 mfa-dash-section-title-centered">Grow With Masjid4All</h2>
			<div class="mfa-dash-points-row">
				<div class="mfa-dash-earn-card">
					<h3 class="mfa-h3 mfa-dash-col-title">Affiliate Program</h3>
					<?php if ( $has_affiliate ) : ?>
						<p class="mfa-body-muted mfa-dash-col-intro"><?php echo esc_html( number_format_i18n( $referral_count ) ); ?> member<?php echo 1 === $referral_count ? '' : 's'; ?> joined through you so far.</p>
						<a href="/member/affiliate/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">View My Affiliates</a>
					<?php else : ?>
						<p class="mfa-body-muted mfa-dash-col-intro">Earn 100 Barakah points and start building your downline by inviting mosques, businesses, and members to join Masjid4All.</p>
						<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-join-affiliate="<?php echo esc_attr( wp_create_nonce( 'mfa_join_affiliate' ) ); ?>">Join Masjid4All Affiliate Program</button>
					<?php endif; ?>
				</div>

				<?php if ( $is_premium ) : ?>
				<div class="mfa-dash-earn-card mfa-dash-premium-card-active">
					<h3 class="mfa-h3 mfa-dash-col-title">You're a Founding Member</h3>
					<p class="mfa-body-muted mfa-dash-col-intro">Thank you for supporting Masjid4All from the start. Your Founding Member benefits are active on your account.</p>
				</div>
				<?php else : ?>
				<div class="mfa-dash-earn-card">
					<h3 class="mfa-h3 mfa-dash-col-title">Become a Founding Member</h3>
					<p class="mfa-body-muted mfa-dash-col-intro">One-time RM29.90 - Tier 1 pricing, limited spots left.</p>
					<ul class="mfa-dash-premium-list">
						<li>1,000 Barakah points added instantly</li>
						<li>Get your RM29.90 back as platform credit - spend it on a premium business or website listing, banner ads, and more</li>
						<li>Priority support and early access to new features</li>
					</ul>
					<a href="/member/premium/" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm">See Founding Member Details</a>
				</div>
				<?php endif; ?>
			</div>
		</section>

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
