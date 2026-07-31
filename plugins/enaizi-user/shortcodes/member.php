<?php

/**
 * Display User Info.
 * [niz_user_info]
 */
add_shortcode('niz_user_info', 'niz_user_info_shortcode');
function niz_user_info_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id = $current_user->ID;
    $name    = !empty($current_user->first_name) ? esc_html($current_user->first_name) : 'Guest';
    $email   = $current_user->user_email;

    $rank   = niz_user_field_by_userid($user_id, 'rank');
    $points = niz_user_field_by_userid($user_id, 'points');
    
    if (!$points){
        $points = 50;
        niz_user_add_points($user_id, 'Welcome Bonus', $points);
    }


    $item_id = absint(get_user_meta($user_id, 'item_id', true));
    $phone   = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));
    $status  = 'New User';
    $chg_pwd = get_user_meta($user_id, 'change_password', true);
    
    // Set phone cookies safely
    if (!empty($phone)) {
        setcookie(
            'user_phone',
            $phone,
            time() + 2592000,
            '/',
            '',
            is_ssl(),
            false // allow JS access
        );
    }
    
    $country     = isset($_COOKIE['country']) ? sanitize_text_field(wp_unslash($_COOKIE['country'])) : '';
    $partner_id  = isset($_COOKIE['partnerid']) ? intval($_COOKIE['partnerid']) : 0;
    $referrer_id = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 14270;

    if (empty($referrer_id)) {
        $referrer_id = 14270;
    }

    // Check CCT Data safely
    $chk_user = niz_user_field_by_itemid($item_id, 'user_id');

    if (empty($chk_user)) {
        $chg_pwd = '';
        $response = add_cct_member([
            'name'        => $name,
            'phone'       => $phone,
            'status'      => 'Member',
            'user_id'     => $user_id,
            'referrer_id' => $referrer_id,
            'partner_id'  => $partner_id,
            'country'     => $country
        ]);

        if ($response['success']) {
            $item_id = $response['insert_id'];
            update_user_meta($user_id, 'item_id', $item_id);
        } else {
            return 'Error: ' . esc_html($response['message']);
        }

    } else {
        $name    = niz_user_field_by_itemid($item_id, 'name');
        $country = niz_user_field_by_itemid($item_id, 'country');
        $status  = niz_user_field_by_itemid($item_id, 'status');
        $email   = niz_user_field_by_itemid($item_id, 'email');
    }

    ob_start();
    ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <div style="text-align: center;">
        <b><?= esc_html(strtoupper($name)); ?></b><br>
        <span><?= $status; ?> | <?= esc_html($rank); ?> | <?= esc_html($points) . ' points'; ?></span>
    </div>
    <?php if (empty($email)) : ?>
        <b style="color: green;">🔴 Please update your profile</b>
    <?php endif; ?>

    <?php if (empty($chg_pwd)) : ?>
        <b style="color: red;">🔴 Please change your password</b>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

/**
 * Displays a logout button
 * [niz_user_logout] 
 */
add_shortcode('niz_user_logout', 'niz_user_logout_shortcode');
function niz_user_logout_shortcode() {
    if (!is_user_logged_in()) {
        return ''; // nothing shown if already logged out
    }
    $user = wp_get_current_user();
    return '<button id="niz_user_logout_btn" class="niz-user-logout-btn">➜]</button>';
}


// Welcome
add_shortcode('niz_user_welcome', 'niz_user_welcome_shortcode');
function niz_user_welcome_shortcode() {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $rank   = niz_user_field_by_userid($user_id, 'rank');
    $points = niz_user_field_by_userid($user_id, 'points');
    
    if (!$points){
        $points = 50;
        niz_user_add_points($user_id, 'Welcome Bonus', $points);
    }
    
    ob_start();
    ?>
    
    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <?php
    // 1. Fetch user progress status (Fixed '&' typo to '$')
    $chk_premium  = niz_user_field_by_userid($user_id, 'chk_premium');
    if ($chk_premium=='Yes'){$chk_premium = ' ✅';}
    $chk_update  = niz_user_field_by_userid($user_id, 'chk_update');
    if ($chk_update=='Yes'){$chk_update = ' ✅';}
    $chk_card    = niz_user_field_by_userid($user_id, 'chk_card');
    if ($chk_card=='Yes'){$chk_card = ' ✅';}
    $chk_share   = niz_user_field_by_userid($user_id, 'chk_share');
    if ($chk_share=='Yes'){$chk_share = ' ✅';}
    $chk_aff     = niz_user_field_by_userid($user_id, 'chk_affiliate'); 
    if ($chk_aff=='Yes'){$chk_aff = ' ✅';}
    ?>

    <b><?= esc_html('🌟 Get Started & Earn Barakah Points'); ?></b>
    <?= 'Complete the following tasks to earn Barakah Points through our community rewards program:<br>'; ?>
    <ul>
        <li>Welcome Bonus Point (50 Points) ✅</li>
        <li>Update Your Profile (50 Points) <?= esc_html($chk_update); ?></li>
        <li>Upgrade to Premium (1,000 Points) <?= esc_html($chk_premium); ?></li>
        <li>Create Your Name Card (100 Points) <?= esc_html($chk_card); ?></li>
        <li>Start Share and Invite (100 Points) <?= esc_html($chk_share); ?></li>
        <li>Join the Affiliate Program (100 Points) <?= esc_html($chk_aff); ?></li>
    </ul>
    <?= 'You can also earn points by inviting new members, adding new mosques, businesses, and websites to the platform, and completing other community activities.<br>'; ?>
    <?= '<b>Together, we can create a stronger, more connected digital ecosystem for the Ummah.</b><br>'; ?>

    <?php
    return ob_get_clean();
}  
