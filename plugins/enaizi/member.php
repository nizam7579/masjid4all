<?php
/**
  FUNCTIONS  
  1. Register new user
  2. Member Login
  
  3. find_userid_by_phone( $phone )
  4. find_memberid_by_phone( $phone )

  6. get_field_from_userid($user_id, $field)
  7. update_field_from_userid($user_id, $field, $value)
  
  5. Get Field from Item ID
  7. Save Field from Item ID  
  8. Update CCT Member Data
  
  DISPLAY
  1. Display Member Info 
  2. Display Membership Info
   
  FORMS
  1. Update Info 
  2. Update Password 
  
*/

/*

// Register User - validate form 
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Only process form ID 5 - Register form
    if ((int) $form->id !== 9) {
        return;
    }

    // Sanitize phone and name inputs
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $name = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));

    $phone = preg_replace('/\D+/', '', $phone);

    $user_id = find_userid_by_phone( $phone );
 
    if ($user_id>0) {
        // User already registered, proceed with updates
        $response = [
            'errors' => "<b>Phone " . $phone . " is already registered.</b>"
        ];
        wp_send_json($response, 423);
    } else {
        // User not found, proceed with registration
        $user_id = member_register($name, $phone);
    }

}, 10, 3);


// 1. Register new user 
function member_register($name, $phone) {
    global $wpdb;
    
    // Clean up phone number to retain only digits
    $phone = preg_replace('/\D+/', '', $phone);

    // Create a new unique username
    $username = uniqid('', true);
 
    // Create the user
    $pwd = mt_rand(100000, 999999);
    $user_id = wp_create_user($username, $pwd, $email);

    if (is_wp_error($user_id)) {
        return $user_id->get_error_message();  // Return error message if user creation fails
    }

    wp_update_user([
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name,
        'user_pass' => $pwd, 
    ]);

    $referrer_id = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 0;
    $country = isset($_COOKIE['country']) ? $_COOKIE['country'] : '';

    // Update ms_cookie
    setcookie("name", $name, time() + 31556926, "/", '', false, true);
    setcookie("phone", "+" . $phone, time() + 31556926, "/", '', false, true);
    setcookie("affiliateid", $user_id, time() + 31556926, "/", '', false, true);
 
    //CREATE CCT MEMBER
    date_default_timezone_set('Asia/Kuala_Lumpur'); 
    $timestamp = time(); // get current timestamp in KL timezone
    $local_time = date('Y-m-d H:i:s', $timestamp); // convert to readable format

    $user_data = array(
        'user_id'       => $user_id,
        'name'          => $name,
        'phone'         => $phone,
        'country'       => $country,
        'status'        => 'Prospect',
        'referrer_id'   => $referrer_id,
        'cct_created'   => $local_time,
    ); 

    // Insert into the CCT table
    $result = $wpdb->insert(
        'wp_jet_cct_member', 
        $user_data
    );
    
    $cct_id = $wpdb->insert_id;
    update_user_meta($user_id, 'item_id', $cct_id);

    // Send Whatsapp Template
    $to = $phone ; // "60177271844";  
    $template_name = "registration"; 
    $language_code = "en";      // English (US)
    
    // Components
    $components = [
        [
            "type" => "body",
            "parameters" => [
                [
                    "type" => "text",
                    "text" => (string) $name   // fills {{1}}
                ]
            ]
        ],
        [
            "type" => "button",
            "sub_type" => "QUICK_REPLY",
            "index" => "0",
            "parameters" => [
                [
                    "type" => "text",
                    "text" => "Next Step"   // must match the Quick Reply button in the template
                ]
            ]
        ]
    ];
    
    // Send template
    wapi_send_template($to, $template_name, $language_code, $components);
        // UPDATE ACTIVITY
    //$activity = 'Register as Member';
    //activity_add_new($user_id, $activity);

    return $user_id;
} 


// 2. Member Login
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Only process Form ID 10
    if ((int) $form->id !== 10) {
        return;
    }

    // Get inputs
    $phone    = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $phone = preg_replace('/\D+/', '', $phone);
    $password = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'password');

    if (empty($phone) || empty($password)) {
        wp_send_json(['errors' => 'Phone and Password fields are required.'], 400);
        wp_die();
    }

    $item_id = find_memberid_by_phone($phone);
    $user_id = member_cct_data($item_id, 'user_id');

    if (!$user_id) {
        wp_send_json(['errors' => 'Account not found. Please Register'], 404);
        wp_die();
    }

    // Load user object
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json(['errors' => 'Invalid user account.'], 404);
        wp_die();
    }

    // Verify password
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        wp_send_json(['errors' => 'Wrong password. Please try again or reset your password.'], 423);
        wp_die();
    }

    //$status = member_cct_data($item_id, 'status');
    //if ($status === 'Prospect') {
    //    update_cct_field($item_id, 'status', 'Member') ;
    //}
    
    // Normalize phone cookie
    if ($phone[0] !== '+') {
        $phone = '+' . $phone;
    }
    setcookie(
        'phone',
        $phone,
        time() + YEAR_IN_SECONDS,
        '/',
        '',
        is_ssl(),
        true
    );
    $_COOKIE['phone'] = $phone;

    // Log user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

}, 10, 3);


// 3. Find user_id by phone number
function find_userid_by_phone( $phone ) {
    global $wpdb;
    $phone = preg_replace('/\D+/', '', $phone);
    
    $table = $wpdb->prefix . 'jet_cct_member';

    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE phone = %s LIMIT 1",
            $phone
        )
    );

    return $user_id ? (int) $user_id : null;
}


// 4. Find item_id by phone number
function find_memberid_by_phone( $phone ) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';

    $item_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT _ID FROM {$table} WHERE phone = %s LIMIT 1",
            $phone
        )
    );

    return $item_id ? (int) $item_id : null;
}

*/



/*

// 6. Get Field from User ID
function get_field_from_userid($user_id, $field) {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT $field FROM wp_jet_cct_member WHERE  user_id = %d",
        $user_id
    );
    $result = $wpdb->get_var($query);
    return ($result !== null) ? $result : null;
}

function update_field_from_userid($user_id, $field, $value) {
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
 
 
// 6. Save Field from Item ID
function update_cct_field($item_id, $field, $value) {
    global $wpdb;

    if (empty($item_id) || empty($field)) {
        return false;
    }

    return $wpdb->update( 
        $wpdb->prefix . 'jet_cct_member',
        array(
            $field => $value
        ),
        array(
            '_ID' => (int) $item_id
        ),
        array('%s'),
        array('%d')
    ); 
}

// Add New cct_member
function add_cct_member($data) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';

    // Default values (optional safety)
    $defaults = [
        'item_id'     => null,
        'name'        => '',
        'phone'       => '',
        'status'      => '',
        'user_id'     => 0,
        'referrer_id' => '',
        'partner_id'  => '',
        'country'     => '',
    ];

    // Merge input with defaults
    $data = wp_parse_args($data, $defaults);

    // Prepare insert data (exclude null item_id if not used)
    $insert_data = [
        'name'        => sanitize_text_field($data['name']),
        'phone'       => sanitize_text_field($data['phone']),
        'status'      => sanitize_text_field($data['status']),
        'user_id'     => intval($data['user_id']),
        'country'     => sanitize_text_field($data['country']),
        'referrer_id' => sanitize_text_field($data['referrer_id']),
        'partner_id'  => sanitize_text_field($data['partner_id']),
        'country'     => sanitize_text_field($data['country']),
    ];

    $format = ['%s','%s','%s','%s','%s','%d','%s'];

    // Include item_id only if provided
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
        'success' => true,
        'insert_id' => $wpdb->insert_id
    ];
}

// 7. Update CCT Member Data
function update_cct_member($item_id, $user_data) {
    global $wpdb;

    // Ensure item_id and data are provided
    if (empty($item_id) || empty($user_data)) {
        return "Error: item_id and user_data are required.";
    }

    // Perform the update
    $result = $wpdb->update(
        'wp_jet_cct_member', // Table name
        $user_data,           // Data to update
        array('_ID' => $item_id), // Where condition
        array_fill(0, count($user_data), '%s'), // Data format
        array('%d') // user_id format
    );

    // Debugging output
    if ($result === false) {
        return "Error updating record: " . $wpdb->last_error;
    } elseif ($result === 0) {
        return "No changes made or record not found.";
    } else {
        return "Record updated successfully.";
    }
}


// DISPLAY //////////////////////////////////



// 2. Display Membership Info
add_shortcode( 'member_status', 'member_status_shortcode' );

function member_status_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user category safely
    $user_id = $current_user->ID;
    $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
    $registered = !empty($current_user->user_registered) ? date('M j, Y', strtotime($current_user->user_registered)) : '-';
    
    $status = member_cct_data($item_id, 'status');
 
    $upgraded = 'No';
    // Default values

    if ($status == 'Prospect') {
        update_cct_field($item_id, 'status', 'Member') ;
        $status = 'Member';
    }
 
    //$status='Premium';
    if ($status=='Member'){
        $rem = '🔴 Upgrade to Premium Membership';
        echo '<style>.btn_contribute { display: none !important; }</style>';
        echo '<style>.btn_payment_record { display: none !important; }</style>';
    }else{
        echo '<style>.btn_upgrade_membership { display: none !important; }</style>';
    }
    
    // Check if user is a Premium Member
    if ($category === 'Premium Member') {
        $plan = 'Premium (Package D)';
        $khairat = 'RM 50,000';
        $validity = '01/01/2025 - 31/12/2025';
        $rem = '✅ Plan is active';
    }

    // Start output buffering
    ob_start(); ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for ahlik_info');
        });
    </script>

    <table>
        <tr>
            <td width="100px">Status</td>
            <td><b><?= $status; ?></b></td>
        </tr>
        <tr> 
            <td>Registered</td>
            <td><b><?= $registered; ?></b></td>
        </tr>
        <tr>
            <td colspan="2" style="color: green; font-weight: bold;"><?= $rem; ?></td>
        </tr>
    </table>
 
    <?php 
    return ob_get_clean();
}





// 2. Display Matrimony
add_shortcode( 'member_matrimony', 'member_matrimony_shortcode' );

function member_matrimony_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user category safely
    $user_id = $current_user->ID;
    $item_id = get_field_from_userid($user_id, '_ID');
    $matrimony = get_field_from_userid($user_id, 'matrimony');
 
    $ret = '';
    //$matrimony = 'Yes';
    if ($matrimony == 'Yes') {
        $ret .= 'Build meaningful halal friendships grounded in shared values, guided by adab, and leading towards marriage.';
        echo '<style>.btn_create_profile { display: none !important; }</style>';
   }else{
        $ret .= '<b>Looking for a life partner? </b>Build meaningful halal friendships grounded in shared values, guided by adab, and leading towards marriage.';
        echo '<style>.btn_search_soulmate { display: none !important; }</style>';
    }

 
    return $ret;
}


////////////////////////////////////////////////////
// Find user by phone number
function find_user_by_phone($phone) {
    $users = get_users([
        'meta_key'   => 'user_phone',
        'meta_value' => $phone
    ]);
    return !empty($users) ? $users[0] : null;
}


// DISPLAY

    /*
    if (empty($email)) {    
        ?>
            <a href="#update-phone" id="auto-popup-trigger" class="modal-trigger" style="display:none;"></a>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(function() {
                    const trigger = document.getElementById('auto-popup-trigger');
                    if (trigger) {
                        trigger.click();
                    }
                }, 2000); // Optional delay: 2 seconds
            });
            </script>
        <?php
    }

*/

// MEMBER - INFO //////////////////////
add_shortcode('member_info', 'member_info_shortcode');

function member_info_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    global $wpdb;
    wp_get_current_user();
    $current_user = wp_get_current_user();

    $user_id = $current_user->ID;
    $name    = !empty($current_user->first_name) ? esc_html($current_user->first_name) : 'Guest';
    $email   = $current_user->user_email;

    $item_id = get_user_meta($user_id, 'item_id', true);
    $phone   = get_user_meta($user_id, 'user_phone', true);
    $status  = 'New User';
    $chg_pwd = get_user_meta($user_id, 'change_password', true);
    
    // Set phone cookies
    setcookie(
        'user_phone',
        $phone,
        time() + 2592000,
        '/',
        '',
        is_ssl(),
        false // 👈 IMPORTANT (allow JS access)
    );
    
   
    $country = isset($_COOKIE['country']) ? sanitize_text_field($_COOKIE['country']) : '';
    $partner_id  = isset($_COOKIE['partnerid']) ? intval($_COOKIE['partnerid']) : 0;
    $referrer_id = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 0;

    if ($referrer_id == '') {
        $referrer_id = 14270;
    }

    // Check CCT
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

    <b><?= esc_html(strtoupper($name)); ?></b>

    <table>
        <tr><td>Mobile No.</td><td><b><?= esc_html($phone); ?></b></td></tr>
        <tr><td>Country</td><td><b><?= esc_html($country); ?></b></td></tr>
        <tr><td>Status</td><td><b><?= esc_html($status); ?></b></td></tr>
    </table>

    <?php if (empty($email)) : ?>
        <b style="color: green;">🔴 Please update your profile</b>
    <?php endif; ?>

    <?php if (empty($chg_pwd)) : ?>
        <b style="color: red;">🔴 Please change your password</b>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

/*





// MEMBER - DINARX ////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_dinarx', 'member_dinarx_shortcode' );
});  

function member_dinarx_shortcode() {
    global $wpdb;
    
    $current_user_id = get_current_user_id();
    
    $table = $wpdb->prefix . "jet_cct_dinarx";
    // Fetch transactions
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT type, description, amount FROM $table WHERE user_id = %d",
            $current_user_id
        )
    );

    $total = 0;
    $transfer = 0;
    foreach ( $results as $row ) {
        $total = $total + intval($row->amount);
    }
    $balance = $total - $transfer;
    $total = esc_html(number_format($total, 0));
    $balance = esc_html(number_format($balance, 0));
    // Start output buffering
    ob_start(); ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for ahlik_info');
        });
    </script>

    <table>
        <tr>
            <td width="100px">Received</td>
            <td><b><?= $total; ?></b> DNX</td>
        </tr>
        <tr> 
            <td>Transfered</td>
            <td><b><?= 0; ?></b> DNX to Crypto Wallet</td>
        </tr>
        <tr> 
            <td width="100px">Balance</td>
            <td><b><?= $balance; ?></b> DNX</td>

        </tr>
        <tr>
            <td colspan="2" style="color: green; font-weight: bold;"><?= $rem; ?></td>
        </tr>
    </table>
    <?php 
    return ob_get_clean();
}

// MEMBER - DINARX TRANS ///////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_dinarx_trans', 'member_dinarx_trans_shortcode' );
});

function member_dinarx_trans_shortcode() {
    global $wpdb;
    
    $current_user_id = get_current_user_id();
    
    $table = $wpdb->prefix . "jet_cct_dinarx";
    // Fetch transactions
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT type, description, amount FROM $table WHERE user_id = %d",
            $current_user_id
        )
    );

    if ( empty($results) ) {
        return '<p>You have not received any DinarX</p>';
    }

    // Build HTML output
    $output  = '<table style="width:100%; border-collapse: collapse;">';
    $output .= '<tr><th style="border-bottom: 1px solid #ccc; text-align:left;">Type</th><th style="border-bottom: 1px solid #ccc; text-align:left;">Description</th><th style="border-bottom: 1px solid #ccc; text-align:right;">Amount</th></tr>';

    foreach ( $results as $row ) {
        $output .= '<tr>';
        //$output .= '<td>' . esc_html($row->date) . '</td>';
        $output .= '<td>' . esc_html(ucfirst($row->type)) . '</td>';
        $output .= '<td>' . esc_html($row->description) . '</td>';
        $output .= '<td style="text-align:right;">' . esc_html(number_format($row->amount, 0)) . '</td>';
        $output .= '</tr>';
    }

    $output .= '</table>';
    return $output;
    
}    

// MEMBER - INCENTIVES //////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_incentives', 'member_incentives_shortcode' );
});

function member_incentives_shortcode() {
    // Ensure the global current user is set
    global $current_user;
    wp_get_current_user();
    $user_id = $current_user->ID;
    $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
    $category = member_cct_data($item_id, 'category');
    $level1 = 0;
    $level2 = 'N/A';
    $level3 = 'N/A';
    if ($category=='Premium Member (Bronze)'){
        $txt1 = '✔️ 50 DinarX on 1st level referrals
        ✔️ 20% Commission on 1st level referrals
        ❌ 5% Team Bonus on 2nd level referrals 
        ❌ 5% Team Bonus on 3rd level referrals
        <br><b>Please Upgrade your Membership to enjoy more incentives</b>';
    }elseif ($category=='Premium Member (Silver)'){
        $txt1 = '✔️ 50 DinarX on 1st level referrals
        ✔️ 20% Commission on 1st level referrals
        ✔️ 5% Team Bonus on 2nd level referrals 
        ❌ 5% Team Bonus on 3rd level referrals
        <br><b>Please Upgrade your Membership to enjoy more incentives</b>';
    }elseif ($category=='Premium Member (Gold)'){
        $txt1 = '✔️ 50 DinarX on 1st level referrals
        ✔️ 20% Commission on 1st level referrals
        ✔️ 5% Team Bonus on 2nd level referrals 
        ✔️ 5% Team Bonus on 3rd level referrals';
    }else{
        $txt1 = '✔️ 50 DinarX on 1st level referrals
        ❌<s> 20% Commission on 1st level referrals</s>
        ❌<s> 5% Team Bonus on 2nd level referrals</s> 
        ❌<s> 5% Team Bonus on 3rd level referrals</s>
        <br><b>Please Upgrade to Premium to enjoy more incentives</b>';
    } 
    //$txt1 .= '<br><br><i style="font-size: 14px;">🔒 Only <b>Premium Members</b> are eligible to earn commissions — up to 3 levels — when their referrals subscribe to any premium package.</i>';

    // Start output buffering
    ob_start(); ?>
    
    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for ahlik_info');
        });
    </script>
    
    <div style="white-space: pre-line; ">
    Status : <b><?= $category; ?></b></div>
    <span style="white-space: pre-line; font-size: 16px;">
    <?= $txt1; ?></span>
    <br>
   
    <?php 
    return ob_get_clean();
}

// MEMBER - AFFILIATE STATUS //////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_affiliate_status', 'member_affiliate_status_shortcode' );
});

function member_affiliate_status_shortcode() {
    global $wpdb;
    global $current_user;
    wp_get_current_user();

    $user_id = $current_user->ID;
    $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
    $category = member_cct_data($item_id, 'category');
    
    // Get referrals
    $table = $wpdb->prefix . "jet_cct_member";

    $level1 = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE referrer_id = %d AND activated = %s",
            $user_id,
            'Yes'
        )
    );

    $level2 = 0;
    $level3 = 0;
    $dinarx = $level1 * 50; 
    
    //$category = "Member";
    $total = 0;
    $comm1 = "Not Eligible";
    $comm2 = "Not Eligible";
    $comm3 = "Not Eligible"; 
    if ($category!="Member"){
        if ($category=="Premium Member (Bronze)"){
            $comm1 = 0 ;
            $comm1 = '$ ' . esc_html(number_format($comm1, 2));
        }elseif($category=="Premium Member (Silver)"){
            $comm1 = 0 ;
            $comm2 = 0 ;
            $comm1 = '$ ' . esc_html(number_format($comm1, 2));
            $comm2 = '$ ' . esc_html(number_format($comm2, 2));
        }elseif($category=="Premium Member (Gold)"){    
            $comm1 = 0 ;
            $comm2 = 0 ;
            $comm3 = 0 ;
            $comm1 = "$ " . (number_format($comm1, 2));
            $comm2 = "$ " . (number_format($comm2, 2));
            $comm3 = "$ " . (number_format($comm3, 2));
        }    
        $total = 0;
        esc_html(number_format($total, 2));
        //$comm1 = esc_html(number_format($comm1, 2));
        //$comm2 = esc_html(number_format($comm2, 2));
        //$comm3 = esc_html(number_format($comm3, 2));
    }
    $total = esc_html(number_format($total, 2));

    // Start output buffering
    ob_start(); ?>
    
    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for ahlik_info');
        });
    </script>
    
    <br><table width="100%">
        <tr><td width="200px">Direct Referrals</td><td><b><?= $level1; ?> members</b></td></td></tr>
        <tr><td>DinarX Bonus</td><td><b><?= $dinarx; ?> DNX</b></td></td></tr>
    </table>

    <?php 
    return ob_get_clean();
}

// MEMBER - UPGRADE TO PREMIUM ///////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_upgrade_premium', 'member_upgrade_premium_shortcode' );
});

function member_upgrade_premium_shortcode() {
//    $userid  = isset($_GET['userid']) ? sanitize_text_field($_GET['userid']) : '';

    // check user_id. 
    $url_userid = intval($_GET['userid']);
    $user_id = get_current_user_id();

    // Redirect if not logged in or user ID does not match
    if ( $user_id === 0 || $user_id !== $url_userid ) {
        wp_redirect(home_url('/member'));
        exit;
    }

    $status  = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    if ($status=='Paid'){
        $total   = isset($_GET['total']) ? sanitize_text_field($_GET['total']) : '';
        $method  = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
        $package = isset($_GET['package']) ? sanitize_text_field($_GET['package']) : '';
        
        if ( stripos($package, 'silver') !== false ) { 
            $package = 'Premium Member (Silver)';
            $amount = 25000;
        } elseif ( stripos($package, 'gold') !== false ) {
            $package = 'Premium Member (Gold)';
            $amount = 50000;
        } else {
            $package = 'Premium Member (Bronze)';
            $amount = 5000;
        }
        
        // Receive Bonus DinarX
        $type = 'Receive';
        member_issue_dinarx($user_id, $package, $type, $amount);

        // Update Member Status
        $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
        member_cct_update($item_id, 'category', $package);
        
        // Send WA Message
        $name = get_user_meta( $user_id, 'first_name', true );;
        $phone = esc_html(get_user_meta($user_id, 'user_phone', true) ?? '');
        $amount = number_format($amount, 0);
        $wa  = "*Payment Successful*\nAmount : *$ " . $total . "*\n\n";
        $wa .= "Hi " . $name . ",\n\n";
        $wa .= "🎉 Thank you for upgrading to our *" . $package . "* package!\n\n";
        $wa .= "🪙 *Bonus Reward:*\n You've received *" . $amount . " DinarX* as a special bonus for upgrading your membership. 🎁\n\n";
        $wa .= "Start enjoying all the exclusive premium features now!\n🔗 https://masjid4All.com/member\n\n";
        $wa .= "📚 *Discover Masjid4All*\nExplore how we're using digital tools to empower mosques and the Ummah through blockchain and community engagement.\n";
        $wa .= "🔗 https://masjid4all.com/knowledge-hub";
        whatsapp_send_message($phone, $wa, '$media_url');
     
        // Redirect to Member's Page
        wp_redirect(home_url('/member'));
        exit;
    }
   
}



// UPDATE CCT MEMBER FIELD
function member_cct_update($item_id, $field, $value) {
    global $wpdb;

    $result = $wpdb->update(
        'wp_jet_cct_member',                  // Table name
        [$field => $value],                   // Data to update
        ['_ID' => $item_id],                  // WHERE condition
        null,                                 // Data format (optional)
        ['%d']                                // WHERE format
    );

    return ($result !== false); // returns true if update succeeded, false on error
}

// ISSUE DINARX
function member_issue_dinarx($user_id, $description, $type, $amount) {
    global $wpdb;
    
    if ( empty($user_id) || empty($amount) ) {
        return new WP_Error('invalid_data', 'User ID and amount are required.');
    }

    // Sanitize input
    $user_id = intval($user_id);
    $description = sanitize_text_field($description);
    $type = $type; // e.g. 'issue', 'bonus', etc.
    $amount = floatval($amount);
    $created = date('Y-m-d H:i:s');

    //CREATE CCT MEMBER
    $user_data = array(
        'user_id'       => $user_id,
        'type'          => $type,
        'description'   => $description,
        'amount'        => $amount,
        'cct_created'   => $created
    );

    // Insert into the CCT table
    $result = $wpdb->insert(
        'wp_jet_cct_dinarx', 
        $user_data
    );

    return true;
    
}

//FLUENTFORM SHARE/PROMOTE (FORM ID : 34)
add_action('fluentform/submission_inserted', 'member_promote', 20, 3);
function member_promote($entryId, $formData, $form) {
    $targetFormId = 34;
    if ($form->id != $targetFormId) {
        return;
    }
    
    $user_id = $formData['user_id'];
    $item_id = esc_html(get_user_meta($user_id, 'item_id', true) ?? '');
 
    $phone = $formData['phone'] ?? '';
    $category = $formData['category'] ?? '';
    $affiliate = member_cct_data($item_id, 'affiliate');
 
    if ($affiliate!='Yes'){
        // Mark as Affiliate
        member_cct_update($item_id, 'affiliate', 'Yes');
    }    
    
    if ($category=='DinarX'){
        $wa  = "🪙 𝗗𝗶𝗻𝗮𝗿𝗫 – 𝗬𝗼𝘂𝗿 𝗗𝗶𝗴𝗶𝘁𝗮𝗹 𝗧𝗼𝗸𝗲𝗻 𝗳𝗼𝗿 𝘁𝗵𝗲 𝗨𝗺𝗺𝗮𝗵! 🌍
Powered by Masjid4All, DinarX makes it easy to support your masjid, earn rewards, and grow with the community — all in a Shariah-compliant way.

✨ 𝗪𝗵𝗮𝘁 𝗖𝗮𝗻 𝗬𝗼𝘂 𝗗𝗼 𝘄𝗶𝘁𝗵 𝗗𝗶𝗻𝗮𝗿𝗫?
✅ Support masjids & halal businesses
✅ Earn rewards for sharing with friends
✅ Get services in the Masjid4All ecosystem
✅ Be part of a global, purpose-driven movement

🎁 Get 𝟱𝟬 𝗙𝗥𝗘𝗘 𝗗𝗶𝗻𝗮𝗿𝗫 when you sign up — and unlock even more when you complete your profile!

👥 𝗥𝗲𝗳𝗲𝗿 & 𝗘𝗮𝗿𝗻 𝗨𝗻𝗹𝗶𝗺𝗶𝘁𝗲𝗱!
Invite your friends — earn 50 DinarX for every person who joins. The more you share, the more you earn!

𝗟𝗲𝘁’𝘀 𝗯𝘂𝗶𝗹𝗱 𝗮 𝘀𝘁𝗿𝗼𝗻𝗴𝗲𝗿 𝗨𝗺𝗺𝗮𝗵 𝘄𝗶𝘁𝗵 𝘀𝗺𝗮𝗿𝘁, 𝗲𝘁𝗵𝗶𝗰𝗮𝗹 𝘁𝗲𝗰𝗵𝗻𝗼𝗹𝗼𝗴𝘆. 🤝
💬 Spread the word and grow the rewards!

🚀 𝗦𝘁𝗮𝗿𝘁 𝗻𝗼𝘄: ";
    $wa .= "\n👉 https://masjid4All.com/dinarx/?id=" . $user_id;

    }elseif ($category=='Masjid Directory'){
        $wa = "🕌 𝗙𝗶𝗻𝗱 𝗠𝗮𝘀𝗷𝗶𝗱𝘀 𝗡𝗲𝗮𝗿 𝗬𝗼𝘂 – 𝗔𝗻𝘆𝘁𝗶𝗺𝗲, 𝗔𝗻𝘆𝘄𝗵𝗲𝗿𝗲! 🌍

𝗧𝗿𝗮𝘃𝗲𝗹𝗶𝗻𝗴 𝗼𝗿 𝗶𝗻 𝗮 𝗻𝗲𝘄 𝗽𝗹𝗮𝗰𝗲?
✨ Masjid4All helps you locate nearby masjids in just a few taps!

𝗛𝗲𝗿𝗲’𝘀 𝘄𝗵𝗮𝘁 𝘆𝗼𝘂 𝗰𝗮𝗻 𝗱𝗼 𝘄𝗶𝘁𝗵 𝗠𝗮𝘀𝗷𝗶𝗱𝟰𝗔𝗹𝗹:
🔍 Quickly find masjids around you
🕋 View prayer times & get directions
🤝 Connect with the local Muslim community
📢 See events, announcements & updates
💼 Add your masjid or surau — 100% FREE!

🌟 𝗝𝗼𝗶𝗻 𝘁𝗵𝗲 𝗺𝗼𝘃𝗲𝗺𝗲𝗻𝘁 𝘁𝗼 𝗱𝗶𝗴𝗶𝘁𝗮𝗹𝗹𝘆 𝗰𝗼𝗻𝗻𝗲𝗰𝘁 𝘁𝗵𝗲 𝗨𝗺𝗺𝗮𝗵.

📤 Share this with friends and let’s grow the network together!

📲 Get started now:";
   $wa .= "\n👉 https://masjid4All.com/mosque/?id=" . $user_id;
 
    }elseif ($category=='Business Directory'){
        $wa = "🏪 𝗙𝗶𝗻𝗱 𝗠𝘂𝘀𝗹𝗶𝗺-𝗙𝗿𝗶𝗲𝗻𝗱𝗹𝘆 𝗕𝘂𝘀𝗶𝗻𝗲𝘀𝘀𝗲𝘀 𝗡𝗲𝗮𝗿 𝗬𝗼𝘂!

Looking for halal food, Islamic services, or Muslim-owned shops?
✨ 𝗠𝗮𝘀𝗷𝗶𝗱𝟰𝗔𝗹𝗹 𝗕𝘂𝘀𝗶𝗻𝗲𝘀𝘀 𝗗𝗶𝗿𝗲𝗰𝘁𝗼𝗿𝘆 𝗵𝗮𝘀 𝗶𝘁 𝗮𝗹𝗹 — 𝗶𝗻 𝗼𝗻𝗲 𝗲𝗮𝘀𝘆 𝗽𝗹𝗮𝗰𝗲!

𝗛𝗲𝗿𝗲’𝘀 𝘄𝗵𝗮𝘁 𝘆𝗼𝘂 𝗰𝗮𝗻 𝗱𝗼:
🔍 Discover trusted Muslim-friendly businesses nearby
🛍️ Support the Ummah with every purchase
📢 List your own business for FREE and reach more customers
🤝 Connect with a growing Muslim network

🌟 𝗪𝗵𝗲𝘁𝗵𝗲𝗿 𝘆𝗼𝘂'𝗿𝗲 𝘀𝗵𝗼𝗽𝗽𝗶𝗻𝗴 𝗼𝗿 𝗽𝗿𝗼𝗺𝗼𝘁𝗶𝗻𝗴 — 𝘁𝗵𝗶𝘀 𝗽𝗹𝗮𝘁𝗳𝗼𝗿𝗺 𝗶𝘀 𝗳𝗼𝗿 𝗬𝗢𝗨!

📤 Share this with your friends and help strengthen the Muslim economy!

📲 Start now:";
        $wa = "🧠 𝐌𝐚𝐬𝐣𝐢𝐝𝟒𝐀𝐥𝐥 𝐊𝐧𝐨𝐰𝐥𝐞𝐝𝐠𝐞 𝐇𝐮𝐛⁣
𝘞𝘩𝘦𝘳𝘦 𝘍𝘢𝘪𝘵𝘩 𝘔𝘦𝘦𝘵𝘴 𝘐𝘯𝘯𝘰𝘷𝘢𝘵𝘪𝘰𝘯⁣
⁣
𝐖𝐚𝐧𝐭 𝐭𝐨 𝐥𝐞𝐚𝐫𝐧 𝐦𝐨𝐫𝐞 𝐚𝐛𝐨𝐮𝐭:⁣
📌 What Masjid4All can do for you⁣
🪙 What is DinarX and how it works⁣
🔗 Easy guides on Blockchain & Crypto⁣
💰 How to earn while helping the Ummah⁣
⁣
🎓 𝘖𝘶𝘳 𝘒𝘯𝘰𝘸𝘭𝘦𝘥𝘨𝘦 𝘏𝘶𝘣 𝘮𝘢𝘬𝘦𝘴 𝘪𝘵 𝘴𝘶𝘱𝘦𝘳 𝘦𝘢𝘴𝘺 — 𝘱𝘦𝘳𝘧𝘦𝘤𝘵 𝘧𝘰𝘳 𝘣𝘦𝘨𝘪𝘯𝘯𝘦𝘳𝘴! 𝘓𝘦𝘢𝘳𝘯 𝘢𝘵 𝘺𝘰𝘶𝘳 𝘰𝘸𝘯 𝘱𝘢𝘤𝘦, 𝘢𝘯𝘺𝘵𝘪𝘮𝘦.⁣
⁣
🌍 𝐄𝐦𝐩𝐨𝐰𝐞𝐫 𝐲𝐨𝐮𝐫𝐬𝐞𝐥𝐟 𝐢𝐧 𝐭𝐡𝐞 𝐝𝐢𝐠𝐢𝐭𝐚𝐥 𝐰𝐨𝐫𝐥𝐝, 𝐭𝐡𝐞 𝐈𝐬𝐥𝐚𝐦𝐢𝐜 𝐰𝐚𝐲.⁣
⁣
📤 Share this with friends — because sharing knowledge is sadaqah!⁣
⁣
💡 𝐒𝐭𝐚𝐫𝐭 𝐥𝐞𝐚𝐫𝐧𝐢𝐧𝐠 𝐧𝐨𝐰 ";
        $wa .= "\n👉 https://masjid4All.com/business/?id=" . $user_id;

    }elseif ($category=='Knowledge Hub'){
        $wa = "🧠 𝐌𝐚𝐬𝐣𝐢𝐝𝟒𝐀𝐥𝐥 𝐊𝐧𝐨𝐰𝐥𝐞𝐝𝐠𝐞 𝐇𝐮𝐛⁣⁣
𝘞𝘩𝘦𝘳𝘦 𝘍𝘢𝘪𝘵𝘩 𝘔𝘦𝘦𝘵𝘴 𝘐𝘯𝘯𝘰𝘷𝘢𝘵𝘪𝘰𝘯⁣⁣
⁣⁣
𝐖𝐚𝐧𝐭 𝐭𝐨 𝐥𝐞𝐚𝐫𝐧 𝐦𝐨𝐫𝐞 𝐚𝐛𝐨𝐮𝐭:⁣⁣
📌 What Masjid4All can do for you⁣⁣
🪙 What is DinarX and how it works⁣⁣
🔗 Easy guides on Blockchain & Crypto⁣⁣
💰 How to earn while helping the Ummah⁣⁣
⁣⁣
🎓 𝘖𝘶𝘳 𝘒𝘯𝘰𝘸𝘭𝘦𝘥𝘨𝘦 𝘏𝘶𝘣 𝘮𝘢𝘬𝘦𝘴 𝘪𝘵 𝘴𝘶𝘱𝘦𝘳 𝘦𝘢𝘴𝘺 — 𝘱𝘦𝘳𝘧𝘦𝘤𝘵 𝘧𝘰𝘳 𝘣𝘦𝘨𝘪𝘯𝘯𝘦𝘳𝘴! 𝘓𝘦𝘢𝘳𝘯 𝘢𝘵 𝘺𝘰𝘶𝘳 𝘰𝘸𝘯 𝘱𝘢𝘤𝘦, 𝘢𝘯𝘺𝘵𝘪𝘮𝘦.⁣⁣
⁣⁣
🌍 𝐄𝐦𝐩𝐨𝐰𝐞𝐫 𝐲𝐨𝐮𝐫𝐬𝐞𝐥𝐟 𝐢𝐧 𝐭𝐡𝐞 𝐝𝐢𝐠𝐢𝐭𝐚𝐥 𝐰𝐨𝐫𝐥𝐝, 𝐭𝐡𝐞 𝐈𝐬𝐥𝐚𝐦𝐢𝐜 𝐰𝐚𝐲.⁣⁣
⁣⁣
📤 Share this with friends — because sharing knowledge is sadaqah!⁣⁣
⁣⁣
💡 𝐒𝐭𝐚𝐫𝐭 𝐥𝐞𝐚𝐫𝐧𝐢𝐧𝐠 𝐧𝐨𝐰 ";
        $wa .= "\n👉 https://masjid4All.com/knowledge-hub/?id=" . $user_id;

}elseif ($category=='Muslim Apps'){
    $wa = "📱 𝗠𝘂𝘀𝗹𝗶𝗺 𝗔𝗽𝗽𝘀 𝗛𝘂𝗯
𝘚𝘮𝘢𝘳𝘵 & 𝘚𝘩𝘢𝘳𝘪𝘢𝘩-𝘊𝘰𝘮𝘱𝘭𝘪𝘢𝘯𝘵 𝘛𝘰𝘰𝘭𝘴 𝘧𝘰𝘳 𝘵𝘩𝘦 𝘔𝘰𝘥𝘦𝘳𝘯 𝘔𝘶𝘴𝘭𝘪𝘮 🌙

🧠 𝗪𝗮𝗻𝘁 𝘂𝘀𝗲𝗳𝘂𝗹 𝗠𝘂𝘀𝗹𝗶𝗺 𝗮𝗽𝗽𝘀 𝘁𝗼 𝗺𝗮𝗸𝗲 𝗹𝗶𝗳𝗲 𝗲𝗮𝘀𝗶𝗲𝗿?
From prayer trackers to halal investing — we’ve got a growing list of apps made just for you!

🔍 𝗗𝗶𝘀𝗰𝗼𝘃𝗲𝗿 𝗮𝗽𝗽𝘀 𝗳𝗼𝗿:
✅ Islamic learning & reminders
✅ Quran & prayer tools
✅ Halal finance & investments
✅ Muslim lifestyle & more

👨‍💻 𝗔𝗿𝗲 𝘆𝗼𝘂 𝗮 𝗠𝘂𝘀𝗹𝗶𝗺 𝗮𝗽𝗽 𝗱𝗲𝘃𝗲𝗹𝗼𝗽𝗲𝗿?
🎯 List your app for FREE and reach Muslims around the world.
📢 Only Halal & Shariah-compliant apps will be featured.

🚀 𝗟𝗲𝘁’𝘀 𝗯𝘂𝗶𝗹𝗱 𝗮 𝘀𝗺𝗮𝗿𝘁𝗲𝗿 𝗱𝗶𝗴𝗶𝘁𝗮𝗹 𝗳𝘂𝘁𝘂𝗿𝗲 𝗳𝗼𝗿 𝘁𝗵𝗲 𝗨𝗺𝗺𝗮𝗵 — 𝘁𝗼𝗴𝗲𝘁𝗵𝗲𝗿.

📤 Share this with friends & developers!

🔗 𝗘𝘅𝗽𝗹𝗼𝗿𝗲 𝗼𝗿 𝘀𝘂𝗯𝗺𝗶𝘁 𝗻𝗼𝘄 ";
    $wa .= "\n👉 https://masjid4All.com/apps/?id=" . $user_id;

}elseif ($category=='Membership'){
    $wa = "🕌 𝗝𝗼𝗶𝗻 𝗠𝗮𝘀𝗷𝗶𝗱𝟒𝗔𝗹𝗹 – 𝗜𝘁’𝘀 𝗠𝗼𝗿𝗲 𝗧𝗵𝗮𝗻 𝗝𝘂𝘀𝘁 𝗮 𝗗𝗶𝗿𝗲𝗰𝘁𝗼𝗿𝘆! 🌟

Be part of a growing digital movement for the Ummah — 𝗮𝗹𝗹 𝗳𝗼𝗿 𝗙𝗥𝗘𝗘!

𝗛𝗲𝗿𝗲’𝘀 𝘄𝗵𝗮𝘁 𝘆𝗼𝘂 𝗴𝗲𝘁 𝗮𝘀 𝗮 𝗺𝗲𝗺𝗯𝗲𝗿:
✅ Find nearby masjids & halal businesses
✅ Get exclusive content, tools & rewards
✅ Earn DinarX tokens by sharing & referring
✅ List your masjid, business, or program – totally FREE
✅ Stay updated with events & community news
✅ Support the digital future of our Ummah

🌍 𝗪𝗵𝗲𝘁𝗵𝗲𝗿 𝘆𝗼𝘂'𝗿𝗲 𝗮 𝘂𝘀𝗲𝗿, 𝗯𝘂𝘀𝗶𝗻𝗲𝘀𝘀 𝗼𝘄𝗻𝗲𝗿, 𝗼𝗿 𝗰𝗼𝗺𝗺𝘂𝗻𝗶𝘁𝘆 𝗹𝗲𝗮𝗱𝗲𝗿 — 𝘁𝗵𝗲𝗿𝗲’𝘀 𝘀𝗼𝗺𝗲𝘁𝗵𝗶𝗻𝗴 𝗳𝗼𝗿 𝗲𝘃𝗲𝗿𝘆𝗼𝗻𝗲!

📤 Share this with friends & help grow our digital Ummah.

💥 𝗜𝘁’𝘀 𝗙𝗥𝗘𝗘 𝘁𝗼 𝗷𝗼𝗶𝗻 — 𝘁𝗮𝗸𝗲𝘀 𝗷𝘂𝘀𝘁 𝗮 𝗺𝗶𝗻𝘂𝘁𝗲!
🔗 Sign up now ";
    $wa .= "\n👉 https://masjid4All.com/member/?id=" . $user_id;

}elseif ($category=='Affiliate Program'){    
    $wa = "🌙 𝗝𝗼𝗶𝗻 𝘁𝗵𝗲 𝗠𝗮𝘀𝗷𝗶𝗱𝟒𝗔𝗹𝗹 𝗔𝗳𝗳𝗶𝗹𝗶𝗮𝘁𝗲 𝗣𝗿𝗼𝗴𝗿𝗮𝗺 & 𝗦𝘁𝗮𝗿𝘁 𝗘𝗮𝗿𝗻𝗶𝗻𝗴! 🕌

💰 𝗘𝗮𝗿𝗻 𝗗𝗶𝗻𝗮𝗿𝗫 & 𝗖𝗼𝗺𝗺𝗶𝘀𝘀𝗶𝗼𝗻𝘀 — 𝗝𝘂𝘀𝘁 𝗯𝘆 𝗦𝗵𝗮𝗿𝗶𝗻𝗴!

Want to support the Ummah and earn rewards?
𝗜𝘁’𝘀 𝗲𝗮𝘀𝘆 𝘄𝗶𝘁𝗵 𝘁𝗵𝗲 𝗠𝗮𝘀𝗷𝗶𝗱𝟒𝗔𝗹𝗹 𝗔𝗳𝗳𝗶𝗹𝗶𝗮𝘁𝗲 𝗣𝗿𝗼𝗴𝗿𝗮𝗺!

𝗛𝗲𝗿𝗲’𝘀 𝗵𝗼𝘄 𝗶𝘁 𝘄𝗼𝗿𝗸𝘀:
🔗 Share your special link
👥 When someone signs up…
🎉 You get 𝟓𝟎 𝗗𝗶𝗻𝗮𝗿𝗫 – 𝗙𝗥𝗘𝗘!
💸 Premium Members also 𝗲𝗮𝗿𝗻 𝗿𝗲𝗮𝗹 𝗰𝗮𝘀𝗵 𝗰𝗼𝗺𝗺𝗶𝘀𝘀𝗶𝗼𝗻𝘀!

✅ No need to sell anything
✅ Just share info with your friends & followers
✅ Everyone wins!

🔥 The more you share, the more you earn!

🚀 𝗥𝗲𝗮𝗱𝘆 𝘁𝗼 𝗯𝗲𝗴𝗶𝗻?
Register now & get your link";
    $wa .= "\n👉 https://masjid4All.com/member/?id=" . $user_id;

}else{    
        $wa  = "🕌 𝗠𝗮𝘀𝗷𝗶𝗱𝟰𝗔𝗹𝗹
𝙂𝙡𝙤𝙗𝙖𝙡 𝘿𝙞𝙧𝙚𝙘𝙩𝙤𝙧𝙮 𝙛𝙤𝙧 𝙈𝙖𝙨𝙟𝙞𝙙𝙨 & 𝙈𝙪𝙨𝙡𝙞𝙢 𝘽𝙪𝙨𝙞𝙣𝙚𝙨𝙨𝙚𝙨

Looking for nearby masjids, halal places, or Muslim-friendly services?

🎁 𝗥𝗲𝗴𝗶𝘀𝘁𝗲𝗿 𝗻𝗼𝘄 & 𝗴𝗲𝘁 𝗙𝗥𝗘𝗘 𝟱𝟬 𝗗𝗶𝗻𝗮𝗿𝗫 𝘁𝗼𝗸𝗲𝗻𝘀!

📢 Promote your business or service — 1𝟬𝟬% 𝗙𝗥𝗘𝗘!

Let’s connect the Ummah, one masjid and business at a time.

📲 Share this with your friends and be part of the movement!
𝗧𝗼𝗴𝗲𝘁𝗵𝗲𝗿, 𝘄𝗲 𝗯𝘂𝗶𝗹𝗱 𝗮 𝘀𝘁𝗿𝗼𝗻𝗴𝗲𝗿, 𝘂𝗻𝗶𝘁𝗲𝗱 𝗨𝗺𝗺𝗮𝗵. 🌙

🔗 Click the link to get started ";
    $wa .= "\n👉 https://masjid4All.com/?id=" . $user_id;
        
    }
    
    //Send Whatsapp
    whatsapp_send_message($phone, $wa, $media);

 
}

//////////////////////////////
///// END ////////////////////
//////////////////////////////


//FORGOT PASSWORD FORM
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    global $wpdb;
    
    if ((int) $form->id !== 14) { 
        return;
    }

    // Retrieve and sanitize phone input
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));

    // Find user by phone number
    $user = find_user_by_phone($phone);

    if($user){
        // Generate a secure temporary password
        $pwd = mt_rand(100000, 999999);
    
        // Update the user's password
        wp_update_user([
            'ID'        => $user->ID,
            'user_pass' => $pwd,
        ]);
        update_user_meta($user->ID, 'change_password', ''); // Clear change password flag
    
        // Fetch user details
        $name = get_user_meta($user->ID, 'first_name', true) ?: 'User';
        
        // Construct WhatsApp message
        $wa_message  = "*RESET PASSWORD*\n\n"; 
        $wa_message .= "Dear *$name* 👋\n\n";
        $wa_message .= "We have reset your password. Please log in with the temporary password.\n\n";
        $wa_message .= "👉 https://masjid4all.com/member/?phone=" . $phone . "\n\n";
        $wa_message .= "Login ID : *$phone* \n";
        $wa_message .= "Temporary Password : *$pwd*\n\n";
        $wa_message .= "Remember to change your password upon login.\nThank you.\n\n";
        $wa_message .= "_If you didn’t request this reset, please inform us._";
    
        // Send WhatsApp message
        whatsapp_send_message($phone, $wa_message, $media);
    }else{
        // Not a member.
        $response = [
            'errors' => "<b>Phone " . $phone . " is not registered.<br>Please Register.</b><br><br>"
        ];
        wp_send_json($response, 423);
    }
  
}, 10, 3);



//////////////////////////////////
// MEMBER SUMMARY             //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('member_summary_country', 'member_summary_country_shortcode');
}); 
 
function member_summary_country_shortcode() {
    ob_start();

    global $wpdb;
    
    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }

    $results = $wpdb->get_results("
        SELECT country, COUNT(*) as business_count 
        FROM wp_jet_cct_member
        " . $date_query . "
        GROUP BY country
        ORDER BY business_count DESC
    ");
    
    if ($results) {
        $total_mosques = 0; // Initialize total count
        $total_country = 0;
        $tbl = '<table>';
        $cnt = 0;
        foreach ($results as $row) {
            $cnt = $cnt + 1;
            if ($cnt < 10){
                $tbl.= '<tr>';
                $tbl .= "<td>{$row->country}</td><td style='text-align: right'>{$row->business_count}</td>";
                $tbl.= '</tr>';
            }
            //$ret .= "{$row->country} ({$row->mosque_count}) &#9679; ";
      
            $total_mosques += $row->business_count; // Add to total count
            $total_country += 1;
        }
        $tbl.= '</table>';
          
        //$summ.= 'Total Mosques : <b>' . number_format($total_mosques) . '<br><br>' ;
        //$summ.= '<b>Total Mosques : ' . $total_mosques . '</b><br><br> ';
        
        //$ret = $summ . $ret;;
     
     } else {
        $ret = "No data found.";
    }
    
    $ret = 'Total Member : <b>' . number_format($total_mosques) . '</b><br><br>';
    return $ret . $tbl . ob_get_clean();
}
  





//MEMBER - FLUENTFORM SHORTCODE (CCT - MEMBER)
//NAME
add_filter('fluentform/editor_shortcode_callback_uname', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'name');
    
    return $dynamicValue;
    
}, 10, 2);

//MEMBER - FLUENTFORM SHORTCODE (CCT - MEMBER)
//SEX
add_filter('fluentform/editor_shortcode_callback_usex', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'sex');
    
    return $dynamicValue;
    
}, 10, 2);

//MEMBER - FLUENTFORM SHORTCODE (CCT - MEMBER)
//BIRTHDAY
add_filter('fluentform/editor_shortcode_callback_ubirthdate', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'birthdate');
    
    return $dynamicValue;
    
}, 10, 2);

//PHONE
add_filter('fluentform/editor_shortcode_callback_uphone', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'phone');
    
    return $dynamicValue;
    
}, 10, 2);

//EMAIL
add_filter('fluentform/editor_shortcode_callback_uemail', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'email');
    if (strpos($dynamicValue, 'pw.com') !== false) {
        $dynamicValue = '';
    }
    
    return $dynamicValue;
    
}, 10, 2);

//COUNTRY
add_filter('fluentform/editor_shortcode_callback_ucountry', function ($value, $form) {
    
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $dynamicValue = get_cct_member_data($user_id, 'country');
    
    return $dynamicValue;
    
}, 10, 2);



//////////////////////////////////////////
// Register User - DinarX                //
//////////////////////////////////////////

add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Only process form ID 5 - Register form
    if ((int) $form->id !== 31) {
        return;
    }

    // Sanitize phone and name inputs
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $name = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));

    // Clean up phone number to keep only digits
    $phone = preg_replace('/\D/', '', $phone);
    $phone = preg_replace('/^\+/', '', $phone); // remove + sign

    // Find the user by phone number
    $user_id = find_userid_by_phone($phone);
    //$user = find_user_by_phone($phone);

    if ($user_id > 0) {
        // User already registered, proceed with updates
        $response = [
            'errors' => "<b>Phone " . $phone . " is already registered.</b><br><br>"
        ];
        wp_send_json($response, 423);
    } else {
        // User not found, proceed with registration
        $user_id = create_new_user($name, $phone);
    }

}, 10, 3);




// MEMBER - REGISTER /////////////////////





//////////////////////////////////////////
// SHARE VIA WHATSAPP                   //
//////////////////////////////////////////
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 6) {
        return;
    }

    // Sanitize phone and name inputs
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $name = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));
    $country = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'country'));
    $referrer_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'referrer_id'));
    $partner_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'partner_id'));

    // Clean up phone number to keep only digits
    $phone = preg_replace('/\D/', '', $phone);
    $phone = preg_replace('/^\+/', '', $phone); // remove + sign

    // Find the user by phone number
    $users = find_user_by_phone($phone);

    if (!empty($users)) {
        // User already registered, proceed with updates
        $user = $users[0];
        $user_id = $user->ID;
    } else {
        // User not found, proceed with registration
    }
    
    // Send Message;
    $wa_message = "*Masjid4ALL - a comprehensive global directory of mosques*\n\n";
    $wa_message .= "Join us in this impactful journey and help us empower the Ummah together!\n\n";
    $wa_message .= "🚀 Masjid4ALL is on a mission to index 1 million mosques worldwide\n\n";
    //$wa_message .= "— and YOU can be a part of it!\n\n";
    $wa_message .= "✅ 10,000+ mosques from 189 countries already added!\n";
    $wa_message .= "✅ One click to search & update your nearest masjid!\n\n";
    $wa_message .= "💡 Imagine the impact — travelers, new Muslims, and the entire Ummah can find accurate, up-to-date mosque locations effortlessly!\n\n";
    $wa_message .= "🔍 Be a Contributor Today!\n\n";
    $wa_message .= "👉 Visit masjid4all.com/?id=" . $user_id  . "\n\n";
    //$wa_message .= "👉 Search & Add Your Local Masjid\n\n";
    
    $wa_message .= "*Let’s build this global masjid directory together!*\n\n";
    
    $wa_message .= "Jazakumullahu khayran for your support! 🌙🤲\n\n";
    $wa_message .= "_Please *SHARE* this message_\n\n";
    //$wa_message .= "_Kindly let us know if you received this message in error._\n";

    // Media link for the WhatsApp message (could be a static welcome banner)
    $media_url = "http://masjid4all.com/wp-content/uploads/2025/03/Masjid4ALL-Main-Banner.jpg";

    // Send the message via WhatsApp API or integration function
    whatsapp_send_message($phone, $wa_message, $media_url);

}, 10, 3);




// Function to get a specific field value from CCT
function get_cct_member_data($user_id, $field) {
    global $wpdb;
    // Retrieve the value from the CCT table
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT $field FROM wp_jet_cct_member WHERE user_id = %d",
            $user_id
        )
    );

    // Return result or an error if not found
    return $result ? $result : "";
}

/////////////////////////////
// Member Page Message     //
/////////////////////////////

add_action('plugins_loaded', function() {
    // Add the shortcode.
    add_shortcode('member_page_message', 'member_page_message_shortcode');
});
 
function member_page_message_shortcode() {
    $ref = ($_GET['ref']);
    $phone = ($_GET['phone']);
    if ($ref == 'm1'){
        $ret = 'Please check your Whatsapp (' . $phone . ') for your temporary password<br>';
        
    }

    return $ret;
} 

// MEMBER - FIRST LOGIN //////////////////
// FIrst login and to activate          //
//////////////////////////////////////////
function member_first_login($user_id, $phone, $password) {
    // Get the user's first name and username
    $name = get_user_meta($user_id, 'first_name', true);
    $user_info = get_userdata($user_id);
    $username = $user_info->user_login;

    // Get partner data from cookie if available
    $partner = isset($_COOKIE['partner']) ? sanitize_text_field($_COOKIE['partner']) : 'Unknown';

    // Clean up the phone number to ensure it's valid
    $phone = preg_replace('/\D/', '', $phone); // Ensure phone contains only numbers

    // Validate phone number format (optional, customize as per requirements)
    if (strlen($phone) < 10) {
        return; // Stop execution if phone number is invalid
    }

    // Create the message content for WhatsApp
    $wa_message = "*WELCOME TO PEWARISAN*\n\n";
    $wa_message .= "Name : " . esc_html($name) . "\n";
    $wa_message .= "Phone : " . esc_html($phone) . "\n\n";
    $wa_message .= "Your temporary password is \n*" . esc_html($password) . "*\n\n";
    $wa_message .= "*Make sure you change your password upon login.*\n\n";
    $wa_message .= "Thank you\n*Pewarisan.my/" . esc_html($partner) . "*\n\n";
    $wa_message .= "_Kindly let us know if you received this message in error._\n";

    // Media link for the WhatsApp message (could be a static welcome banner)
    $media_url = "https://pewarisan.my/wp-content/uploads/2025/01/Pewarisan-Welcome-Banner.png";

    // Send the message via WhatsApp API or integration function
    whatsapp_send_message($phone, $wa_message, $media_url);
}  

/*
// MEMBER - LOGIN /////////////////////
// Login User                        //
///////////////////////////////////////
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    // Ensure we're only processing form ID 4
    if ((int) $form->id !== ww4) { 
        return;
    }

    // Retrieve phone and password from submitted form data
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'whatsapp'));
    $password = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'password'));

    if (empty($phone) || empty($password)) {
        wp_send_json(['errors' => 'Phone and Password fields are required.'], 400);
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


// MEMBER - LIFE PLANNING /////////////
// Display Member's Life Planning    //
///////////////////////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_life_plan', 'member_life_plan_shortcode' );
});  

function member_life_plan_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user ID and category safely
    $user_id = $current_user->ID;
    $category = esc_html(get_user_meta($user_id, 'user_category', true) ?? '');

    // Get life planning data, ensuring it's numeric
    //$life_planning = get_user_meta($user_id, 'life_planning', true);
    //$life_planning = is_numeric($life_planning) ? (int) $life_planning : 0;
    $life_counter = (int)get_cct_member_data($user_id, 'life_counter');
    // Start output buffering
    ob_start(); ?>
 
    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for ahlik_info');
        });
    </script>

    <p>Create a roadmap to achieve your dreams. Set clear goals and confidently manage your life priorities with actionable plans.</p>

    <?php if ($life_counter <= 0) : ?>
        <p style="color: green; font-weight: bold;">🔴 Start your Life Planning Now</p>
    <?php endif; ?>

    <?php 
    return ob_get_clean();
}

// MEMBER - LEGACY PLANNING ///////////
// Display Member's Legacy Planning  //
///////////////////////////////////////
add_action( 'plugins_loaded', function() { 
    // Add the shortcode.
    add_shortcode( 'member_legacy_plan', 'member_legacy_plan_shortcode' );
});  

function member_legacy_plan_shortcode() {
    // Ensure the current user is loaded
    global $current_user;
    wp_get_current_user();

    // Get user ID and category safely
    $user_id = $current_user->ID;
    $category = esc_html(get_user_meta($user_id, 'user_category', true) ?? '');

    // Get legacy planning data, ensuring it's numeric
    $legacy_counter = (int)get_cct_member_data($user_id, 'legacy_counter');
    // Start output buffering
    ob_start(); ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for member_legacy_plan');
        });
    </script>

    <p>Protect your family’s future. Start planning your legacy today for smooth asset distribution and a lasting impact.</p>

    <?php if ($legacy_counter <= 0) : ?>
        <p style="color: green; font-weight: bold;">🔴 Start your Legacy Planning Now</p>
    <?php endif; ?>

    <?php 
    return ob_get_clean();
}

add_action( 'plugins_loaded', function() {
    add_shortcode( 'member_wealth_plan', 'member_wealth_plan_shortcode' );
});

function member_wealth_plan_shortcode() {
    global $current_user;
    wp_get_current_user(); // Ensures the user data is loaded

    $user_id = $current_user->ID;
    $category = esc_html( get_user_meta( $user_id, 'user_category', true ) ?: '' ); // Slightly shorter
    $inheritance_planning = (int) get_user_meta( $user_id, 'inheritance_planning', true ) ?: 0; // More concise type casting

    ob_start(); ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Footer loaded for member_inheritance_plan'); // Consider a more descriptive console message
        });
    </script>

    <p>Smart financial planning, wealth creation, and investment strategies to secure a prosperous future.</p>

    <?php if ( $inheritance_planning === 0 ) : // More precise comparison ?>
        <p style="color: green; font-weight: bold;">🔴 Start Your Wealth Planning Now</p>
    <?php endif; ?>

    <?php return ob_get_clean();
}


add_action( 'plugins_loaded', function() {
    add_shortcode( 'member_legacy_analysis', 'member_legacy_analysis_shortcode' );
});
function member_legacy_analysis_shortcode() {
    $user_id = get_current_user_id();
    $analysis = get_cct_member_data($user_id, 'legacy_plan');
  
    return $analysis;
}  

// Function to find user by phone or email
function find_user_by_phone_or_email($value) {
    $users = get_users([
        'meta_key' => 'user_phone',
        'meta_value' => $value
    ]);

    if (empty($users)) {
        $users = get_users([
            'meta_key' => 'user_email',
            'meta_value' => $value
        ]);
    }

    return !empty($users) ? $users[0] : null;
}

add_action( 'plugins_loaded', function() {
    add_shortcode( 'member_life_analysis', 'member_life_analysis_shortcode' );
}); 
function member_life_analysis_shortcode() {
    $user_id = get_current_user_id();
    $analysis = get_cct_member_data($user_id, 'life_plan');
  
    return $analysis;
}

//////////////////////////////////
// MEMBER SUMMARY BY REGION     //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('member_summary_continent', 'member_summary_continent_shortcode');
}); 

function member_summary_continent_shortcode() {
    ob_start();
    global $wpdb;

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }
    
    $results = $wpdb->get_results("
        SELECT continent, COUNT(*) AS member_count
        FROM {$wpdb->prefix}jet_cct_member
        WHERE continent IS NOT NULL AND continent <> ''
        " . $date_query . "
        GROUP BY continent
        ORDER BY member_count DESC
    ");
    
    $total_member = 0;
    $total_regions = 0;

    // Header Info (Blue Theme)
    echo '<div style="padding: 15px; background: #e7f3ff; border-left: 5px solid #2196f3; border-radius: 4px; margin-bottom:15px;">';
    
    if ($results) {
        foreach ($results as $row) {
            $total_member += $row->member_count; 
            $total_regions += 1;
        }
    }
    echo 'Total Global Members: <b>' . number_format($total_member) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if ($results) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Members</th>
                 </tr>';
        
        foreach ($results as $row) {
            $tot = number_format($row->member_count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($row->continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-member-drilldown-btn' data-continent='" . esc_attr($row->continent) . "' style='text-decoration: underline; color: #2196f3; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo "<p>No member regional data found.</p>";
    }

    // HTML Modal Member
    ?>
    <div id="m4aMemberDrilldownModal" class="m4a-modal-overlay">
        <div class="m4a-modal-content">
            <span class="m4a-close-modal m4a-member-close">&times;</span>
            <h2 id="m4aMemberModalTitle" style="margin-top:0; color:#2196f3; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>
            
            <div id="m4aMemberModalLoader" style="text-align:center; padding:50px 0; display:none;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:30px; color:#2196f3;"></i>
                <p style="margin-top:10px; color:#555;">Compiling data...</p>
            </div>

            <div id="m4aMemberModalBody" class="m4a-modal-body" style="display:none;">
                <div class="m4a-modal-chart-wrap">
                    <h4 style="margin: 0 0 15px 0; color:#444;">Top 10 Countries</h4>
                    <div class="m4a-chart-canvas-box">
                        <canvas id="m4aMemberDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap">
                    <div id="m4aMemberModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("m4aMemberDrilldownModal");
        if (modal && !modal.closest('body > .m4a-modal-overlay')) { document.body.appendChild(modal); }

        const closeBtn = document.querySelector(".m4a-member-close");
        const drillBtns = document.querySelectorAll(".m4a-member-drilldown-btn");
        let chartInst = null; 

        drillBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aMemberModalTitle").innerText = "Members in " + continent;
                modal.style.display = "block";
                document.getElementById("m4aMemberModalBody").style.display = "none";
                document.getElementById("m4aMemberModalLoader").style.display = "block";

                const formData = new FormData();
                formData.append('action', 'm4a_get_member_drilldown');
                formData.append('continent', continent);

                fetch("<?php echo admin_url('admin-ajax.php'); ?>", { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById("m4aMemberModalLoader").style.display = "none";
                        document.getElementById("m4aMemberModalBody").style.display = "flex";
                        document.getElementById("m4aMemberModalTableContent").innerHTML = data.data.table_html;

                        const ctx = document.getElementById("m4aMemberDrilldownChart").getContext("2d");
                        if(chartInst != null) { chartInst.destroy(); }

                        chartInst = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: data.data.chart_labels,
                                datasets: [{
                                    data: data.data.chart_data,
                                    backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#2ecc71', '#e74c3c', '#34495e'],
                                    borderWidth: 2, borderColor: '#ffffff'
                                }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11} } } } }
                        });
                    }
                });
            });
        });

        const closeModal = function() { modal.style.display = "none"; };
        if(closeBtn) closeBtn.onclick = closeModal;
        window.addEventListener('click', function(e) { if (e.target == modal) closeModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

// AJAX BACKEND: MEMBER
add_action('wp_ajax_m4a_get_member_drilldown', 'm4a_ajax_member_drilldown_callback');
function m4a_ajax_member_drilldown_callback() {
    global $wpdb;
    $continent = sanitize_text_field($_POST['continent'] ?? '');

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }
   
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT country, COUNT(*) as count 
        FROM {$wpdb->prefix}jet_cct_member
        WHERE continent = %s AND country IS NOT NULL AND country <> ''
        " . $date_query . "
        GROUP BY country ORDER BY count DESC
    ", $continent));

    $chart_labels = []; $chart_data = [];
    $html = '<table style="width:100%; border-collapse: collapse;">';
    $html .= '<tr style="background:#f1f1f1; text-align:left;"><th style="padding:8px; border-bottom:2px solid #ccc;">Country</th><th style="padding:8px; border-bottom:2px solid #ccc; text-align:right;">Total</th></tr>';

    $i = 0;
    foreach ($results as $row) {
        $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;">' . esc_html($row->country) . '</td><td style="padding:8px; border-bottom:1px solid #eee; text-align:right; font-weight:bold;">' . intval($row->count) . '</td></tr>';
        if ($i < 10) { $chart_labels[] = $row->country; $chart_data[] = (int)$row->count; }
        $i++;
    }
    $html .= '</table>';
    wp_send_json_success(['table_html' => $html, 'chart_labels' => $chart_labels, 'chart_data' => $chart_data]);
}

*/
