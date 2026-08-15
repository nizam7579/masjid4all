<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_contact_form] - the form on /contact-us/, replacing FluentForm 8.
 * Fields (name, WhatsApp number, subject, message) and the admin
 * notification email match the old form's config exactly (see the
 * fluentform_forms/fluentform_form_meta rows for form_id 8 before this
 * change). Keeps the same Cloudflare Turnstile spam check the old form
 * used - reads the site/secret key pair from FluentForm's own
 * `_fluentform_turnstile_details` option at runtime rather than
 * hardcoding them (FluentForm itself stays installed for now for the
 * Business/Website single "Update Info" forms it doesn't handle anymore
 * but Kadence-content pages like Receipt still do), so removing this one
 * shortcode doesn't touch that shared credential.
 *
 * Email field added 2026-08-14, alongside wiring this form to
 * wp_jet_cct_contact_us (previously an unused, empty table - no
 * JetEngine config to inherit conventions from, confirmed before
 * building) and a confirmation email to the sender - see
 * mfa_ajax_contact_submit() below. The email field is required for both:
 * there was no way to email the sender back before this, since the old
 * form only ever collected a WhatsApp number.
 */
add_shortcode( 'mfa_contact_form', 'mfa_contact_form_shortcode' );
function mfa_contact_form_shortcode() {
	$turnstile    = get_option( '_fluentform_turnstile_details' );
	$site_key     = is_array( $turnstile ) && ! empty( $turnstile['siteKey'] ) ? $turnstile['siteKey'] : '';

	if ( $site_key ) {
		wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
	}

	ob_start();
	?>
	<form id="mfa-contact-form" class="mfa-modal-form" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_contact_submit' ) ); ?>">
		<div class="mfa-form-group">
			<label for="mfa-contact-name">Name</label>
			<input type="text" id="mfa-contact-name" name="name" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-contact-email">Email</label>
			<input type="email" id="mfa-contact-email" name="email" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-contact-phone">Whatsapp Number</label>
			<input type="tel" id="mfa-contact-phone" name="phone" placeholder="Mobile Number" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-contact-subject">Subject</label>
			<input type="text" id="mfa-contact-subject" name="subject" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-contact-message">Message</label>
			<textarea id="mfa-contact-message" name="message" rows="5" required></textarea>
		</div>
		<?php if ( $site_key ) : ?>
			<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
		<?php endif; ?>
		<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Send Message</button>
		<p class="mfa-modal-message" data-mfa-form-message></p>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for the form above. Verifies the Turnstile token
 * server-side via Cloudflare's siteverify endpoint before sending the
 * notification email - the widget rendering alone is client-side only
 * and doesn't stop a scripted POST straight to admin-ajax.php.
 */
add_action( 'wp_ajax_mfa_contact_submit', 'mfa_ajax_contact_submit' );
add_action( 'wp_ajax_nopriv_mfa_contact_submit', 'mfa_ajax_contact_submit' );
function mfa_ajax_contact_submit() {
	check_ajax_referer( 'mfa_contact_submit', 'nonce' );

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( empty( $name ) || empty( $email ) || empty( $phone ) || empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in all fields.' ) );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
	}

	$turnstile = get_option( '_fluentform_turnstile_details' );
	if ( is_array( $turnstile ) && ! empty( $turnstile['secretKey'] ) ) {
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Please complete the verification check.' ) );
		}

		$verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $turnstile['secretKey'],
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		) );

		$verify_body = is_wp_error( $verify ) ? array() : json_decode( wp_remote_retrieve_body( $verify ), true );
		if ( empty( $verify_body['success'] ) ) {
			wp_send_json_error( array( 'message' => 'Verification failed. Please try again.' ) );
		}
	}

	$stored = mfa_contact_us_store( $name, $email, $phone, $subject, $message, get_current_user_id() );

	if ( ! $stored ) {
		wp_send_json_error( array( 'message' => 'Could not send your message. Please try again later.' ) );
	}

	wp_send_json_success( array( 'message' => "Thank you! We've received your message and will get back to you soon." ) );
}

/**
 * Store a Contact-Us submission and send the notifications. Shared by the
 * web form's AJAX handler above and Sofia's WhatsApp contact flow
 * (niz-wa-integration.php's niz_wa_contact_route()), so both channels write
 * the same wp_jet_cct_contact_us row (status "New", feeds /admin/inquiry/)
 * and send the same team + sender emails.
 *
 * The stored CCT row is the durable record and the success criterion; both
 * emails are best-effort - a mail failure (staging's sender subdomain isn't
 * always deliverable) must not lose the submission. Returns true if the row
 * was stored. $phone is the WhatsApp number for the WhatsApp channel, or the
 * number typed into the web form.
 */
function mfa_contact_us_store( $name, $email, $phone, $subject, $message, $author_id = 0 ) {
	global $wpdb;

	$stored = $wpdb->insert(
		$wpdb->prefix . 'jet_cct_contact_us',
		array(
			'cct_status'    => 'New',
			'name'          => $name,
			'email'         => $email,
			'phone'         => $phone,
			'subject'       => $subject,
			'message'       => $message,
			'cct_author_id' => (int) $author_id,
			'cct_created'   => current_time( 'mysql' ),
			'cct_modified'  => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
	);

	$headers = array( 'From: Masjid4All <' . get_option( 'admin_email' ) . '>' );

	// Notify the team.
	$team_body = "Name: {$name}\n"
		. "Email: {$email}\n"
		. "Whatsapp Number: {$phone}\n"
		. "Subject: {$subject}\n"
		. "Message: {$message}\n\n"
		. 'Submitted via: ' . home_url( '/contact-us/' );
	wp_mail( 'nizam7579@gmail.com', 'Contact Us - ' . $subject, $team_body, $headers );

	// Confirm to the sender.
	$confirm_body = "Assalamualaikum {$name},\n\n"
		. "Thank you for reaching out to Masjid4All. We've received your message and our team will get back to you soon.\n\n"
		. "Your message:\n"
		. "Subject: {$subject}\n"
		. "{$message}\n\n"
		. "JazakAllah khair,\nMasjid4All Team";
	wp_mail( $email, "We've received your message - Masjid4All", $confirm_body, $headers );

	return (bool) $stored;
}
