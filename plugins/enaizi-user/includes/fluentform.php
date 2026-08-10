<?php

//////////////////////////////////////////
// Update User - validate form (FIXED)  //
//////////////////////////////////////////
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Only process form ID 17 - Update Profile
    if ((int) $form->id !== 17) {
        return;
    }

    // 1. Dapatkan input dari borang
    $name = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'uname'));
    $email = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'uemail'));
    $country = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'ucountry'));
    $sex = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'usex'));
    $birthdate = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'ubirthdate'));
    
    // (PILIHAN) Jika anda mahu kira umur, kira sahaja tetapi jangan masukkan dalam database
    $date = DateTime::createFromFormat('d/m/Y', $birthdate);
    if (!$date) {
        $birthdate = '';
    }

    $user_id = get_current_user_id();
    
    global $wpdb;
    
    // 2. DAPATKAN _ID (ITEM ID CCT) YANG SEBENAR BERDASARKAN USER_ID
    $item_id = $wpdb->get_var(
        $wpdb->prepare("SELECT _ID FROM wp_jet_cct_member WHERE user_id = %d", $user_id)
    );
    //echo $item_id . "<----item_id";
    // Jika pengguna ini tiada rekod dalam CCT member, hentikan proses
    if (!$item_id) {
        return; 
    }

    // 3. Susun Array Data (Tidak perlu masukkan user_id kerana kita tak mahu kemaskini ID)
    $user_data = array(
        'name'          => $name,
        'email'         => $email,
        'country'       => $country,
        'sex'           => $sex,
        'birthdate'     => $birthdate
    );

    // 4. Panggil fungsi update dan HANTAR $item_id, BUKAN $user_id
    if (function_exists('update_cct_member')) {
        update_cct_member($item_id, $user_data);
    }
    
    // Check update_info
    $update_info = niz_user_field_by_userid($user_id,'chk_update') ;

    if ($update_info!='Yes'){
        // Issue Points
        niz_user_add_points($user_id, 'Update Profile', 50);
        // Mark updated
        niz_user_update_field($user_id,'chk_update', 'Yes');
    }
 
}, 10, 3);

 

//FLUENTFORM UPDATE MEMBER CCT (FORM ID : 38)
add_action('fluentform/submission_inserted', 'update_user_info', 20, 3);
function update_user_info($entryId, $formData, $form) {
    global $wpdb;
    
    $targetFormId = 38;
    if ($form->id != $targetFormId) {
        return;
    }
     
    $item_id = $formData['itemID'];
    $user_id = $formData['userID'];
    $uname = $formData['uname'] ?? '';
    $uemail = $formData['uemail'] ?? '';
    $ucountry = $formData['ucountry'] ?? '';
    $usex = $formData['usex'] ?? '';
    $ubirthdate = $formData['ubirthdate'] ?? '';
    
    $wpdb->update(
        'wp_jet_cct_member',
        [
            'name' => $uname,
            'email' => $uemail,
            'sex' => $usex,
            'birthdate' => $ubirthdate,
            'country' => $ucountry,
        ],
        ['_ID' => $item_id],
        ['%s', '%s', '%s', '%s','%s'],
        ['%d']
    );
    
    // Check update_info
    $update_info = niz_user_field_by_itemid($item_id, 'chk_update');
    if ($update_info!='Yes'){
        // Issue Points
        niz_user_add_points($user_id, 'Update Profile', 50);
        // Mark updated
        member_cct_update($item_id, 'chk_update', 'Yes');

    }
}