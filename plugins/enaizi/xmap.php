<?php





/*

// CALCULATE DISTANCE

add_filter( 'jet-engine/cct/query/args', function( $args, $cct_slug ) {
    if ( 'mosque' !== $cct_slug ) {
        return $args; // only modify mosque CCT
    }

    global $wpdb;

    // read user location from cookies
    $lat = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 0;
    $lon = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 0;

    // only modify if we have user location
    if ( $lat && $lon ) {
        $table = $wpdb->prefix . 'jet_cct_mosque';

        $args['select'] = "$table.*, 
            (6371 * acos(
                cos( radians($lat) ) * cos( radians($table.latitude) ) 
                * cos( radians($table.longitude) - radians($lon) ) 
                + sin( radians($lat) ) * sin( radians($table.latitude) )
            )) AS distance";

        $args['orderby'] = 'distance ASC';
    } else {
        error_log("No user location found, mosque query unchanged");
    }

    return $args;
}, 10, 2 );

 
// push distance into Listing Grid item meta
add_filter( 'jet-engine/listings/modify-item', function( $item, $listing ) {
    if ( isset( $item['distance'] ) ) {
        $item['meta']['distance'] = round( $item['distance'], 2 ); // show in KM
    }
    return $item;
}, 10, 2 );
/*

// TO INDEX NEW LOCATION BASED ON USER'S LATITUDE AND LONGITUDE 
add_shortcode('masjid_crawler', 'masjid_crawler_shortcode');

function masjid_crawler_shortcode() {
    ob_start();
    echo '<div id="masjid-crawler-status"></div>';
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', 'enqueue_masjid_crawler_scripts');
function enqueue_masjid_crawler_scripts() {
    wp_register_script('masjid-crawler-js', '', [], false, true);
    wp_enqueue_script('masjid-crawler-js');

    $ajax_nonce = wp_create_nonce('run_masjid_crawler_nonce');

    wp_add_inline_script('masjid-crawler-js', '
        (function() {
            // Debug configuration
            const DEBUG_MODE = true;
            const LOG_PREFIX = "[Masjid Loc]";
            const STORAGE_KEY = "masjid_loc_data_v2";
            
            // Service intervals
            const INITIAL_DELAY = 1500; // 1.5s after page load
            const SERVER_CHECK_INTERVAL = 300000; // 5 minutes
            const ERROR_COOLDOWN = 3600000; // 1 hour
            
            // Debug logging with timestamp
            function debugLog(message, force = false) {
                if (DEBUG_MODE || force) {
                    const timestamp = new Date().toISOString().substring(11, 23);
                    console.log(`${LOG_PREFIX} [${timestamp}] ${message}`);
                    
                    // Send to server log if needed
                    try {
                        fetch("' . admin_url('admin-ajax.php') . '?action=debug_log&message=" + 
                            encodeURIComponent(message) + "&nonce=' . esc_js($ajax_nonce) . '", {
                            keepalive: true
                        });
                    } catch (e) {
                        console.error(`${LOG_PREFIX} Debug log failed:`, e);
                    }
                }
            }
            
            // Error message display
            function showError(message) {
                debugLog(`UI Error: ${message}`, true);
                try {
                    const existing = document.querySelector(".masjid-loc-error");
                    if (existing) existing.remove();
                    
                    const errorDiv = document.createElement("div");
                    errorDiv.className = "masjid-loc-error";
                    errorDiv.innerHTML = `
                        <div style="
                            position: fixed;
                            bottom: 20px;
                            left: 50%;
                            transform: translateX(-50%);
                            padding: 12px 24px;
                            background: #f44336;
                            color: white;
                            border-radius: 4px;
                            z-index: 9999;
                            font-family: sans-serif;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                            max-width: 80vw;
                            text-align: center;
                        ">
                            ${message}
                            <span style="margin-left:15px;cursor:pointer" onclick="this.parentElement.remove()">×</span>
                        </div>
                    `;
                    
                    document.body.appendChild(errorDiv);
                    setTimeout(() => {
                        if (errorDiv.parentNode) errorDiv.remove();
                    }, 5000);
                } catch (e) {
                    debugLog(`Error display failed: ${e}`, true);
                }
            }
            
            // Location storage management
            function getStoredLocation() {
                try {
                    const data = localStorage.getItem(STORAGE_KEY);
                    return data ? JSON.parse(data) : null;
                } catch (e) {
                    debugLog(`Storage read error: ${e}`);
                    return null;
                }
            }
            
            function storeLocation(lat, lng, verified = false) {
                try {
                    const data = {
                        lat: parseFloat(lat).toFixed(3),
                        lng: parseFloat(lng).toFixed(3),
                        timestamp: Date.now(),
                        serverVerified: verified,
                        version: 2
                    };
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                    debugLog(`Stored location: ${JSON.stringify(data)}`);
                } catch (e) {
                    debugLog(`Storage write error: ${e}`);
                }
            }
            
            // Server communication
            async function verifyLocation(lat, lng) {
                try {
                    debugLog(`Verifying location: ${lat},${lng}`);
                    const response = await fetch("' . admin_url('admin-ajax.php') . '?action=check_location_exists&lat=" + 
                        encodeURIComponent(lat) + "&lng=" + encodeURIComponent(lng) + 
                        "&nonce=' . esc_js($ajax_nonce) . '", {
                            credentials: "same-origin",
                            headers: { "Cache-Control": "no-cache" }
                        });
                    
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    const data = await response.json();
                    debugLog(`Verification response: ${JSON.stringify(data)}`);
                    return data.exists === true;
                } catch (e) {
                    debugLog(`Verification failed: ${e}`);
                    return false;
                }
            }
            
            async function sendLocation(lat, lng) {
                try {
                    debugLog(`Sending location: ${lat},${lng}`);
                    const response = await fetch("' . admin_url('admin-ajax.php') . '?action=run_masjid_crawler&lat=" + 
                        encodeURIComponent(lat) + "&lng=" + encodeURIComponent(lng) + 
                        "&nonce=' . esc_js($ajax_nonce) . '", {
                            credentials: "same-origin",
                            keepalive: true
                        });
                    
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    debugLog("Location successfully sent");
                    return true;
                } catch (e) {
                    debugLog(`Location send failed: ${e}`);
                    return false;
                }
            }
            
            // Location handling logic
            async function processNewLocation(position) {
                const lat = position.coords.latitude.toFixed(3);
                const lng = position.coords.longitude.toFixed(3);
                
                try {
                    // Step 1: Verify if location exists on server
                    const exists = await verifyLocation(lat, lng);
                    
                    if (!exists) {
                        // Step 2: If not exists, send to server
                        const sent = await sendLocation(lat, lng);
                        
                        if (sent) {
                            // Step 3: Store as verified if successful
                            storeLocation(lat, lng, true);
                            debugLog("Location processed successfully");
                        } else {
                            throw new Error("Failed to send location");
                        }
                    } else {
                        // Location already exists, just store verification
                        storeLocation(lat, lng, true);
                        debugLog("Location already exists on server");
                    }
                } catch (e) {
                    debugLog(`Location processing failed: ${e}`);
                    throw e;
                }
            }
            
            // Main service initialization
            function initLocationService() {
                debugLog("Initializing service...");
                
                // Check geolocation support
                if (!navigator.geolocation) {
                    showError("Geolocation is not supported by your browser");
                    return;
                }
                
                // Check permissions state
                navigator.permissions.query({name: "geolocation"})
                    .then(permissionStatus => {
                        debugLog(`Permission state: ${permissionStatus.state}`);
                        
                        if (permissionStatus.state === "denied") {
                            showError("Please enable location permissions in your browser settings");
                            return;
                        }
                        
                        // Start periodic checks
                        setInterval(checkLocation, SERVER_CHECK_INTERVAL);
                        
                        // Immediate check if needed
                        const stored = getStoredLocation();
                        if (!stored || !stored.serverVerified) {
                            debugLog("No verified location - performing initial check");
                            checkLocation();
                        } else {
                            debugLog("Using existing verified location");
                        }
                    })
                    .catch(e => {
                        debugLog(`Permission check failed: ${e}`);
                        checkLocation();
                    });
            }
            
            function checkLocation() {
                debugLog("Starting location check...");
                
                navigator.geolocation.getCurrentPosition(
                    position => {
                        debugLog("Location acquired");
                        processNewLocation(position)
                            .catch(e => showError("Failed to process location"));
                    },
                    error => {
                        const errors = {
                            1: "PERMISSION_DENIED",
                            2: "POSITION_UNAVAILABLE",
                            3: "TIMEOUT"
                        };
                        debugLog(`Location error (${errors[error.code] || "UNKNOWN"}): ${error.message}`);
                        
                        if (error.code === 1) { // PERMISSION_DENIED
                            showError("Location access was denied. Please enable permissions.");
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 300000, // 5 minutes
                        timeout: 10000
                    }
                );
            }
            
            // Start the service after slight delay
            document.addEventListener("DOMContentLoaded", () => {
                setTimeout(initLocationService, INITIAL_DELAY);
            });
        })();
    ');
}

// AJAX handler for location verification
add_action('wp_ajax_check_location_exists', 'handle_check_location_exists');
add_action('wp_ajax_nopriv_check_location_exists', 'handle_check_location_exists');
function handle_check_location_exists() {
    try {
        if (!wp_verify_nonce($_GET['nonce'], 'run_masjid_crawler_nonce')) {
            throw new Exception('Invalid nonce');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . "jet_cct_location";
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE latitude = %s AND longitude = %s LIMIT 1",
            sanitize_text_field($_GET['lat']),
            sanitize_text_field($_GET['lng'])
        ));
        
        wp_send_json_success(['exists' => (bool)$exists]);
        
    } catch (Exception $e) {
        error_log("Masjid Location Check Error: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

// AJAX handler for debug logging
add_action('wp_ajax_debug_log', 'handle_debug_log');
add_action('wp_ajax_nopriv_debug_log', 'handle_debug_log');
function handle_debug_log() {
    if (!wp_verify_nonce($_GET['nonce'], 'run_masjid_crawler_nonce')) {
        error_log("Masjid Debug Log: Invalid nonce");
        wp_die();
    }
    
    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
    error_log("Masjid Debug: " . $message);
    wp_die();
}
 
// AJAX HOOKS FOR BACKGROUND CRAWLER
add_action('wp_ajax_nopriv_run_masjid_crawler', 'run_masjid_crawler');
add_action('wp_ajax_run_masjid_crawler', 'run_masjid_crawler');

function run_masjid_crawler() {
    // Verify input and nonce
    if (!isset($_GET['lat']) || !isset($_GET['lng']) || !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'run_masjid_crawler_nonce')) {
        error_log('Masjid Crawler: Invalid request - missing parameters or nonce');
        wp_send_json_error('Invalid request');
        wp_die();
    }

    // Format coordinates
    $lat = number_format(floatval($_GET['lat']), 3, '.', '');
    $lng = number_format(floatval($_GET['lng']), 3, '.', '');
    
    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_location";
    
    // Debug: Log received coordinates
    error_log("Masjid Crawler: Checking location - Lat: $lat, Lng: $lng");
    
    // Check if location exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table 
         WHERE latitude = %s 
         AND longitude = %s",
        $lat,
        $lng
    ));
    
    if ($exists) {
        error_log("Masjid Crawler: Location exists - Lat: $lat, Lng: $lng");
        wp_send_json_success('Location exists');
        wp_die();
    }
     
    // Run crawler and verify result
    error_log("Masjid Crawler: Running crawler for new location");
    $crawler_result = crawler($lat, $lng);
       
    if ($crawler_result === false) {
        error_log("Masjid Crawler: Crawler failed for Lat: $lat, Lng: $lng");
        wp_send_json_error('Crawler failed');
        wp_die();
    }else{
        error_log("Masjid Crawler: Crawler success for Lat: $lat, Lng: $lng");
    }
    
    // Insert new location
    $inserted = $wpdb->insert($table, [
        'latitude' => $lat,
        'longitude' => $lng,
        'mosque' => 'Updated',
        'business' => 'Updated'
    ], ['%s', '%s', '%s', '%s']); // Explicit format specification
    
    if (false === $inserted) {
        error_log("Masjid Crawler: DB insert failed. Error: " . $wpdb->last_error);
        wp_send_json_error('Failed to insert location. Error: ' . $wpdb->last_error);
    } else {
        error_log("Masjid Crawler: Successfully added location ID: " . $wpdb->insert_id);
        wp_send_json_success(['message' => 'Location added', 'id' => $wpdb->insert_id]);
    }
    
    wp_die();
}

function crawler($latitude, $longitude) {
    global $wpdb;
    
    // Mosque
    $location = $latitude . ',' . $longitude;
    admin_crawl_data('mosque', $location);
  
    // Business
    $location = $latitude . ',' . $longitude;
    admin_crawl_data('business', $location);
    
    return;

    
}

add_shortcode('mosque_distance_shortcode', function($atts, $content = null) {
    $obj = jet_engine()->listings->data->get_current_object();

    if (empty($obj->latitude) || empty($obj->longitude)) {
        return;
    }

    if (!isset($_COOKIE['latitude']) || !isset($_COOKIE['longitude'])) {
        return;
    }

    $distance = calculate_distance_km(
        (float)$_COOKIE['latitude'],
        (float)$_COOKIE['longitude'],
        (float)$obj->latitude,
        (float)$obj->longitude
    );

    return sprintf(
        '<i>%s km (%s miles) away</i>',
        number_format($distance, 1),
        number_format($distance * 0.621371, 1)
    );
});

function calculate_distance_km($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // KM
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/*

function update_cct_record($table_name, $place_id, $data) {
    global $wpdb;

    if (empty($place_id) || empty($data)) {
        return "Error: place_id and data are required.";
    }

    // Ensure place_id is included in $data
    $data['place_id'] = $place_id;

    // Check if record exists
    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT _ID FROM {$table_name} WHERE place_id = %s",
            $place_id
        )
    );

    if ($existing_id) {
        // Update existing record
        $result = $wpdb->update(
            $table_name,
            $data,
            ['place_id' => $place_id],
            array_fill(0, count($data), '%s'),
            ['%s']
        );
        return $result !== false ? $existing_id : false;
    } else {
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            $data,
            array_fill(0, count($data), '%s')
        );
        return $result !== false ? $wpdb->insert_id : false;
    }
}

function update_cct_business($place_id, $data) {
    if (empty($place_id) || empty($data)) {
        return "Error: place_id and data are required.";
    }

    $cct = jet_engine()->cct->get_cct( 'cct_business' );
    if ( ! $cct ) {
        return "Error: CCT 'cct_business' not found.";
    }

    $existing = $cct->get_item_by( 'place_id', $place_id );
    $data['place_id'] = $place_id;

    if ( $existing ) {
        $result = $cct->update_item( $existing['_ID'], $data );
        return $result ? $existing['_ID'] : false;
    } else {
        $new_id = $cct->create_item( $data );
        return $new_id ? $new_id : false;
    }
}

// Function to calculate the distance between two coordinates (Haversine formula)
function calculate_distancex($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Radius of Earth in km

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLng/2) * sin($dLng/2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earth_radius * $c, 2); // Return distance in km (rounded to 2 decimal places)
}






// UPDATE CCT BUSINESS
function update_cct_business($place_id, $data) {
    global $wpdb;

    if (empty($place_id) || empty($data)) {
        return "Error: place_id and data are required.";
    }

    $table_name = 'wp_jet_cct_business';

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

 
*/


/*
// UPDATE LOCATION
function update_location($latitude,$longitude) {
    global $wpdb;

    $mosque_table = $wpdb->prefix . "jet_cct_mosque";
    
    $lat = floatval($latitude);
    $lng = floatval($longitude);
    $loc = sprintf('{"q":"mosque","ll":"@%f,%f,11z"}', $lat, $lng);
    $loc = sanitize_text_field($loc);
    echo '<br>' . $loc;
 
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
    //echo '<br>' . $response;
    // Decode JSON
    $data = json_decode($response, true);
    
    $country = get_country_from_lat_long($lat, $lng);
    
    $info = [];

    foreach ($data['places'] as $item) {
        $place_id = $item['placeId'];
        $name = strtoupper($item['title']);
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
    
        $ret.= ' PlaceID ' . $place_id . '<br>' ;

        // UPDATE CCT MOSQUE
        $item_id = update_cct_mosque($place_id, $place);

        // SEARCH CONTENT
        //$search = $name . ' ' . $address ;
        //$country_code = get_country_code($country);
        //$links = business_search_shortcode($search,$country_code);
        
        // GET CONTENT
        $content = '';
        //$question = json_encode($place, JSON_PRETTY_PRINT);
        //$content = ask_gemini_mosque($question . '<br>' . $links);
        //$ret .= '<br>' . $content . '<br>';
        
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
        $ret.= $page_url . '<br>';
        // Update the CCT record (post_id & page_url)
        $update_result = $wpdb->update(
            $mosque_table,                        // Table
            [
                'post_id'            => $post_id,
                'cct_single_post_id' => $post_id,
                'page_url'           => $page_url,
                'cct_modified'       => $modified_date,
                'cct_status'         => 'Updated'
            ],
            ['_ID' => $item_id],                  // Where
            ['%d', '%s'],                         // Data format
            ['%d']                                // Where format
        );
        
        $ret.= $name . '<br>' . $address . '<br>' . $country . ' - ' . $item_id . '<br><br>';
    }
 
    // REFRESH PAGE
    return $ret;
    
    echo '<script>
        setTimeout(function() {
            window.location.reload();
        }, 2000); // 2000 milliseconds = 2 seconds
    </script>';   

    return $ret;
    
}  

*/

/*

// SET CURRENT USER LOCATION
add_shortcode('map_set_location', 'map_set_location_shortcode');

function map_set_location_shortcode() {
    ob_start();
    $loc = $_COOKIE['location'] ?? 0;
    echo $loc ;
    //echo '<form method="post"><button type="submit" name="process">Update Your Location</button></form>';
    echo '<form method="post">
  <button 
    type="submit" 
    name="process" 
    style="
      background-color: #D4591E;
      color: white;
      padding: 6px 15px;
      border: none;
      border-radius: 4px;
      font-size: 13px;
      cursor: pointer;
      transition: background-color 0.3s;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    "
  >
    Update Your Location
  </button>
</form>';
    // Step 1: After JS sets cookies, PHP should detect and fetch address
    if (isset($_COOKIE['latitude'], $_COOKIE['longitude'], $_GET['location_updated'])) {
        $lat = floatval($_COOKIE['latitude']);
        $lon = floatval($_COOKIE['longitude']);

        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon";
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: PHP/" . phpversion() . "\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);
            $loc = $data['display_name'] ?? 'Unknown Location';

            // Set final location cookie
            setcookie("location", $loc, time() + 31556926, "/", "", false, true);
            $_COOKIE['location'] = $loc;
        }

        // Redirect to same page WITHOUT the ?location_updated param
        global $wp;
        $url = home_url($wp->request);
        wp_redirect($url);
        exit();
    }

    // Step 2: If form was submitted, trigger JS to get coordinates
    if (isset($_POST['process'])) {
        ?>
        <script>
            function setCookie(name, value, days) {
                const expires = new Date(Date.now() + days * 864e5).toUTCString();
                document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/";
            }
    
            function updateUserLocation(lat, lon) {
                setCookie("latitude", lat, 1);
                setCookie("longitude", lon, 1);
                if (!window.location.search.includes("location_updated=1")) {
                    window.location.href = window.location.href.split('?')[0] + "?location_updated=1";
                }
            }
    
            function fetchByIP() {
                fetch("https://ipinfo.io/json?token=e29a08767990bb")
                    .then(res => res.json())
                    .then(data => {
                        const loc = data.loc.split(',');
                        updateUserLocation(loc[0], loc[1]);
                    });
            }
    
            if (!document.cookie.includes("latitude") || !document.cookie.includes("longitude")) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        pos => updateUserLocation(pos.coords.latitude, pos.coords.longitude),
                        err => fetchByIP(),
                        { timeout: 5000 }
                    );
                } else {
                    fetchByIP();
                }
            }
        </script>
        <?php
    }

    return ob_get_clean();
}
*/

/*
// DISPLAY CURRENT LOCATION
add_shortcode('map_current_location', 'map_current_location_shortcode');

function map_current_location_shortcode() {
    $lat = floatval($_COOKIE['latitude'] ?? 0);
    $lon = floatval($_COOKIE['longitude'] ?? 0);
    //$lat = number_format($lat, 4);
    //$lon = number_format($lon, 4);
    $loc = $_COOKIE['location'];
    if ($loc==''){
        $loc = 'Please update your location';
    }
    $ret = 'Location (' . $lat . ',' . $lon . ')<br>';
    //$ret .= '<b>' . $loc . '</b><br>';
    return $ret;


    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon";
    // Set User-Agent to avoid 403 error
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP/" . phpversion() . "\r\n"
        ]
    ];
    $context = stream_context_create($opts);

    // Fetch the data
    $response = file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        $ret = "Unable to get address.";
    }
    $data = json_decode($response, true);


    

}    

*/

/*

// FORCE RELOAD COOKIES
add_shortcode('force_location_cookie', function () {
    ob_start();
    ?>
    <script>
        function setCookie(name, value, days) {
            const expires = new Date(Date.now() + days * 864e5).toUTCString();
            document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/";
        }

        function updateUserLocation(lat, lon) {
            setCookie("latitude", lat, 1);
            setCookie("longitude", lon, 1);
            if (!window.location.search.includes("location_updated=1")) {
                window.location.href = window.location.href.split('?')[0] + "?location_updated=1";
            }
        }

        function fetchByIP() {
            fetch("https://ipinfo.io/json?token=e29a08767990bb")
                .then(res => res.json())
                .then(data => {
                    const loc = data.loc.split(',');
                    updateUserLocation(loc[0], loc[1]);
                });
        }

        if (!document.cookie.includes("latitude") || !document.cookie.includes("longitude")) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => updateUserLocation(pos.coords.latitude, pos.coords.longitude),
                    err => fetchByIP(),
                    { timeout: 5000 }
                );
            } else {
                fetchByIP();
            }
        }
    </script>
    <?php
    return ob_get_clean();
});


function get_nearby_mosques($lat,$lon) {
    if (!isset($_POST['lat']) || !isset($_POST['lon'])) {
        wp_send_json_error("Location data is missing.");
    }

    //$_COOKIE['latitude'] = $lat;
    //$_COOKIE['longitude'] = $lon;
    
    //$lat = sanitize_text_field($_POST['lat']);
    //$lon = sanitize_text_field($_POST['lon']);
    $radius = 5000; // 5km search radius

    // Overpass API Query to Fetch Nearby Mosques
    $query = "[out:json];node[\"amenity\"=\"place_of_worship\"][\"religion\"=\"muslim\"](around:$radius, $lat, $lon);out;";
    $overpass_url = "https://overpass-api.de/api/interpreter?data=" . urlencode($query);

    $response = wp_remote_get($overpass_url);
    if (is_wp_error($response)) {
        wp_send_json_error("Error fetching mosque data.");
    }

    $mosques = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($mosques['elements']) || empty($mosques['elements'])) {
        wp_send_json_error("No mosques found nearby.");
    }

    global $wpdb;
    $table_name = "wp_jet_cct_masjid"; // Change this to your JetEngine CCT table name

    $results = [];
    foreach ($mosques['elements'] as $mosque) {
        $mosque_lat = $mosque['lat'];
        $mosque_lon = $mosque['lon'];
        $name = isset($mosque['tags']['name']) ? $mosque['tags']['name'] : "Unnamed Mosque";

        // Reverse Geocode to Get Full Address, Postcode, City, Country
        $nominatim_url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$mosque_lat&lon=$mosque_lon";
        $nominatim_response = wp_remote_get($nominatim_url);
        $address = (!is_wp_error($nominatim_response)) ? json_decode(wp_remote_retrieve_body($nominatim_response), true)['address'] : [];

        $street = isset($address['road']) ? $address['road'] : "";
        $postcode = isset($address['postcode']) ? $address['postcode'] : "";
        $city = isset($address['city']) ? $address['city'] : (isset($address['town']) ? $address['town'] : (isset($address['village']) ? $address['village'] : ""));
        $country = isset($address['country']) ? $address['country'] : "";
        $full_address = trim("$street, $postcode, $city, $country", ", ");

        // Check if mosque already exists in JetEngine CCT
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE latitude = %s AND longitude = %s", $mosque_lat, $mosque_lon));
 
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                [
                    "name" => $name,
                    "address" => $street,
                    "postcode" => $postcode,
                    "city" => $city,
                    "country" => $country,
                    "latitude" => $mosque_lat,
                    "longitude" => $mosque_lon
                ],
                ["id" => $existing->id]
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                [
                    "name" => $name,
                    "address" => $street,
                    "postcode" => $postcode,
                    "city" => $city,
                    "country" => $country,
                    "latitude" => $mosque_lat,
                    "longitude" => $mosque_lon
                ]
            );
        }

        $results[] = [
            "name" => $name,
            "address" => $full_address,
            "postcode" => $postcode,
            "city" => $city,
            "country" => $country,
            "lat" => $mosque_lat,
            "lon" => $mosque_lon
        ];
    }

    wp_send_json_success($results);
}

add_action('wp_ajax_get_nearby_mosques', 'get_nearby_mosques');
add_action('wp_ajax_nopriv_get_nearby_mosques', 'get_nearby_mosques');

function list_nearby_mosques() {
    ob_start();
    ?>
    <div id="mosque-list">Fetching nearby mosques...</div>
    <script>
        function fetchMosques(lat, lon) {
            let formData = new FormData();
            formData.append("action", "get_nearby_mosques");
            formData.append("lat", lat);
            formData.append("lon", lon);

            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let mosques = data.data;
                    let output = "<ul>";
                    mosques.forEach(mosque => {
                        output += `<li><strong>${mosque.name}</strong><br>
                                   ${mosque.address}<br>
                                   <b>Postcode:</b> ${mosque.postcode}<br>
                                   <b>City:</b> ${mosque.city}<br>
                                   <b>Country:</b> ${mosque.country}<br>
                                   //<a href="https://www.openstreetmap.org/?mlat=${mosque.lat}&mlon=${mosque.lon}" target="_blank">View on Map</a>
                                   https://maps.googleapis.com/maps/api/staticmap?center=LAT,LNG&zoom=14&size=600x300&maptype=roadmap&markers=color:red|LAT,LNG&key=YOUR_API_KEY
                                   </li>`;
                    });
                    output += "</ul>";
                    document.getElementById("mosque-list").innerHTML = output;
                } else {
                    document.getElementById("mosque-list").innerHTML = "<p>No mosques found nearby.</p>";
                }
            })
            .catch(error => console.error("Error:", error));
        }

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        fetchMosques(position.coords.latitude, position.coords.longitude);
                    },
                    function() {
                        document.getElementById("mosque-list").innerHTML = "Location access denied.";
                    }
                );
            } else {
                document.getElementById("mosque-list").innerHTML = "Geolocation is not supported.";
            }
        }

        document.addEventListener("DOMContentLoaded", getLocation);
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('nearby_mosques', 'list_nearby_mosques');






// DISPLAY MAP
function display_google_map($atts) {
    global $wpdb;
    
    $atts = shortcode_atts(
        ['item_id' => ''], // Default value
        $atts,
        'google_map'
    );

    if (empty($atts['item_id'])) {
        return "<p>❌ Error: Please provide an item_id.</p>";
    }

    $table_name = "wp_jet_cct_masjid"; // Change table name if needed
    $item_id = intval($atts['item_id']);
 
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE _ID = %d", 
        $item_id
    ), ARRAY_A);

    if (!$record) {
        return "<p>⚠️ No record found for item_id: <b>{$item_id}</b></p>";
    }

    // DISPLAY INFO
    $output .= "<h3>{$record['name']}</h3>";
    $output .= "{$record['address']}</br>";
    $output .= "{$record['postcode']} ";
    $output .= "{$record['city']}</br>";
    $output .= "<b>{$record['country']}</b?</br><br><br>";

    $lat = $record['latitude'];
    $lon = $record['longitude'];

    $gmap = "https://www.google.com/maps?q={$lat},{$lon}(Mosque)";
    $waze = "https://www.waze.com/ul?ll={$lat},{$lon}&navigate=yes";
    
    $button_style = "display: inline-flex; align-items: center; text-decoration: none; padding: 10px 15px; font-size: 16px; border-radius: 8px; color: #fff; margin-right: 10px;";
    $google_style = "background-color: #4285F4;"; // Google Blue
    $waze_style = "background-color: #03A9F4;"; // Waze Blue
    
    $buttons = '<div style="margin-top:10px;">
        <a href="'.$gmap.'" target="_blank" style="'.$button_style.$google_style.'">
            <img src="https://upload.wikimedia.org/wikipedia/commons/9/9b/Google_Maps_logo.svg" width="20" height="20" style="margin-right:8px;"> Google Maps
        </a>
        <a href="'.$waze.'" target="_blank" style="'.$button_style.$waze_style.'">
            <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Waze_logo.svg" width="20" height="20" style="margin-right:8px;"> Waze
        </a>
    </div>';
    
    $output .= $buttons;
    
    // DISPLAY MAP
    $api_key = GOOGLE_STATIC_MAPS_API_KEY; // resolved in keys.php
     
    // Google Static Map URL
    $map_url = "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lon}&zoom=14&size=600x600&markers=color:red%7Clabel:M%7C{$lat},{$lon}&key={$api_key}";

    // Display the image
    $output .= "<img src='$map_url' alt='Google Static Map' style='max-width:100%; border:1px solid #ccc;'>";

    return $output;
    
}
add_shortcode('google_map', 'display_google_map');


// COPY DATA
function copy_jetengine_cct_data() {
    global $wpdb;
    
    $source_table = $wpdb->prefix . "jet_cct_mosque"; // Source Table
    //$source_table = "jet-cct-mosque"; // Source Table
    $target_table = $wpdb->prefix . "jet_cct_masjid"; // Target Table

    // Fetch all records from the source table
    $records = $wpdb->get_results("SELECT * FROM $source_table", ARRAY_A);

    if (!$records) {
        return "<p>No data found in the mosque table.</p>";
    }

   
    
    $count = 0;
    foreach ($records as $record) {
        // Check if record already exists in masjid table (Prevent duplicates)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $target_table WHERE latitude = %s AND longitude = %s", 
            $record['latitude'], $record['longitude']
        ));

        if (!$exists) {
            // Insert into masjid table
            $wpdb->insert(
                $target_table,
                [
                    'name' => $record['name'],
                    'latitude' => $record['latitude'],
                    'longitude' => $record['longitude'],
                    'address' => $record['address'],
                    'city' => $record['city'],
                    'country' => $record['country']
                ]
            );
            $count++;
        }
    }

    return "<p>✅ Successfully copied <b>$count</b> mosques to the masjid table.</p>";
}

*/

// Register Shortcode
//add_shortcode('copy_mosques_to_masjid', 'copy_jetengine_cct_data');






