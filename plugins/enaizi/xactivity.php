<?php

// ADD NEW ACTIVITY
function activity_add_new($user_id, $business_id, $activity) {
    global $wpdb;
 
    // Set Malaysian timezone
    date_default_timezone_set('Asia/Kuala_Lumpur');
    // Current Malaysian date & time
    $myTime = date('Y-m-d H:i:s');
    
    $name = wp_get_current_user()->nickname;
    if ($name!=""){
       $activity .= ' by ' . $name; 
    }

    $staff_id = get_current_user_id();
    
    //CREATE CCT MEMBER
    $user_data = array(
        'user_id'       => $user_id,
        'business_id'   => $business_id,
        'staff_id'      => $staff_id,
        'activity'      => $activity,
        'cct_created'   => $myTime,
    );

    // Insert into the CCT table
    $result = $wpdb->insert(
        'wp_jet_cct_activity', 
        $user_data
    );
    
    $cct_id = $wpdb->insert_id;

    return $cct_id;
} 