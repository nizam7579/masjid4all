<?php

add_shortcode('niz_member_platform_credit', 'niz_member_platform_credit_shortcode');
function niz_member_platform_credit_shortcode($atts) {
    // 1. Crucial: Bring $wpdb into the function scope
    global $wpdb; 
  
    $user_id = get_current_user_id();
    
    // Safety check for guests
    if (!$user_id) {
        return 'Please log in to view your points.';
    }

    // 3. Formatted correctly - Fixed the unclosed </b> tag here!
    $credit = 0;
    $utilized = 0;
    $balance = 0;
    
    $ret  = 'Platform Credit : $' . number_format($credit, 2, '.', ',') . '<br>';
    $ret .= 'Utilized : $' . number_format($utilized, 2, '.', ',') . '<br>'; // <-- Fixed </b> here
    $ret .= 'Balance : $' . number_format($balance, 2, '.', ',') . '<br>'; // <-- Fixed </b> here

    return $ret;
}

 
add_shortcode('niz_member_barakah_points', 'niz_member_barakah_points_shortcode');
function niz_member_barakah_points_shortcode($atts) {
    // 1. Crucial: Bring $wpdb into the function scope
    global $wpdb; 

    $user_id = get_current_user_id();
    
    // Safety check for guests
    if (!$user_id) {
        return 'Please log in to view your points.';
    }
    
    $table = $wpdb->prefix . 'jet_cct_barakah';
    $points = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(points) FROM {$table} WHERE user_id = %d", 
            $user_id
        )
    );

    $total_points = $points ? intval($points) : 0;

    $rank = '';
    $next = '';
    
    // 2. Rank logic (Using $total_points for safety in math)
    if ($total_points >= 10000){
        $rank = 'Diamond';        
        $next = 'Congratulations!';
    } elseif ($total_points >= 5000){
        $rank = 'Platinum';
        $num  = 10000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Diamond';
    } elseif ($total_points >= 2000){
        $rank = 'Gold';  
        $num  = 6000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Platinum';
    } elseif ($total_points >= 500){
        $rank = 'Silver'; 
        $num  = 3000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Gold';
    } else {
        $rank = 'Bronze';
        $num  = 1000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Silver';
    }

    // Update Rank/Points
    niz_user_update_field($user_id, 'rank', $rank);
    niz_user_update_field($user_id, 'points', $points);
    
    // 3. Formatted correctly - Fixed the unclosed </b> tag here!
    $ret  = 'Barakah Points: ' . number_format($total_points) . '<br>';
    $ret .= 'Rank: ' . $rank . '<br>'; // <-- Fixed </b> here
    $ret .= $next;
    
    return $ret;
}

// Barakah
add_shortcode('niz_member_barakah', 'niz_member_barakah_shortcode');

function niz_member_barakah_shortcode() {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $rank   = niz_user_field_by_userid($user_id, 'rank');
    $points = niz_user_field_by_userid($user_id, 'points');
    
    if (!$points){
        $points = 100;
        niz_user_add_points($user_id, 'New Registration', $points);
    }
    
    ob_start();
    ?>
   
    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <b><?= esc_html('🌟 Earn Barakah Points'); ?></b>
    <?= 'Want to help our community grow? Barakah Points is our community rewards program designed to encourage active participation, positive contributions, and meaningful engagement within the platform.<br>'; ?>
 
    <?php
    return ob_get_clean();
}    

function niz_member_add_points($user_id, $desc, $points) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'jet_cct_barakah'; 
    
    // Sanitize/Cast inputs to ensure data integrity
    $insert_data = [
        'user_id'     => (int) $user_id,
        'description' => sanitize_text_field($desc),
        'points'      => (int) $points,
        'cct_created' => current_time('mysql')
    ];

    $insert_format = [
        '%d', // user_id
        '%s', // description
        '%d', // points
        '%s'  // cct_created
    ];

    $result = $wpdb->insert($table, $insert_data, $insert_format);

    if ($result === false) {
        return [
            'success' => false,
            'message' => $wpdb->last_error,
        ];
    }

    niz_user_update_field($user_id, 'points', $points);

    return [
        'success'   => true,
        'insert_id' => (int) $wpdb->insert_id // Casted to int for clean API responses
    ];
} 

// Invites
add_shortcode('niz_member_invite', 'niz_member_invite_shortcode');
function niz_member_invite_shortcode() {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    ob_start();
    ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <b><?= esc_html('Invite Friends and Family'); ?></b>
    <?= esc_html('Share with family and friends and invite them to join. Get 100 points for every member you bring in.'); ?>


    <?php
    return ob_get_clean();
}    