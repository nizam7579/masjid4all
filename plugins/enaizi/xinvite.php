<?php

/*

// INVITE BUSINESS OWNER
add_shortcode('invite_business_owners', 'invite_business_owners_shortcode');
 
function invite_business_owners_shortcode() {
    global $wpdb;

    // CHECK IF ADMIN
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    
    if ( current_user_can('administrator') ) {
        //$item_id = 8528;
        //echo 'You are an admin.';
    }else{
        wp_redirect('/member/');
        exit;
    }

    // Table name
    $table = $wpdb->prefix . "jet_cct_business";
    
    $offset = 0;
    $limit = 1;
    $country = 'United Kingdom';
    $start_no = '447';
    // Get results
    // Malaysia - 601
    // UK - 447
    
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE country = %s
             AND whatsapp LIKE %s
             AND (wa != %s OR wa IS NULL OR wa = '')
             LIMIT %d OFFSET %d",
            $country,
            '447%',   // This now works with LIKE
            'Yes',    // Exclude already invited
            $limit,
            $offset
        )
    );
     
    // Loop through results
    foreach ($results as $row) {
        $item_id = $row->_ID;
        $name = $row->name;
        $country = $row->country;
        $page_url =  $row->page_url;
        $address =  $row->address;
        $phone   = $row->whatsapp;
        
        $post_id   = $row->post_id;
        $updated = get_post_meta($post_id, 'updated', true);
        
        // Randomly select the message variations
        $variations = [
            "Assalamualaikum & Hello 🌙,\n\n*Masjid4All* connects the Muslim community to mosques, halal food, and trusted local businesses worldwide.\nWith the help of our AI agents 🤖, we’ve discovered and listed your business in our Muslim-friendly Business Directory.\n\n",
        
            "Assalamualaikum & Greetings 🌙,\n\nAt *Masjid4All*, we help the Muslim community find mosques, halal eateries, and reliable businesses worldwide.\nOur AI agents 🤖 have already indexed and added your business to our Muslim-friendly Business Directory.\n\n",
        
            "Salam & Hello 🌙,\n\n*Masjid4All* is your gateway to connecting Muslims with mosques, halal dining, and trusted local services globally.\nUsing advanced AI agents 🤖, we’ve included your business in our Muslim-friendly Business Directory.\n\n",
        
            "Assalamualaikum & Hi 🌙,\n\n*Masjid4All* serves as a bridge between the Muslim community and mosques, halal food spots, and reputable local businesses worldwide.\nThrough our AI agents 🤖, we’ve indexed and featured your business in our Muslim-friendly Business Directory.\n\n",
        
            "Salam & Greetings 🌙,\n\n*Masjid4All* brings together Muslims and their local needs — from mosques to halal food and trusted services — anywhere in the world.\nThanks to our AI agents 🤖, your business is now listed in our Muslim-friendly Business Directory.\n\n"
        ];
    
        // Randomly pick one
        $intro = $variations[array_rand($variations)];

        $wa  = $intro;
 
        $wa .= "*" . $name . "*\n";
        if ($address!=''){
            $wa .= $address . "\n";
        }
        $wa .= $country . "\n\n";
        $wa .= "*Claim your business today* — it’s *FREE* and takes just 1 minute to unlock your full profile!\n";
        $wa .= $page_url . "\n\n";
        $wa .= "Reach more customers, build stronger community ties, and watch your business thrive!\n\n";
        $wa .= "*Masjid4All Team*";
        
        // SEND WHATSAPP
        //$phone = '60177271844';
        whatsapp_send_message($phone, $wa, '');
        
        // mark as invited
        business_cct_update($item_id, 'wa', 'Yes');
        
        echo $post_id . '<br>' . $name . '<br>' . $phone . '<br>' . $page_url .'<br>' . $updated . '<br><br>';
        
    }
    
    // Random wait time between 100–150 seconds
    $waitTime = rand(60, 120);
    ?>
    
    <script>
    // Wait in the browser, then refresh
    setTimeout(function(){
        location.reload();
    }, <?php echo $waitTime * 1000; ?>); // Convert seconds to milliseconds
    </script>
    
    <p>Waiting <?php echo $waitTime; ?> seconds before refreshing...</p>
    
    <?php    
    return ; 
}

// UPDATE BUSINESS CONTENT
add_shortcode('invite_business_content', 'invite_business_content_shortcode');
 
function invite_business_content_shortcode() {

    global $wpdb;

    // Table name
    $table = $wpdb->prefix . "jet_cct_business";
    
    $offset = 0;
    $limit = 100;
    $country = 'United Kingdom';
    $start_no = '447';
    // Get results
    // Malaysia - 601
    // UK - 447

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE country = %s
             AND whatsapp LIKE %s
             AND (wa != %s OR wa IS NULL OR wa = '')
             LIMIT %d OFFSET %d",
            $country,
            '447%',   // This now works with LIKE
            'Yes',    // Exclude already invited
            $limit,
            $offset
        )
    );
     
    // Loop through results
    foreach ($results as $row) {
        $item_id = $row->_ID;
        $name = $row->name;
        $country = $row->country;
        $page_url =  $row->page_url;
        $address =  $row->address;
        $phone   = $row->whatsapp;
        
        $post_id   = $row->post_id;
        $updated = get_post_meta($post_id, 'updated', true);
        
        // Randomly select the message variations
        $variations = [
            "Assalamualaikum & Hello 🌙,\n\n*Masjid4All* connects the Muslim community to mosques, halal food, and trusted local businesses worldwide.\nWith the help of our AI agents 🤖, we’ve discovered and listed your business in our Muslim-friendly Business Directory.\n\n",
        
            "Assalamualaikum & Greetings 🌙,\n\nAt *Masjid4All*, we help the Muslim community find mosques, halal eateries, and reliable businesses worldwide.\nOur AI agents 🤖 have already indexed and added your business to our Muslim-friendly Business Directory.\n\n",
        
            "Salam & Hello 🌙,\n\n*Masjid4All* is your gateway to connecting Muslims with mosques, halal dining, and trusted local services globally.\nUsing advanced AI agents 🤖, we’ve included your business in our Muslim-friendly Business Directory.\n\n",
        
            "Assalamualaikum & Hi 🌙,\n\n*Masjid4All* serves as a bridge between the Muslim community and mosques, halal food spots, and reputable local businesses worldwide.\nThrough our AI agents 🤖, we’ve indexed and featured your business in our Muslim-friendly Business Directory.\n\n",
        
            "Salam & Greetings 🌙,\n\n*Masjid4All* brings together Muslims and their local needs — from mosques to halal food and trusted services — anywhere in the world.\nThanks to our AI agents 🤖, your business is now listed in our Muslim-friendly Business Directory.\n\n"
        ];
    
        // Randomly pick one
        $intro = $variations[array_rand($variations)];

        
        $wa  = $intro;
        //$wa .= "*Masjid4All* is a platform connecting the Muslim community with mosques, halal food, and trusted local businesses worldwide.\n\n";
        //$wa .= "Using our AI agents 🤖, we’ve indexed and included your business in our Muslim-friendly Business Directory.\n\n";
        
        $wa .= "*" . $name . "*\n";
        if ($address!=''){
            $wa .= $address . "\n";
        }
        $wa .= $country . "\n\n";
        $wa .= "*Claim your business today* — it’s *FREE* and takes just 1 minute to unlock your full profile!\n";
        $wa .= $page_url . "\n\n";
        $wa .= "Reach more customers, build stronger community ties, and watch your business thrive!\n\n";
        $wa .= "*Masjid4All Team*";
        
        // SEND WHATSAPP
        //$phone = '60177271844';
        //whatsapp_send_message($phone, $wa, '');
        
        // mark as invited
        //business_cct_update($item_id, 'wa', 'Yes');
        
        echo $post_id . '<br>' . $name . '<br>' . $phone . '<br>' . $page_url .'<br>' . $updated . '<br><br>';
        
    }
    
    // Random wait time between 120–180 seconds
    $waitTime = rand(100, 150);
    ?>
    
    <script>
    // Wait in the browser, then refresh
    setTimeout(function(){
        location.reload();
    }, <?php echo $waitTime * 1000; ?>); // Convert seconds to milliseconds
    </script>
    
    <p>Waiting <?php echo $waitTime; ?> seconds before refreshing...</p>
    
    <?php    
    return ; 
}

*/


