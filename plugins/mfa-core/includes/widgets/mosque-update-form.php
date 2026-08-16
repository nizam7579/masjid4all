<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_mosque_update_form] - the "Edit Mosque" modal form on the single
 * mosque post's Home tab (mosque-single.php), Administrator/Editor only -
 * same role gate as that page's existing "Upload Image" modal. Unlike
 * business-update-form.php (owner-or-staff, via mfa_business_user_can_manage()),
 * there's no listing-owner concept for mosques yet, so this is staff-only,
 * no separate authorization helper needed.
 *
 * Field set is the exact list requested 2026-08-17: Name, listing_status,
 * address, city, country, email, website, phone, WhatsApp, Facebook,
 * YouTube. Reads/writes wp_jet_cct_mosque directly via $wpdb (never the
 * JetEngine PHP API, per the project's standing rule), keyed on
 * cct_single_post_id - same lookup mfa_mosque_info_display() already uses,
 * not a separate `item_id` postmeta (business-update-form.php's pattern -
 * mosque posts don't carry that meta key).
 */
add_shortcode( 'mfa_mosque_update_form', 'mfa_mosque_update_form_shortcode' );
function mfa_mosque_update_form_shortcode() {
	if ( ! current_user_can( 'administrator' ) && ! current_user_can( 'editor' ) ) {
		return '';
	}

	$post_id = get_the_ID();

	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_mosque';
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE cct_single_post_id = %d", $post_id ), ARRAY_A );
	if ( ! $row ) {
		return '<p class="mfa-body-muted">No linked mosque record found.</p>';
	}

	$val = function ( $key ) use ( $row ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : '';
	};

	$status_options = function_exists( 'mfa_admin_mosque_status_options' ) ? mfa_admin_mosque_status_options() : array( 'New', 'Pending', 'Approved', 'Active', 'Rejected', 'Error', 'Deleted' );
	$uid            = esc_attr( $post_id );

	ob_start();
	?>
	<form id="mfa-mosque-edit-form-<?php echo $uid; ?>" class="mfa-modal-form mfa-mosque-edit-form" data-post-id="<?php echo $uid; ?>" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_mosque_edit_' . $post_id ) ); ?>">
		<div class="mfa-form-group">
			<label for="mfa-mosque-edit-name-<?php echo $uid; ?>">Name</label>
			<input type="text" id="mfa-mosque-edit-name-<?php echo $uid; ?>" name="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>" required>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-mosque-edit-status-<?php echo $uid; ?>">Status</label>
			<select id="mfa-mosque-edit-status-<?php echo $uid; ?>" name="listing_status">
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $val( 'listing_status' ), $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="mfa-form-group">
			<label for="mfa-mosque-edit-address-<?php echo $uid; ?>">Address</label>
			<input type="text" id="mfa-mosque-edit-address-<?php echo $uid; ?>" name="address" value="<?php echo esc_attr( $val( 'address' ) ); ?>" required>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-city-<?php echo $uid; ?>">City</label>
				<input type="text" id="mfa-mosque-edit-city-<?php echo $uid; ?>" name="city" value="<?php echo esc_attr( $val( 'city' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-country-<?php echo $uid; ?>">Country</label>
				<?php
				$countries = function_exists( 'mfa_get_country_list' ) ? mfa_get_country_list() : array( 'Malaysia' );
				?>
				<select id="mfa-mosque-edit-country-<?php echo $uid; ?>" name="country">
					<option value="">Select</option>
					<?php foreach ( $countries as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $val( 'country' ), $c ); ?>><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-email-<?php echo $uid; ?>">Email</label>
				<input type="email" id="mfa-mosque-edit-email-<?php echo $uid; ?>" name="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-website-<?php echo $uid; ?>">Website</label>
				<input type="url" id="mfa-mosque-edit-website-<?php echo $uid; ?>" name="website" value="<?php echo esc_attr( $val( 'website' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-phone-<?php echo $uid; ?>">Phone</label>
				<input type="text" id="mfa-mosque-edit-phone-<?php echo $uid; ?>" name="phone" value="<?php echo esc_attr( $val( 'phone' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-whatsapp-<?php echo $uid; ?>">WhatsApp</label>
				<input type="text" id="mfa-mosque-edit-whatsapp-<?php echo $uid; ?>" name="whatsapp" value="<?php echo esc_attr( $val( 'whatsapp' ) ); ?>">
			</div>
		</div>
		<div class="mfa-form-row">
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-facebook-<?php echo $uid; ?>">Facebook</label>
				<input type="url" id="mfa-mosque-edit-facebook-<?php echo $uid; ?>" name="facebook" value="<?php echo esc_attr( $val( 'facebook' ) ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-mosque-edit-youtube-<?php echo $uid; ?>">YouTube</label>
				<input type="url" id="mfa-mosque-edit-youtube-<?php echo $uid; ?>" name="youtube" value="<?php echo esc_attr( $val( 'youtube' ) ); ?>">
			</div>
		</div>
		<button type="submit" class="mfa-btn mfa-btn-primary mfa-modal-submit">Save Changes</button>
		<p class="mfa-modal-message" data-mfa-form-message></p>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler for the form above. Re-checks the same Administrator/Editor
 * gate the shortcode uses to decide whether to render the form at all - the
 * form only being visible to authorized users doesn't stop a raw POST to
 * admin-ajax.php from anyone else.
 */
add_action( 'wp_ajax_mfa_mosque_update_info', 'mfa_ajax_mosque_update_info' );
function mfa_ajax_mosque_update_info() {
	if ( ! current_user_can( 'administrator' ) && ! current_user_can( 'editor' ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to do this.' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( empty( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid mosque.' ) );
	}

	check_ajax_referer( 'mfa_mosque_edit_' . $post_id, 'nonce' );

	global $wpdb;
	$table   = $wpdb->prefix . 'jet_cct_mosque';
	$item_id = $wpdb->get_var( $wpdb->prepare( "SELECT _ID FROM {$table} WHERE cct_single_post_id = %d", $post_id ) );
	if ( ! $item_id ) {
		wp_send_json_error( array( 'message' => 'No linked mosque record found.' ) );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
	if ( empty( $name ) || empty( $address ) ) {
		wp_send_json_error( array( 'message' => 'Name and address are required.' ) );
	}

	$status_options = function_exists( 'mfa_admin_mosque_status_options' ) ? mfa_admin_mosque_status_options() : array( 'New', 'Pending', 'Approved', 'Active', 'Rejected', 'Error', 'Deleted' );
	$status         = isset( $_POST['listing_status'] ) ? sanitize_text_field( wp_unslash( $_POST['listing_status'] ) ) : '';
	if ( ! in_array( $status, $status_options, true ) ) {
		$status = '';
	}

	$update_data = array(
		'name'           => $name,
		'listing_status' => $status,
		'address'        => $address,
		'city'           => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
		'country'        => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
		'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'website'        => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
		'phone'          => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		'whatsapp'       => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
		'facebook'       => isset( $_POST['facebook'] ) ? esc_url_raw( wp_unslash( $_POST['facebook'] ) ) : '',
		'youtube'        => isset( $_POST['youtube'] ) ? esc_url_raw( wp_unslash( $_POST['youtube'] ) ) : '',
	);

	$wpdb->update(
		$table,
		$update_data,
		array( '_ID' => $item_id ),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	wp_update_post( array(
		'ID'         => $post_id,
		'post_title' => $name,
	) );

	wp_send_json_success( array( 'message' => 'Mosque information updated.' ) );
}
