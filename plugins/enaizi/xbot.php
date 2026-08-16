<?php

/////////////////////////////
// BOT - INDEX BUSINESS    //
/////////////////////////////
add_shortcode('bot_index_business', 'bot_index_business_shortcode');

function bot_index_business_shortcode() {
    global $wpdb;

    // Location Table
    $location_table = $wpdb->prefix . "jet_cct_countries";
    
    // 1. Get one unprocessed location (Business <> 'Updated')
    $record = $wpdb->get_row(
        "SELECT * FROM $location_table WHERE (business IS NULL OR business != 'Updated') LIMIT 1"
    );
    
    if ($record) {
        $latitude  = $record->latitude;
        $longitude = $record->longitude;
        $item_id   = $record->_ID;
        
        $ret .= "PROCESSING ITEM ID : " . $item_id . "<br>";
        $ret .= "Lat : " . $latitude . ' Lon : ' . $longitude . "<br><br>";
 
        // 2. Run your custom function
        $result = bot_crawl_business($latitude,$longitude); // Replace with your actual function
     
        // 3. Update the record to set business = 'Updated'
        $updated = $wpdb->update(
            $location_table,
            ['business' => 'Updated'],
            ['_ID' => $item_id],
            ['%s'], 
            ['%d']
        );
    } else {
        $ret .= "❌ No record found<br>";
    }

    return $ret . $result;
}


// UPDATE BUSINESS LOCATION
function bot_crawl_business($latitude,$longitude) {
    global $wpdb;

    $business_table = $wpdb->prefix . "jet_cct_business";
    
    $lat = floatval($latitude);
    $lng = floatval($longitude);
    $loc = sprintf('{"q":"halal","ll":"@%f,%f,11z"}', $lat, $lng);
    $loc = sanitize_text_field($loc);

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://google.serper.dev/maps',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $loc,    
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: 8015ce8dddce3cd0fc1c880bb3eafee9b15544c7',
        'Content-Type: application/json'
      ),
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    
    $country = get_country_from_lat_long($lat, $lng);
    
    $info = [];

    foreach ($data['places'] as $item) {
        $place_id = $item['placeId'];
        $name = strtoupper($item['title']);
        $latitude = strtoupper($item['latitude']);
        $longitude = strtoupper($item['longitude']);
        $address = $item['address'];
        $opening_hours = '';
        foreach ($item['openingHours'] as $day => $hours) {
            $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
        }
        
        $place = [
            'place_id' => $item['placeId'],
            'name' => strtoupper($item['title']),
            'address' => $item['address'],
            'country' => $country,
            'latitude' => $item['latitude'],
            'longitude' => $item['longitude'],
            'rating' => $item['rating'],
            'rating_count' => $item['ratingCount'],
            'type' => $item['type'],
            'tags' => $item['types'],
            'website' => $item['website'],
            'phone' => $item['phoneNumber'],
            'opening_hours' => $opening_hours,
            'cid' => $item['cid']
        ];
    
        //$ret.= ' PlaceID ' . $place_id . '<br>' ;

        // UPDATE CCT BUSINESS
        $item_id = update_cct_business($place_id, $place);
        $content = '';
        
        // UPDATE CPT BUSINESS
        $existing = new WP_Query([
            'post_type'  => 'business',
            'meta_query' => [
                [
                    'key'   => 'place_id',
                    'value' => $place_id,
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($existing->have_posts()) {
            $existing->the_post();
            $post_id = get_the_ID();

            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $name,
                'post_content' => $content,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'business',
                'post_title'  => $name,
                'post_content' => $content,
                'post_status' => 'publish',
            ]);
        }

        // Update post meta
        update_post_meta($post_id, 'place_id', $place_id);
        update_post_meta($post_id, 'item_id', $item_id);

        // Generate page URL
        $post     = get_post($post_id);
        $slug     = $post->post_name;
        $page_url = 'https://masjid4all.com/business/' . $slug;
        $modified_date = current_time('timestamp');
        
         
        // Update the CCT record (post_id & page_url)
        $update_result = $wpdb->update(
            $business_table,                        
            [ 
                'post_id'            => $post_id,
                'cct_single_post_id' => $post_id,
                'page_url'           => $page_url,
                'cct_modified'       => $modified_date,
                'cct_status'         => 'Updated'
            ],
            ['_ID' => $item_id],                  
            ['%d', '%d', '%s', '%s', '%s'],       // Fixed data formats
            ['%d']                                
        );
        
        //$ret.= $page_url . '<br>';
        $ret.= '<b>' . $name . '</b><br>' . $address . '<br>' . $country . ' - ' . $item_id . '<br>';
        $ret.= $latitude . ',' . $longitude . '<br>';
        $ret.= $page_url . '<br><br>';
    }
 
    // REFRESH PAGE
    
    echo '<script>
        setTimeout(function() {
            window.location.reload();
        }, 2000); // 2000 milliseconds = 2 seconds
    </script>';   
     
    
    return $ret;
    
}  




/////////////////////////////
// BOT - INDEX MOSQUE      //
/////////////////////////////
add_shortcode('bot_index_mosques', 'bot_index_mosques_shortcode');

function bot_index_mosques_shortcode() {
    global $wpdb;

    // Location Table
    $location_table = $wpdb->prefix . "jet_cct_location";
    
    // 1. Get one unprocessed location (Mosque <> 'Updated')
    $record = $wpdb->get_row(
        "SELECT * FROM $location_table WHERE (mosque IS NULL OR mosque != 'Updated') LIMIT 1"
    );
    
    if ($record) {
        $latitude  = $record->latitude;
        $longitude = $record->longitude;
        $item_id   = $record->_ID;
        
        $ret .= "PROCESSING ITEM ID : " . $item_id . "<br>";
        $ret .= "Lat : " . $latitude . ' Lon : ' . $longitude . "<br><br>";
 
        // 2. Run your custom function
        $result = bot_crawl_mosque($latitude,$longitude); // Replace with your actual function
     
        // 3. Update the record to set mosque = 'Updated'
        $updated = $wpdb->update(
            $location_table,
            ['mosque' => 'Updated'],
            ['_ID' => $item_id],
            ['%s'],
            ['%d']
        );
        //$ret .= $updated ? "✅ Updated mosque to Updated\n" : "⚠️ Update failed\n";
    } else {
        $ret .= "❌ No record found<br>";
    }

    return $ret . $result;
}


// UPDATE MOSQUE LOCATION
function bot_crawl_mosque($latitude,$longitude) {
    global $wpdb;

    $mosque_table = $wpdb->prefix . "jet_cct_mosque";
    
    $lat = floatval($latitude);
    $lng = floatval($longitude);
    $loc = sprintf('{"q":"mosque","ll":"@%f,%f,11z"}', $lat, $lng);
    $loc = sanitize_text_field($loc);

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://google.serper.dev/maps',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $loc,    
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: 8015ce8dddce3cd0fc1c880bb3eafee9b15544c7',
        'Content-Type: application/json'
      ),
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    
    $country = get_country_from_lat_long($lat, $lng);
    
    $info = [];

    foreach ($data['places'] as $item) {
        $place_id = $item['placeId'];
        $name = strtoupper($item['title']);
        $latitude = strtoupper($item['latitude']);
        $longitude = strtoupper($item['longitude']);
        $address = $item['address'];
        $opening_hours = '';
        foreach ($item['openingHours'] as $day => $hours) {
            $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
        }
        
        $place = [
            'place_id' => $item['placeId'],
            'name' => strtoupper($item['title']),
            'address' => $item['address'],
            'country' => $country,
            'latitude' => $item['latitude'],
            'longitude' => $item['longitude'],
            'rating' => $item['rating'],
            'rating_count' => $item['ratingCount'],
            'type' => $item['type'],
            'tags' => $item['types'],
            'website' => $item['website'],
            'phone' => $item['phoneNumber'],
            'opening_hours' => $opening_hours,
            'cid' => $item['cid']
        ];
    
        //$ret.= ' PlaceID ' . $place_id . '<br>' ;

        // UPDATE CCT MOSQUE
        $item_id = update_cct_mosque($place_id, $place);
        $content = '';
        
        // UPDATE CPT MOSQUE
        $existing = new WP_Query([
            'post_type'  => 'mosque',
            'meta_query' => [
                [
                    'key'   => 'place_id',
                    'value' => $place_id,
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($existing->have_posts()) {
            $existing->the_post();
            $post_id = get_the_ID();

            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $name,
                'post_content' => $content,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'mosque',
                'post_title'  => $name,
                'post_content' => $content,
                'post_status' => 'publish',
            ]);
        }

        // Update post meta
        update_post_meta($post_id, 'place_id', $place_id);
        update_post_meta($post_id, 'item_id', $item_id);

        // Generate page URL
        $post     = get_post($post_id);
        $slug     = $post->post_name;
        $page_url = 'https://masjid4all.com/mosque/' . $slug;
        $modified_date = current_time('timestamp');
        
        
        // Update the CCT record (post_id & page_url)
        $update_result = $wpdb->update(
            $mosque_table,                        
            [ 
                'post_id'            => $post_id,
                'cct_single_post_id' => $post_id,
                'page_url'           => $page_url,
                'cct_modified'       => $modified_date,
                'cct_status'         => 'Updated'
            ],
            ['_ID' => $item_id],                  
            ['%d', '%d', '%s', '%s', '%s'],       // Fixed data formats
            ['%d']                                
        );
        
        $ret.= '<b>' . $name . '</b><br>' . $address . '<br>' . $country . ' - ' . $item_id . '<br>';
        $ret.= $latitude . ',' . $longitude . '<br>';
        $ret.= $page_url . '<br><br>';
    }
 
    // REFRESH PAGE

    echo '<script>
        setTimeout(function() {
            window.location.reload();
        }, 2000); // 2000 milliseconds = 2 seconds
    </script>';   

    return $ret;
    
}  
 







