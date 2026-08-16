<?php

/**
  SHORTCODES
  - business_owner_info
  - business_info
  - member_mybusiness
  
  FORMS
  - Register as Business Owner
  
  FUNCTIONS
  
  
    
    
*/

// SHORTCODES ////////////////////////////////////////

// Display Business Owner
add_shortcode( 'business_owner_info', 'business_owner_info_shortcode' );
function business_owner_info_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user category safely
    $user_id = $current_user->ID;
    $item_id = get_field_from_userid($user_id, '_ID');
    $owner   = get_field_from_userid($user_id, 'business_owner');

    $ret = '';
    //$owner = 'Yes';
    if ($owner == 'Yes') {
        $ret .= 'Claim and manage your business listing to connect with the community and increase visibility through our Business Directory';
        echo '<style>.btn_business_owner { display: none !important; }</style>';
    }else{
        $ret .= '<b>Are you a business owner? </b>Claim and manage your business listing to connect with the community and increase visibility through our Business Directory';
        echo '<style>.btn_business_listing { display: none !important; }</style>';
    }

    return $ret;
}

// Display Business Info
add_shortcode( 'business_info', 'business_info_shortcode' );
function business_info_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user category safely
    $user_id = $current_user->ID;
    $name = get_field_from_userid($user_id, 'name');
    //$company = get_field_from_userid($user_id, 'company_name');

    $ret = '';
    $ret .= '<b>' . $name . '<b><br>';
    //$ret .= '<b>' . $company . '<b><br>';

    return $ret;
}

// Display Business Listing
add_shortcode( 'business_listing', 'business_listing_shortcode' );
function business_listing_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user category safely
    $user_id = $current_user->ID;
    $name = get_field_from_userid($user_id, 'name');
    //$company = get_field_from_userid($user_id, 'company_name');

    $ret = 'No business listed';
    //$ret .= '<b>' . $name . '<b><br>';
    //$ret .= '<b>' . $company . '<b><br>';

    return $ret;
}

// FORMS /////////////////////////////////////////////
// Register as Business Owner 
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Form ID 5 - Register as Business Owner
    if ((int) $form->id !== 56) {
        return;
    }

    // Sanitize phone and name inputs
    $user_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'user_id'));
    $company = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'company'));

    // Update Field
    update_field_from_userid($user_id, 'business_owner', 'Yes');
    update_field_from_userid($user_id, 'company_name', $company);
    
    // Redirect to Business Owner
    //wp_redirect(home_url('/member/business-owner/'));
    //exit;
    

}, 10, 3);



//Display Member Info
add_shortcode('member_mybusiness', 'member_mybusiness_shortcode');
 
function member_mybusiness_shortcode() {
    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    if (empty($item_id)) {
        // Redirect safely to member page if no pid provided
        echo 'XXX NO PID';
        wp_safe_redirect(home_url('/member'));
        exit;
    }
    
    $post_id = get_cct_business_data($item_id, 'post_id');

    $name = get_cct_business_data($item_id, 'name');
    $tags = get_cct_business_data($item_id, 'tags');
    $email = get_cct_business_data($item_id, 'email');
    $website = get_cct_business_data($item_id, 'website');
    $phone = get_cct_business_data($item_id, 'phone');
    $whatsapp = get_cct_business_data($item_id, 'whatsapp');
    $address = get_cct_business_data($item_id, 'address');
    $country = get_cct_business_data($item_id, 'country');
    $page_url = get_cct_business_data($item_id, 'page_url');
    $page_url = '<a href="' . $page_url . '" target="_blank" rel="noopener noreferrer">Visit Webpage</a>';

    $business_status = get_cct_business_data($item_id, 'business_status');
    
    $owner_id = get_cct_business_data($item_id, 'owner_id');
    $user_info = get_userdata($owner_id);
    if ($user_info) {
        if ($user_info) {
            $url = '/admin/member/update/?pid=' . $owner_id;
            $owner = get_user_meta($owner_id, 'first_name', true);
            $owner = '<a href="' . $url . '" target="" rel="noopener noreferrer">' . $owner . '</a>';
        }
    }

    if ($country=='Malaysia' AND $whatsapp==''){
        $clean = preg_replace('/[^0-9]/', '', $phone);
        // Convert international format +60 -> 0
        if (strpos($clean, '60') === 0) {
            $clean = '0' . substr($clean, 2);
        }
        // Check if it starts with 01
        if (strpos($clean, '01') === 0) {
            $whatsapp = $phone;
            // UPDATE CCT BUSINESS
            $data = [
                'whatsapp' => $whatsapp
            ];
            //$ret.= 'WA ' . $whatsapp . '<br>';
            update_cct_business($post_id, $data);
        }   
    }elseif ($country=='United Kingdom' AND $whatsapp==''){
        $clean = preg_replace('/[^0-9]/', '', $phone);
        // Check if it starts with 447
        if (strpos($clean, '447') === 0) {
            $whatsapp = $phone;
            // UPDATE CCT BUSINESS
            $data = [
                'whatsapp' => $whatsapp
            ];
            //$ret.= 'WA ' . $whatsapp . '<br>';
            update_cct_business($post_id, $data);
        }   
        
    }
    $ret.= '<b>'. $name . '</b><br>';
    $ret.= ''. $address . '<br>';
    $ret.= '<b>'. $country . '</b><br>';
    $ret.= 'Tags : <b>'. $tags . '</b><br>';
    $ret.= $page_url . '<br><br>';
    
    $ret.= '<table>';
    $ret.= '<tr><td style="width:100px;">PostID</td><td>' . $post_id . '/' . $item_id . '</td></tr>';
    $ret.= '<tr><td>Email</td><td>' . $email . '</td></tr>';
    $ret.= '<tr><td>Website</td><td>' . $website . '</td></tr>';
    $ret.= '<tr><td>Phone</td><td>' . $phone . '</td></tr>';
    $ret.= '<tr><td>Whatsapp</td><td>' . $whatsapp . '</td></tr>';
    $ret.= '<tr><td>Status</td><td>' . $business_status . '</td></tr>';

    $ret.= '</table>';
    
    
    return $ret;
}


