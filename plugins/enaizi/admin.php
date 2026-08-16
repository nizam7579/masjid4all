<?php

// COUNTRY FILTER
add_action('init', function() {
    if (!empty($_GET['country'])) {
        $country = sanitize_text_field($_GET['country']);
        $country = 'Indonesia';
        setcookie('country', $country, time() + (86400 * 30), '/'); // 30-day cookie
        $_COOKIE['country'] = $country; // Set it manually for immediate access
    }
}); 
 
add_shortcode('country_cookie_filter', function() {
    return $_COOKIE['country'] ?? ''; 
});

add_filter('jet-engine/listings/dynamic-tags/custom-tags', function($tags) {
    $tags['country_cookie'] = [
        'label' => 'Country Cookie',
        'cb'    => function() {
            return sanitize_text_field($_COOKIE['country'] ?? '');
        }
    ];
    return $tags;
});

// MOSQUE ADD NEW - BASED ON LOCATION
add_shortcode('admin_mosques_add', 'admin_mosques_add_shortcode');

function admin_mosques_add_shortcode() {
    ob_start();
    ?>
    <br><h3>Search Mosques by Location</h3>
    <form method="post">
        <label>Location : <input type="text" name="location" value="" required></label><br>
        <button type="submit" name="process">Search Mosques</button>
    </form>
    <hr>
    <?php

    if (isset($_POST['process'])) {
        $location = sanitize_text_field($_POST['location']);
        $result = admin_crawl_data('mosque', $location);
        return $result;
    }

    return ob_get_clean();
}

// BUSINESS ADD NEW - BASED ON LOCATION
add_shortcode('admin_business_add', 'admin_business_add_shortcode');
 
function admin_business_add_shortcode() {
    ob_start();
    ?>
    <br><h3>Search New Business</h3>
    <form method="post">
        <label>Search :
            <input type="text" name="location" value="" required style="width: 500px;">
        </label><br>
        <button type="submit" name="process">Search Business</button>
    </form>
    <hr>
    <?php

    if (isset($_POST['process'])) {
        $search = sanitize_text_field($_POST['search']);
        $location = sanitize_text_field($_POST['location']);
        //$location = $search . ' ' . $location;
        $result = admin_crawl_data('business', $location);
        return $result;
    }

    return ob_get_clean();
}

function admin_crawl_data($category, $location){
    global $wpdb;
    if ($category=='mosque'){
        // Mosque
        $table = $wpdb->prefix . "jet_cct_mosque";
       
        if (strpos($location, ',') !== false) {
            // Search by Latitude and Longitude
            $parts = explode(',', $location);
            // Trim and cast to float
            $lat = isset($parts[0]) ? floatval(trim($parts[0])) : null;
            $lng = isset($parts[1]) ? floatval(trim($parts[1])) : null;
            $loc = sprintf('{"q":"Mosque, Masjid or Surau","ll":"@%f,%f,11z"}', $lat, $lng);
        } else {
            // Search by location
            $loc = 'Mosque or Masjid near ' . $location;
            $loc = '{"q":"' . $loc . '"}';
        }
    }else{
        // Business    
        $table = $wpdb->prefix . "jet_cct_business"; 
        if (strpos($location, ',') !== false) {
            // Search by Latitude and Longitude
            $parts = explode(',', $location);
            // Trim and cast to float
            $lat = isset($parts[0]) ? floatval(trim($parts[0])) : null;
            $lng = isset($parts[1]) ? floatval(trim($parts[1])) : null;
            $loc = sprintf('{"q":"halal","ll":"@%f,%f,11z"}', $lat, $lng);
            //$ret.= $loc;
            //return $ret;
        } else {
            // Search by location
            $loc = $location;
            $loc = '{"q":"' . $loc . '"}';
        }
    }
    
    $loc = sanitize_text_field($loc);
    $ret.= $location . ' - ';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://google.serper.dev/maps',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $loc,
        CURLOPT_HTTPHEADER => [
            'X-API-KEY: 8015ce8dddce3cd0fc1c880bb3eafee9b15544c7',
            'Content-Type: application/json'
        ],
    ]);
        
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    $itemCount = count($data['places']);
    $ret.= '<b>' . $itemCount . '</b><br><br>';
    
    $cnt = 0;
    foreach ($data['places'] as $item) {
        $place_id = $item['placeId'];
        $name = strtoupper($item['title']);
        $opening_hours = '';
        foreach ($item['openingHours'] as $day => $hours) {
            $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
        }
        $type = $item['type'];
        $types = $item['types'];
        $tags = implode(', ', $types);

        $lat = $item['latitude'];
        $lng = $item['longitude'];
        $country = get_country_from_lat_long($lat, $lng);
        $price_level = isset($item['priceLevel']) && $item['priceLevel'] !== null ? $item['priceLevel'] : '';
 
        // CHECK NON HALAL
        $non_halal = NonHalal($name);
 
        if ($non_halal){
            $ret.= '<b>' . $name . '</b> - **NON-HALAL**<br>';
        }else{   
            // Prepare cct data
            $cct_data = [
                'place_id' => $place_id,
                'name' => $name,
                'address' => $item['address'],
                'country' => $country,
                'latitude' => $item['latitude'],
                'longitude' => $item['longitude'],
                'rating' => $item['rating'],
                'rating_count' => $item['ratingCount'],
                'type' => $item['type'],
                'tags' => $tags,
//                'price_level' => $price_level,
                'website' => $item['website'],
                'phone' => $item['phoneNumber'],
                'opening_hours' => $opening_hours,
                'cid' => $item['cid'],
                'cct_modified' => current_time('mysql'),
                'cct_status' => 'Updated'
            ];
            // UPDATE CCT
            $item_id = admin_cct_update($place_id, $cct_data, $category);

            // UPDATE CPT 
            $existing = new WP_Query([
                'post_type'  => $category,
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
            } else {
                $post_id = wp_insert_post([
                    'post_type'   => $category,
                    'post_title'  => $name,
                    'post_status' => 'publish',
                ]);
            }
        
            // Update post meta
            update_post_meta($post_id, 'place_id', $place_id);
            update_post_meta($post_id, 'item_id', $item_id);
            update_post_meta($post_id, 'updated', '');
            
            // Generate page URL
            $post     = get_post($post_id);
            $slug     = $post->post_name;
            $page_url = 'https://masjid4all.com/' . $category . '/' . $slug;
            $modified_date = current_time('timestamp');
    
            // Update the CCT record (post_id & page_url)
            $update_result = $wpdb->update(
                $table,                        
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
                
            $ret.= '<b>' . $name . '</b> - ' . $country . ' - ' . $tags . '<br>';
        }     
    }
        
    return $ret;
}   


// UPDATE CCT 
function admin_cct_update($place_id, $data, $category) {
    global $wpdb;

    if (empty($place_id) || empty($data)) {
        return "Error: place_id and data are required.";
    }

    if ($category=='mosque'){
       $table_name = $wpdb->prefix . "jet_cct_mosque";
    }else{
       $table_name = $wpdb->prefix . "jet_cct_business"; 
    }

    // Check if record exists and get the ID
    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT _ID FROM {$table_name} WHERE place_id = %s",
            $place_id
        )
    );

    // Ensure place_id is included in $data
    $data['place_id'] = $place_id;

    if ($existing_id) {
        // Update record
        $where = ['place_id' => $place_id];

        $result = $wpdb->update(
            $table_name,
            $data,
            $where,
            array_fill(0, count($data), '%s'),
            array('%s')
        );

        // Return the existing ID if update successful
        return $result !== false ? $existing_id : false;

    } else {
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            $data,
            array_fill(0, count($data), '%s')
        );

        // Return the new inserted ID
        return $result !== false ? $wpdb->insert_id : false;
    }
}

// ADMIN ONLY
add_shortcode('admin_only', 'check_admin_redirect_shortcode');
 
function check_admin_redirect_shortcode() {
    // Check if Admin or Editor
    if (current_user_can('administrator') || current_user_can('editor')) {
        //$ret = '<h3>' . strtoupper(get_the_title()) . '</h3>';
        return $ret ;
    } else {
        // Redirect non-administrators to home page
        wp_redirect(home_url());
        exit;
    } 
}



function isValidWhatsAppNumber($phone) {
    $apiKey = 'f460b6cdcfmsh3de4308e8de857dp1cc79bjsnf48e98123c57';
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://valid-whatsapp.p.rapidapi.com/wchk?phone=" . urlencode($phone),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: valid-whatsapp.p.rapidapi.com",
            "x-rapidapi-key: " . $apiKey
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);

    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }

    if ($httpCode !== 200) {
        return false; // API request failed
    }

    $data = json_decode($response, true);
    
    // Adjust this based on the actual API response structure
    return $data ; //isset($data['is_valid']) && $data['is_valid'] === true;
}


// MOSQUE INDEX BASED ON LOCATION
add_shortcode('admin_mosques_locationx', 'admin_mosques_location_shortcode');

function admin_mosques_location_shortcode() {
    global $wpdb;

    // Table name
    $table = $wpdb->prefix . "jet_cct_location";
    
    // 1. Get one unprocessed record where mosque is NOT 'Yes'
    $record = $wpdb->get_row(
        "SELECT * FROM $table WHERE (mosque IS NULL OR mosque != 'Yes') LIMIT 1"
    );
    
    if ($record) {
        $latitude  = $record->latitude;
        $longitude = $record->longitude;
        $item_id   = $record->_ID;
        $ret .= "PROCESSING ITEM ID : " . $item_id . "<br>";
        $ret .= "Lat : " . $latitude . ' Lon : ' . $longitude . "<br>";
        // for testing
        //$latitude  = 3.19 ;
        //$longitude = 101.71;
        
        // 2. Run your custom function
        $result = update_location($latitude,$longitude); // Replace with your actual function
     
        // 3. Update the record to set mosque = 'Yes'
        
        $updated = $wpdb->update(
            $table,
            ['mosque' => 'Yes'],
            ['_ID' => $item_id],
            ['%s'],
            ['%d']
        );
        $ret .= $updated ? "✅ Updated mosque to 'Yes'\n" : "⚠️ Update failed\n";
        
        $ret .= "✅ Updated <br><br>";
    } else {
        $ret .= "❌ No record found<br>";
    }

    return $ret . $result;
}





// MOSQUE UPDATE
// link Mosque CCT to Mosque CPT
add_shortcode('admin_mosques_updatex', 'admin_mosques_update_shortcode');

function admin_mosques_update_shortcode() {
    global $wpdb;

    ob_start();
    ?>
    <form method="post">
        <label>Offset: <input type="number" name="offset" value="0" required></label><br>
        <label>Limit: <input type="number" name="limit" value="1000" required></label><br>
        <button type="submit" name="process">Start Mosque Update</button>
    </form>
    <hr>
    <?php

    if (isset($_POST['process'])) {
        $offset = intval($_POST['offset']);
        $limit = intval($_POST['limit']);

        echo "<p>Processing CCT `mosque` from offset <strong>" . esc_html($offset) . "</strong> with limit <strong>" . esc_html($limit) . "</strong></p>";

        $mosque_table = $wpdb->prefix . "jet_cct_mosque";

        // Fetch records with LIMIT and OFFSET
        $query = $wpdb->prepare(
            "SELECT * FROM $mosque_table LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        $results = $wpdb->get_results($query);

        if (!empty($results)) {
            foreach ($results as $mosque) {
                $name     = $mosque->name;
                $place_id = $mosque->place_id;
                $item_id  = $mosque->_ID;

                echo "<p><strong>" . esc_html($name) . "</strong> - ";

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
                    ]);
                    echo "Updated existing (ID: " . esc_html($post_id) . ")";
                } else {
                    $post_id = wp_insert_post([
                        'post_type'   => 'mosque',
                        'post_title'  => $name,
                        'post_status' => 'publish',
                    ]);
                    echo "Created new (ID: " . esc_html($post_id) . ")";
                }

                // Update post meta
                update_post_meta($post_id, 'place_id', $place_id);
                update_post_meta($post_id, 'item_id', $item_id);

                // Generate page URL
                $post     = get_post($post_id);
                $slug     = $post->post_name;
                $page_url = 'https://masjid4all.com/mosque/' . $slug;

                // Update the CCT record (post_id & page_url)
                $update_result = $wpdb->update(
                    $mosque_table,                        // Table
                    [
                        'post_id'   => $post_id,
                        'page_url'  => $page_url
                    ],
                    ['_ID' => $item_id],                  // Where
                    ['%d', '%s'],                         // Data format
                    ['%d']                                // Where format
                );

                if ($update_result !== false) {
                    echo " | CCT updated ✅";
                } else {
                    echo " | CCT update failed ❌";
                }

                echo "</p>";
                wp_reset_postdata();
            }
        } else {
            echo "<p>No records found.</p>";
        }
    }

    return ob_get_clean();
}