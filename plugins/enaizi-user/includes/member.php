<?php

/**
 * Functions
 * - niz_user_member_cct($user_id)     // get or create cct_member
 * - niz_user_itemid_by_phone($phone)  // get item_id from phone
 * 
 * - niz_user_field_by_itemid($item_id,$field)  
 * - niz_user_field_by_userid($user_id,$field)
 * - niz_user_update_field($user_id, $field, $value)
 * - niz_user_create_cct_member($data)
 * - niz_user_update_cct_member($item_id,$data)
 * 
 * - member_cct_data($item_id, $field) x
 * - get_cct_member_data($user_id, $field)
 * - add_cct_member($data) x
 * - update_cct_member($item_id, $user_data)
 * 
 * Fluent Form
 * - Update Member Info (17)
 * 
 * Fluentform Filters
 * - filter - uname
 * - filter - Phone
 * - filter - Email
 * - filter - Country
 * - filter - Sex
 * - filter - Birthdate
 */

// SHORTCODES /////////////////////////////////////////////


// FUNCTIONS moved to mfa-core/includes/member-cct-core.php (2026-08-10 mfa-core consolidation):
// niz_user_member_cct(), niz_user_itemid_by_phone(), niz_user_field_by_itemid(),
// niz_user_field_by_userid(), niz_user_update_field(), member_cct_data(),
// add_cct_member(), update_cct_member(), get_cct_member_data()

// FLUENT FORM ///////////////////////////////////////////////////

/**
 * Update Member Info (17)
 */
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 17) {
        return;
    }

    $name      = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'uname'));
    $email     = sanitize_email(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'uemail'));
    $country   = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'ucountry'));
    $sex       = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'usex'));
    $birthdate = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'ubirthdate'));
    
    $date = DateTime::createFromFormat('d/m/Y', $birthdate);
    if (!$date) {
        $birthdate = '';
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_member';
    
    $item_id = $wpdb->get_var(
        $wpdb->prepare("SELECT _ID FROM {$table} WHERE user_id = %d", $user_id)
    );
    
    if (!$item_id) {
        return; 
    }

    $user_data = [
        'name'      => $name,
        'email'     => $email,
        'country'   => $country,
        'sex'       => $sex,
        'birthdate' => $birthdate
    ];

    if (function_exists('update_cct_member')) {
        update_cct_member($item_id, $user_data);
    }
 
}, 10, 3);


/**
 * Update Namecard (63)
 */
 
// Switch the hook to submission_inserted
add_action('fluentform/submission_inserted', function ($entryId, $formData, $form) {
    
    // 1. Target your specific form ID (e.g., 5)
    if ($form->id !== 63) { // Replace with your actual form ID
        return;
    }

    // 2. Grab the banner upload data array
    $banner = \FluentForm\Framework\Helpers\ArrayHelper::get($formData, 'banner');
    if (empty($banner) || !is_array($banner)) {
        return;
    }

    // 3. Get your target Post ID
    // (Assuming $user_id is available in your scope, or get current logged in user)
    $user_id = get_current_user_id(); 
    $post_id = niz_user_field_by_userid($user_id, 'post_id');

    if (!$post_id) {
        error_log("FluentForm Sideload Error: Post ID not found for User " . $user_id);
        return;
    }

    // 4. Directly get the absolute server path using Fluent Forms' upload structure
    $raw_url = reset($banner); 
    $wp_upload_dir = wp_upload_dir();

    // Reconstruct path by converting the URL path to the base directory path
    $relative_path = str_replace($wp_upload_dir['baseurl'], '', $raw_url);
    $file_path = $wp_upload_dir['basedir'] . $relative_path;

    // Remove any query strings or trailing hashes Fluent Forms adds to the URL string
    $file_path = explode('?', $file_path)[0];

    // Debug tracking to your logs
    error_log("Target Post ID: " . $post_id);
    error_log("Resolved Server File Path: " . $file_path);

    // 5. If the file physically exists on the disk, sideload it into WordPress Media
    if (file_exists($file_path)) {
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file_array = [
            'name'     => basename($file_path),
            'tmp_name' => $file_path
        ];

        // This copies the file into WP Media and handles registration
        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (!is_wp_error($attachment_id)) {
            // Success! Set the featured image
            set_post_thumbnail($post_id, $attachment_id);
            error_log("Successfully set Featured Image ID " . $attachment_id . " to Post " . $post_id);
        } else {
            error_log("WP Sideload Error: " . $attachment_id->get_error_message());
        }
    } else {
        error_log("FluentForm Sideload Error: File does not exist at path: " . $file_path);
    }
}, 10, 3);

 
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 63) {
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    $name      = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'uname'));
    $job_title = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'job_title'));
    $intro     = (\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'introduction'));
    $phone     = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_phone'));
    $whatsapp  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_wa'));
    
    $website   = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_website'));
    $email     = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_email'));

    $linkedin  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_linkedin'));
    $facebook  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_fb'));

    $twitter   = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_x'));
    $tiktok    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_tiktok'));
 
    $instagram = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_instagram'));
    $youtube   = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_youtube'));
 
 /*
 
    // Grab the banner data using the ArrayHelper
    $banner = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'banner');

    if (empty($banner) || !is_array($banner)) {
        return $insertData;
    }

    // 3. Define the Post ID you are trying to attach this featured image to
    $post_id   = niz_user_field_by_userid($user_id, 'post_id');

    // 4. Extract the relative path piece (it contains the file name at the end)
    $raw_string = reset($banner); 
    $file_name = basename(parse_url($raw_string, PHP_URL_PATH));

    // 5. Build the server's absolute folder path
    $wp_upload_dir = wp_upload_dir();
    $target_dir = $wp_upload_dir['basedir'] . '/fluentform/' . $form->id . '/';

    // Search the multi-level hashed directories created by Fluent Forms for our file
    $file_path = '';
    if (file_exists($target_dir)) {
        $di = new RecursiveDirectoryIterator($target_dir);
        foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
            if (basename($filename) === $file_name) {
                $file_path = $filename;
                break;
            }
        }
    }

    // If the file can't be matched on the server hardware, exit safely
    if (empty($file_path) || !file_exists($file_path)) {
        return $insertData;
    }

    // 6. Pull in the core WordPress files required to handle media injections
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Create a mock $_FILES structure for WordPress to process
    $file_array = [
        'name'     => basename($file_path),
        'tmp_name' => $file_path
    ];

    // 7. Hand the server file directly to WordPress to create a Media Library ID
    $attachment_id = media_handle_sideload($file_array, $post_id);

    // 8. If no error occurs, tie it directly to your Post as the Featured Image
    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
    }

 */
 

    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_member';
    
    $item_id = $wpdb->get_var(
        $wpdb->prepare("SELECT _ID FROM {$table} WHERE user_id = %d", $user_id)
    );
    
    if (!$item_id) {
        return; 
    }

    $user_data = [
        'name'                => $name,
        'job_title'           => $job_title,
        'introduction'        => $intro,
        'affiliate_phone'     => $phone,
        'affiliate_wa'        => $whatsapp,
        'affiliate_website'   => $website,
        'affiliate_email'     => $email,
        'affiliate_linkedin'  => $linkedin,
        'affiliate_fb'        => $facebook,
        'affiliate_x'         => $twitter,
        'affiliate_tiktok'    => $tiktok,
        'affiliate_instagram' => $instagram,
        'affiliate_youtube'   => $youtube,
    ];
 
    if (function_exists('update_cct_member')) {
        update_cct_member($item_id, $user_data);
    }
 
}, 10, 3);

// FLUENT FORM FILTERS //////////////////////////////////

/**
 * Shared Core Helper function to clean filter redundant footprints
 */ 
function niz_get_fluentform_filter_value($field, $strip_pw_domain = false) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '';
    }

    $value = niz_user_field_by_userid($user_id, $field);

    if ($strip_pw_domain && strpos($value, 'pw.com') !== false) {
        return '';
    }

    return $value;
}

function niz_get_fluentform_filter_image($field) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '';
    }
    $image_id = niz_user_field_by_userid($user_id, $field);
    $image_data = wp_get_attachment_image_src( $image_id, 'full' );

    if ( $image_data ) {
        $image_url = $image_data[0]; // The URL is always the first element in the array
        //echo $image_url;
    }
    return $image_url;
}

// filter - Name
add_filter('fluentform/editor_shortcode_callback_uname', function ($value, $form) {
    return niz_get_fluentform_filter_value('name');
}, 10, 2);

// filter Phone
add_filter('fluentform/editor_shortcode_callback_uphone', function ($value, $form) {
    return niz_get_fluentform_filter_value('phone');
}, 10, 2);

// filter Email
add_filter('fluentform/editor_shortcode_callback_uemail', function ($value, $form) {
    return niz_get_fluentform_filter_value('email', true);
}, 10, 2);

// filter Country
add_filter('fluentform/editor_shortcode_callback_ucountry', function ($value, $form) {
    return niz_get_fluentform_filter_value('country');
}, 10, 2);

// filter Sex
add_filter('fluentform/editor_shortcode_callback_usex', function ($value, $form) {
    return niz_get_fluentform_filter_value('sex');
}, 10, 2);

// filter Birthdate
add_filter('fluentform/editor_shortcode_callback_ubirthdate', function ($value, $form) {
    return niz_get_fluentform_filter_value('birthdate');
}, 10, 2);
 
// filter Tagline
add_filter('fluentform/editor_shortcode_callback_job_title', function ($value, $form) {
    return niz_get_fluentform_filter_value('job_title');
}, 10, 2);
 
// filter Introduction
add_filter('fluentform/editor_shortcode_callback_introduction', function ($value, $form) {
    return niz_get_fluentform_filter_value('introduction');
}, 10, 2); 

// filter affiliate_phone
add_filter('fluentform/editor_shortcode_callback_affiliate_phone', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_phone');
}, 10, 2); 

// filter affiliate_wa
add_filter('fluentform/editor_shortcode_callback_affiliate_wa', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_wa');
}, 10, 2); 

// filter affiliate_website
add_filter('fluentform/editor_shortcode_callback_affiliate_website', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_website');
}, 10, 2);

// filter affiliate_email
add_filter('fluentform/editor_shortcode_callback_affiliate_email', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_email');
}, 10, 2);

// filter affiliate_fb
add_filter('fluentform/editor_shortcode_callback_affiliate_fb', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_fb');
}, 10, 2);

// filter affiliate_linkedin
add_filter('fluentform/editor_shortcode_callback_affiliate_linkedin', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_linkedin');
}, 10, 2);

// filter affiliate_x
add_filter('fluentform/editor_shortcode_callback_affiliate_x', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_x');
}, 10, 2);

// affiliate_tiktok
add_filter('fluentform/editor_shortcode_callback_affiliate_tiktok', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_tiktok');
}, 10, 2);

// affiliate_youtube
add_filter('fluentform/editor_shortcode_callback_affiliate_youtube', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_youtube');
}, 10, 2);

// affiliate_instagram
add_filter('fluentform/editor_shortcode_callback_affiliate_instagram', function ($value, $form) {
    return niz_get_fluentform_filter_value('affiliate_instagram');
}, 10, 2);

// affiliate_banner
add_filter('fluentform/editor_shortcode_callback_affiliate_banner', function ($value, $form) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '';
    }

    $post_id = niz_user_field_by_userid($user_id, 'post_id');
    $image_url = get_the_post_thumbnail_url($post_id, 'full');

    return $image_url;
}, 10, 2);

 
 













