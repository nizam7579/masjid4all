<?php


/////////////////////////////////////
// Add new list based on location  //
/////////////////////////////////////

/*
 

add_action('plugins_loaded', function() {
    // Add the shortcode.
    add_shortcode('directory_new_list', 'directory_new_list_shortcode');
});
 
function directory_new_list_shortcode($atts) {

    // Get user latitude and longitude from cookies
    $latitude = floatval($_COOKIE['latitude']);
    $longitude = floatval($_COOKIE['longitude']);
    $country = esc_html($_COOKIE['country']);
    $loc = '"ll":"@' . $latitude . ',' . $longitude . ',16z"';
    
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
      CURLOPT_POSTFIELDS =>'{"q":"halal",' . $loc . '}',
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: 5a39e7769853abdb21881675e945464322f30325',
        'Content-Type: application/json'
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    
    // Decode JSON
    $data = json_decode($response, true);

    foreach ($data['places'] as $item) {
        $place_id = $item['placeId'];
        $name = strtoupper($item['title']);
        
        // Check if place_id already exists
        $existing = new WP_Query([
            'post_type' => 'business',
            'meta_query' => [
                [
                    'key'     => 'place_id',
                    'value'   => $place_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids' // return only post IDs for performance
        ]);
    
        if ($existing->have_posts()) {
            return; 
        }

        $latitude = $item['latitude'];
        $longitude = $item['longitude'];
        $address = $item['address'];
        $phone = $item['phoneNumber'];
        $website = $item['website'];
        $image = $item['thumbnailUrl'];
        $opening_hours = '';
        foreach ($item['openingHours'] as $day => $hours) {
            $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
        }
        $rating = $item['rating'];
        $rating_count = $item['ratingCount'];
        $type = $item['type'];
        $types = $item['types'];
        $tags = implode(', ', $types);
        $price_level = $item['priceLevel'];
        
        // Insert new post
        $new_post_id = wp_insert_post([
            'post_title'  => $name,
            'post_type'   => 'business',
            'post_status' => 'publish',
        ]);
    
        if (!is_wp_error($new_post_id)) {
            update_post_meta($new_post_id, 'place_id', $place_id);
            update_post_meta($new_post_id, 'latitude', $latitude);
            update_post_meta($new_post_id, 'longitude', $longitude);
            update_post_meta($new_post_id, 'address', $address);
            update_post_meta($new_post_id, 'country', $country);
            update_post_meta($new_post_id, 'phone', $phone);
            update_post_meta($new_post_id, 'website', $website);
            update_post_meta($new_post_id, 'image', $image);
            update_post_meta($new_post_id, 'opening_hours', $opening_hours);
            update_post_meta($new_post_id, 'rating', $rating);
            update_post_meta($new_post_id, 'rating_count', $rating_count);
            update_post_meta($new_post_id, 'type', $type);
            update_post_meta($new_post_id, 'tags', $tags);
            update_post_meta($new_post_id, 'price_level', $price_level);
            
            $ret.= "<b>" . $name . "</b><br>";
            $ret.= $country . "<br><br>";
            //return $new_post_id;
        }    

    }
    
    return $ret;

    /*
    // Check if places exist
    if (!empty($data['places'])) {
   
        $ret = "<h1>List of Business</h1>";
  
        foreach ($data['places'] as $item) {
            $place_id = $item['placeId'];
            $name = strtoupper($item['title']);
            $latitude = $item['latitude'];
            $longitude = $item['longitude'];
            $address = $item['address'];
            $phone = $item['phoneNumber'];
            $website = $item['website'];
            $image = $item['thumbnailUrl'];
            $opening_hours = '';
            foreach ($item['openingHours'] as $day => $hours) {
                $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
            }
            $rating = $item['rating'];
            $rating_count = $item['ratingCount'];
            $type = $item['type'];
            $types = $item['types'];
            $tags = implode(', ', $types);
            $price_level = $item['priceLevel'];
            
            $list = array(
                'place_id'          => $place_id,
                'name'              => $name,
                'introduction'      => $country,
                'address'           => $address,
                'latitude'          => $latitude,
                'longitude'         => $longitude,
                'phone'             => $phone,
                'cid'               => $country,
                'thumbnail_url'     => $image,
                'opening_hours'     => $opening_hours,
                'type'              => $type,
                'tags'              => $tags,
                'price_level'       => $price_level,
                'rating'            => $rating,
                'rating_count'      => $rating_count
            );
    
            update_directory_cct($place_id, $list);

            $ret.= "<b>" . $name . "</b><br>";
            $ret.= $country . "<br><br>";
        }
        
  
    } else {
        return "No items found.";
    }
    
} 

// UPDATE TO DIRECTORY CCT
function update_directory_cct($place_id, $data){
    global $wpdb;
    $table_name = "wp_jet_cct_directory"; 
 
     
     // Check if the place_id already exists
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE place_id = %s",
        $place_id
    );
    $count = $wpdb->get_var($query);
    
    // Check if place_id exists
    if ($count > 0) {
        // place_id exists in the database
        
        return 'Exist';
    } else {
        // place_id does not exist in the database
        $result = $wpdb->insert(
            $table_name, 
            $data
        ); 
        return 'New';
    }

}

*/



