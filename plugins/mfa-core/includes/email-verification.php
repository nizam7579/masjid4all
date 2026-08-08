<?php
/**
 * Email verification - moved here from enaizi-identity's Niz_Email_Verification
 * class as part of the ongoing identity/user consolidation into mfa-core (see
 * this repo's CLAUDE.md). Same class name preserved so the only two other
 * call sites (enaizi-identity's class-register.php, generate_token()/send_email()
 * at registration time) needed no changes - PHP class resolution is by name,
 * not by which plugin defines it.
 *
 * Still calls enaizi-identity's Niz_Mailer::send() (guarded by class_exists) -
 * that's a small, genuinely separate mailer utility also used by
 * Niz_Forgot_Password, not part of this move.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Niz_Email_Verification {


	public static function init() {


		add_action(
			'init',
			array(
				__CLASS__,
				'verify_email'
			)
		);

		add_action(
			'wp_ajax_niz_resend_verification',
			array(
				__CLASS__,
				'resend'
			)
		);

	}



	/**
	 * Generate verification token
	 */
	public static function generate_token(
		$user_id
	) {


		$token = wp_hash(
			$user_id .
			time() .
			wp_rand()
		);


		update_user_meta(
			$user_id,
			'niz_email_verification_token',
			$token
		);


		update_user_meta(
			$user_id,
			'niz_email_verification_expiry',
			time() + DAY_IN_SECONDS
		);


		return $token;


	}




	/**
	 * Verify email link
	 */
	public static function verify_email() {


		if (
			empty($_GET['niz_verify'])
		) {

			return;

		}



		$token =
		sanitize_text_field(
			$_GET['niz_verify']
		);



		$users = get_users(

			array(

				'meta_key' =>
				'niz_email_verification_token',

				'meta_value' =>
				$token,

				'number' => 1

			)

		);



		if (empty($users)) {

			// Invalid, stale, or already-consumed token - redirect with
			// no query arg so [niz_email_verified]'s default branch (the
			// "Verification Link Invalid" card) renders. Previously this
			// added niz_verify_status=success, which is backwards - it
			// showed "Email Verified Successfully" even though nothing
			// was actually verified (bug found 2026-08-09: a user whose
			// browser/click landed on a superseded token saw a false
			// success page while niz_email_verified stayed 'No').
			wp_redirect(
				home_url('/email-verified/')
			);

			exit;


		}



		$user = $users[0];



		$expiry = get_user_meta(

			$user->ID,

			'niz_email_verification_expiry',

			true

		);



		if (
			time() > $expiry
		) {


			wp_die(
				'Verification link expired.'
			);


		}



		update_user_meta(

			$user->ID,

			'niz_email_verified',

			'Yes'

		);


		update_user_meta(

			$user->ID,

			'niz_email_verified_at',

			current_time('mysql')

		);


		update_user_meta(

			$user->ID,

			'niz_email_verification_token',

			''

		);


		/**
		 * Increase trust score
		 */
		$score =
		(int)get_user_meta(

			$user->ID,

			'niz_trust_score',

			true

		);


		update_user_meta(

			$user->ID,

			'niz_trust_score',

			$score + 10

		);


		/**
		 * Award Barakah points - only reachable once per token match
		 * (a re-clicked/already-used link falls into the empty-$users
		 * branch above and returns early), plus mfa_award_points()'s
		 * own per-user dedup as a second safety net.
		 */
		if ( function_exists( 'mfa_award_points' ) ) {

			mfa_award_points(
				$user->ID,
				'Verify Email',
				25
			);

		}


		// A real, successful match - this is the only branch that should
		// show the success card (see the empty($users) branch above).
		wp_redirect(

			add_query_arg(

				'niz_verify_status',

				'success',

				home_url( '/email-verified/' )

			)

		);


		exit;


	}

	public static function send_email(
		$user_id,
		$token
	) {


		$user =
		get_userdata(
			$user_id
		);


		$verify_url =
		add_query_arg(

			array(

				'niz_verify'=>$token

			),

			home_url()

		);



		$html = '

		<h2>
		Verify your email
		</h2>


		<p>
		Thank you for registering.
		Please verify your email address.
		</p>


		<p>

		<a href="'.$verify_url.'">

		Verify Email

		</a>

		</p>


		';



		if ( ! class_exists( 'Niz_Mailer' ) ) {
			return false;
		}

		return Niz_Mailer::send(

			$user->user_email,

			'Verify your account',

			$html

		);


	}


	public static function resend() {


		if (
			!is_user_logged_in()
		) {


			wp_send_json_error(
				array(
					'message' =>
					'Please login first.'
				)
			);


		}



		check_ajax_referer(
			'niz_resend_email',
			'nonce'
		);



		$user_id =
		get_current_user_id();



		$verified =
		get_user_meta(

			$user_id,

			'niz_email_verified',

			true

		);



		if (
			$verified === 'Yes'
		) {


			wp_send_json_error(
				array(
					'message' =>
					'Email already verified.'
				)
			);


		}



		$token =
		self::generate_token(
			$user_id
		);



		$sent =
		self::send_email(
			$user_id,
			$token
		);



		if ( ! $sent ) {

			wp_send_json_error(
				array(
					'message' =>
					"We couldn't send the verification email right now. Please try again shortly, or contact support if this keeps happening."
				)
			);

		}



		wp_send_json_success(
			array(

				'message' =>
				'Verification email sent. Please check your inbox.'

			)
		);


	}



}

Niz_Email_Verification::init();
