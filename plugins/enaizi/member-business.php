<?php

// MEMBER - CLAIM BUSINESS
add_shortcode('member_business_claim', 'member_business_claim_shortcode');
 
function member_business_claim_shortcode() {
    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    $name = get_cct_business_data($item_id, 'name');

    if (!$item_id || !$name){
        $ret = 'Please go to Business Directory to search your business.<br>';
        $ret.= '<a href="/business/" >Business Directory</a>';
        echo '<style>.claim-business { display: none; }</style>';
        return $ret;
    }
    $address = get_cct_business_data($item_id, 'address');
    $country = get_cct_business_data($item_id, 'country');
    $business_status = get_cct_business_data($item_id, 'business_status');
    $owner_id = get_cct_business_data($item_id, 'owner_id');
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    
    $ret= '<h4>' . $name . '</h4>';  
    $ret.= $address . '<br>'; 
    $ret.= $country . '<br>'; 
    
    if ($owner_id) {
        $owner = get_user_meta($owner_id, 'first_name', true);
        $ret.= '<br><b>THIS BUSINESS HAS ALREADY BEEN CLAIMED</b><br>'; 
        $ret.= 'Owner : <b>' . $owner . ' (' . $owner_id . ')</b><br><br>';
        $ret.= 'Please Login to Update your business<br><br>';
        $ret.= '<i>If you believe this is incorrect and you are the rightful owner, please contact us
 for assistance.</i>';
        echo '<style>.claim-business { display: none; }</style>';
    }
    
    return $ret;
}

// UPDATE CONTENT (/admin/business/info)
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
//add_action('fluentform/after_submission', function ($insertData, $data, $form) {
    if ((int) $form->id !== 47) {
        return;
    }

    $content = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'bcontent');
    $item_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'pid'));
    $post_id = get_cct_business_data($item_id, 'post_id');

    // Update post content
    $post_data = array(
        'ID'           => $post_id,
        'post_content' => $content,
    );
    wp_update_post($post_data);

}, 10, 3);

// CLAIM BUSINESS
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 11) {
        return;
    }

    // Sanitize phone and name inputs
    $item_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'pid'));
    $user_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'user_id'));
    $name  = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));
    $email = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'email'));
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));

    // CHECK/REGISTER USER
    $user = find_user_by_phone_or_email($email);
    if ($user){
        $user_id = $user->ID;
    }else{
        $user_id = member_register($name, $phone, $email);
    }    
  
    // UPDATE BUSINESS
    business_cct_update_field($item_id, 'business_status', 'Claimed');
    business_cct_update_field($item_id, 'owner_id', $user_id);

}, 10, 3);
 
// MEMBER - DISPLAY BUSINESS INFO
add_shortcode('member_business_info', 'member_business_info_shortcode');

function member_business_info_shortcode() {

    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    $owner_id = get_cct_business_data($item_id, 'owner_id');
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;

    if ($user_id<>$owner_id){
        // if not owner
        if ( current_user_can('administrator') || current_user_can('editor') ) {
            $item_id = 8528;
        }else{
            wp_redirect('/member/');
            exit;
        }
    } 

    $post_id = get_cct_business_data($item_id, 'post_id');
    $status = get_cct_business_data($item_id, 'business_status');
    if ($status=='' || $status=='Unclaimed'){
        update_post_meta($post_id, 'status', 'Claimed'); // Clear change password flag
        $status = get_post_meta($post_id, 'status', true);
    } 

    // CHECK FOR PAYMENT
    $paid = $_GET['status'];
    if ($paid=='Paid'){
        update_post_meta($post_id, 'status', 'Premium'); // Clear change password flag
        $data = [
            'business_status' => 'Premium'
        ];
        update_cct_business($post_id, $data);
        
        // Send Whatsapp
        $wa = "*Premium Business Listing*\n\n";
        $wa .= get_the_title($post_id);
        $wa .= "\nPayment Received";
        $admin_phone = '60177271844';
        whatsapp_send_message($admin_phone, $wa, '$media_url');
  
        $url = '/member/my-business/?pid=' . $item_id ;
        wp_redirect(home_url($url));
        exit;
    }
    
    if ($status == 'Premium') {
        echo '<style>.upgrade_premium { display: none; }</style>';
    }
  
    $name = get_cct_business_data($item_id, 'name');
    $phone = get_cct_business_data($item_id, 'phone');
    $whatsapp = get_cct_business_data($item_id, 'whatsapp');
    $address = get_cct_business_data($item_id, 'address');
    $country = get_cct_business_data($item_id, 'country');
    $business_status = get_cct_business_data($item_id, 'business_status');
    $post_url = get_permalink($post_id);

    $ret.= '<b>'. $name . '</b><br>';
    $ret.= $address . '<br>';
    $ret.= '<b>' . $country . '</b><br>';
    $ret.= 'Status : <b>' . $status . '</b><br>';
    $ret.= '<a href="' . esc_url($post_url) . '" target="_blank" rel="noopener noreferrer">View Business Page</a>';

    return $ret;
}

// MEMBER - DISPLAY BUSINESS CONTENT
add_shortcode('member_business_content', 'member_business_content_shortcode');
 
function member_business_content_shortcode() {
    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    if (empty($item_id)) {
        // Redirect safely to member page if no pid provided
        //wp_safe_redirect(home_url('/member'));
        //exit; 
        return;
    }
    
    $post_id = get_cct_business_data($item_id, 'post_id');
    $content = get_post_field( 'post_content', $post_id );
 
    // Apply WordPress formatting (shortcodes, embeds, paragraphs, etc.)
    $content = apply_filters( 'the_content', $content );
    
    $post = get_post($post_id);
    $title = $post->post_title;
    $image_url = get_the_post_thumbnail_url($post_id, 'large');

    if ($image_url=='') {
        $image_url = 'http://masjid4all.com/wp-content/uploads/2025/08/Sample-Featured-Image.jpg';
    }
    $image = '<img src="' . esc_url($image_url) . '" alt="Business Image" style="max-width:100%; height:auto;" />';

 
    $ret.= $image;
    $ret.= '<h4>' . $title . '</h4>';
    $ret.= $content;
     
    wp_reset_postdata();
    $post = $original_post; // restore
    return $ret;
}
 
// BUSINESS CONTENT
add_filter('fluentform/editor_shortcode_callback_bintroduction', function ($value, $form) {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    $content = '';

    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'business') {
            //$content = preg_replace('/[\r\n]{2,}/', "\n", $post->post_content); // Collapse multiple newlines
            $content = $post->post_content; // Collapse multiple newlines
            $content = preg_replace('/[\r\n]{2,}/', "", $content);
            //$content = trim($content);
        }
    }
    
    return $content;
    
}, 10, 2);

// BUSINESS TITLE
add_filter('fluentform/editor_shortcode_callback_btitle', function ($value, $form) {
    $item_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
    $title = get_cct_business_data($item_id, 'name');
   
    return $title;
    
}, 10, 2);

// BUSINESS STATUS
add_filter('fluentform/editor_shortcode_callback_bstatus', function ($value, $form) {
    $item_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
    $status = get_cct_business_data($item_id, 'business_status');

    return $status;
    
}, 10, 2);



// BUSINESS TAGS
add_filter('fluentform/editor_shortcode_callback_btags', function ($value, $form) {
    $item_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
    $post_id = get_cct_business_data($item_id, 'post_id');
    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'business') {
            $tags = get_the_tags($post_id);
        }
    }
    $tags = get_the_tags($post_id);

    if ($tags && !is_wp_error($tags)) {
        $tag_names = array_map(function($tag) {
            return $tag->name;
        }, $tags);
    
        $tags = implode(', ', $tag_names);
    }
    
    return $tags;
    
}, 10, 2);

// BUSINESS IMAGE
add_filter('fluentform/editor_shortcode_callback_bimage', function ($value, $form) {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    $image = '';

    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'business') {
            $image_url = get_the_post_thumbnail_url($post_id, 'full');
            if (!empty($image_url)) {
                $image = '<img src="' . esc_url($image_url) . '" alt="Business Image" style="max-width:100%; height:auto;" />';
            }
        }
    }

    // Use default image if no featured image found
    if (empty($image)) {
        $default_url = 'http://masjid4all.com/wp-content/uploads/2025/08/Sample-Featured-Image.jpg';
        $image = '<img src="' . esc_url($default_url) . '" alt="Default Business Image" style="max-width:100%; height:auto;" />';
    }
    
    return $image_url; //$image;
    
}, 10, 2);

/// UPDATE CONTENT
add_action('fluentform_submission_inserted', function ($entryId, $formId) {

    // Get the submitted form data
    $entry = wpFluent()->table('fluentform_submissions')->where('id', $entryId)->first();
    $data = json_decode($entry->response, true);

    $post_id = intval($data['post_id']);
    $post_content = wp_kses_post($data['bintroduction']);
    $post_title = $data['btitle'];
    $post_excerpt = $data['bexcerpt'];
    if ($post_content!=''){
        // Update post content
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $post_content ,
        ]);
    }    
    if ($post_title!=''){
        // Update post title
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $post_title ,
            'post_excerpt' => $post_excerpt ,
        ]);
    }   
  

}, 10, 2);

// BUSINESS INFO
//FLUENTFORM UPDATE BUSINESS CCT (FORM ID : 22)
add_action('fluentform/submission_inserted', 'member_update_business_info', 20, 3);
function member_update_business_info($entryId, $formData, $form) {
    global $wpdb;

    $targetFormId = 22;
    if ($form->id != $targetFormId) {
        return;
    }
    
    $item_id = $formData['itemID'];
    $bname = wp_kses_post($formData['bname']) ?? '';
    $badd = $formData['badd'] ?? '';
    $bcountry = $formData['bcountry'] ?? '';
    $bphone = $formData['bphone'] ?? '';
    $bws = $formData['bws'] ?? '';
    $bemail = $formData['bemail'] ?? '';
    $bweb = $formData['bweb'] ?? '';
    $bfb = $formData['bfb'] ?? '';
    $binsta = $formData['binsta'] ?? '';
    $btiktok = $formData['btiktok'] ?? '';
    $blinkedin = $formData['blinkedin'] ?? '';
    
    //Check current business_status
    $current_status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT business_status FROM wp_jet_cct_business WHERE _ID = %d",
            $item_id
        )
    );

    
    //If current is null/New/Pending, then set to Claimed
    if (is_null($current_status) || $current_status === 'New' || $current_status === 'Pending') {
        $new_status = 'Claimed';
    } else {
        $new_status = $current_status;
    }
    
    $wpdb->update(
        'wp_jet_cct_business',
        [
            'name' => $bname,
            'address' => $badd,
            'country' => $bcountry,
            'phone' => $bphone,
            'whatsapp' => $bws,
            'email' => $bemail,
            'website' => $bweb,
            'fb' => $bfb,
            'insta' => $binsta,
            'tiktok' => $btiktok,
            'linkedin' => $blinkedin,
            'business_status' => $new_status,
        ],
        ['_ID' => $item_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );
    
    // update post, remove updated 
    //$post_id = get_cct_business_data($item_id, 'post_id');
    //update_post_meta($post_id, 'updated', ''); // Clear change password flag
    
    // SEND WHATSAPP
    
    
}

// INSERT TAGS
add_action('fluentform_submission_inserted', function ($entryId, $formData, $form) {
    if ($form->id != 44) {
        return;
    }

    $item_id = intval($formData['pid']); // or get_post_id however you store it
    $post_id = business_cct_get_field($item_id, 'post_id');
    $title   = sanitize_text_field($formData['btitle']);
    $excerpt = sanitize_text_field($formData['bexcerpt']);
    $tags    = $formData['btags']; // e.g. comma-separated from a text input

    // UPDATE BUSINESS NAME
    business_cct_update_field($item_id, 'name', $title);
    // update post
    wp_update_post([
        'ID'           => $post_id,
        'post_title'   => $title,
        'post_excerpt' => $excerpt
    ]);
    
    // Update post tags
    $tags_array = array_map('trim', explode(',', $tags));
    wp_set_object_terms($post_id, $tags_array, 'post_tag');

}, 10, 3); 

// INSERT IMAGE/BANNER
add_action('fluentform_submission_inserted', function ($entryId, $formData, $form) {
    if ($form->id != 43) {
        return;
    }

    $item_id = intval($formData['pid']); // or get_post_id however you store it
    //$post_id = business_cct_get_field($item_id, 'post_id');
    $post_id = get_cct_business_data($item_id, 'post_id');
    $banner  = $formData['banner'];

    if (is_array($banner)) {
        $file_url = $banner['url'] ?? reset($banner);
    } else {
        $file_url = $banner;
    }
    
    $file_url = strtok($file_url, '?'); // remove query params
    $desc = 'Masjid4All Banner';
    $image = media_sideload_image( $file_url, $post_id, $desc,'id' );

    set_post_thumbnail( $post_id, $image );

}, 10, 3);




