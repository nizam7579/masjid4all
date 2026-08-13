<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_website_update_form] - the "Update Info" form on the single website
 * post's Home tab, replacing FluentForm 69 ("Web - Update") per the
 * FluentForm-removal workstream (mirrors business-update-form.php exactly -
 * see that file for the wider rationale). Field set and sanitisation mirror
 * the old 'fluentform/before_insert_submission' handler for form 69
 * (enaizi-mfa/includes/web.php) - EXCEPT one bug fixed here: that handler
 * read $data['url'] for the website-URL field, but form 69's actual field
 * name is "website" - so the jet_cct_web.url column was never actually
 * updated by that form (ArrayHelper::get() on a missing key silently
 * returns empty). This rewrite reads $_POST['website'] correctly.
 */
add_shortcode( 'mfa_website_update_form', 'mfa_website_update_form_shortcode' );
function mfa_website_update_form_shortcode() {
	$post_id = get_the_ID();
	$item_id = get_post_meta( $post_id, 'item_id', true );
	if ( empty( $item_id ) ) {
		return '';
	}

	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE _ID = %d", $item_id ), ARRAY_A );
	$row   = $row ? $row : array();

	$val = function ( $key ) use ( $row ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : '';
	};

	$countries = function_exists( 'mfa_get_country_list' ) ? mfa_get_country_list() : array( 'Malaysia' );
	$uid       = esc_attr( $post_id );

	ob_start();
	?>
	<form id="mfa-web-update-form-<?php echo $uid; ?>" class="mfa-modal-form mfa-web-update-form" data-post-id="<?php echo $uid; ?>" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_website_update_' . $post_id ) ); ?>">
		<div class="mfa-form-group">
			<label for="mfa-web-name-<?php echo $uid; ?>">Name</label>
			<input type="text" id="mfa-web-name-<?php echo $uid; ?>" name="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-web-intro-<?php echo $uid; ?>">Introduction</label>
			<textarea id="mfa-web-intro-<?php echo $uid; ?>" name="introduction" rows="4" required><?php echo esc_textarea( $val( 'introduction' ) ); ?></textarea>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-web-address-<?php echo $uid; ?>">Address</label>
			<input type="text" id="mfa-web-address-<?php echo $uid; ?>" name="address" value="<?php echo esc_attr( $val( 'address' ) ); ?>" required>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-web-city-<?php echo $uid; ?>">City</label>
				<input type="text" id="mfa-web-city-<?php echo $uid; ?>" name="city" value="<?php echo esc_attr( $val( 'city' ) ); ?>" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-web-country-<?php echo $uid; ?>">Country</label>
				<select id="mfa-web-country-<?php echo $uid; ?>" name="country" required>
					<option value="">Select</option>
					<?php foreach ( $countries as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $val( 'country' ), $c ); ?>><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-web-phone-<?php echo $uid; ?>">Phone</label>
				<input type="text" id="mfa-web-phone-<?php echo $uid; ?>" name="phone" value="<?php echo esc_attr( $val( 'phone' ) ); ?>" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-web-whatsapp-<?php echo $uid; ?>">WhatsApp</label>
				<input type="text" id="mfa-web-whatsapp-<?php echo $uid; ?>" name="whatsapp" value="<?php echo esc_attr( $val( 'whatsapp' ) ); ?>" required>
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-web-website-<?php echo $uid; ?>">Website</label>
				<input type="url" id="mfa-web-website-<?php echo $uid; ?>" name="website" value="<?php echo esc_attr( $val( 'url' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-web-email-<?php echo $uid; ?>">Email</label>
				<input type="email" id="mfa-web-email-<?php echo $uid; ?>" name="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-web-fb-<?php echo $uid; ?>">Facebook</label>
				<input type="url" id="mfa-web-fb-<?php echo $uid; ?>" name="facebook" value="<?php echo esc_attr( $val( 'fb' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-web-insta-<?php echo $uid; ?>">Instagram</label>
				<input type="url" id="mfa-web-insta-<?php echo $uid; ?>" name="instagram" value="<?php echo esc_attr( $val( 'insta' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-web-linkedin-<?php echo $uid; ?>">LinkedIn</label>
				<input type="url" id="mfa-web-linkedin-<?php echo $uid; ?>" name="linkedin" value="<?php echo esc_attr( $val( 'linkedin' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-web-tiktok-<?php echo $uid; ?>">TikTok</label>
				<input type="url" id="mfa-web-tiktok-<?php echo $uid; ?>" name="tiktok" value="<?php echo esc_attr( $val( 'tiktok' ) ); ?>">
			</div>
		</div>
		<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Save Changes</button>
		<p class="mfa-modal-message" data-mfa-form-message></p>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for the form above. Re-checks mfa_website_user_can_manage()
 * (extracted in website-single.php) server-side, same reasoning as
 * business-update-form.php's handler.
 */
add_action( 'wp_ajax_mfa_website_update_info', 'mfa_ajax_website_update_info' );
function mfa_ajax_website_update_info() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( empty( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid website.' ) );
	}

	check_ajax_referer( 'mfa_website_update_' . $post_id, 'nonce' );

	if ( ! function_exists( 'mfa_website_user_can_manage' ) || ! mfa_website_user_can_manage( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to update this listing.' ) );
	}

	$item_id = get_post_meta( $post_id, 'item_id', true );
	if ( empty( $item_id ) ) {
		wp_send_json_error( array( 'message' => 'No linked website record found.' ) );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
	if ( empty( $name ) || empty( $address ) ) {
		wp_send_json_error( array( 'message' => 'Name and address are required.' ) );
	}

	$update_data = array(
		'name'         => $name,
		'introduction' => isset( $_POST['introduction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['introduction'] ) ) : '',
		'address'      => $address,
		'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
		'country'      => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
		'phone'        => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		'whatsapp'     => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
		'url'          => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
		'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'fb'           => isset( $_POST['facebook'] ) ? esc_url_raw( wp_unslash( $_POST['facebook'] ) ) : '',
		'insta'        => isset( $_POST['instagram'] ) ? esc_url_raw( wp_unslash( $_POST['instagram'] ) ) : '',
		'linkedin'     => isset( $_POST['linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['linkedin'] ) ) : '',
		'tiktok'       => isset( $_POST['tiktok'] ) ? esc_url_raw( wp_unslash( $_POST['tiktok'] ) ) : '',
	);

	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'jet_cct_web',
		$update_data,
		array( '_ID' => $item_id ),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	wp_update_post( array(
		'ID'         => $post_id,
		'post_title' => $name,
	) );

	wp_send_json_success( array( 'message' => 'Website information updated.' ) );
}
