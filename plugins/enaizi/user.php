<?php

// USER - INFO //////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'user_info', 'user_info_shortcode' );
});

function user_info_shortcode() {
    // Ensure the global current user is set
    global $current_user;
    global $wpdb;
    wp_get_current_user();

    // Fetch user details safely
    $user_id = $current_user->ID;
    $name = !empty($current_user->first_name) ? esc_html($current_user->first_name) : 'Guest';
    $email = $current_user->user_email;
    $chg_pwd = get_user_meta($user_id, 'change_password', true) ?? '';
    $country = isset($_COOKIE['country']) ? $_COOKIE['country'] : '';
    $partner_id = isset($_COOKIE['partnerid']) ? intval($_COOKIE['partnerid']) : 0;
    $referrer_id = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 0;
    if ($referrer_id =''){
       $referrer_id = 14270;
    }
    $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
 
    // check if item id is not set, create member cct 
    if ($item_id == ''){
        // Create Member CCT
        $user_data = array(
            'user_id'       => $user_id,
            'name'          => $name,
            'phone'         => $phone,
            'email'         => $email,
            'country'       => $country,
            'category'      => 'Member',
            'partner_id'    => $partner_id,
            'referrer_id'   => $referrer_id,
        );
    
        // Insert into the CCT table
        $result = $wpdb->insert(
            'wp_jet_cct_member', 
            $user_data
        );
        $item_id = $wpdb->insert_id;
        update_user_meta($user_id, 'item_id', $item_id);
    
        // Mark as Activated
        member_cct_update($item_id, 'activated', 'Yes');
            
        // issue DinarX
        $type = 'Receive';
        $amount = 50;

        $desc = 'Register as Member';
        member_issue_dinarx($user_id, $desc, $type, $amount);
            
        // Issue DinarX to referrer 
        $desc = 'Affiliate - ' . $name ;
        member_issue_dinarx($referrer_id, $desc, $type, $amount);
    }

    $handphone = niz_user_field_by_itemid($item_id, 'phone');
    $country = niz_user_field_by_itemid($item_id, 'country');
    $sex = niz_user_field_by_itemid($item_id, 'sex');
    $birthdate = niz_user_field_by_itemid($item_id, 'birthdate');
    $update_info = niz_user_field_by_itemid($item_id, 'update_info');
    $activated = niz_user_field_by_itemid($item_id, 'activated');
    $referrer_id = niz_user_field_by_itemid($item_id, 'referrer_id');

    // Start output buffering
    ob_start(); ?>
    
    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded');
        });
    </script>
    
    <b><?= strtoupper($name); ?></b>
    <table>
        <tr><td>User ID</td><td><b><?= $user_id; ?></b></td></tr>
        <tr><td>Email</td><td><b><?= $email; ?></b></td></tr>
        <tr><td>Mobile No.</td><td><b><?= $handphone; ?></b></td></tr>
        <tr><td>Country</td><td><b><?= $country; ?></b></td></tr>
        <tr><td>Sex/Birthdate</td><td><b><?= $sex; ?> - <?= $birthdate; ?></b></td></tr>
    </table>

    <?php if ($update_info!='Yes') : ?>
        <b style="color: green;">🔴 Update your profile and get 50 DinarX</b>
    <?php endif; ?>

    <?php if (empty($chg_pwd)) : ?>
        <b style="color: red;">🔴 Please change your password</b>
    <?php endif; ?>

    <?php 
    return ob_get_clean();
}  

/*

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
    $update_info = member_cct_data($item_id, 'update_info');
    if ($update_info!='Yes'){
        // Issue DinarX
        $desc = 'Update Information';
        $type = 'Receive';
        $amount = 20;
        member_issue_dinarx($user_id, $desc, $type, $amount);

        // Mark updated
        member_cct_update($item_id, 'update_info', 'Yes');

    }
}

*/

// MEMBER - LOGIN /////////////////////
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    // Ensure we're only processing form ID 4
    if ((int) $form->id !== 37) { 
        return;
    }
 
    // Retrieve phone and password from submitted form data
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $password = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'password'));

    if (empty($phone) || empty($password)) {
        wp_send_json(['errors' => 'Phone and Password fields are required.'], 400);
    }

    // Function to find user by phone or email
    function find_user_by_phone_or_email($phone) {
        $users = get_users([
            'meta_key' => 'user_phone',
            'meta_value' => $phone
        ]);

        if (empty($users)) {
            $users = get_users([
                'meta_key' => 'user_email',
                'meta_value' => $phone
            ]);
        }

        return !empty($users) ? $users[0] : null;
    }

    // Get the user object
    $user = find_user_by_phone_or_email($phone);

    if (!$user) {
        wp_send_json(['errors' => 'Phone or Email is not registered. Please register.'], 423);
    }

    $user_id = $user->ID;

    // Validate the password
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        wp_send_json(['errors' => 'Wrong Password. If you forgot your password, please reset.'], 423);
    }

    // Check and update user status if needed
    $status = get_user_meta($user_id, 'user_category', true);
    if ($status === 'Prospect') {
        update_user_meta($user_id, 'user_category', 'Member');
    }

    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

}, 10, 3);
