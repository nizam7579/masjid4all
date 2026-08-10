<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_account_modals] - native "Edit Profile" / "Change Password"
 * popups for the member dashboard (see member-dashboard.php's trigger
 * buttons), replacing the earlier Fluent Form embed per the 2026-08-08
 * decision to avoid 3rd-party form-plugin dependency here. Same overlay/
 * aria-hidden modal pattern as [mfa_sofia_button] (this repo's proven
 * custom-modal template - see CLAUDE.md's Design/Frontend Work section) -
 * plain HTML + a small AJAX handler, no Kadence Blocks Pro modal either.
 */
/**
 * Standard country list for the Edit Profile "Country" field - a select
 * instead of free text, so the value is always consistent/usable data
 * rather than however each user happens to type it.
 */
function mfa_get_country_list() {
	return array(
		'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
		'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan',
		'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon',
		'Canada', 'Cape Verde', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica',
		'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'East Timor', 'Ecuador',
		'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France',
		'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau',
		'Guyana', 'Haiti', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq',
		'Ireland', 'Israel', 'Italy', 'Ivory Coast', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati',
		'Kosovo', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein',
		'Lithuania', 'Luxembourg', 'Macau', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands',
		'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique',
		'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea',
		'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru',
		'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
		'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia',
		'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname',
		'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Tonga', 'Trinidad and Tobago',
		'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay',
		'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
	);
}

add_shortcode( 'mfa_member_account_modals', 'mfa_member_account_modals_shortcode' );
function mfa_member_account_modals_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$user_id = get_current_user_id();
	$user    = wp_get_current_user();

	$name      = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'name' ) : '';
	$sex       = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'sex' ) : '';
	$birthdate = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'birthdate' ) : '';
	$country   = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'country' ) : '';

	if ( empty( $name ) ) {
		$name = $user->display_name;
	}

	global $wpdb;
	$barakah_history = $wpdb->get_results(
		$wpdb->prepare( "SELECT description, points, cct_created FROM {$wpdb->prefix}jet_cct_barakah WHERE user_id = %d ORDER BY cct_created DESC LIMIT 50", $user_id )
	);

	ob_start();
	?>
	<div class="mfa-modal-overlay" data-mfa-modal-overlay></div>

	<div class="mfa-modal" id="mfa-edit-profile-modal" role="dialog" aria-modal="true" aria-label="Edit Profile" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">Edit Profile</h3>
		<form id="mfa-edit-profile-form" class="mfa-modal-form">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_update_profile' ) ); ?>">
			<div class="mfa-form-group">
				<label for="mfa-profile-name">Name</label>
				<input type="text" id="mfa-profile-name" name="name" value="<?php echo esc_attr( $name ); ?>" required>
			</div>
			<div class="mfa-form-row">
				<div class="mfa-form-group">
					<label for="mfa-profile-sex">Sex</label>
					<select id="mfa-profile-sex" name="sex">
						<option value="" <?php selected( $sex, '' ); ?>>Select</option>
						<option value="Male" <?php selected( $sex, 'Male' ); ?>>Male</option>
						<option value="Female" <?php selected( $sex, 'Female' ); ?>>Female</option>
					</select>
				</div>
				<div class="mfa-form-group">
					<label for="mfa-profile-birthdate">Birthdate</label>
					<input type="date" id="mfa-profile-birthdate" name="birthdate" value="<?php echo esc_attr( $birthdate ); ?>">
				</div>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-profile-country">Country</label>
				<select id="mfa-profile-country" name="country">
					<option value="" <?php selected( $country, '' ); ?>>Select</option>
					<?php foreach ( mfa_get_country_list() as $country_option ) : ?>
						<option value="<?php echo esc_attr( $country_option ); ?>" <?php selected( $country, $country_option ); ?>><?php echo esc_html( $country_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Save Changes</button>
			<p class="mfa-modal-message" data-mfa-form-message></p>
		</form>
	</div>

	<div class="mfa-modal" id="mfa-change-password-modal" role="dialog" aria-modal="true" aria-label="Change Password" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">Change Password</h3>
		<form id="mfa-change-password-form" class="mfa-modal-form">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_change_password' ) ); ?>">
			<div class="mfa-form-group">
				<label for="mfa-current-password">Current Password</label>
				<input type="password" id="mfa-current-password" name="current_password" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-new-password">New Password</label>
				<input type="password" id="mfa-new-password" name="new_password" minlength="8" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-confirm-password">Confirm New Password</label>
				<input type="password" id="mfa-confirm-password" name="confirm_password" minlength="8" required>
			</div>
			<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Update Password</button>
			<p class="mfa-modal-message" data-mfa-form-message></p>
		</form>
	</div>

	<div class="mfa-modal" id="mfa-update-email-modal" role="dialog" aria-modal="true" aria-label="Update Email Address" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">Update Email Address</h3>
		<p class="mfa-body-muted">Entered the wrong email when you registered? Update it here - we'll send a fresh verification link to the new address.</p>
		<form id="mfa-update-email-form" class="mfa-modal-form">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_update_email' ) ); ?>">
			<div class="mfa-form-group">
				<label for="mfa-new-email">Correct Email Address</label>
				<input type="email" id="mfa-new-email" name="email" placeholder="Enter your correct email address" required>
			</div>
			<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Update Email</button>
			<p class="mfa-modal-message" data-mfa-form-message></p>
		</form>
	</div>

	<div class="mfa-modal" id="mfa-share-modal" role="dialog" aria-modal="true" aria-label="Share Masjid4All" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">Invite a Friend</h3>
		<p class="mfa-body-muted">Share your personal referral link. Anyone who joins Masjid4All through it is linked to you, and you'll earn Barakah points when they register.</p>
		<?php
		// The ?id={user_id} -> affiliateid cookie -> referrer_id capture
		// mechanism already runs sitewide (enaizi-mfa's niz_mfa_location_init(),
		// hooked on init) - this was previously only exposed generically via
		// the floating share button (share-button.php), which builds the same
		// kind of link client-side for "share the current page". This is the
		// first place a member's OWN link is shown to them explicitly.
		$referral_link = home_url( '/?id=' . $user_id );
		$share_text    = "Assalamualaikum! I'd like to invite you to join Masjid4All, a Muslim community platform with mosque directories, prayer times, and more: " . $referral_link;
		$share_url     = 'https://wa.me/?text=' . rawurlencode( $share_text );
		?>
		<div class="mfa-form-group">
			<label for="mfa-referral-link">Your Referral Link</label>
			<div class="mfa-referral-link-row">
				<input type="text" id="mfa-referral-link" class="mfa-referral-link-input" value="<?php echo esc_attr( $referral_link ); ?>" readonly>
				<button type="button" class="mfa-btn mfa-btn-primary mfa-dash-btn-sm" data-mfa-copy-link="<?php echo esc_attr( $referral_link ); ?>">Copy</button>
			</div>
		</div>
		<a href="<?php echo esc_url( $share_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Share on WhatsApp</a>
	</div>

	<div class="mfa-modal mfa-modal-lg" id="mfa-barakah-history-modal" role="dialog" aria-modal="true" aria-label="My Barakah Points" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">My Barakah Points</h3>
		<?php if ( $barakah_history ) : ?>
			<ul class="mfa-barakah-history-list">
				<?php foreach ( $barakah_history as $entry ) : ?>
					<li>
						<div>
							<span class="mfa-barakah-history-desc"><?php echo esc_html( $entry->description ); ?></span>
							<span class="mfa-barakah-history-date"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $entry->cct_created ) ) ); ?></span>
						</div>
						<span class="mfa-barakah-history-pts">+<?php echo esc_html( number_format_i18n( (int) $entry->points ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="mfa-body-muted">No Barakah points yet - complete your onboarding steps to start earning.</p>
		<?php endif; ?>
	</div>

	<div class="mfa-modal" id="mfa-barakah-info-modal" role="dialog" aria-modal="true" aria-label="About Barakah Points" aria-hidden="true">
		<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
		<h3 class="mfa-h3">About Barakah Points</h3>
		<p class="mfa-body-muted">Barakah Points are Masjid4All's community rewards program - recognizing members who help build a trusted platform for Muslims everywhere.</p>
		<p class="mfa-body-muted"><strong>Ways to earn points today:</strong></p>
		<ul class="mfa-barakah-info-list">
			<li>Join Masjid4All - <strong>+50</strong> Welcome Bonus</li>
			<li>Verify your email address - <strong>+25</strong></li>
			<li>Complete your profile - <strong>+50</strong></li>
			<li>Verify your WhatsApp number - <strong>+25</strong></li>
		</ul>
		<p class="mfa-body-muted">More ways to earn - like sharing with friends and adding mosques, businesses, and websites - are coming soon.</p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Updates the caller's own jet_cct_member profile fields. Replaces the
 * Fluent Form 17 handler that used to do this for the dashboard's
 * "Complete Profile" step (see mfa-core.php's own docblock for the wider
 * consolidation rule) - awards the same 50-point bonus here instead.
 */
add_action( 'wp_ajax_mfa_update_profile', 'mfa_ajax_update_profile' );
function mfa_ajax_update_profile() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	check_ajax_referer( 'mfa_update_profile', 'nonce' );

	$user_id = get_current_user_id();

	$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$sex       = isset( $_POST['sex'] ) ? sanitize_text_field( wp_unslash( $_POST['sex'] ) ) : '';
	$birthdate = isset( $_POST['birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['birthdate'] ) ) : '';
	$country   = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

	if ( empty( $name ) ) {
		wp_send_json_error( array( 'message' => 'Please provide your name.' ) );
	}

	if ( function_exists( 'niz_user_member_cct' ) ) {
		// Ensures the jet_cct_member row exists before updating it - a user
		// who registered via Google/WhatsApp only may not have one yet.
		niz_user_member_cct( $user_id );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'name', $name );
		niz_user_update_field( $user_id, 'sex', $sex );
		niz_user_update_field( $user_id, 'birthdate', $birthdate );
		niz_user_update_field( $user_id, 'country', $country );
	}

	if ( function_exists( 'mfa_award_points' ) ) {
		mfa_award_points( $user_id, 'Complete Profile', 50 );
	}

	wp_send_json_success( array( 'message' => 'Profile updated successfully.' ) );
}

/**
 * Lets a logged-in user change their own password. Requires their current
 * password (standard practice, not a token/reset flow). wp_set_password()
 * wipes all existing session tokens as a side effect (including the
 * current one) - wp_set_auth_cookie() re-establishes a fresh session for
 * this browser only, matching how WordPress core's own user-edit.php
 * handles a user changing their own password.
 */
add_action( 'wp_ajax_mfa_change_password', 'mfa_ajax_change_password' );
function mfa_ajax_change_password() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	check_ajax_referer( 'mfa_change_password', 'nonce' );

	$user              = wp_get_current_user();
	$current_password  = isset( $_POST['current_password'] ) ? (string) $_POST['current_password'] : '';
	$new_password      = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
	$confirm_password  = isset( $_POST['confirm_password'] ) ? (string) $_POST['confirm_password'] : '';

	if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
		wp_send_json_error( array( 'message' => 'Current password is incorrect.' ) );
	}

	if ( strlen( $new_password ) < 8 ) {
		wp_send_json_error( array( 'message' => 'New password must be at least 8 characters.' ) );
	}

	if ( $new_password !== $confirm_password ) {
		wp_send_json_error( array( 'message' => 'New passwords do not match.' ) );
	}

	wp_set_password( $new_password, $user->ID );
	wp_set_auth_cookie( $user->ID );

	wp_send_json_success( array( 'message' => 'Password updated successfully.' ) );
}

/**
 * Lets a user correct their email if they registered with a wrong/dummy
 * address they can't verify - the only way to recover, since email
 * verification is a mandatory gate (2026-08-08 decision) before Edit
 * Profile / WhatsApp verification unlock. This updates the REAL account
 * email (wp_users.user_email, what login and verification both check) -
 * unlike the old Edit Profile "Email" field, which only ever wrote to a
 * secondary jet_cct_member column and silently did nothing useful (found
 * and removed as part of this same change). Resets verification status
 * and sends a fresh link to the new address.
 */
add_action( 'wp_ajax_mfa_update_email', 'mfa_ajax_update_email' );
function mfa_ajax_update_email() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	check_ajax_referer( 'mfa_update_email', 'nonce' );

	$user_id   = get_current_user_id();
	$new_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( empty( $new_email ) || ! is_email( $new_email ) ) {
		wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
	}

	$existing_owner = email_exists( $new_email );
	if ( $existing_owner && (int) $existing_owner !== $user_id ) {
		wp_send_json_error( array( 'message' => 'This email is already registered to another account.' ) );
	}

	$result = wp_update_user( array(
		'ID'         => $user_id,
		'user_email' => $new_email,
	) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'email', $new_email );
	}

	update_user_meta( $user_id, 'niz_email_verified', 'No' );

	if ( class_exists( 'Niz_Email_Verification' ) ) {
		$token = Niz_Email_Verification::generate_token( $user_id );
		Niz_Email_Verification::send_email( $user_id, $token );
	}

	wp_send_json_success( array( 'message' => 'Email updated. A new verification link has been sent to your new address.' ) );
}

/**
 * Lets the current user opt into the affiliate program - sets chk_affiliate
 * on their jet_cct_member row (an existing but previously never-written
 * field, found 2026-08-09 - the old legacy dashboard only ever read it)
 * and awards the one-time Barakah bonus. Triggered from a plain button
 * (data-mfa-join-affiliate, not a modal form) on both the dashboard's
 * Affiliate Program card and /member/affiliate/ itself - see
 * member-account-modals-v1.js for the click handler shared by both.
 * Referral capture and per-referral points already exist separately
 * (identity-registration.php) - this is just the opt-in step and its
 * bonus. Commission/payout is a later phase once Founding Member + a
 * payment gateway exist - not built here.
 */
add_action( 'wp_ajax_mfa_join_affiliate', 'mfa_ajax_join_affiliate' );
function mfa_ajax_join_affiliate() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	check_ajax_referer( 'mfa_join_affiliate', 'nonce' );

	$user_id = get_current_user_id();

	if ( function_exists( 'niz_user_member_cct' ) ) {
		// Ensures the jet_cct_member row exists before updating it - a user
		// who registered via Google/WhatsApp only may not have one yet.
		niz_user_member_cct( $user_id );
	}

	$already = function_exists( 'niz_user_field_by_userid' ) ? niz_user_field_by_userid( $user_id, 'chk_affiliate' ) : '';
	if ( 'Yes' === $already ) {
		wp_send_json_success( array( 'message' => 'Already joined.' ) );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'chk_affiliate', 'Yes' );
	}

	if ( function_exists( 'mfa_award_points' ) ) {
		mfa_award_points( $user_id, 'Join Affiliate Program', 100 );
	}

	wp_send_json_success( array( 'message' => "You're in! 100 Barakah points added." ) );
}
