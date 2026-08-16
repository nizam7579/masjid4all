<?php

/////////////////
add_shortcode('biz_index_location1', 'biz_index_location1_shortcode');
function biz_index_location1_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    $table_name = $wpdb->prefix . 'jet_cct_location';

    // Get ONE unprocessed row (no OFFSET, faster & safer)
    $row = $wpdb->get_row("
        SELECT _ID, location 
        FROM $table_name 
        WHERE _ID > 0 
        AND _ID <= 10000
        AND COALESCE(business_done, '') <> 'Done'
        ORDER BY _ID ASC 
        LIMIT 1
    ");

    if (!$row) {
        return "<h3>✅ Finished! All records processed.</h3>";
    }

    $loc = trim($row->location);

    // Validate location format
    if (strpos($loc, '|') === false) {
        return "❌ Invalid location format: " . esc_html($loc);
    }

    list($latitude, $longitude) = explode('|', $loc);

    $latitude = trim($latitude);
    $longitude = trim($longitude);

    // Basic validation
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return "❌ Invalid lat/lng: " . esc_html($loc);
    }

    // Run your function
    if (!function_exists('serper_nearest_business')) {
        return "<strong>Error:</strong> Function 'serper_nearest_business' missing.";
    }

    $result = serper_nearest_business($latitude, $longitude);
    $processed = $result['processed'] ;
    $inserted  = $result['inserted'] ;
    $updated   = $result['updated'] ;
    $list      = $result['list'];
    $num = $inserted + $updated;
    // Update using PRIMARY KEY (important)
    $wpdb->update(
        $table_name,
        [
            'business_done' => 'Done',
            'business' => intval($num)
        ],
        ['_ID' => $row->_ID],
        ['%s', '%d'],
        ['%d']
    );

    //$result = implode(", ", $result); 
    ob_start();
    ?>
    <div style="padding:20px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;">
        <strong>ID:</strong> <?php echo esc_html($row->_ID); ?><br>
        <strong>Location:</strong> <?php echo esc_html($loc); ?><br><br>
        Processed: <?php echo ($processed); ?> <br>
        Inserted :</strong> <?php echo ($inserted); ?> <br>
        Updated  :</strong> <?php echo ($updated); ?> <br>
        <?php echo ($list); ?> <br>
        <p>⏳ Auto-processing next...</p>
    </div>

    <script>
        setTimeout(function() {
            window.location.reload();
        }, 800);
    </script>
    <?php

    return ob_get_clean();
}

/////////////////
add_shortcode('biz_index_location2', 'biz_index_location2_shortcode');
function biz_index_location2_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    $table_name = $wpdb->prefix . 'jet_cct_location';

    // Get ONE unprocessed row (no OFFSET, faster & safer)
    $row = $wpdb->get_row("
        SELECT _ID, location 
        FROM $table_name 
        WHERE _ID > 10000 
        AND _ID <= 20000
        AND COALESCE(business_done, '') <> 'Done'
        ORDER BY _ID ASC 
        LIMIT 1
    ");

    if (!$row) {
        return "<h3>✅ Finished! All records processed.</h3>";
    }

    $loc = trim($row->location);

    // Validate location format
    if (strpos($loc, '|') === false) {
        return "❌ Invalid location format: " . esc_html($loc);
    }

    list($latitude, $longitude) = explode('|', $loc);

    $latitude = trim($latitude);
    $longitude = trim($longitude);

    // Basic validation
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return "❌ Invalid lat/lng: " . esc_html($loc);
    }

    // Run your function
    if (!function_exists('serper_nearest_business')) {
        return "<strong>Error:</strong> Function 'serper_nearest_business' missing.";
    }

    $result = serper_nearest_business($latitude, $longitude);
    $processed = $result['processed'] ;
    $inserted  = $result['inserted'] ;
    $updated   = $result['updated'] ;
    $list      = $result['list'];
    $num = $inserted + $updated;
    // Update using PRIMARY KEY (important)
    $wpdb->update(
        $table_name,
        [
            'business_done' => 'Done',
            'business' => intval($num)
        ],
        ['_ID' => $row->_ID],
        ['%s', '%d'],
        ['%d']
    );

    //$result = implode(", ", $result); 
    ob_start();
    ?>
    <div style="padding:20px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;">
        <strong>ID:</strong> <?php echo esc_html($row->_ID); ?><br>
        <strong>Location:</strong> <?php echo esc_html($loc); ?><br><br>
        Processed: <?php echo ($processed); ?> <br>
        Inserted :</strong> <?php echo ($inserted); ?> <br>
        Updated  :</strong> <?php echo ($updated); ?> <br>
        <?php echo ($list); ?> <br>
        <p>⏳ Auto-processing next...</p>
    </div>
 
    <script>
        setTimeout(function() {
            window.location.reload();
        }, 800);
    </script>
    <?php
 
    return ob_get_clean();
}

/////////////////
add_shortcode('biz_index_location3', 'biz_index_location3_shortcode');
function biz_index_location3_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    $table_name = $wpdb->prefix . 'jet_cct_location';

    // Get ONE unprocessed row (no OFFSET, faster & safer)
    $row = $wpdb->get_row("
        SELECT _ID, location 
        FROM $table_name 
        WHERE _ID > 20000 
        AND _ID <= 30000
        AND COALESCE(business_done, '') <> 'Done'
        ORDER BY _ID ASC 
        LIMIT 1
    ");

    if (!$row) {
        return "<h3>✅ Finished! All records processed.</h3>";
    }

    $loc = trim($row->location);

    // Validate location format
    if (strpos($loc, '|') === false) {
        return "❌ Invalid location format: " . esc_html($loc);
    }

    list($latitude, $longitude) = explode('|', $loc);

    $latitude = trim($latitude);
    $longitude = trim($longitude);

    // Basic validation
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return "❌ Invalid lat/lng: " . esc_html($loc);
    }

    // Run your function
    if (!function_exists('serper_nearest_business')) {
        return "<strong>Error:</strong> Function 'serper_nearest_business' missing.";
    }

    $result = serper_nearest_business($latitude, $longitude);
    $processed = $result['processed'] ;
    $inserted  = $result['inserted'] ;
    $updated   = $result['updated'] ;
    $list      = $result['list'];
    $num = $inserted + $updated;
    // Update using PRIMARY KEY (important)
    $wpdb->update(
        $table_name,
        [
            'business_done' => 'Done',
            'business' => intval($num)
        ],
        ['_ID' => $row->_ID],
        ['%s', '%d'],
        ['%d']
    );

    //$result = implode(", ", $result); 
    ob_start();
    ?>
    <div style="padding:20px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;">
        <strong>ID:</strong> <?php echo esc_html($row->_ID); ?><br>
        <strong>Location:</strong> <?php echo esc_html($loc); ?><br><br>
        Processed: <?php echo ($processed); ?> <br>
        Inserted :</strong> <?php echo ($inserted); ?> <br>
        Updated  :</strong> <?php echo ($updated); ?> <br>
        <?php echo ($list); ?> <br>
        <p>⏳ Auto-processing next...</p>
    </div>

    <script>
        setTimeout(function() {
            window.location.reload();
        }, 800);
    </script>
    <?php

    return ob_get_clean();
}

/////////////////
add_shortcode('biz_index_location4', 'biz_index_location4_shortcode');
function biz_index_location4_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    $table_name = $wpdb->prefix . 'jet_cct_location';

    // Get ONE unprocessed row (no OFFSET, faster & safer)
    $row = $wpdb->get_row("
        SELECT _ID, location 
        FROM $table_name 
        WHERE _ID > 30000 
        AND COALESCE(business_done, '') <> 'Done'
        ORDER BY _ID ASC 
        LIMIT 1
    ");

    if (!$row) {
        return "<h3>✅ Finished! All records processed.</h3>";
    }

    $loc = trim($row->location);

    // Validate location format
    if (strpos($loc, '|') === false) {
        return "❌ Invalid location format: " . esc_html($loc);
    }

    list($latitude, $longitude) = explode('|', $loc);

    $latitude = trim($latitude);
    $longitude = trim($longitude);

    // Basic validation
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return "❌ Invalid lat/lng: " . esc_html($loc);
    }

    // Run your function
    if (!function_exists('serper_nearest_business')) {
        return "<strong>Error:</strong> Function 'serper_nearest_business' missing.";
    }

    $result = serper_nearest_business($latitude, $longitude);
    $processed = $result['processed'] ;
    $inserted  = $result['inserted'] ;
    $updated   = $result['updated'] ;
    $list      = $result['list'];
    
    $num = $inserted + $updated;
    // Update using PRIMARY KEY (important)
    $wpdb->update(
        $table_name,
        [
            'business_done' => 'Done',
            'business' => intval($num)
        ],
        ['_ID' => $row->_ID],
        ['%s', '%d'],
        ['%d']
    );

    //$result = implode(", ", $result); 
    ob_start();
    ?>
    <div style="padding:20px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;">
        <strong>ID:</strong> <?php echo esc_html($row->_ID); ?><br>
        <strong>Location:</strong> <?php echo esc_html($loc); ?><br><br>
        Processed: <?php echo ($processed); ?> <br>
        Inserted :</strong> <?php echo ($inserted); ?> <br>
        Updated  :</strong> <?php echo ($updated); ?> <br>
        <?php echo ($list); ?> <br>
        <p>⏳ Auto-processing next...</p>
    </div>

    <script>
        setTimeout(function() {
            window.location.reload();
        }, 800);
    </script>
    <?php

    return ob_get_clean();
}

///////////////

add_shortcode('mosque_index_location1', 'mosque_index_location1_shortcode');
function mosque_index_location1_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;
 
    // Use the exact table name from your error message
    $table_name = $wpdb->prefix . 'jet_cct_location';

    // 1. Retrieve 1 record
    $row = $wpdb->get_row("SELECT location FROM $table_name WHERE mosque_done IS NULL OR mosque_done = '' LIMIT 1 ");

    if (!$row) {
        return "<h3>Finished! All records in $table_name have been processed.</h3>";
    }

    $loc = $row->location;
    list($latitude, $longitude) = explode('|', $loc);

    // 2. Run Function
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($latitude, $longitude);
    } else {
        return "<strong>Error:</strong> The function 'serper_nearest_mosques' is missing.";
    }
 
    // 3. Mark as Done
   $wpdb->update(
        $table_name,
        [
            'mosque_done' => 'Done',
            'mosque' => $num  // Updating the mosque field with the result
        ],
        ['location' => $loc],
        ['%s', '%s'], 
        ['%s']        // id is integer (%d)
    );

    ob_start();
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
       <p><strong>Location:</strong> <?php echo esc_html($loc); ?></p>
        <p>Processing... Page will refresh in 1 second.</p>
    </div>

    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    </script>
    <?php
    
    $buffer_content = ob_get_clean();
    $ret = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    $ret .= 'No of records - ' . $num;
    $ret .= '</div>';
    // Combine your existing $ret (if any) with the buffer and return it
    return $buffer_content . $ret;
}

///////////////
add_shortcode('mosque_index_location2', 'mosque_index_location2_shortcode');

function mosque_index_location2_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    // Use the exact table name from your error message
    $table_name = $wpdb->prefix . 'jet_cct_location';

    // 1. Retrieve 1 record
    $row = $wpdb->get_row("SELECT location FROM $table_name WHERE mosque_done IS NULL OR mosque_done = '' LIMIT 1 OFFSET 10000");

    if (!$row) {
        return "<h3>Finished! All records in $table_name have been processed.</h3>";
    }

    $loc = $row->location;
    list($latitude, $longitude) = explode('|', $loc);

    // 2. Run Function
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($latitude, $longitude);
    } else {
        return "<strong>Error:</strong> The function 'serper_nearest_mosques' is missing.";
    }

    // 3. Mark as Done
   $wpdb->update(
        $table_name,
        [
            'mosque_done' => 'Done',
            'mosque' => $num  // Updating the mosque field with the result
        ],
        ['location' => $loc],
        ['%s', '%s'], 
        ['%s']        // id is integer (%d)
    );

    ob_start();
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
       <p><strong>Location:</strong> <?php echo esc_html($loc); ?></p>
        <p>Processing... Page will refresh in 1 second.</p>
    </div>

    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    </script>
    <?php
    
    $buffer_content = ob_get_clean();
    $ret = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    $ret .= 'No of records - ' . $num;
    $ret .= '</div>';
    // Combine your existing $ret (if any) with the buffer and return it
    return $buffer_content . $ret;
}

add_shortcode('mosque_index_location3', 'mosque_index_location3_shortcode');

function mosque_index_location3_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    // Use the exact table name from your error message
    $table_name = $wpdb->prefix . 'jet_cct_location';

    // 1. Retrieve 1 record
    $row = $wpdb->get_row("SELECT location FROM $table_name WHERE mosque_done IS NULL OR mosque_done = '' LIMIT 1 OFFSET 20000");

    if (!$row) {
        return "<h3>Finished! All records in $table_name have been processed.</h3>";
    }

    $loc = $row->location;
    list($latitude, $longitude) = explode('|', $loc);

    // 2. Run Function
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($latitude, $longitude);
    } else {
        return "<strong>Error:</strong> The function 'serper_nearest_mosques' is missing.";
    }

    // 3. Mark as Done
   $wpdb->update(
        $table_name,
        [
            'mosque_done' => 'Done',
            'mosque' => $num  // Updating the mosque field with the result
        ],
        ['location' => $loc],
        ['%s', '%s'], 
        ['%s']        // id is integer (%d)
    );

    ob_start();
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
       <p><strong>Location:</strong> <?php echo esc_html($loc); ?></p>
        <p>Processing... Page will refresh in 1 second.</p>
    </div>

    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    </script>
    <?php
    
    $buffer_content = ob_get_clean();
    $ret = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    $ret .= 'No of records - ' . $num;
    $ret .= '</div>';
    // Combine your existing $ret (if any) with the buffer and return it
    return $buffer_content . $ret;
}


add_shortcode('mosque_index_location4', 'mosque_index_location4_shortcode');

function mosque_index_location4_shortcode() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    // Use the exact table name from your error message
    $table_name = $wpdb->prefix . 'jet_cct_location';

    // 1. Retrieve 1 record
    $row = $wpdb->get_row("SELECT location FROM $table_name WHERE mosque_done IS NULL OR mosque_done = '' LIMIT 1 OFFSET 30000");

    if (!$row) {
        return "<h3>Finished! All records in $table_name have been processed.</h3>";
    }

    $loc = $row->location;
    list($latitude, $longitude) = explode('|', $loc);

    // 2. Run Function
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($latitude, $longitude);
    } else {
        return "<strong>Error:</strong> The function 'serper_nearest_mosques' is missing.";
    }

    // 3. Mark as Done
   $wpdb->update(
        $table_name,
        [
            'mosque_done' => 'Done',
            'mosque' => $num  // Updating the mosque field with the result
        ],
        ['location' => $loc],
        ['%s', '%s'], 
        ['%s']        // id is integer (%d)
    );

    ob_start();
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
       <p><strong>Location:</strong> <?php echo esc_html($loc); ?></p>
        <p>Processing... Page will refresh in 1 second.</p>
    </div>

    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    </script>
    <?php
    
    $buffer_content = ob_get_clean();
    $ret = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    $ret .= 'No of records - ' . $num;
    $ret .= '</div>';
    // Combine your existing $ret (if any) with the buffer and return it
    return $buffer_content . $ret;
}

add_shortcode('mosque_auto_processor', 'mosque_auto_processor_logic');

function mosque_auto_processor_logic() {
    if (!current_user_can('manage_options')) return "Unauthorized.";

    global $wpdb;

    // Use the exact table name from your error message
    $table_name = 'wp_countries'; 

    // 1. Retrieve 1 record
    $row = $wpdb->get_row("SELECT id, city, country, latitude, longitude FROM $table_name WHERE status IS NULL OR status = '' LIMIT 1");

    if (!$row) {
        return "<h3>Finished! All records in $table_name have been processed.</h3>";
    }

    // 2. Run Function
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($row->latitude, $row->longitude, $row->country, $row->city);
    } else {
        return "<strong>Error:</strong> The function 'serper_nearest_mosques' is missing.";
    }

    // 3. Mark as Done
   $wpdb->update(
        $table_name,
        [
            'status' => 'done',
            'mosque' => $num  // Updating the mosque field with the result
        ],
        ['id' => $row->id],
        ['%s', '%s'], 
        ['%d']        // id is integer (%d)
    );

    ob_start();
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
        <p><strong>Current ID:</strong> <?php echo $row->id; ?></p>
        <p><strong>Location:</strong> <?php echo esc_html($row->city . ', ' . $row->country); ?></p>
        <p>Processing... Page will refresh in 1 second.</p>
    </div>

    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    </script>
    <?php
    
    $buffer_content = ob_get_clean();
    $ret = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    $ret .= 'No of records - ' . $num;
    $ret .= '</div>';
    // Combine your existing $ret (if any) with the buffer and return it
    return $buffer_content . $ret;
}

/////////////////////////////////////////////
// NEARBY MOSQUES LIST - USER CAN UPLOAD CSV
/////////////////////////////////////////////

add_shortcode('serper_mosques_upload', 'serper_mosques_upload_shortcode');

function serper_mosques_upload_shortcode() {

    ob_start();

    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="mosque_csv" accept=".csv" required>
        <button type="submit" name="upload_csv">Upload & Process</button>
    </form>

    <?php
    if (isset($_POST['upload_csv']) && !empty($_FILES['mosque_csv']['tmp_name'])) {
        $file = $_FILES['mosque_csv']['tmp_name'];

        // Read CSV file
        $csv_data = array_map('str_getcsv', file($file));
        
        // Remove header row if it exists
        if (!empty($csv_data) && strtolower($csv_data[0][0]) == 'latitude') {
            array_shift($csv_data);
        }
 
        $total_records = count($csv_data);
        $ret =  "<h3>Total Records: $total_records</h3>";
        $rec = 0;
        
        if ($total_records > 0) {
            foreach ($csv_data as $row) {
                $rec = $rec + 1;
                if (count($row) < 2) continue; // Skip invalid rows
                
                $city      = trim($row[0]);
                $latitude  = trim($row[1]);
                $longitude = trim($row[2]);
                $country   = trim($row[3]);
           
                $ret .= '' . $rec . ' : ' . $latitude . ',' . $longitude . '  ' . $country .  '<br>';      
              
                serper_nearest_mosques($latitude, $longitude, $country, $city);
            }
            echo "<h3>Processing Complete!</h3>";
        } else {
            echo "<h3>No valid data found in the uploaded file.</h3>";
        }
    }
 
    return $ret; ob_get_clean();
}


////////////////////////
add_shortcode('update_mosque_country', 'mosque_country_shortcode');
 
function mosque_country_shortcode() {
    // Query all mosque CPT posts
    $args = array(
        'post_type' => 'masjid', // Replace 'mosque' with your CPT slug
        'posts_per_page' => -1, // Get all mosques
    );

    $mosques = new WP_Query($args);

    if ($mosques->have_posts()) {
        while ($mosques->have_posts()) {
            $mosques->the_post();

            $post_id = get_the_ID();
            $latitude = get_post_meta($post_id, 'latitude', true); // Replace 'latitude' with your latitude meta key
            $longitude = get_post_meta($post_id, 'longitude', true); // Replace 'longitude' with your longitude meta key
            $country = get_post_meta($post_id, 'country', true); // Replace 'country' with your country meta key
            //echo $post_id . ' ' . $country . '<br>';
     
            // Check if latitude and longitude exist and country is empty
            //if (!empty($latitude) && !empty($longitude) && $country=='Country not found (no comma).' ) {
            //if (substr($country,0,9)=='Singapore' ) { 
            if ($country=='Czechia' ) { 
             
                $country1 = get_country_from_lat_long($latitude, $longitude);
                update_post_meta($post_id, 'country', $country1);
                echo $post_id . ' ' . $country . ' ' . $country1 . '<br>';
            }
        }
        wp_reset_postdata(); // Reset the query
        return "Mosque country data updated.";
    } else {
        return "No mosques found.";
    }
}

function get_country_from_lat_long($latitude, $longitude) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" . urlencode($latitude) . "&lon=" . urlencode($longitude);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Masjid4All/1.0'); // Important: Set a user agent
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result) {
        $data = json_decode($result, true);
        if (isset($data['address']['country'])) {
            $country_code = isset($data['address']['country_code']) ? strtoupper($data['address']['country_code']) : null;

            if ($country_code) {
                // Use a lookup table for standard country names
                $country_names = get_standard_country_names();

                if (isset($country_names[$country_code])) {
                    return $country_names[$country_code];
                } else {
                    // Fallback to the short name if standard name isn't found
                    return $data['address']['country'];
                }
            } else {
                return $data['address']['country']; // Fallback to short name if no country code
            }
        } else {
            return "";
        }
    } else {
        return "";
    }
}

function get_standard_country_names() {
    // Lookup table for country codes and standard names (ISO 3166-1 alpha-2)
    return array(
        "AF" => "Afghanistan",
        "AL" => "Albania",
        "DZ" => "Algeria",
        "AD" => "Andorra",
        "AO" => "Angola",
        "AG" => "Antigua and Barbuda",
        "AR" => "Argentina",
        "AM" => "Armenia",
        "AU" => "Australia",
        "AT" => "Austria",
        "AZ" => "Azerbaijan",
        "BS" => "Bahamas",
        "BH" => "Bahrain",
        "BD" => "Bangladesh",
        "BB" => "Barbados",
        "BY" => "Belarus",
        "BE" => "Belgium",
        "BZ" => "Belize",
        "BJ" => "Benin",
        "BT" => "Bhutan",
        "BO" => "Bolivia",
        "BA" => "Bosnia and Herzegovina",
        "BW" => "Botswana",
        "BR" => "Brazil",
        "BN" => "Brunei",
        "BG" => "Bulgaria",
        "BF" => "Burkina Faso",
        "BI" => "Burundi",
        "CV" => "Cabo Verde",
        "KH" => "Cambodia",
        "CM" => "Cameroon",
        "CA" => "Canada",
        "CF" => "Central African Republic",
        "TD" => "Chad",
        "CL" => "Chile",
        "CN" => "China",
        "CO" => "Colombia",
        "KM" => "Comoros",
        "CG" => "Congo",
        "CD" => "Democratic Republic of the Congo",
        "CR" => "Costa Rica",
        "CI" => "Côte d'Ivoire",
        "HR" => "Croatia",
        "CU" => "Cuba",
        "CY" => "Cyprus",
        "CZ" => "Czechia",
        "DK" => "Denmark",
        "DJ" => "Djibouti",
        "DM" => "Dominica",
        "DO" => "Dominican Republic",
        "EC" => "Ecuador",
        "EG" => "Egypt",
        "SV" => "El Salvador",
        "GQ" => "Equatorial Guinea",
        "ER" => "Eritrea",
        "EE" => "Estonia",
        "SZ" => "Eswatini",
        "ET" => "Ethiopia",
        "FJ" => "Fiji",
        "FI" => "Finland",
        "FR" => "France",
        "GA" => "Gabon",
        "GM" => "Gambia",
        "GE" => "Georgia",
        "DE" => "Germany",
        "GH" => "Ghana",
        "GR" => "Greece",
        "GD" => "Grenada",
        "GT" => "Guatemala",
        "GN" => "Guinea",
        "GW" => "Guinea-Bissau",
        "GY" => "Guyana",
        "HT" => "Haiti",
        "HN" => "Honduras",
        "HU" => "Hungary",
        "IS" => "Iceland",
        "IN" => "India",
        "ID" => "Indonesia",
        "IR" => "Iran",
        "IQ" => "Iraq",
        "IE" => "Ireland",
        "IL" => "Israel",
        "IT" => "Italy",
        "JM" => "Jamaica",
        "JP" => "Japan",
        "JO" => "Jordan",
        "KZ" => "Kazakhstan",
        "KE" => "Kenya",
        "KI" => "Kiribati",
        "KP" => "North Korea",
        "KR" => "South Korea",
        "KW" => "Kuwait",
        "KG" => "Kyrgyzstan",
        "LA" => "Laos",
        "LV" => "Latvia",
        "LB" => "Lebanon",
        "LS" => "Lesotho",
        "LR" => "Liberia",
        "LY" => "Libya",
        "LI" => "Liechtenstein",
        "LT" => "Lithuania",
        "LU" => "Luxembourg",
        "MG" => "Madagascar",
        "MW" => "Malawi",
        "MY" => "Malaysia",
        "MV" => "Maldives",
        "ML" => "Mali",
        "MT" => "Malta",
        "MH" => "Marshall Islands",
        "MR" => "Mauritania",
        "MU" => "Mauritius",
        "MX" => "Mexico",
        "FM" => "Micronesia",
        "MD" => "Moldova",
        "MC" => "Monaco",
        "MN" => "Mongolia",
        "ME" => "Montenegro",
        "MA" => "Morocco",
        "MZ" => "Mozambique",
        "MM" => "Myanmar",
        "NA" => "Namibia",
        "NR" => "Nauru",
        "NP" => "Nepal",
        "NL" => "Netherlands",
        "NZ" => "New Zealand",
        "NI" => "Nicaragua",
        "NE" => "Niger",
        "NG" => "Nigeria",
        "NO" => "Norway",
        "OM" => "Oman",
        "PK" => "Pakistan",
        "PW" => "Palau",
        "PS" => "Palestine",
        "PA" => "Panama",
        "PG" => "Papua New Guinea",
        "PY" => "Paraguay",
        "PE" => "Peru",
        "PH" => "Philippines",
        "PL" => "Poland",
        "PT" => "Portugal",
        "QA" => "Qatar",
        "RO" => "Romania",
        "RU" => "Russia",
        "RW" => "Rwanda",
        "KN" => "Saint Kitts and Nevis",
        "LC" => "Saint Lucia",
        "VC" => "Saint Vincent and the Grenadines",
        "WS" => "Samoa",
        "SM" => "San Marino",
        "ST" => "Sao Tome and Principe",
        "SA" => "Saudi Arabia",
        "SN" => "Senegal",
        "RS" => "Serbia",
        "SC" => "Seychelles",
        "SL" => "Sierra Leone",
        "SG" => "Singapore",
        "SK" => "Slovakia",
        "SI" => "Slovenia",
        "SB" => "Solomon Islands",
        "SO" => "Somalia",
        "ZA" => "South Africa",
        "SS" => "South Sudan",
        "ES" => "Spain",
        "LK" => "Sri Lanka",
        "SD" => "Sudan",
        "SR" => "Suriname",
        "SE" => "Sweden",
        "CH" => "Switzerland",
        "SY" => "Syria",
        "TW" => "Taiwan",
        "TJ" => "Tajikistan",
        "TZ" => "Tanzania",
        "TH" => "Thailand",
        "TL" => "Timor-Leste",
        "TG" => "Togo",
        "TO" => "Tonga",
        "TT" => "Trinidad and Tobago",
        "TN" => "Tunisia",
        "TR" => "Türkiye",
        "TM" => "Turkmenistan",
        "TV" => "Tuvalu",
        "UG" => "Uganda",
        "UA" => "Ukraine",
        "AE" => "United Arab Emirates",
        "GB" => "United Kingdom",
        "US" => "United States",
        "UY" => "Uruguay",
        "UZ" => "Uzbekistan",
        "VU" => "Vanuatu",
        "VA" => "Vatican City",
        "VE" => "Venezuela",
        "VN" => "Vietnam",
        "YE" => "Yemen",
        "ZM" => "Zambia",
        "ZW" => "Zimbabwe"
    );
}

//////////////////
function get_country_from_string($address_string) {
    // Find the last comma in the string.
    $last_comma_position = strrpos($address_string, ',');

    if ($last_comma_position !== false) {
        // Extract the substring after the last comma.
        $country = trim(substr($address_string, $last_comma_position + 1));
        return $country;
    } else {
        // If no comma is found, return the original string or an error message.
        return "";
    }
}

/////////////////////////////
// Serper - Map            //
/////////////////////////////

add_action('plugins_loaded', function() {
    // Add the shortcode.
    add_shortcode('serper_google_map', 'serper_google_map_shortcode');
});
 
function serper_google_map_shortcode($atts) {
    // Define default attributes
    $atts = shortcode_atts(array(
        'lat' => '',
        'lng' => '',
    ), $atts);
    
    $lat = sanitize_text_field($atts['lat']);
    $lng = sanitize_text_field($atts['lng']);
    
    $loc = '"ll":"@' . $lat . ',' . $lng . ',16z"';
    
    //echo $loc;
    echo '<br>"ll":"@3.1963,101.7125,16z"';
    //return;
     
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
      
      //"ll":"@3.1963,101.7125,16z"
      CURLOPT_POSTFIELDS =>'{"q":"mosque",' . $loc . '}',
      //CURLOPT_HTTPHEADER => array(
      //    'X-API-KEY: 5a39e7769853abdb21881675e945464322f30325',
      //    'Content-Type: application/json'
      //),
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: 96d2c4458179ef645bbcebe48e434dd50734ab7c',
        'Content-Type: application/json'
      ),
      
    ));
     
    $response = curl_exec($curl);
    
    curl_close($curl);
    
    // Decode JSON
    $data = json_decode($response, true);

    // Check if places exist
    if (!empty($data['places'])) {
   
        $ret = "<h1>List of Mosques</h1>";
  
        foreach ($data['places'] as $mosque) {
            $place_id = $mosque['placeId'];
            $name = $mosque['title'];
            $address = $mosque['address'];
            $phone = $mosque['phoneNumber'];
            $website = $mosque['website'];
            $image = "<img src='" . htmlspecialchars($mosque['thumbnailUrl']) . "' alt='Image of " . htmlspecialchars($mosque['title']) . "' width='300'/>";
            $opening_hours = '';
            foreach ($mosque['openingHours'] as $day => $hours) {
                $opening_hours .= "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
            }
    
            $ret.= "<b>" . $name . "</b><br>";
            $ret.= $image;
            $ret.= $address . "<br>";
            $ret.= "PlaceID : " . $place_id . "<br>";
            $ret.= "Tel : " . $phone . "<br>";
            $ret.= "Website : " . $website . "<br>";
            $ret.= "Opening Hours<br>" . $opening_hours . "<br>";
        }
        
        /*
            echo "<li>";
            echo "<h2>" . htmlspecialchars($mosque['title']) . "</h2>";
            echo "Place ID : " .  htmlspecialchars($mosque['placeId']) ;
            echo "<p><strong>Address:</strong> " . htmlspecialchars($mosque['address']) . "</p>";
            echo "<p><strong>Phone:</strong> " . htmlspecialchars($mosque['phoneNumber']) . "</p>";
            echo "<p><strong>Rating:</strong> " . htmlspecialchars($mosque['rating']) . " (" . htmlspecialchars($mosque['ratingCount']) . " reviews)</p>";
            echo "<p><strong>Website:</strong> <a href='" . htmlspecialchars($mosque['website']) . "' target='_blank'>" . htmlspecialchars($mosque['website']) . "</a></p>";
            echo "<p><strong>Opening Hours:</strong></p><ul>";
            foreach ($mosque['openingHours'] as $day => $hours) {
                echo "<li>" . htmlspecialchars($day) . ": " . htmlspecialchars($hours) . "</li>";
            }
            echo "</ul>";
            echo "<img src='" . htmlspecialchars($mosque['thumbnailUrl']) . "' alt='Image of " . htmlspecialchars($mosque['title']) . "' width='300'/>";
            echo "<hr>";
            echo "</li>";
        }
        echo "</ul>";
        */
    } else {
        return "No mosque data found.";
    }
 
    return $ret;
    
} 


// MOSQUE SUMMARY //////////////////////

add_shortcode('mosque_summary', 'mosque_summary_shortcode');

function mosque_summary_shortcode() {
    $args = array(
        'post_type' => 'masjid',
        'posts_per_page' => -1,
    );

    $mosques = new WP_Query($args);
    $country_counts = array();
   
    if ($mosques->have_posts()) {
        while ($mosques->have_posts()) {
            $mosques->the_post();
            $country = get_post_meta(get_the_ID(), 'country', true); // Replace 'country' with your meta key

            if (!empty($country)) {
                if (isset($country_counts[$country])) {
                    $country_counts[$country]++;
                } else {
                    $country_counts[$country] = 1;
                }
            }
        }
        wp_reset_postdata();

        // Sort the countries alphabetically
        ksort($country_counts);

        // Build the table
        $output = '<table style="border-collapse: collapse; width: 100%;">';
        $output .= '<thead><tr><th style="border: 1px solid black; padding: 8px;">Country</th><th style="border: 1px solid black; padding: 8px;">Number of Mosques</th></tr></thead><tbody>';

        $tot_country = 0;
        $tot_mosque = 0;
        foreach ($country_counts as $country => $count) {
            $tot_country = $tot_country + 1;
            $tot_mosque = $tot_mosque + $count;
            $output .= '<tr><td style="border: 1px solid black; padding: 8px;">' . esc_html($country) . '</td><td style="border: 1px solid black; padding: 8px;">' . esc_html($count) . '</td></tr>';
        }

        $output .= '</tbody></table>';
        $output .= 'TOTAL COUNTRY - <b>' . $tot_country . '</b><br>';
        $output .= 'TOTAL MOSQUE - <b>' . $tot_mosque . '</b><br>';
        return $output;
    } else {
        return '<p>No mosques found.</p>';
    }
}

