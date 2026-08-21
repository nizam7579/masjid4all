<?php

/**
 * 1. mfa_member_info
 * 2. mfa_member_profile
 * 3. mfa_member_todo
 * 4. mfa_member_update_field
 * 5. mfa_member_add_cct
 * 6. mfa_member_field_by_userid
 * 7. mfa_member_add_points
 */
 
 
// MEMBER INFO 
add_shortcode('mfa_member_info', 'mfa_member_info_shortcode');
function mfa_member_info_shortcode() {

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
    $phone   = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));
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
    $item_id = absint(get_user_meta($user_id, 'item_id', true));

    if (empty($item_id)) {
        $chg_pwd = '';
        $rank    = 'Bronze';
        $status  = 'Member';
        $response = mfa_member_add_cct([
            'name'        => $name,
            'phone'       => $phone,
            'status'      => $status,
            'email'       => $email,
            'rank'        => $rank,
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
        $name    = mfa_member_field_by_userid($user_id, 'name');
        $country = mfa_member_field_by_userid($user_id, 'country');
        $status  = mfa_member_field_by_userid($user_id, 'status');
        $email   = mfa_member_field_by_userid($user_id, 'email');
        $rank    = mfa_member_field_by_userid($user_id, 'rank');
    }
    
    $points = mfa_member_field_by_userid($user_id, 'points');
    if (!$points){
        $points = 50;
        mfa_member_add_points($user_id, 'Welcome Bonus', $points);
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

    <?php
    return ob_get_clean();
}


// MEMBER PROFILE 
add_shortcode('mfa_member_profile', 'mfa_member_profile_shortcode');
function mfa_member_profile_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id    = $current_user->ID;
    $user_email = $current_user->user_email;
    $user_phone = get_user_meta($user_id, 'user_phone', true);

     
    $name       = mfa_member_field_by_userid($user_id, 'name');
    $email      = mfa_member_field_by_userid($user_id, 'email');
    $phone      = mfa_member_field_by_userid($user_id, 'phone');
    $sex        = mfa_member_field_by_userid($user_id, 'sex');
    $birthdate  = mfa_member_field_by_userid($user_id, 'birthdate');
    $country    = mfa_member_field_by_userid($user_id, 'country');
    $status     = mfa_member_field_by_userid($user_id, 'status');
    $rank       = mfa_member_field_by_userid($user_id, 'rank');
    $points     = mfa_member_field_by_userid($user_id, 'points');

    if (empty($phone)){
        if (!empty($user_phone)){
            $phone = $user_phone;
            // Update Phone
            mfa_member_update_field($user_id, 'phone', $user_phone);
        }
        if (empty($phone)){
            $phone = '<i style="color: #25988B;">Please update</i>';
        }
    }
    
    if (empty($email)){
        if (!empty($user_email)){
            $email = $user_email;
            // Update Email
            mfa_member_update_field($user_id, 'email', $user_email);
        }
        if (empty($email)){
            $email = '<i style="color: #25988B;">Please update</i>';
        }
    }

    
    if (empty($sex)){
        $sex = '<i style="color: #25988B;">Please update</i>';
    }
    if (empty($birthdate)){
        $birthdate = '<i style="color: #25988B;">Please update</i>';
    }
    if (empty($country)){
        $country = '<i style="color: #25988B;">Please update</i>';
    }
    
    ob_start();
    ?>

    <table>
        <tr><td width="100">Name</td><td><b><?= esc_html(strtoupper($name)); ?></b></td></tr>
        <tr><td>Email</td><td><b><?= $email; ?></b></td></tr>
        <tr><td>Phone</td><td><b><?= $phone; ?></b></td></tr>
        <tr><td>Sex</td><td><b><?= $sex; ?></b></td></tr>
        <tr><td>Birthdate</td><td><b><?= $birthdate; ?></b></td></tr>
        <tr><td>Country</td><td><b><?= $country; ?></b></td></tr>
        <tr><td>&nbsp;</td><td><b></td></tr>
        <tr><td>Status</td><td><b><?= esc_html($status); ?></b></td></tr>
        <tr><td>Rank</td><td><b><?= esc_html($rank); ?></b></td></tr>
        <tr><td>Points</td><td><b><?= esc_html($points); ?></b></td></tr>
    </table>

    <?php
    return ob_get_clean();
}

// UPDATE FIELD
function mfa_member_update_field($user_id, $field, $value) {
    global $wpdb;

    if (empty($user_id) || empty($field)) {
        return false;
    }

    return $wpdb->update(
        $wpdb->prefix . 'jet_cct_member',
        [ $field => $value ],
        [ 'user_id' => (int) $user_id ],
        [ '%s' ],           // Format for $data (status field - string)
        [ '%d' ]            // Format for $where (user_id - integer)
    );
}

// ADD CCT
function mfa_member_add_cct($data) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';
    $defaults = [
        'item_id'     => null,
        'name'        => '',
        'phone'       => '',
        'status'      => '',
        'rank'        => '',
        'points'      => 0,
        'user_id'     => 0,
        'referrer_id' => '',
        'partner_id'  => '',
        'country'     => '',
    ];

    $data = wp_parse_args($data, $defaults);

    $insert_data = [
        'name'        => sanitize_text_field($data['name']),
        'phone'       => sanitize_text_field($data['phone']),
        'status'      => sanitize_text_field($data['status']),
        'user_id'     => intval($data['user_id']),
        'rank'        => sanitize_text_field($data['rank']),
        'points'      => sanitize_text_field($data['points']),
        'country'     => sanitize_text_field($data['country']),
        'referrer_id' => sanitize_text_field($data['referrer_id']),
        'partner_id'  => sanitize_text_field($data['partner_id']),
        'cct_created' => current_time('mysql')
    ];

    $format = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];

    if (!empty($data['item_id'])) {
        $insert_data['item_id'] = intval($data['item_id']);
        $format[] = '%d';
    }

    $result = $wpdb->insert($table, $insert_data, $format);

    if ($result === false) {
        return [
            'success' => false,
            'message' => $wpdb->last_error
        ];
    }

    return [
        'success'   => true,
        'insert_id' => $wpdb->insert_id
    ];
}


// MEMBER TODO
add_shortcode('mfa_member_todo', 'mfa_member_todo_shortcode');
function mfa_member_todo_shortcode() {
        if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id = $current_user->ID;
    $email   = $current_user->user_email;
    $phone   = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));
    $chg_pwd = get_user_meta($user_id, 'change_password', true);
    
    $chk_update  = mfa_member_field_by_userid($user_id,'chk_update');
    $chk_premium = mfa_member_field_by_userid($user_id,'chk_premium');
    $chk_card    = mfa_member_field_by_userid($user_id,'chk_card');
    $chk_share   = mfa_member_field_by_userid($user_id,'chk_share');
    $chk_affiliate   = mfa_member_field_by_userid($user_id,'chk_affiliate');
    
    ob_start();
    ?>
    <span style="color: #25988B;">TODO</span>
    <ul>
    <?php if (empty($chk_update)) : ?>
        <li style="color: red;">Please update your profile</li>
    <?php endif; ?>

    <?php if (empty($chg_pwd)) : ?>
        <li style="color: red;">Please update your password</li>
    <?php endif; ?>
    
    <?php if (empty($chk_premium)) : ?>
        <li style="color: #25988B;">Upgrade to Premium Membership</li>
    <?php endif; ?>
    
    <?php if (empty($chk_card)) : ?>
        <li style="color: #25988B;">Create your Digital Namecard</li>
    <?php endif; ?>
    
    <?php if (empty($chk_share)) : ?>
        <li style="color: #25988B;">Start Share/Promote</li>
    <?php endif; ?>
    
    <?php if (empty($chk_affiliate)) : ?>
        <li style="color: #25988B;">Join our Affiliate Program</li>
    <?php endif; ?>
    
    </ul>

    <?php
    return ob_get_clean();

}

function mfa_member_field_by_userid($user_id,$field) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';
    $result = $wpdb->get_var(
        $wpdb->prepare("SELECT {$field} FROM {$table} WHERE user_id = %d", absint($user_id))
    );

    return ($result !== null) ? $result : null;
}


/**
 * Delegates to mfa_award_points() - see mfa-core/includes/barakah.php.
 *
 * 2026-08-21. This was the least broken of the three - it did dedupe on
 * (user_id, description) - but its sync line was commented out, so it wrote
 * the ledger row and left jet_cct_member.points untouched. A member awarded
 * only through this path kept whatever stale figure the field already held.
 */
function mfa_member_add_points($user_id, $desc, $points) {
    if ( function_exists( 'mfa_award_points' ) ) {
        return mfa_award_points( $user_id, $desc, $points );
    }

    return [
        'success' => false,
        'message' => 'mfa-core inactive - no award written',
    ];
}


add_shortcode('mfa_member_logout', 'mfa_member_logout_shortcode');
function mfa_member_logout_shortcode() {
    if (!is_user_logged_in()) {
        return ''; // nothing shown if already logged out
    }
    $user = wp_get_current_user();
    // 2026-08-21: was a <button> whose click handler lived in enaizi-user's
    // niz-user.js, which has been deleted - so it would have rendered a
    // button that does nothing. Now a nonce-protected wp_logout_url() link,
    // matching mfa-core's [niz_user_logout].
    return '<a href="' . esc_url(wp_logout_url(home_url('/'))) . '"'
        . ' class="niz-user-logout-btn" rel="nofollow">&#10148;] Logout</a>';
}

// mfa_member_share moved to mfa-core/includes/widgets/member-share.php
