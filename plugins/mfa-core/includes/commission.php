<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Commission-accrual ledger for affiliate referrals (2026-08-10) - the
 * "real monetary commission" half of the affiliate program the old
 * (never-launched) /member/affiliate/ page promised ("5% standard / 20%
 * Founding Member") but that this project's Founding Member rebuild
 * deliberately left out, pending Founding Member + a payment gateway
 * existing first (see member-affiliate-single.php's docblock). Both now
 * exist (founding-member.php, live Stripe checkout), so this closes that
 * gap. v1 scope: accrual tracking only - no payout mechanism yet, per
 * explicit direction. Plain custom table (dbDelta), not a JetEngine CCT,
 * per the project's standing rule for new data-storage needs.
 *
 * Separate from and additional to the existing Barakah referral bonus
 * (niz_user_award_referrer_points() in identity-registration.php, fires
 * once at registration time regardless of any purchase) - this fires only
 * when a referred member actually pays for something, currently just the
 * Founding Member one-time purchase.
 */

function mfa_commission_table() {
	global $wpdb;
	return $wpdb->prefix . 'mfa_commission_ledger';
}

define( 'MFA_COMMISSION_TABLE_VERSION', '1.0' );

add_action( 'plugins_loaded', 'mfa_commission_maybe_create_table' );
function mfa_commission_maybe_create_table() {
	if ( get_option( 'mfa_commission_table_version' ) === MFA_COMMISSION_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table = mfa_commission_table();
	$cc    = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		affiliate_user_id BIGINT UNSIGNED NOT NULL,
		referred_user_id BIGINT UNSIGNED NOT NULL,
		payment_id BIGINT UNSIGNED NOT NULL,
		rate_pct DECIMAL(5,2) NOT NULL,
		amount DECIMAL(10,2) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'accrued',
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY payment_id (payment_id),
		KEY affiliate_user_id (affiliate_user_id)
	) {$cc};" );

	update_option( 'mfa_commission_table_version', MFA_COMMISSION_TABLE_VERSION );
}

/**
 * Called once per completed payment from mfa_grant_founding_member_benefits()
 * (founding-member.php), after that function's own idempotency check has
 * already confirmed this is a genuinely new payment - so no separate dedup
 * needed here beyond the UNIQUE KEY on payment_id as a defensive backstop.
 *
 * Rate is decided by the REFERRER's own status at accrual time (5% standard,
 * 20% if the referrer is themselves a Founding Member) - matches the old
 * promotional copy. $reference_amount is the same normalized USD-equivalent
 * value (not the raw local currency charge) that mfa_award_points()/
 * niz_user_add_credit() already use for this same payment, for consistency.
 *
 * @param int   $referred_user_id The person who just paid.
 * @param int   $payment_id       wp_jet_cct_payment._ID for this purchase.
 * @param float $reference_amount USD-equivalent reference amount (e.g. 29.90).
 */
function mfa_accrue_commission( $referred_user_id, $payment_id, $reference_amount ) {
	global $wpdb;

	$referrer_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT referrer_id FROM {$wpdb->prefix}jet_cct_member WHERE user_id = %d",
		$referred_user_id
	) );

	// Same guards as niz_user_award_referrer_points() (identity-registration.php):
	// 14270 is the hardcoded "no real referrer" sentinel used across the
	// codebase, and it isn't even a real WordPress user.
	if ( ! $referrer_id || 14270 === $referrer_id || $referrer_id === (int) $referred_user_id ) {
		return;
	}

	if ( ! get_userdata( $referrer_id ) ) {
		return;
	}

	$referrer_is_premium = function_exists( 'niz_user_field_by_userid' ) && 'Yes' === niz_user_field_by_userid( $referrer_id, 'chk_premium' );
	$rate_pct            = $referrer_is_premium ? 20.00 : 5.00;
	$commission_amount   = round( $reference_amount * $rate_pct / 100, 2 );

	$wpdb->insert(
		mfa_commission_table(),
		array(
			'affiliate_user_id' => $referrer_id,
			'referred_user_id'  => (int) $referred_user_id,
			'payment_id'        => (int) $payment_id,
			'rate_pct'          => $rate_pct,
			'amount'            => $commission_amount,
			'status'            => 'accrued',
			'created_at'        => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%d', '%f', '%f', '%s', '%s' )
	);
}

function mfa_get_commission_total( $user_id ) {
	global $wpdb;
	$table = mfa_commission_table();
	$total = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(amount) FROM {$table} WHERE affiliate_user_id = %d",
		$user_id
	) );
	return $total ? (float) $total : 0.0;
}
