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

/**
 * Delegates to mfa_award_points() - see mfa-core/includes/barakah.php.
 *
 * 2026-08-21. This one had NO dedup check at all, so a shortcode re-rendering
 * or a double submit awarded the same milestone twice, and like its two
 * siblings it wrote this award's value into jet_cct_member.points rather than
 * the recomputed total.
 */
function niz_member_add_points($user_id, $desc, $points) {
    if ( function_exists( 'mfa_award_points' ) ) {
        return mfa_award_points( $user_id, $desc, $points );
    }

    return [
        'success' => false,
        'message' => 'mfa-core inactive - no award written',
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