<?php

/**
 * Business Functions
 *  1. mfa_get_business_field
 *  2. mfa_update_business_field
 *  3. mfa_get_business_data
 *  4. mfa_update_business_data
**/


// ============================================
// 1. GET BUSINESS FIELD
// ============================================ 
function mfa_get_business_field($item_id, $field) {
    global $wpdb;

    if (empty($item_id) || empty($field)) {
        return null;
    }

    // Sanitize field name for safety (prevents SQL injection on the column name)
    $field = sanitize_key($field);
    $table = $wpdb->prefix . 'jet_cct_business';
    
    $result = $wpdb->get_var(
        $wpdb->prepare("SELECT {$field} FROM {$table} WHERE _ID = %d", absint($item_id))
    );

    return ($result !== null) ? $result : null;
}

// ============================================
// 2. UPDATE BUSINESS FIELD
// ============================================ 
function mfa_update_business_field($item_id, $field, $value) {
    global $wpdb;

    if (empty($item_id) || empty($field)) {
        return false;
    }

    $format = is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');

    return $wpdb->update(
        $wpdb->prefix . 'jet_cct_business',
        [ $field => $value ],             // Data to update
        [ '_ID' => absint($item_id) ],    // Where clause
        [ $format ],                      // Dynamic format for the value
        [ '%d' ]                          // Format for the _ID
    );
}

// ============================================
// 3. GET BUSINESS DATA
// ============================================ 
function mfa_get_business_data($item_id) {
    global $wpdb;

    if (empty($item_id)) {
        return "Error: item_id required.";
    }

    $table = $wpdb->prefix . 'jet_cct_business';

    // Fetch the entire row as an associative array
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE _ID = %d", absint($item_id)),
        ARRAY_A
    );

    return !empty($row) ? $row : "Error: Record not found.";
}

// ============================================
// 4. UPDATE BUSINESS DATA
// ============================================ 
function mfa_update_business_data($item_id, $data) {
    global $wpdb;

    if (empty($item_id) || empty($data) || !is_array($data)) {
        return "Error: item_id and data are required.";
    }

    $table = $wpdb->prefix . 'jet_cct_business';
    
    // Map data types accurately via dynamic parsing
    $formats = [];
    foreach ($data as $key => $value) {
        if (is_int($value)) {
            $formats[] = '%d';
        } elseif (is_float($value)) {
            $formats[] = '%f';
        } else {
            $formats[] = '%s';
        }
    }

    $result = $wpdb->update(
        $table, 
        $data, 
        ['_ID' => absint($item_id)], 
        $formats, 
        ['%d']
    );

    if ($result === false) {
        return "Error updating record: " . $wpdb->last_error;
    } elseif ($result === 0) {
        return "No changes made or record not found.";
    } else {
        return "Record updated successfully.";
    }
}

/////////////////////////////////////////////////
// FLUENTFORM - UPDATE FORM
/////////////////////////////////////////////////

// UPDATE BUSINESS
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    global $wpdb;
    
    // Ensure this only runs for Form ID 68
    if ((int) $form->id !== 68) { 
        return;
    }

    // Retrieve and sanitize input
    $post_id  = intval(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'post_id'));
    $user_id  = intval(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'user_id'));
    
    $name     = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));
    $intro    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'introduction'));
    $address  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'address'));
    $city     = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'city'));
    $country  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'country'));
    $phone    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $whatsapp = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'whatsapp'));
    
    $website  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'website'));
    $email    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'email'));
    $fb       = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'facebook'));
    $insta    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'instagram'));
    $linkedin = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'linkedin'));
    $tiktok   = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'tiktok'));
    
    // Safety check: Abort if no post ID was passed
    if (empty($post_id)) {
        return;
    }

    // UPDATE CCT TABLE 
    $item_id = get_post_meta($post_id, 'item_id', true);
    
    // Safety check: Abort if this post doesn't have an associated JetEngine CCT record
    if (empty($item_id)) {
        return;
    }

    $table = $wpdb->prefix . 'jet_cct_business';
    
    // Prepare the clean data array for the database insertion
    $update_data = array(
        'name'         => $name,
        'introduction' => $intro,
        'address'      => $address,
        'city'         => $city,
        'country'      => $country,
        'phone'        => $phone,
        'whatsapp'     => $whatsapp,
        'website'      => $website,
        'email'        => $email,
        'fb'           => $fb,
        'insta'        => $insta,
        'linkedin'     => $linkedin,
        'tiktok'       => $tiktok
    );
    
    // Execute the database update
    $wpdb->update(
        $table,
        $update_data,
        array('_ID' => $item_id), // WHERE clause matches the CCT Item ID
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'), // Data formats (all strings)
        array('%d') // WHERE format (integer)
    );

    // Keep the native WordPress Post Title synced with the new Business Name
    if (!empty($name)) {
        wp_update_post(array(
            'ID'         => $post_id,
            'post_title' => $name
        ));
    }

}, 10, 3);

/////////////////////////////////////////////////
// FLUENTFORM - SHORTCODE 
/////////////////////////////////////////////////

// Address
add_filter('fluentform/editor_shortcode_callback_biz_address', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'address');
    return $dynamicValue;
}, 10, 2);

// Introduction
add_filter('fluentform/editor_shortcode_callback_biz_introduction', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'introduction');
    return $dynamicValue;
}, 10, 2);

// City
add_filter('fluentform/editor_shortcode_callback_biz_city', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'city');
    return $dynamicValue;
}, 10, 2);

// Country
add_filter('fluentform/editor_shortcode_callback_biz_country', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'country');
    return $dynamicValue;
}, 10, 2);

// Phone
add_filter('fluentform/editor_shortcode_callback_biz_phone', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'phone');
    return $dynamicValue;
}, 10, 2);

// Whatsapp
add_filter('fluentform/editor_shortcode_callback_biz_whatsapp', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'whatsapp');
    return $dynamicValue;
}, 10, 2);


// Website
add_filter('fluentform/editor_shortcode_callback_biz_website', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'website');
    return $dynamicValue;
}, 10, 2);

// Email
add_filter('fluentform/editor_shortcode_callback_biz_email', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'email');
    return $dynamicValue;
}, 10, 2);

// Facebook
add_filter('fluentform/editor_shortcode_callback_biz_facebook', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'fb');
    return $dynamicValue;
}, 10, 2);

// Instagram
add_filter('fluentform/editor_shortcode_callback_biz_instagram', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'insta');
    return $dynamicValue;
}, 10, 2);

// LinkedIn
add_filter('fluentform/editor_shortcode_callback_biz_linkedin', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'linkedin');
    return $dynamicValue;
}, 10, 2);

// TikTok
add_filter('fluentform/editor_shortcode_callback_biz_tiktok', function ($value, $form) {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true );
    $dynamicValue = mfa_get_business_field($item_id, 'tiktok');
    return $dynamicValue;
}, 10, 2);

