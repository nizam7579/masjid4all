<?php

/*

add_shortcode('import_countries_from_csv', function () {
    global $wpdb;

    $file_path = plugin_dir_path(__FILE__) . 'csv/countries.csv';
    $location_table = $wpdb->prefix . "jet_cct_countries";

    if (!file_exists($file_path)) {
        return "CSV file not found at: $file_path";
    }

    if (($handle = fopen($file_path, "r")) !== false) {
        $row = 0;
        $success_count = 0;
        $failed_count = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            if ($row === 0) {
                $row++;
                continue; // Skip header
            }
 
            list($code, $latitude, $longitude, $country) = array_map('trim', $data);

            $inserted = $wpdb->insert(
                $location_table,
                [
                    'code'      => sanitize_text_field($code),
                    'latitude'  => floatval($latitude),
                    'longitude' => floatval($longitude),
                    'country'   => sanitize_text_field($country),
                ],
                [
                    '%s', // code
                    '%f', // latitude
                    '%f',  // longitude
                    '%s'   // country
                ]
            );

            if ($inserted) {
                $success_count++;
            } else {
                $failed_count++;
            }

            $row++;
        }

        fclose($handle);
        return "Imported: $success_count countries. Failed: $failed_count.";
    }

    return "Failed to open CSV file.";
});

////////////
add_shortcode('sync_mosque_locations', 'sync_mosque_locations_shortcode');

function sync_mosque_locations_shortcode($atts) {
    global $wpdb;
    
    // Allow shortcode attribute for limiting how many to process
    $atts = shortcode_atts([
        'limit' => -1, // default: no limit
    ], $atts);

    $limit = intval($atts['limit']);

    $mosques = get_posts([
        'post_type'      => 'business', // Your CCT slug
        'posts_per_page' => $limit,
        'post_status'    => 'publish',
    ]);

    if (empty($mosques)) {
        return 'No mosque records found.';
    }

    $created_count = 0;
    $processed = 0;

    foreach ($mosques as $mosque) {
        $lat = get_post_meta($mosque->ID, 'latitude', true);
        $lng = get_post_meta($mosque->ID, 'longitude', true);

        if ($lat === '' || $lng === '') {
            continue;
        }

        // Round to 2 decimal points
        $lat_2dp = number_format((float)$lat, 2, '.', '');
        $lng_2dp = number_format((float)$lng, 2, '.', '');

        //echo $lat . ' - ' . $lat_2dp . ' ' . $lat_2dp . '<br>';

        // Set your CCT table name
        $table_name = $wpdb->prefix . 'jet_cct_location';
        
        // Check if location exists (exact match with 2 decimal precision)
        $query = $wpdb->prepare(
            "SELECT _ID 
            FROM $table_name 
            WHERE latitude = %f 
            AND longitude = %f
            LIMIT 1",
            $lat_2dp,
            $lng_2dp
        );
        
        $existing_id = $wpdb->get_var($query);

        if ($existing_id) {
            //echo 'ID : ' . (int)$existing_id . '<br>'; // Return existing ID
        }else{
            //CREATE CCT MEMBER
            $user_data = array(
                'latitude'      => $lat_2dp,
                'longitude'     => $lng_2dp,
            );

            // Insert into the CCT table
            $result = $wpdb->insert(
                'wp_jet_cct_location', 
                $user_data
            );
            $ret.= 'New ' . $lat_2dp . '<br>';
        }
    
        $processed++;
    }

    $ret .= "Done. Processed: {$processed}, New Locations Created: {$created_count}";
    
    return $ret;
    
}

*/




