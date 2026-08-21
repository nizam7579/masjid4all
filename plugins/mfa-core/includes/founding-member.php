<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grants Founding Member benefits for one completed Stripe Checkout
 * Session - shared by both niz-mfa's webhook handler
 * (mfa_handle_stripe_webhook(), authoritative, server-to-server) and its
 * /payment-success/ page shortcode (mfa_render_payment_success_details(),
 * a client-side fallback for when the webhook doesn't reach this
 * environment - e.g. a Stripe Dashboard test-mode webhook still pointed
 * at production while testing on staging, 2026-08-09). Before this, those
 * two paths had diverged badly: the success-page only ever set an unused
 * mfa_membership_status meta key, never chk_premium (what the dashboard's
 * $is_premium actually checks), never awarded points/credit, and never
 * created a payment record - so a staging payment could show "Payment
 * Successful!" while granting nothing real.
 *
 * Also fixes a second bug found while unifying these: niz_user_register()
 * returns a WP_Error (not a user ID) when the payer's WhatsApp number
 * already belongs to an existing member (not a 'prospect') - the exact,
 * very plausible case of an already-registered member upgrading to
 * Founding Member. The old webhook code passed that WP_Error straight
 * into niz_user_update_field()/niz_user_add_points() as if it were a
 * user ID, silently failing. This resolves the existing account instead
 * (by phone, then by email) rather than erroring out.
 *
 * @param object $stripe_session A \Stripe\Checkout\Session with ->id,
 *                                ->currency, ->amount_total, ->metadata.
 * @return int|WP_Error The granted user's ID, or WP_Error if one couldn't be resolved.
 */
/**
 * Master switch for the whole Founding Member paid offer.
 *
 * Deactivated 2026-08-21 by decision: the offer is being reworked to run
 * through the standard registration chokepoint
 * (niz_user_complete_registration()) rather than its own niz_user_register()
 * path, which is the last thing bypassing it.
 *
 * BOTH halves are switched off by this one function on purpose - the grant
 * below, and the two ways to pay in enaizi-mfa (the [mfa_stripe_form]
 * shortcode and the mfa/v1/create-session REST route). Disabling the grant
 * on its own would leave the checkout open and hand somebody a completed
 * payment with nothing granted for it, which is worse than not selling.
 *
 * The Founding Member WAITLIST is a separate feature and is deliberately left
 * running - see mfa_lead_types()['founding_member'] in sofia-leads.php, the
 * Sofia keyword, and the invite_founding admin action.
 *
 * Re-enable by returning true here, or via the filter, once the offer is
 * wired to the shared registration path.
 */
function mfa_founding_member_enabled() {
	return (bool) apply_filters( 'mfa_founding_member_enabled', false );
}

function mfa_grant_founding_member_benefits( $stripe_session ) {
	global $wpdb;

	if ( ! mfa_founding_member_enabled() ) {
		return new WP_Error(
			'founding_member_disabled',
			'The Founding Member offer is currently disabled.'
		);
	}

	$stripe_session_id = $stripe_session->id;

	// One payment record per Stripe session - the shared idempotency guard
	// for both callers (the webhook may run, then the browser also lands
	// on /payment-success/ for the same session; or vice versa if the
	// webhook is delayed). Several of the individual grants below
	// (niz_user_add_credit(), the payment insert itself) have no dedup of
	// their own, so this check must happen before any of them run.
	$existing_payment_user = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_id FROM {$wpdb->prefix}jet_cct_payment WHERE stripe_session_id = %s",
			$stripe_session_id
		)
	);
	if ( $existing_payment_user ) {
		return (int) $existing_payment_user;
	}

	$metadata = $stripe_session->metadata;
	$name     = sanitize_text_field( $metadata->user_name );
	$email    = sanitize_email( $metadata->user_email );
	$whatsapp = sanitize_text_field( $metadata->user_whatsapp );
	$desc     = sanitize_text_field( $metadata->description );
	$country  = sanitize_text_field( $metadata->country );

	$user_id = function_exists( 'niz_user_register' )
		? niz_user_register( $whatsapp, $name, true )
		: new WP_Error( 'missing_fn', 'niz_user_register() is not available.' );

	if ( is_wp_error( $user_id ) ) {
		// Most commonly "user_exists" - resolve their existing account by
		// phone, then by email, instead of erroring out silently.
		$existing_id = function_exists( 'niz_user_check' ) ? niz_user_check( $whatsapp ) : 0;
		if ( ! $existing_id && $email ) {
			$existing_user = get_user_by( 'email', $email );
			$existing_id   = $existing_user ? $existing_user->ID : 0;
		}
		if ( ! $existing_id ) {
			error_log( 'mfa_grant_founding_member_benefits: could not resolve a user for Stripe session ' . $stripe_session_id . ' - ' . $user_id->get_error_message() );
			return $user_id;
		}
		$user_id = (int) $existing_id;
	}

	if ( function_exists( 'niz_user_member_cct' ) ) {
		// Ensures the jet_cct_member row exists before updating it below -
		// niz_user_register()'s own happy path already creates one, but a
		// user resolved via the WP_Error fallback above (an existing
		// account, e.g. registered via email/Google, with no CCT row yet)
		// may not have one - same guard mfa_ajax_update_profile() already
		// uses for the same reason. Confirmed missing this caused
		// niz_user_update_field() below to silently update zero rows in
		// testing (2026-08-09).
		niz_user_member_cct( $user_id );
	}

	if ( function_exists( 'niz_user_update_field' ) ) {
		niz_user_update_field( $user_id, 'status', 'Premium Lifetime' );
		niz_user_update_field( $user_id, 'email', $email );
		niz_user_update_field( $user_id, 'country', $country );
		niz_user_update_field( $user_id, 'chk_premium', 'Yes' );
	}

	// Reference amount ($29.90 USD-equivalent, regardless of the local
	// currency actually charged) vs. the real charged amount - preserves
	// the original webhook's intentional MYR/GBP handling: the "$29.90
	// Platform Credit" promise on /founding-members/ is a fixed USD value,
	// not whatever 130 MYR/23 GBP converts to.
	$session_currency = strtoupper( $stripe_session->currency );
	$charge_amount    = $stripe_session->amount_total / 100;
	$amount           = in_array( $session_currency, array( 'MYR', 'GBP' ), true ) ? 29.90 : $charge_amount;
	$local_amount     = $charge_amount;

	if ( function_exists( 'mfa_award_points' ) ) {
		// mfa_award_points() (not the older niz_user_add_points(), which
		// this replaces here) dedupes per (user_id, description) - correct
		// for these fixed, once-only bonuses.
		mfa_award_points( $user_id, 'Welcome Bonus', 50 );
		mfa_award_points( $user_id, 'Founding Member Bonus', 1000 );
	}

	if ( function_exists( 'niz_user_add_credit' ) ) {
		niz_user_add_credit( $user_id, 'Founding Member', $amount );
	}

	$wpdb->insert(
		$wpdb->prefix . 'jet_cct_payment',
		array(
			'user_id'           => $user_id,
			'description'       => $desc,
			'amount'            => $amount,
			'currency'          => $session_currency,
			'local_amount'      => $local_amount,
			'stripe_session_id' => $stripe_session_id,
			'payment_status'    => 'Completed',
			'paid_at'           => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%f', '%s', '%f', '%s', '%s', '%s' )
	);

	if ( function_exists( 'mfa_accrue_commission' ) ) {
		// Only reachable once per Stripe session - the idempotency check at
		// the top of this function already returned early for a session
		// that's been processed before, so this can't double-accrue.
		mfa_accrue_commission( $user_id, $wpdb->insert_id, $amount );
	}

	return $user_id;
}
