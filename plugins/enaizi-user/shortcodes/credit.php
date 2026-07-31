<?php

function niz_user_add_credit($user_id, $desc, $credit) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'jet_cct_platform_credit'; 
    
    // Sanitize/Cast inputs to ensure data integrity
    $insert_data = [
        'user_id'     => (int) $user_id,
        'description' => sanitize_text_field($desc),
        'credit'      => $credit,
        'cct_created' => current_time('mysql')
    ];

    $insert_format = [
        '%d', // user_id
        '%s', // description
        '%d', // credit
        '%s'  // cct_created
    ];

    $result = $wpdb->insert($table, $insert_data, $insert_format);

    if ($result === false) {
        return [
            'success' => false,
            'message' => $wpdb->last_error,
        ];
    }

    //niz_user_update_field($user_id, 'points', $points);

    return [
        'success'   => true,
        'insert_id' => (int) $wpdb->insert_id // Casted to int for clean API responses
    ];
} 


add_shortcode('niz_user_platform_credit', 'niz_user_platform_credit_shortcode');
function niz_user_platform_credit_shortcode($atts) {
    // 1. Crucial: Bring $wpdb into the function scope
    global $wpdb; 

    $user_id = get_current_user_id();
    
    // Safety check for guests
    if (!$user_id) {
        return 'Please log in to view your points.';
    }
    
    $table = $wpdb->prefix . 'jet_cct_platform_credit';
    $credits = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(credit) FROM {$table} WHERE user_id = %d", 
            $user_id
        )
    );

    $total_credits = $credits ? intval($credits) : 0;

    // 3. Formatted correctly - Fixed the unclosed </b> tag here!
    $utilized = 0;
    $balance = $total_credit - $utilized;
    
     $ret  = 'Platform Credits : ' . number_format( $total_credits,2) . '<br>';
    $ret .= 'Utilized : ' . number_format( $utilized,2) . '<br>';
    $ret .= 'BALANCE : ' . number_format( $balance,2) . '<br>';
   
    return $ret;
}


