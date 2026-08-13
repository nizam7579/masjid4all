<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_business_update_form] - the "Update Info" form on the single business
 * post's Home tab, replacing FluentForm 68 ("Business - Update") per the
 * FluentForm-removal workstream (bundled with the Kadence Blocks Pro modal
 * -> [mfa_modal] conversion in business-single.php, since form 68 only ever
 * lived inside that modal). Field set, sanitisation, and the facebook->fb /
 * instagram->insta column mapping mirror the old
 * 'fluentform/before_insert_submission' handler for form 68 exactly
 * (enaizi-mfa/includes/business.php) so existing jet_cct_business data is
 * read/written the same way. Country list reuses mfa_get_country_list()
 * (member-account-modals.php) instead of duplicating FluentForm's own
 * ~195-territory list - same field, same site-wide convention.
 */
add_shortcode( 'mfa_business_update_form', 'mfa_business_update_form_shortcode' );
function mfa_business_update_form_shortcode() {
	$post_id = get_the_ID();
	$item_id = get_post_meta( $post_id, 'item_id', true );
	if ( empty( $item_id ) ) {
		return '';
	}

	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_business';
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE _ID = %d", $item_id ), ARRAY_A );
	$row   = $row ? $row : array();

	$val = function ( $key ) use ( $row ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : '';
	};

	$countries = function_exists( 'mfa_get_country_list' ) ? mfa_get_country_list() : array( 'Malaysia' );
	$uid       = esc_attr( $post_id );

	ob_start();
	?>
	<form id="mfa-biz-update-form-<?php echo $uid; ?>" class="mfa-modal-form mfa-biz-update-form" data-post-id="<?php echo $uid; ?>" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_business_update_' . $post_id ) ); ?>">
		<div class="mfa-form-group">
			<label for="mfa-biz-name-<?php echo $uid; ?>">Name</label>
			<input type="text" id="mfa-biz-name-<?php echo $uid; ?>" name="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-biz-intro-<?php echo $uid; ?>">Introduction</label>
			<textarea id="mfa-biz-intro-<?php echo $uid; ?>" name="introduction" rows="4" required><?php echo esc_textarea( $val( 'introduction' ) ); ?></textarea>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-biz-address-<?php echo $uid; ?>">Address</label>
			<input type="text" id="mfa-biz-address-<?php echo $uid; ?>" name="address" value="<?php echo esc_attr( $val( 'address' ) ); ?>" required>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-biz-city-<?php echo $uid; ?>">City</label>
				<input type="text" id="mfa-biz-city-<?php echo $uid; ?>" name="city" value="<?php echo esc_attr( $val( 'city' ) ); ?>" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-biz-country-<?php echo $uid; ?>">Country</label>
				<select id="mfa-biz-country-<?php echo $uid; ?>" name="country" required>
					<option value="">Select</option>
					<?php foreach ( $countries as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $val( 'country' ), $c ); ?>><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-biz-phone-<?php echo $uid; ?>">Phone</label>
				<input type="text" id="mfa-biz-phone-<?php echo $uid; ?>" name="phone" value="<?php echo esc_attr( $val( 'phone' ) ); ?>" required>
			</div>
			<div class="mfa-form-group">
				<label for="mfa-biz-whatsapp-<?php echo $uid; ?>">WhatsApp</label>
				<input type="text" id="mfa-biz-whatsapp-<?php echo $uid; ?>" name="whatsapp" value="<?php echo esc_attr( $val( 'whatsapp' ) ); ?>" required>
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-biz-website-<?php echo $uid; ?>">Website</label>
				<input type="url" id="mfa-biz-website-<?php echo $uid; ?>" name="website" value="<?php echo esc_attr( $val( 'website' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-biz-email-<?php echo $uid; ?>">Email</label>
				<input type="email" id="mfa-biz-email-<?php echo $uid; ?>" name="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-biz-fb-<?php echo $uid; ?>">Facebook</label>
				<input type="url" id="mfa-biz-fb-<?php echo $uid; ?>" name="facebook" value="<?php echo esc_attr( $val( 'fb' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-biz-insta-<?php echo $uid; ?>">Instagram</label>
				<input type="url" id="mfa-biz-insta-<?php echo $uid; ?>" name="instagram" value="<?php echo esc_attr( $val( 'insta' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-biz-linkedin-<?php echo $uid; ?>">LinkedIn</label>
				<input type="url" id="mfa-biz-linkedin-<?php echo $uid; ?>" name="linkedin" value="<?php echo esc_attr( $val( 'linkedin' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-biz-tiktok-<?php echo $uid; ?>">TikTok</label>
				<input type="url" id="mfa-biz-tiktok-<?php echo $uid; ?>" name="tiktok" value="<?php echo esc_attr( $val( 'tiktok' ) ); ?>">
			</div>
		</div>
		<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Save Changes</button>
		<p class="mfa-modal-message" data-mfa-form-message></p>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for the form above. Re-checks the same authorization gate
 * mfa_business_home_tab_shortcode() uses to decide whether to render the
 * form at all (owner via jet_cct_listing_owner, or editor/administrator) -
 * the form only being visible to authorized users doesn't stop a raw POST
 * to admin-ajax.php from anyone else.
 */
add_action( 'wp_ajax_mfa_business_update_info', 'mfa_ajax_business_update_info' );
function mfa_ajax_business_update_info() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Please login first.' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( empty( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid business.' ) );
	}

	check_ajax_referer( 'mfa_business_update_' . $post_id, 'nonce' );

	if ( ! function_exists( 'mfa_business_user_can_manage' ) || ! mfa_business_user_can_manage( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to update this listing.' ) );
	}

	$item_id = get_post_meta( $post_id, 'item_id', true );
	if ( empty( $item_id ) ) {
		wp_send_json_error( array( 'message' => 'No linked business record found.' ) );
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
		'website'      => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
		'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'fb'           => isset( $_POST['facebook'] ) ? esc_url_raw( wp_unslash( $_POST['facebook'] ) ) : '',
		'insta'        => isset( $_POST['instagram'] ) ? esc_url_raw( wp_unslash( $_POST['instagram'] ) ) : '',
		'linkedin'     => isset( $_POST['linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['linkedin'] ) ) : '',
		'tiktok'       => isset( $_POST['tiktok'] ) ? esc_url_raw( wp_unslash( $_POST['tiktok'] ) ) : '',
	);

	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'jet_cct_business',
		$update_data,
		array( '_ID' => $item_id ),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	wp_update_post( array(
		'ID'         => $post_id,
		'post_title' => $name,
	) );

	wp_send_json_success( array( 'message' => 'Business information updated.' ) );
}
