<?php

// Status keahlian
function wapi_register($phone){

    $item_id = find_memberid_by_phone( $phone );
 
    $name   = niz_user_field_by_itemid($item_id, 'name');
    $status = niz_user_field_by_itemid($item_id, 'status');

    $text  = "*WELCOME TO MASJID4ALL*  \n\n";    

    if ($status=='Prospect'){ 
        // Generate a secure 6-digit temporary password and update
        $pwd = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $update_result = wp_update_user([
            'ID'        => $user_id,
            'user_pass' => $pwd
        ]);
        // Clear "change password" flag
        update_user_meta($user_id, 'change_password', '');
        $text .= "*Please Login to Activate your Account*\n\n";;
        $text .= "Whatsapp Number : *" . $phone . "*\n";
        $text .= "Temporary Password : *" . $pwd . "*\n\n";
        $text .= "👉 Community Page\nhttps://masjid4all.com/member/\n\n";
        $text .= "_By logging in, you acknowledge and agree to our *Privacy Policy* and *Terms of Service*._\n\n";
        $text .= "https://masjid4all.com/privacy-policy\n";
        $text .= "https://masjid4all.com/terms-of-service\n\n";
        $text .= "Thank You\n*Masjid4All*";
         
        // Send Email to Admin
        $msg = 'Nama : ' . $name . '<br>Phone : ' . $phone;
        wapi_send_mail('Ahli Baru',$msg);
    
    }else{ 
 
        $text .= "You are already registered.\n\n";       
        $text .= "*" . $name . "*\n";
        $text .= "Whatsapp Number : *" . $phone . "*\n";
        $text .= "Status : " . $status . "\n\n";
        $text .= "👉 Community Page\nhttps://masjid4all.com/member/\n\n";
 
        $text .= "*Forgot password?*\n_Type_ *Password* _to reset and get temporary password_\n\n";

    }
  
    // Send Whatsapp
    wapi_send_text($phone, $text);

}

// Next Step
function wapi_next_step($phone){
    global $wpdb;
    $phone = preg_replace('/\D/', '', $phone);
    
    $user_id = find_userid_by_phone( $phone );
    $item_id = find_memberid_by_phone( $phone );
    $name    = niz_user_field_by_itemid($item_id, 'name');
    $status  = niz_user_field_by_itemid($item_id, 'status');
    
    // Generate a secure 6-digit temporary password
    $pwd = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Update the user's password
    $update_result = wp_update_user([
        'ID'        => $user_id,
        'user_pass' => $pwd
    ]);

    // Clear "change password" flag
    update_user_meta($user_id, 'change_password', '');

    // Send Temporary Password via WhatsApp
    //$pwd  = "123456";
    $msg  = "*WELCOME TO MASJID4ALL*\n\n";
    $msg .= "*" . $name . "*\n\n";
    $msg .= "*Please Login and Change Password*\n";
    $msg .= "👉 https://masjid4all.com/member/\n\n";
    $msg .= "Whatsapp Number : *" . $phone ."*\n";
    $msg .= "Temporary Password : *" . $pwd . "*\n\n";
    //$msg .= "*Step 2 - Find nearest Mosque*\n";
    //$msg .= "👉 https://masjid4all.com/mosque/\n\n";
    //$msg .= "*Step 3 - Find nearest Business*\n";
    //$msg .= "👉 https://masjid4all.com/business/\n\n";

    wapi_send_text($phone, $msg);
    
 
    // Log Activity
    //member_activity_update($member_id, 'Member', 'Reset Password');
    
}

// Change Password
function wapi_password($phone){
    global $wpdb;
    $phone = preg_replace('/\D/', '', $phone);
    // Find user by phone number
    
    $user_id = find_userid_by_phone( $phone );
    $item_id = find_memberid_by_phone( $phone );
    $name    = niz_user_field_by_itemid($item_id, 'name');
    $status  = niz_user_field_by_itemid($item_id, 'status');
    
    // Generate a secure 6-digit temporary password
    $pwd = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Update the user's password
    $update_result = wp_update_user([
        'ID'        => $user_id,
        'user_pass' => $pwd
    ]);

    // Clear "change password" flag
    update_user_meta($user_id, 'change_password', '');

    // Send Temporary Password via WhatsApp
    //$pwd  = "123456";
    $msg  = "*PASSWORD RESET*\n\n";
    $msg .= "Whatsapp Number : *" . $phone ."*\n";
    $msg .= "Temporary Password : *" . $pwd . "*\n\n";
    $msg .= "_*Please change your password upon login.*_\n\n";
    $msg .= "👉 Community Page\nhttps://masjid4all.com/member/";
    wapi_send_text($phone, $msg);
    
 
    // Log Activity
    //member_activity_update($member_id, 'Member', 'Reset Password');
    
}




function wapi_get_member_id($phone){
    $user = find_user_by_phone($phone);
    $user_id = $user->ID;
	$member_id = get_user_meta($user_id, 'member_id', true);
    $name = $user->first_name;
    if ($member_id == ''){
        $member_id == "1496";
    }
    return $member_id;
}




