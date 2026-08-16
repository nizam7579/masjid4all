<?php

/*

// Register custom REST API route for JetEngine CCT mosques
function register_nearest_mosques_api() {
    register_rest_route('custom/v1', '/mosques', array(
        'methods' => 'GET',
        'callback' => 'get_nearest_mosques',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_nearest_mosques_api');

// Callback function to get nearest mosques
function get_nearest_mosques(WP_REST_Request $request) {
    // Ensure latitude and longitude are set in cookies
    if (!isset($_COOKIE['latitude']) || !isset($_COOKIE['longitude'])) {
        return new WP_REST_Response('Location data missing', 400);
    }

    $user_lat = (float)$_COOKIE['latitude'];
    $user_lng = (float)$_COOKIE['longitude'];

    // Fetch mosques from CCT (replace 'mosque' with your CCT slug)
    $args = array(
        'post_type' => 'mosque', // Your CCT slug
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'latitude',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => 'longitude',
                'compare' => 'EXISTS',
            ),
        ),
    );

    $query = new WP_Query($args);
    $mosques = [];

    // Loop through mosques and calculate distance
    while ($query->have_posts()) {
        $query->the_post();
        $latitude = get_post_meta(get_the_ID(), 'latitude', true);
        $longitude = get_post_meta(get_the_ID(), 'longitude', true);
 
        // Calculate distance using Haversine formula
        $distance = haversine($user_lat, $user_lng, (float)$latitude, (float)$longitude);

        $mosques[] = [
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance' => round($distance, 1),
        ];
    }

    // Sort mosques by distance (ascending)
    usort($mosques, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    return new WP_REST_Response($mosques, 200);
}

// Haversine formula to calculate distance in kilometers
function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // in kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

///////////////////////////
function update_jetengine_cct_entry($cct_slug, $item_id, $data) {
    if (!function_exists('jet_engine')) {
        error_log("JetEngine is not active.");
        return new WP_Error('jetengine_missing', 'JetEngine plugin not loaded.');
    }
 
    $cct = jet_engine()->cct->get_cct($cct_slug);

    if (!$cct) {
        error_log("CCT slug '$cct_slug' not found.");
        return new WP_Error('cct_not_found', "CCT '$cct_slug' not found.");
    }

    // Flush cache to make sure fresh values are picked up
    $cct->clear_items_cache();

    // Double check _ID exists in items
    $items = $cct->get_items();
    $found = false;

    foreach ($items as $item) {
        if ((int)$item['_ID'] === (int)$item_id) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        error_log("Item ID $item_id not found in CCT '$cct_slug'");
        return new WP_Error('item_not_found', "Item ID not found.");
    }

    // Proceed to update
    $result = $cct->update_item($item_id, $data);

    if (is_wp_error($result)) {
        error_log("Failed to update CCT item: " . $result->get_error_message());
        return $result;
    }

    error_log("CCT item with ID $item_id updated successfully.");
    return true;
}

*/
