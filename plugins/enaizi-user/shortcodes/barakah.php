<?php
 
add_shortcode('niz_user_barakah_points', 'niz_user_barakah_points_shortcode');
function niz_user_barakah_points_shortcode($atts) {
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
        $num  = 5000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Platinum';
    } elseif ($total_points >= 500){
        $rank = 'Silver'; 
        $num  = 2000 - $total_points;
        $next = 'You need ' . $num . ' more points to reach Gold';
    } else {
        $rank = 'Bronze';
        $num  = 500 - $total_points;
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
add_shortcode('niz_user_barakah', 'niz_user_barakah_shortcode');
function niz_user_barakah_shortcode() {
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
 * 2026-08-21. The signature is kept because eleven call sites across
 * enaizi-user and enaizi-mfa still use it; only the body changed. Two bugs
 * lived here and were visible in production data:
 *
 * 1. The dedup check read `WHERE description = %s` with NO `user_id`, so once
 *    any one member held an award with a given description, no other member
 *    could ever receive that same-named award again.
 * 2. It wrote `$points` - the value of THIS award - into the single-value
 *    jet_cct_member.points field instead of the recomputed running total, so
 *    that field drifted from the ledger after the very first award. The
 *    fingerprint was unmistakable: every drifted row held the value of the
 *    member's most recent award. User 14267 showed 10 points against a
 *    ledger of 2,545 while ranked Gold.
 *
 * The ledger itself was always right; only the stored figure the UI reads was
 * wrong. mfa_award_points() dedupes on (user_id, description) and writes the
 * recomputed total.
 *
 * Return shape is preserved for callers that inspect it. The old function
 * returned null when the award already existed; this returns the array
 * mfa_award_points() gives, which is strictly more informative and still
 * falsy-safe on ['success'].
 */
function niz_user_add_points($user_id, $desc, $points) {
    if ( function_exists( 'mfa_award_points' ) ) {
        return mfa_award_points( $user_id, $desc, $points );
    }

    // mfa-core inactive: award nothing rather than reintroduce the buggy
    // insert. A missing award is recoverable from the ledger; a wrong
    // points field silently misreports every member's balance.
    return [
        'success' => false,
        'message' => 'mfa-core inactive - no award written',
    ];
}

// Invites
add_shortcode('niz_user_invite', 'niz_user_invite_shortcode');
function niz_user_invite_shortcode() {
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