<?php

/**
 * Shortcode: [sync_cradle_users]
 * 
 * Loops through jet-cct-business records where:
 * - country IS NOT empty
 * - phone IS NOT empty
 * 
 * Then creates new record in:
 * - jet-cct-cradle_user
 * 
 * Fields:
 * - phone
 * - country
 * 
 * IMPORTANT:
 * - Designed for large dataset (~40K records)
 * - Uses batch processing
 * - Avoids duplicate phone numbers
 */

add_shortcode('sync_cradle_usersx', function () {

    if ( ! current_user_can('manage_options') ) {
        return 'Unauthorized';
    }

    global $wpdb;

    $business_table = $wpdb->prefix . 'jet_cct_business';
    $cradle_table  = $wpdb->prefix . 'jet_cct_cradle_user';

    $batch_size = 500;
    $offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Get batch
    $records = $wpdb->get_results(
        $wpdb->prepare("
            SELECT phone, country
            FROM {$business_table}
            WHERE country IS NOT NULL
            AND country != ''
            AND phone IS NOT NULL
            AND phone != ''
            LIMIT %d OFFSET %d
        ", $batch_size, $offset)
    );

    if ( empty($records) ) {
        return "Done syncing.";
    }

    $inserted = 0;
    $skipped  = 0;

    foreach ($records as $record) {

        $phone   = trim($record->phone);
        $country = trim($record->country);

        // Optional normalization
        $phone = preg_replace('/\s+/', '', $phone);

        if ( empty($phone) || empty($country) ) {
            continue;
        }

        // Check duplicate by phone
        $exists = $wpdb->get_var(
            $wpdb->prepare("
                SELECT id
                FROM {$cradle_table}
                WHERE phone = %s
                LIMIT 1
            ", $phone)
        );

        if ($exists) {
            $skipped++;
            continue;
        }

        // Insert new record
        $result = $wpdb->insert(
            $cradle_table,
            [
                'phone'   => $phone,
                'country' => $country,
            ],
            [
                '%s',
                '%s',
            ]
        );

        if ($result) {
            $inserted++;
        }
    }

    $next_offset = $offset + $batch_size;

    $next_url = add_query_arg([
        'offset' => $next_offset
    ]);

    $output  = "<div style='padding:20px;border:1px solid #ddd'>";
    $output .= "<h3>Batch Processed</h3>";
    $output .= "<p>Offset: {$offset}</p>";
    $output .= "<p>Inserted: {$inserted}</p>";
    $output .= "<p>Skipped: {$skipped}</p>";
    $output .= "<p><a href='{$next_url}'>Process Next Batch</a></p>";
    $output .= "</div>";

    return $output;
});

function no_mosque_country_shortcode() {
    global $wpdb;

    $table  = 'wp_jet_cct_mosque';
    $limit  = 100;
    $offset = 0;
    
    $api_key = GOOGLE_MAPS_API_KEY; // resolved in keys.php
    $ret = '';
    
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT _ID, name, type, cct_single_post_id, latitude, longitude, address 
             FROM $table 
             WHERE (type IS NULL OR type != 'Mosque') 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );

    if (empty($rows)) {
        return "✅ DONE. All records updated.";
    }

    $updated = 0;
    $errors = 0;
    $updated = 0;
    $errors = 0;
    
    
    foreach ($rows as $row) {
        
        $lat = trim($row->latitude);
        $lng = trim($row->longitude);
        $ret .= '<br>' .  $row->type . ' - ' . $row->name . ' - ' . trim($row->cct_single_post_id);

        /*
        
         
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}";
        $response = wp_remote_get($url);
        //$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";
        //$response = wp_remote_get($url);

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $ret.= $address;
            if (!empty($data['address']['country'])) {
                $country = $data['address']['country'];
                $ret.= ' - ' . $country;
            }
        }
        */
        
        //usleep(200000);
    }

    return 'COMPLETED<br>' . $ret; 
}

add_shortcode('no_mosque_country', 'no_mosque_country_shortcode');


function update_mosque_country_shortcode() {
    global $wpdb;

    $table = 'wp_jet_cct_business';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $api_key = GOOGLE_MAPS_API_KEY; // resolved in keys.php

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT _ID, latitude, longitude 
             FROM $table 
             WHERE (country IS NULL OR country = '') 
             AND latitude IS NOT NULL 
             AND longitude IS NOT NULL
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );

    if (empty($rows)) {
        return "✅ DONE. All records updated.";
    }

    $updated = 0;
    $errors = 0;

    foreach ($rows as $row) {

        $lat = trim($row->latitude);
        $lng = trim($row->longitude);

        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            $errors++;
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || $body['status'] !== 'OK') {
            $errors++;
            continue;
        }

        $country = '';

        foreach ($body['results'] as $result) {
            foreach ($result['address_components'] as $comp) {
                if (in_array('country', $comp['types'])) {
                    $country = $comp['long_name'];
                    break 2;
                }
            }
        }

        if (!empty($country)) {
            $wpdb->update(
                $table,
                ['country' => $country],
                ['_ID' => $row->_ID]
            );
            $updated++;
        }else{
            print_r($body);
            return $body;
        }

        usleep(200000);
    }

    $next_offset = $offset + $limit;

    // Auto refresh to next batch
    $next_url = add_query_arg([
        'offset' => $next_offset,
        'limit'  => $limit
    ]);

    return "
        ✅ Processed: $limit <br>
        🟢 Updated: $updated <br>
        ❌ Errors: $errors <br>
        ➡️ Next offset: $next_offset <br><br>

        //⏳ Processing next batch automatically...
        //<script>
        //    setTimeout(function(){
        //        window.location.href = '{$next_url}';
        //    }, 1500);
        //</script>
    ";
}

add_shortcode('update_mosque_country', 'update_mosque_country_shortcode');

/////

add_shortcode('update_business_country', 'update_business_country_shortcode');
function update_business_country_shortcode() {
    global $wpdb;

    $table = 'wp_jet_cct_business';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $api_key = GOOGLE_MAPS_API_KEY; // resolved in keys.php

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT _ID, latitude, longitude 
             FROM $table 
             WHERE (country IS NULL OR country = '') 
             AND latitude IS NOT NULL 
             AND longitude IS NOT NULL
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );

    if (empty($rows)) {
        return "✅ DONE. All records updated.";
    }

    $updated = 0;
    $errors = 0;

    foreach ($rows as $row) {

        $lat = trim($row->latitude);
        $lng = trim($row->longitude);

        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            $errors++;
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || $body['status'] !== 'OK') {
            $errors++;
            continue;
        }

        $country = '';

        foreach ($body['results'] as $result) {
            foreach ($result['address_components'] as $comp) {
                if (in_array('country', $comp['types'])) {
                    $country = $comp['long_name'];
                    break 2;
                }
            }
        }

        if (!empty($country)) {
            $wpdb->update(
                $table,
                ['country' => $country],
                ['_ID' => $row->_ID]
            );
            $updated++;
        }else{
            print_r($body);
            return $body;
        }

        usleep(200000);
    }
 
    $next_offset = $offset + $limit;

    // Auto refresh to next batch
    $next_url = add_query_arg([
        'offset' => $next_offset,
        'limit'  => $limit
    ]);

    return "
        ✅ Processed: $limit <br>
        🟢 Updated: $updated <br>
        ❌ Errors: $errors <br>
        ➡️ Next offset: $next_offset <br><br>

        //⏳ Processing next batch automatically...
        //<script>
        //    setTimeout(function(){
        //        window.location.href = '{$next_url}';
        //    }, 1500);
        //</script>
    ";
}



///////////


function niz_remove_duplicate_countries_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_countries'; // adjust if needed
    $column = 'country'; // change to your actual field name

    // Get duplicates
    $duplicates = $wpdb->get_results("
        SELECT $column, COUNT(*) as total
        FROM $table
        GROUP BY $column
        HAVING total > 1
    ");

    if (empty($duplicates)) {
        return 'No duplicate countries found.';
    }

    $deleted = 0;

    foreach ($duplicates as $dup) {
        // Get all IDs for this country
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT _ID FROM $table
            WHERE $column = %s
            ORDER BY _ID ASC
        ", $dup->$column));

        // Keep first, delete the rest
        array_shift($rows);

        foreach ($rows as $row) {
            $wpdb->delete($table, ['_ID' => $row->_ID]);
            $deleted++;
        }
    }

    return "Removed $deleted duplicate country records.";
}

add_shortcode('remove_duplicate_countries', 'niz_remove_duplicate_countries_shortcode');


 
add_shortcode('sync_mosque_locations', function () {
    global $wpdb;

    $mosque_table = $wpdb->prefix . 'countries';
    $location_table = $wpdb->prefix . 'jet_cct_location';

    // Get all mosque lat/lng
    $rows = $wpdb->get_results("
        SELECT latitude, longitude 
        FROM {$mosque_table}
        WHERE latitude IS NOT NULL 
        AND longitude IS NOT NULL
        LIMIT 10000
        OFFSET 50000
    ");

    if (!$rows) {
        return 'No mosque data found.';
    }

    $inserted = 0;
    $skipped = 0;
    $ret = '';
    foreach ($rows as $row) {

        // Format to 2 decimal places
        //$lat = number_format((float)$row->latitude, 2, '.', '');
        //$lng = number_format((float)$row->longitude, 2, '.', '');
        
         
        $lat = number_format(truncate_2dp((float)$row->latitude), 2, '.', '');
        $lng = number_format(truncate_2dp((float)$row->longitude), 2, '.', '');

        $loc = $lat . '|' . $lng;

        // Check if exists
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$location_table} 
            WHERE location = %s
        ", $loc));

        if (!$exists) {
            //$ret.= $loc . ' - ' . $row->latitude . ' - ' . $row->longitude . '<br>';
            $wpdb->insert(
                $location_table,
                [
                    'location' => $loc
                ],
                ['%s']
            );

            $inserted++;
        } else {
            $skipped++;
        }
    }

    return "<br>Done. Inserted: {$inserted}, Skipped: {$skipped}";
});

function truncate_2dp($value) {
    return $value >= 0
        ? floor($value * 100) / 100
        : ceil($value * 100) / 100;
}
        
add_shortcode('clean_mosque_url', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_mosque';

    $rows = $wpdb->get_results("
        SELECT *
        FROM {$table}  
    ");
 
    if (empty($rows)) {
        return "No records found.";
    }
 
    $updated = 0;
    $skipped = 0;
    $ret = '';
    foreach ($rows as $row) {

        $post_id = (int) $row->cct_single_post_id;

        if (empty($post_id)) {
            $skipped++;
            continue;
        }

        $slug = get_post_field('post_name', $post_id);
        
        if (strpos($slug, '?') !== false) {
            // slug contains ?
            $ret .= $slug . '<br>';
        }

        $updated++;
    }

    return "Done. Updated: {$updated}, Skipped: {$skipped}<br>" . $ret;
});

add_shortcode('sync_mosque_postsx', function () {
    global $wpdb; 
    $table = $wpdb->prefix . 'jet_cct_mosque';

    $rows = $wpdb->get_results("
        SELECT *
        FROM {$table}  
        LIMIT 10000 OFFSET 30000
    ");
 
    if (empty($rows)) {
        return "No records found.";
    }
 
    $updated = 0;
    $skipped = 0;

    foreach ($rows as $row) {

        $post_id = (int) $row->cct_single_post_id;

        if (empty($post_id)) {
            $skipped++;
            continue;
        }

        $post = get_post($post_id);

        if (!$post) {
            $skipped++;
            continue;
        }

        // Data
        $name    = $row->name ?? '';
        $address = $row->address ?? '';
        $country = $row->country ?? '';

        $new_title   = trim($name);
        $new_content = trim($name) . '<br>' . $address . '<br>' . $country . '<br><br>More details will be updated soon';
        
        // Update post title
        if (!empty($new_title)) {
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $new_title,
            ]);
        }

        // Update content only if empty
        $current_post = get_post($post_id);
        
        if ($current_post && empty(trim($current_post->post_content))) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $new_content,
            ]);
        }
        
        // Focus keyword
        update_post_meta($post_id, 'rank_math_focus_keyword', $new_title);

        $updated++;
    }

    return "Done. Updated: {$updated}, Skipped: {$skipped}";
});


add_shortcode('sync_cct_business_to_post', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_business';
  
    $rows = $wpdb->get_results("
        SELECT *
        FROM {$table}
        WHERE country <> ''
          AND place_id <> ''
          AND type LIKE '%Food court%'
          AND (business_status = '' OR business_status IS NULL)
    ");
 
    if (empty($rows)) {
        return 'No business records found.';
    }

    $created  = 0;
    $existing = 0;
    $skipped  = 0;

    foreach ($rows as $row) {

        $post_id = 0;

        // If already linked
        if (!empty($row->cct_single_post_id)) {
            $existing_post = get_post((int) $row->cct_single_post_id);

            if ($existing_post && $existing_post->post_type === 'business') {
                $existing++;
                $post_id = $existing_post->ID;
            }
        }

        // Create new business post if not found
        if (!$post_id) {
            $post_id = wp_insert_post([
                'post_type'   => 'business',
                'post_title'  => wp_strip_all_tags($row->name ?? 'Business'),
                'post_status' => 'publish',
            ]);

            if (is_wp_error($post_id) || !$post_id) {
                $skipped++;
                continue;
            }

            $created++;
        }

        // Save CCT ID to post meta
        update_post_meta($post_id, 'item_id', $row->_ID);

        $permalink = get_permalink($post_id);

        // Update CCT record
        $wpdb->update(
            $table,
            [
                'page_url'           => $permalink,
                'cct_single_post_id' => $post_id,
                'post_id'            => $post_id,
                'business_status'    => 'Listed',
            ],
            ['_ID' => $row->_ID],
            ['%s', '%d', '%d', '%s'],
            ['%d']
        );
    }

    return "Sync completed (TEST MODE). Created: {$created}, Existing: {$existing}, Skipped: {$skipped}";
});
 

add_shortcode('sync_cct_mosque_to_post', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_mosque';

    // Fetch ONLY 10 records for testing
    $rows = $wpdb->get_results("
        SELECT *
        FROM {$table}
        WHERE country <> ''
          AND place_id <> ''
          AND (type = '' OR type IS NULL)
   //       AND (business_status = '' OR business_status IS NULL)
    ");

    if (empty($rows)) {
        return 'No mosque records found.';
    }

    $created = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        
        // Skip if already linked to existing masjid post
        if (!empty($row->cct_single_post_id)) {
            $existing_post = get_post((int) $row->cct_single_post_id);
            if ($existing_post ) {
                $skipped++;
                continue;
            }
        }

         
        // Create new masjid post
        $post_id = wp_insert_post([
            'post_type'   => 'masjid',
            'post_title'  => wp_strip_all_tags($row->name ?? 'Masjid'),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        // Save CCT ID to post meta
        update_post_meta($post_id, 'item_id', $row->_ID);

        $permalink = get_permalink($post_id);

        // Update CCT record
        $wpdb->update(
            $table,
            [
                'page_url'           => $permalink,
                'cct_single_post_id' => $post_id,
                'post_id'            => $post_id,
                'type'               => 'Mosque',
                'business_status'    => 'Listed',
            ],
            ['_ID' => $row->_ID],
            ['%s', '%d', '%d','%s','%s'],
            ['%d']
        );
        
        $created++;
    }

    return "Sync completed (TEST MODE). Created: {$created}, Skipped: {$skipped}";
});


add_shortcode('test_perplexity', function() {
    // Call UNIQUE API function
    $place_id  = 'ChIJscz0Os_mcUgRdycSsOk8BkA';
    $name      = 'USA CHICKEN';
    $address   = '28 Skinner St, Newport NP20 1HB, United Kingdom';
    $website   = 'https://usachickennewport.com/';
    $facebook  = '';
    $instagram = '';
    $gmap      = 'https://www.google.com/maps/place/?q=:' . $place_id;

    $info = array(
        'name'           => $name,
        'address'        => $address,
        'website'        => $website,
        'facebook'       => $facebook,
        'instagram'      => $instagram,
        'google_maps_url'=> $gmap
    );
    $json_raw = perplexity_business($info);

    $data = json_decode($json_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('AI returned invalid JSON.');
    }
     
    // Call UNIQUE HTML builder
    $html_content = biz_build_html_layout($data);
    
    return $html_content;
     
});



add_shortcode('reset_business_status', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_business';

    $wpdb->query("UPDATE $table SET business_status = NULL");
    //$updated = $wpdb->query( 
    //    "UPDATE $table SET business_status = ''" 
    //);

    if ($updated !== false) {
        return "Business status reset successfully for $updated rows.";
    } else {
        return "Failed to reset business status.";
    }
});

add_shortcode('mosque_empty_cid_or_place_id', function() {
    global $wpdb;

    $posts_table = $wpdb->prefix . 'posts';
    $meta_table  = $wpdb->prefix . 'postmeta';

    // Get mosque posts where cid or place_id is empty or not set
    $results = $wpdb->get_results("
        SELECT p.ID, p.post_title,
               pm_cid.meta_value AS cid,
               pm_place.meta_value AS place_id
        FROM $posts_table p
        LEFT JOIN $meta_table pm_cid ON p.ID = pm_cid.post_id AND pm_cid.meta_key = 'cid'
        LEFT JOIN $meta_table pm_place ON p.ID = pm_place.post_id AND pm_place.meta_key = 'place_id'
        WHERE p.post_type = 'mosque'
          AND (pm_cid.meta_value IS NULL OR pm_cid.meta_value = '' 
               OR pm_place.meta_value IS NULL OR pm_place.meta_value = '')
        ORDER BY p.ID ASC
    ");

    if (!$results) {
        return '<p>All mosque posts have both CID and Place ID.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>Post ID</th><th>Post Title</th><th>CID</th><th>Place ID</th></tr>";

    foreach ($results as $item) {
        $cid = $item->cid ?? '';
        $place_id = $item->place_id ?? '';
        $output .= "<tr>
            <td>{$item->ID}</td>
            <td>{$item->post_title}</td>
            <td>{$cid}</td>
            <td>{$place_id}</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});

add_shortcode('mosque_empty_place_id', function() {
    global $wpdb;

    $posts_table = $wpdb->prefix . 'posts';
    $meta_table  = $wpdb->prefix . 'postmeta';

    // Get mosque posts where place_id is empty or not set
    $results = $wpdb->get_results("
        SELECT p.ID, p.post_title
        FROM $posts_table p
        LEFT JOIN $meta_table pm ON p.ID = pm.post_id AND pm.meta_key = 'place_id'
        WHERE p.post_type = 'mosque'
          AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ORDER BY p.ID ASC
    ");

    if (!$results) {
        return '<p>All mosque posts have place_id.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>Post ID</th><th>Post Title</th></tr>";

    foreach ($results as $item) {
        $output .= "<tr>
            <td>{$item->ID}</td>
            <td>{$item->post_title}</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});

add_shortcode('duplicate_cid_mosque', function() {
    global $wpdb;

    $posts_table = $wpdb->prefix . 'posts';
    $meta_table  = $wpdb->prefix . 'postmeta';

    // Find duplicate CIDs for post_type = 'mosque'
    $duplicates = $wpdb->get_results("
        SELECT pm.meta_value AS cid, COUNT(*) as count
        FROM $meta_table pm
        INNER JOIN $posts_table p ON pm.post_id = p.ID
        WHERE pm.meta_key = 'cid' 
          AND pm.meta_value != ''
          AND p.post_type = 'mosque'
        GROUP BY pm.meta_value
        HAVING COUNT(*) > 1
    ");

    if (!$duplicates) {
        return '<p>No duplicate CIDs found for mosques.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>CID</th><th>Post IDs</th><th>Post Titles</th><th>Count</th></tr>";

    foreach ($duplicates as $dup) {
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title
            FROM $posts_table p
            INNER JOIN $meta_table pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'cid' 
              AND pm.meta_value = %s
              AND p.post_type = 'mosque'
            ORDER BY p.ID ASC
        ", $dup->cid));

        if (!$items) continue;

        $post_ids = array_map(fn($i) => $i->ID, $items);
        $titles   = array_map(fn($i) => $i->post_title, $items);

        $output .= "<tr>
            <td>{$dup->cid}</td>
            <td>" . implode(', ', $post_ids) . "</td>
            <td>" . implode(', ', $titles) . "</td>
            <td>{$dup->count}</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});

add_shortcode('duplicate_place_id_mosque', function() {
    global $wpdb;

    $posts_table = $wpdb->prefix . 'posts';
    $meta_table  = $wpdb->prefix . 'postmeta';

    // Find duplicate place_ids for post_type = 'mosque'
    $duplicates = $wpdb->get_results("
        SELECT pm.meta_value AS cid, COUNT(*) as count
        FROM $meta_table pm
        INNER JOIN $posts_table p ON pm.post_id = p.ID
        WHERE pm.meta_key = 'place_id' 
          AND pm.meta_value != ''
          AND p.post_type = 'mosque'
        GROUP BY pm.meta_value
        HAVING COUNT(*) > 1
    ");

    if (!$duplicates) {
        return '<p>No duplicate place_id found for mosques.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>Place ID</th><th>Post IDs</th><th>Post Titles</th><th>Count</th></tr>";

    foreach ($duplicates as $dup) {
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title
            FROM $posts_table p
            INNER JOIN $meta_table pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'place_id' 
              AND pm.meta_value = %s
              AND p.post_type = 'mosque'
            ORDER BY p.ID ASC
        ", $dup->place_id));

        if (!$items) continue;

        $post_ids = array_map(fn($i) => $i->ID, $items);
        $titles   = array_map(fn($i) => $i->post_title, $items);

        $output .= "<tr>
            <td>{$dup->place_id}</td>
            <td>" . implode(', ', $post_ids) . "</td>
            <td>" . implode(', ', $titles) . "</td>
            <td>{$dup->count}</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});

add_shortcode('delete_duplicate_cid', function() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_mosque';

    if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
        return '<p>Table not found: '.$table.'</p>';
    }

    // Find duplicate CIDs
    $duplicates = $wpdb->get_results("
        SELECT place_id, COUNT(*) as count
        FROM $table
        WHERE place_id IS NOT NULL AND place_id != ''
        GROUP BY place_id
        HAVING COUNT(*) > 1
    ");

    if (!$duplicates) {
        return '<p>No duplicate CIDs found.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>CID</th><th>Kept _ID</th><th>Deleted _IDs</th><th>Deleted Count</th></tr>";

    foreach ($duplicates as $dup) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT _ID FROM $table WHERE place_id = %s ORDER BY _ID ASC",
            $dup->place_id
        ));

        if (!$items || count($items) < 2) continue;

        $kept = array_shift($items); // keep first record
        $delete_ids = array_map(fn($i) => (int)$i->_ID, $items); // cast to integer

        if (!empty($delete_ids)) {
            $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
            $query = "DELETE FROM $table WHERE _ID IN ($placeholders)";
            $wpdb->query($wpdb->prepare($query, ...$delete_ids));
        }

        $output .= "<tr>
            <td>{$dup->place_id}</td>
            <td>{$kept->_ID}</td>
            <td>" . implode(', ', $delete_ids) . "</td>
            <td>" . count($delete_ids) . "</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});


add_shortcode('duplicate_cid_list', function() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_mosque';

    // Check if table exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table){
        return '<p>Table not found: '.$table.'</p>';
    }

    // Find duplicate CIDs
    $duplicates = $wpdb->get_results("
        SELECT place_id, COUNT(*) as count
        FROM $table
        WHERE place_id IS NOT NULL AND place_id != ''
        GROUP BY place_id
        HAVING COUNT(*) > 1
    ");

    if (!$duplicates) {
        return '<p>No duplicate CIDs found.</p>';
    }

    $output = "<table border='1' cellpadding='5' cellspacing='0'>";
    $output .= "<tr><th>CID</th><th>Item IDs</th><th>Names</th><th>Count</th></tr>";

    foreach ($duplicates as $dup) {
        // Get the item IDs and names for this CID
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE place_id = %s",
            $dup->place_id
        ));

        if (!$items) continue;

        $item_ids = [];
        $names = [];

        foreach ($items as $i) {
            $item_ids[] = $i->_ID ?? '';
            // Try multiple possibilities for name column
            if(isset($i->name)) $names[] = $i->name;
            elseif(isset($i->mosque_name)) $names[] = $i->mosque_name;
            elseif(isset($i->post_title)) $names[] = $i->post_title;
            else $names[] = 'Unknown';
        }

        $output .= "<tr>
            <td>{$dup->place_id}</td>
            <td>" . implode(', ', $item_ids) . "</td>
            <td>" . implode(', ', $names) . "</td>
            <td>{$dup->count}</td>
        </tr>";
    }

    $output .= "</table>";

    return $output;
});



add_shortcode('serper_test', 'shortcode_serper_test');

function shortcode_serper_test() {
    $latitude  = '3.2328739';
    $longitude = '101.8547417';
 
    $ret = serper_nearest_mosques($latitude, $longitude);
    
    return $ret ;
}

add_shortcode('send_wa_testx', function($atts) {
    $atts = shortcode_atts(array(
        'to' => '60198417242',
        'msg' => 'Hello from WordPress!'
    ), $atts);

    if (!$atts['to']) return 'Recipient number is required.';

    $response = send_whatsapp_message($atts['to'], $atts['msg']);
    return '<pre>' . $response . '</pre>';
});

// HARVEST WA NUMBER
add_shortcode('test_check_whatsapp', 'test_check_whatsapp_shortcode');
 
function test_check_whatsapp_shortcode() {

    global $wpdb;

    // Table name
    $table = $wpdb->prefix . "jet_cct_business";
    
    $offset = 0;
    $limit = 100;
    
    // Get results
    // Malaysia - 601
    // UK - 447
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE country = %s 
             AND whatsapp IS NOT NULL 
             LIMIT %d OFFSET %d",
            'Malaysia', $limit, $offset
        )
    );
    
    // Loop through results
    foreach ($results as $row) {
        $item_id = $row->_ID;
        $phone   = $row->phone;
        $phone   = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($phone, "601")) {
            $whatsapp = $phone;
            $wa = 'yes';
        }else{
            $whatsapp = '';
            $wa = '';
        }    
        
        $updated = $wpdb->update(
            $table,
            [
                'whatsapp' => $whatsapp
            ],
            ['_ID' => $item_id],
            ['%s'],
            ['%d']
        );
        
        //if ($updated === false) {
        //    echo $wpdb->last_error; // show DB error if any
        //} else {
        //    echo "Updated rows: $updated";
        //}
        
        echo "Business: " . esc_html($row->name) . "<br>";
        echo "Phone: " . esc_html($row->phone) . "<br>";
        echo "WhatsApp: " . esc_html($whatsapp) . "<br><br>";
    }
    
    return ;
}

// UPDATE COUNTRY
add_shortcode('test_update_country', 'test_update_country_shortcode');

function test_update_country_shortcode() {

    global $wpdb;

    // Table name
    $table = $wpdb->prefix . "jet_cct_business";
    
    $offset = 0;
    $limit = 100;
    
    // Get results
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE country = %s 
             LIMIT %d OFFSET %d",
            '', $limit, $offset
        )
    ); 
    
    // Loop through results
    foreach ($results as $row) {
        $item_id = $row->_ID;
 
        $lat   = $row->latitude;
        $lng   = $row->longitude;
        $country = get_country_from_lat_long($lat, $lng);
     
        $updated = $wpdb->update(
            $table,
            ['country' => $country],
            ['_ID' => $item_id],
            ['%s'],
            ['%d']
        );
        // Access individual fields, for example:
        echo esc_html($row->name) . "<br>";
        echo esc_html($country) . "<br><br>";
    }
    
    return $ret ;
}

add_shortcode('reset_invalid_updated', function () {
    $args = [
        'post_type'      => 'mosque',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        's'              => 'error',
    ];

    $query = new WP_Query($args);
    $updated_count = 0;

    if ($query->have_posts()) {
        $ret = '';
        foreach ($query->posts as $post) {
            if (strpos(strtolower($post->post_content), 'error') !== false) {
                // Update post content to empty
                wp_update_post([
                    'ID'           => $post->ID,
                    'post_content' => '',
                ]);

                // Update post meta 'updated' to empty string
                update_post_meta($post->ID, 'updated', '');
                $ret.= $post->ID . ' updated';
                $updated_count++;
            }
        }
    }

    wp_reset_postdata();

    $ret.= "Updated {$updated_count} posts.<br>" . $ret;

    return $ret;
});



