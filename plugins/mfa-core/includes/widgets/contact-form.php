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
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( empty( $name ) || empty( $phone ) || empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in all fields.' ) );
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

	$to      = 'nizam7579@gmail.com';
	$email_subject = 'Contact Us - ' . $subject;
	$body    = "Name: {$name}\n"
		. "Whatsapp Number: {$phone}\n"
		. "Subject: {$subject}\n"
		. "Message: {$message}\n\n"
		. 'This form submitted at: ' . home_url( '/contact-us/' );

	$headers = array( 'From: Masjid4All <' . get_option( 'admin_email' ) . '>' );
	$sent    = wp_mail( $to, $email_subject, $body, $headers );

	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => 'Could not send your message. Please try again later.' ) );
	}

	wp_send_json_success( array( 'message' => "Thank you! We've received your message and will get back to you soon." ) );
}
