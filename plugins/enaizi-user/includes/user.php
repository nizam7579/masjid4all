<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Check User 
 * 2. Register New User
 * 3. Get user_info from user_id 
 * 4. Send password
 * 5. Register Prospect 
 */

/**
 * Normalize phone number: remove all non-digits.
 */
function niz_user_normalize_phone($phone) {
    return preg_replace('/\D/', '', trim($phone));
}

/** 1. Check User 
 *     $user_id = niz_user_check($phone) 
 *     if empty, not a member 
 */
 
function niz_user_check($phone) {
    $phone = niz_user_normalize_phone($phone);
    if (empty($phone)) {
        return null;
    }
    $users = get_users([
        'meta_key'     => 'user_phone',
        'meta_value'   => $phone,
        'meta_compare' => '=',
        'number'       => 1,
        'fields'       => 'ID',
    ]);
    return !empty($users) ? $users[0] : null;
} 


/**
 * Register a new user or upgrade an existing prospect securely.
 * 
 * @param string $phone          The user's mobile/WhatsApp number.
 * @param string $name           The full name of the user.
 * @param bool   $send_template  Whether to trigger a WhatsApp password template.
 * @return int|WP_Error          Returns user ID on success, WP_Error object on failure.
 */
function niz_user_register( $phone, $name, $send_template = false ) {
    // Normalize phone down to standard clean string format
    $phone = niz_user_normalize_phone( $phone );

    // 1. Initial Validation
    if ( empty( $phone ) || strlen( $phone ) < 8 || strlen( $phone ) > 15 ) {
        return new WP_Error( 'invalid_phone', __( 'Invalid phone number. Must be 8-15 digits.', 'enaizi-user' ) );
    }

    if ( empty( $name ) ) {
        return new WP_Error( 'empty_name', __( 'Name is required.', 'enaizi-user' ) );
    }

    $sanitized_name   = sanitize_text_field( $name );
    $existing_user_id = niz_user_check( $phone );

    // 2. Handle Existing Users (Prospects vs Active Members)
    if ( $existing_user_id ) {
        $status = get_user_meta( $existing_user_id, 'user_status', true );
        
        if ( $status === 'prospect' ) {
            // Upgrade Existing Prospect to full Member status
            wp_update_user([
                'ID'           => $existing_user_id,
                'display_name' => $sanitized_name,
                'first_name'   => $sanitized_name,
            ]);
    
            update_user_meta( $existing_user_id, 'user_status', 'member' );
            update_user_meta( $existing_user_id, 'change_password', 'yes' );
    
            if ( $send_template && function_exists( 'niz_wa_send_password' ) ) {
                $temp_pass = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
                wp_set_password( $temp_pass, $existing_user_id );
                niz_wa_send_password( $phone, $temp_pass );
            }

            // Sync or generate the CCT record for this newly upgraded prospect
            niz_sync_cct_member_record( $existing_user_id, $sanitized_name, $phone );
    
            return $existing_user_id;
        }
    
        // True Duplicate Protection Block
        return new WP_Error( 'user_exists', __( 'User already exists with this phone number.', 'enaizi-user' ) );
    }

    // 3. Create Brand New WordPress User
    $temp_pass = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    $username  = 'mfa_' . $phone;
    $fake_email = $phone . '@mfa.com';

    $user_id = wp_create_user( $username, $temp_pass, $fake_email );

    if ( is_wp_error( $user_id ) ) {
        error_log( 'niz_user_register: wp_create_user failed - ' . $user_id->get_error_message() );
        return $user_id; // Return the actual WP_Error tracking object for precise system insight
    }

    // Update profiles cleanly
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $sanitized_name,
        'first_name'   => $sanitized_name,
    ]);

    update_user_meta( $user_id, 'user_phone', $phone );
    update_user_meta( $user_id, 'change_password', 'yes' );
    update_user_meta( $user_id, 'user_status', 'member' );

    // Track Lead Source attribution natively based on transaction initiation method
    if ( $send_template && function_exists( 'niz_wa_send_password' ) ) {
        niz_wa_send_password( $phone, $temp_pass );
        update_user_meta( $user_id, 'lead_source', 'website' );
    } else {
        update_user_meta( $user_id, 'lead_source', 'whatsapp' );
    }

    // Drop tracking cookie cleanly for JS/system availability
    setcookie(
        'user_phone',
        $phone,
        time() + 2592000,
        '/',
        '',
        is_ssl(),
        false // Keep false to allow theme/front-end javascript interaction
    );
    
    // 4. Generate the JetEngine CCT Member row safely
    $cct_result = niz_sync_cct_member_record( $user_id, $sanitized_name, $phone );
    if ( is_wp_error( $cct_result ) ) {
        return $cct_result; // Intercept database entry runtime errors early
    }
    
    return $user_id;
}

/**
 * Helper function to handle JetEngine CCT record creation and validation cleanly.
 */
function niz_sync_cct_member_record( $user_id, $name, $phone ) {
    $country     = isset( $_COOKIE['country'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['country'] ) ) : '';
    $partner_id  = isset( $_COOKIE['partnerid'] ) ? intval( $_COOKIE['partnerid'] ) : 0;
    $referrer_id = isset( $_COOKIE['affiliateid'] ) ? intval( $_COOKIE['affiliateid'] ) : 14270;

    if ( empty( $referrer_id ) ) {
        $referrer_id = 14270;
    }

    // Safe lookups tracking backwards directly via the verified User ID key instance
    $existing_item_id = get_user_meta( $user_id, 'item_id', true );

    if ( empty( $existing_item_id ) ) {
        $response = add_cct_member([
            'name'        => $name,
            'phone'       => $phone,
            'status'      => 'Member',
            'user_id'     => $user_id,
            'referrer_id' => $referrer_id,
            'partner_id'  => $partner_id,
            'country'     => $country,
            'cct_created' => current_time('mysql')
        ]); 

        if ( ! empty( $response['success'] ) ) {
            update_user_meta( $user_id, 'item_id', $response['insert_id'] );
            return $response['insert_id'];
        } else {
            $error_msg = ! empty( $response['message'] ) ? $response['message'] : 'Unknown CCT Insertion Error.';
            return new WP_Error( 'cct_insertion_failed', $error_msg );
        }
    }

    return $existing_item_id;
}

/*
 * 3. Get user_info from user_id 
 *    $info = niz_user_info($user_id) 
 *    $name = $info['name'] 
 *    $phone = $info['name'] 
 *    if empty, not a member 
 */ 
function niz_user_info($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    $phone = get_user_meta($user_id, 'user_phone', true);
    return [
        'id'           => $user->ID,
        'username'     => $user->user_login,
        'email'        => $user->user_email,
        'name'         => $user->first_name,
        'phone'        => $phone,
        'registered'   => $user->user_registered,
    ];
} 

/*
 * 4. Send password 
 *    $password = niz_user_password($phone) 
 *    if empty, not a member, 
 */
function niz_user_password($phone) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_check($phone);
    
    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }
    
    // Generate random 6-digit number (e.g., 123456)
    $new_password = (string) random_int(100000, 999999);
    
    wp_set_password($new_password, $user_id);
    update_user_meta($user_id, 'change_password', 'yes');
    
    return $new_password;
} 


/* 2. Register New User 
 *    $user_id = niz_user_register($phone, $name) 
 *    if empty, already registered 
 */ 
function niz_user_create_prospect($phone, $name = '') {
    $phone = niz_user_normalize_phone($phone);

    if (empty($phone) || strlen($phone) < 8 || strlen($phone) > 15) {
        return new WP_Error(
            'invalid_phone',
            __('Invalid phone number. Must be 8-15 digits.', 'enaizi-user')
        );
    }

    $existing = niz_user_check($phone);

    if ($existing) {
        return $existing;
    }

    if (empty($name)) {
        $name = 'Prospect ' . $phone;
    }

    $username = 'mfa_' . $phone;
    $password = wp_generate_password(20);
    $email    = $phone . '@mfa.com';

    $user_id = wp_create_user(
        $username,
        $password,
        $email
    );

    if (is_wp_error($user_id)) {
        error_log(
            'niz_user_create_prospect failed: ' .
            $user_id->get_error_message()
        );

        return '';
    }

    wp_update_user([
        'ID'           => $user_id,
        'display_name' => sanitize_text_field($name),
        'first_name'   => sanitize_text_field($name),
    ]);

    update_user_meta($user_id, 'user_phone', $phone);
    update_user_meta($user_id, 'user_status', 'prospect');
    update_user_meta($user_id, 'lead_source', 'whatsapp');

    return $user_id;
}


   
/////////////////////////////////////////////////////

/**
 * Check if a user exists by phone number.
 * Returns user ID or false.
 */
function niz_user_exists_by_phone($phone) {
    $phone = niz_user_normalize_phone($phone);
    if (empty($phone)) {
        return false;
    }
    $users = get_users([
        'meta_key'     => 'user_phone',
        'meta_value'   => $phone,
        'meta_compare' => '=',
        'number'       => 1,
        'fields'       => 'ID',
    ]);
    return !empty($users) ? $users[0] : false;
}

/**
 * Get user info by ID.
 */


/**
 * Register a new user.
 * Returns user ID on success, WP_Error on failure.
 */


/**
 * Login user by phone and password.
 * Returns WP_User on success, WP_Error on failure.
 */
function niz_user_login($phone, $password) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_exists_by_phone($phone);
    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }
    $user = get_user_by('id', $user_id);
    if (wp_check_password($password, $user->user_pass, $user->ID)) {
        return $user;
    }
    return new WP_Error('incorrect_password', __('Invalid password.', 'enaizi-user'));
}

/**
 * Reset password.
 */


 
 
function niz_user_reset_password($phone) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_exists_by_phone($phone);
    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }
    $new_password = wp_generate_password(8, false);
    wp_set_password($new_password, $user_id);
    update_user_meta($user_id, 'change_password', 'yes');

    if (function_exists('niz_wa_template')) {
        niz_wa_template($phone, 'temporary_password', ['password' => $new_password]);
    } else {
        error_log('niz_user_reset_password: niz_wa_template not found – WhatsApp not sent.');
    }
    return true;
}