<?php
/**
 *  Mosque Directory & Listings 
 * 1. niz_mfa_masjid_header
 * 2. niz_mfa_mosques_info
 * 3. niz_mfa_masjid_carousel
 * 4. niz_mfa_nearest_mosque
 * 5. niz_mfa_load_more_mosques_handler
 * 6. niz_mfa_local_mosques
 * 7. niz_mfa_load_local_mosques_handler
 */
if (!defined('ABSPATH')) exit;

// ============================================
// 1. HEADER IMAGE
// ============================================
add_shortcode('niz_mfa_masjid_header', function($atts) {
    // 1. Define the fallback default image
    $default_url = "https://cdn.masjid4all.com/mosque/join-community.webp";
    
    // 2. Check if the current post/page has a featured image
    if ( has_post_thumbnail() ) {
        // Grab the 'full' size URL of the featured image
        $img_url = get_the_post_thumbnail_url(null, 'full');
    } else {
        // Otherwise, use the default CDN image
        $img_url = $default_url;
    }

    // 3. Output the image tag
    return '<img src="' . esc_url($img_url) . '" alt="Mosque Header" class="niz-mfa-header-img" style="width: 100%; height: auto; object-fit: cover;">';
});

// ============================================
// 2. MOSQUE DISPLAY INFO (AI UPDATE)
// ============================================
add_shortcode('niz_mfa_mosques_info', function() {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);
    
    // Fallback if function doesn't exist
    $status = function_exists('get_cct_mosque_data') ? get_cct_mosque_data($item_id, 'business_status') : '';
    $content = get_the_content();
    $name    = get_the_title($post_id);
    
    ob_start(); 
    ?>
    <div id="mosque-info-container" class="mosque-info-wrapper">
        <?php if ($status === 'Updated' || $content != '') : ?>
            <div class="mosque-actual-content">
                <?php echo apply_filters('the_content', $content); ?>
            </div>
        <?php else : ?>
            <div class="update-prompt-box" style="padding:15px;border:1px dashed #ccc;border-radius:10px;text-align:center;background:#fafafa;">
                <div id="update-spinner">
                    <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#28a745;"></i><br><br>
                    <b><?= esc_html($name); ?></b><br>
                    <span style="color:#28a745;font-weight:600;">
                        Please wait...<br>We are updating the mosque information.
                    </span>
                </div>
            </div>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var formData = new FormData();
                formData.append('action', 'niz_mfa_mosques_ajax');
                formData.append('post_id', <?php echo $post_id; ?>);
                formData.append('mosque_name', "<?php echo esc_js($name); ?>");

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(response => {
                    if(response.success) location.reload();
                }).catch(err => console.error(err));
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});


// 2. Mosque AI Update Callback
add_action('wp_ajax_mfa_mosques_callback', 'niz_mfa_mosques_callback');
add_action('wp_ajax_nopriv_mfa_mosques_callback', 'niz_mfa_mosques_callback'); // Support logged-out users

function niz_mfa_mosques_callback() {
    // === BOT GUARD: Skip expensive AI calls for bots ===
    if (m4a_is_bot_request()) {
        wp_send_json_success('Skipped (bot)'); // Silent success for bots
        return;
    }
    // ===================================================

    // Check if ID exists
    if (!isset($_POST['post_id'])) {
        wp_send_json_error('Missing Post ID');
    }

    $post_id = intval($_POST['post_id']);
    $item_id = get_post_meta($post_id, 'item_id', true);
    if (!$item_id) {
        wp_send_json_error('Data mapping error.');
    }

    $name     = get_cct_mosque_data($item_id, 'name');
    $address  = get_cct_mosque_data($item_id, 'address');
    $city     = get_cct_mosque_data($item_id, 'city');
    $country  = get_cct_mosque_data($item_id, 'country');
    $latitude = get_cct_mosque_data($item_id, 'latitude');
    $longitude= get_cct_mosque_data($item_id, 'longitude');
    $website  = get_cct_mosque_data($item_id, 'website');
    $place_id = get_cct_mosque_data($item_id, 'place_id');
    $data     = "Name: $name, Address: $address, Latitude: $latitude, Longitude: $longitude, Website: $website, City: $country, Country: $country, PlaceID: $place_id";
    
    $result = mosques_perplexity($data);
    $parsed = json_decode($result, true);
 
    if ( ! is_array( $parsed ) || empty( $parsed['content'] ) ) {
        wp_send_json_error( 'Invalid AI content received.' );
    }
    
    $html_content = $parsed['content']; // <-- use AI content
    $rm_title    = $parsed['title'];
    $rm_excerpt  = $parsed['excerpt'];
    $rm_keywords = $parsed['keywords'];
    $status      = $parsed['status'];
    $country     = $parsed['country'];
    $city        = $parsed['city'];
    $website     = $parsed['website'];
    $email       = $parsed['email'];
    $phone       = $parsed['phone'];
    $whatsapp    = $parsed['whatsapp'];
    
    error_log( 'HTML CONTENT FROM AI: ' . $html_content );
    
    $post_update = wp_update_post([
        'ID'           => $post_id,
        'post_content' => $html_content,
    ], true);
    
    update_post_meta($post_id, 'rank_math_title', $rm_title ?? '');
    update_post_meta($post_id, 'rank_math_description', $rm_excerpt ?? '');
    update_post_meta($post_id, 'rank_math_focus_keyword', $rm_keywords ?? '');

    if ( is_wp_error( $post_update ) ) {
        wp_send_json_error( 'WP Update Failed: ' . $post_update->get_error_message() );
    }
    
    save_cct_mosque_data( $item_id, 'business_status', 'Updated' );
    save_cct_mosque_data( $item_id, 'listing_status', $status );

    if (!empty(trim($country))) {
        save_cct_mosque_data($item_id,'country',sanitize_text_field($country));
    }
    if (!empty(trim($city))) {
        save_cct_mosque_data($item_id,'city',sanitize_text_field($city));
    }    
    if (!empty(trim($website))) {
        save_cct_mosque_data($item_id,'website',sanitize_text_field($website));
    }    
    if (!empty(trim($email))) {
        save_cct_mosque_data($item_id,'email',sanitize_text_field($email));
    }    
    if (!empty(trim($phone))) {
        save_cct_mosque_data($item_id,'phone',sanitize_text_field($phone));
    }    
    if (!empty(trim($whatsapp))) {
        save_cct_mosque_data($item_id,'whatsapp',sanitize_text_field($whatsapp));
    }    

    wp_send_json_success( 'Updated successfully (AI content saved)' );
}

// ============================================
// 3. MASJID CAROUSEL (SWIPER)
// ============================================
add_shortcode('niz_mfa_masjid_carousel', 'niz_masjid_carousel_shortcode');
function niz_masjid_carousel_shortcode($atts) {
    $atts = shortcode_atts(['ids' => ''], $atts);
    if (empty($atts['ids'])) return '<p style="text-align:center; color:#94a3b8;">Please provide post IDs.</p>';

    $id_array = array_map('intval', explode(',', $atts['ids']));
    $id_array = array_slice($id_array, 0, 10);

    $query = new WP_Query([
        'post_type'           => 'masjid',
        'post__in'            => $id_array,
        'orderby'             => 'post__in',
        'posts_per_page'      => 10,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) return '<p style="text-align:center; color:#94a3b8;">No matching mosques found.</p>';

    // Load assets
    wp_enqueue_style('swiper-cdn-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
    wp_enqueue_script('swiper-cdn-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);
    
    // Use plugin_dir_url for correct pathing
    wp_enqueue_style('niz-mosque-css', plugin_dir_url(__FILE__) . '../assets/css/mosque.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-mosque-js', plugin_dir_url(__FILE__) . '../assets/js/mosque.js', ['swiper-cdn-js'], NIZ_MFA_VERSION, true);

    $instance_id = 'niz-swiper-' . wp_generate_password(8, false);
    ob_start();
    ?>
    <div class="niz-carousel-wrapper">
        <div id="<?php echo esc_attr($instance_id); ?>" class="swiper nizMosqueSwiper" data-post-count="<?php echo esc_attr($query->post_count); ?>">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $img = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://cdn.staging.masjid4all.com/media/placeholder.webp';
                    $excerpt = wp_trim_words(get_the_excerpt(), 15, '...');
                ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="niz-mosque-card">
                            <div class="niz-card-img-box"><img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr(get_the_title()); ?>"></div>
                            <div class="niz-card-content">
                                <h3 class="niz-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                                <p class="niz-card-excerpt"><?php echo esc_html($excerpt); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================
// 4. NEAREST MOSQUE (GLOBAL DIRECTORY)
// ============================================
add_shortcode('niz_mfa_nearest_mosque', 'niz_mfa_nearest_mosque_shortcode');

function niz_mfa_nearest_mosque_shortcode($atts) {
    global $wpdb;
    
    // Accept user-defined columns parameter, default to 3 columns on desktop
    $atts = shortcode_atts([
        'columns' => '3'
    ], $atts);
    
    $cols = intval($atts['columns']);
    if ($cols < 1 || $cols > 4) {
        $cols = 3;
    }

    $table = $wpdb->prefix . "jet_cct_mosque";
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $current_mosque_id = 0;
    
    // Set default filter bar class
    $filter_class = 'niz-filter-bar';

    // Detect if we are on a single mosque post to pass its database tracking ID
    // ALSO: Add 'is-masjid-post' class to force the 2-row layout
    if ($post_type === 'masjid') {
        $current_mosque_id = get_post_meta($post_id, 'item_id', true);
        $filter_class .= ' is-masjid-post';
    }
    
    // Use WordPress Transients to cache the country list for 12 hours. Prevents DB spam.
    $country_list = get_transient('niz_mfa_country_list');
    if (false === $country_list) {
        $country_list = $wpdb->get_col("SELECT DISTINCT country FROM $table WHERE country IS NOT NULL AND country != '' AND listing_status IN ('Approved','Active') ORDER BY country ASC");
        set_transient('niz_mfa_country_list', $country_list, 12 * HOUR_IN_SECONDS);
    }
    if (empty($country_list)) {
        $country_list = ['Malaysia'];
    }

    wp_enqueue_style('niz-mosque-css', plugin_dir_url(__FILE__) . '../assets/css/mosque.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-mosque-js', plugin_dir_url(__FILE__) . '../assets/js/mosque.js', [], NIZ_MFA_VERSION, true);

    // Generate isolated token layout wrapper ID
    $widget_id = 'niz-mosque-widget-' . wp_generate_password(6, false);

    ob_start();
    ?>
    
    <style>
        @media (min-width: 768px) {
            #<?php echo $widget_id; ?> #mosque-list {
                display: grid !important;
                grid-template-columns: repeat(<?php echo $cols; ?>, 1fr) !important;
            }
        }
    </style>

    <div id="<?php echo esc_attr($widget_id); ?>" class="niz-directory-wrapper" 
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-current-id="<?php echo esc_attr($current_mosque_id); ?>">
         
        <div class="<?php echo esc_attr($filter_class); ?>">
            <div class="niz-input-group">
                <label for="niz-search-input">Search Mosque</label>
                <input type="text" id="niz-search-input" placeholder="Type name or address...">
            </div>
            <div class="niz-input-group">
                <label for="niz-country-select">Country</label>
                <select id="niz-country-select">
                    <?php foreach ($country_list as $c_name): ?>
                        <option value="<?php echo esc_attr($c_name); ?>"><?php echo esc_html($c_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div id="mosque-list" class="niz-grid-canvas">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Detecting your location...
            </div>
        </div>
        <button id="load-more-btn" style="display:none;">Load More Mosques</button>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================
// 5. Load More Mosques (Global Directory)
// ============================================
add_action('wp_ajax_niz_mfa_load_more_mosques', 'niz_mfa_load_more_mosques_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_more_mosques', 'niz_mfa_load_more_mosques_handler');

function niz_mfa_load_more_mosques_handler() {
    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_mosque";

    // 1. Read directly from the JS AJAX POST (No Sessions!)
    $latitude  = isset($_POST['lat']) ? floatval($_POST['lat']) : 3.1390;
    $longitude = isset($_POST['lng']) ? floatval($_POST['lng']) : 101.6869;
    $country   = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'Malaysia';
    $search    = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset    = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit     = isset($_POST['limit']) ? intval($_POST['limit']) : 9;
    $exclude   = isset($_POST['current_mosque_id']) ? intval($_POST['current_mosque_id']) : 0;

    $where_clauses = ["country = %s"];
    $params = [$country];

    if ($exclude > 0) {
        $where_clauses[] = "_ID != %d";
        $params[] = $exclude;
    }

    if (!empty($search)) {
        $where_clauses[] = "(name LIKE %s OR address LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    // Ensure we only show approved/active listings
    $where_clauses[] = "listing_status IN ('Approved','Active')";

    $where_sql = implode(" AND ", $where_clauses);

    // 2. SQL Haversine Formula (Calculates distance at the database level for extreme speed)
    // 6371 is Earth's radius in KM.
    $sql = $wpdb->prepare("
        SELECT _ID, cct_single_post_id, name, address, page_url,
        ( 6371 * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance
        FROM {$table}
        WHERE {$where_sql}
        ORDER BY distance ASC
        LIMIT %d OFFSET %d
    ", array_merge([$latitude, $longitude, $latitude], $params, [$limit, $offset]));

    $slice = $wpdb->get_results($sql, ARRAY_A);

    if (empty($slice)) { echo ''; wp_die(); }

    // 3. Render HTML
    foreach ($slice as $m) {
        $post_id = intval($m['cct_single_post_id']);
        $img = get_the_post_thumbnail_url($post_id, 'medium'); // Do not force fallback template string here
        $permalink = get_permalink($post_id) ?: '#';
        $km = floatval($m['distance']);
        $mile = $km * 0.621371;
        ?>
        <a href="<?php echo esc_url($m['page_url'] ?? '#'); ?>" class="mosque-card-link">
            <div class="mosque-card">
                <!-- 1. HIDE IMAGE CONTAINER COMPLETELY IF NO IMAGE IS SET -->
                <?php if (!empty($img)) : ?>
                    <div class="web-card-img-box">
                        <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr($m['name']); ?>">
                    </div>
                <?php endif; ?>
                <div class="mosque-card-content">
                    <div class="mosque-meta-body
                        <h3 class="mosque-name"><?php echo esc_html($m['name'] ?? 'Masjid'); ?></h3>
                        <p class="mosque-excerpt"><?php echo esc_html($m['address'] ?? ''); ?></p>
                    </div>
                    <div class="mosque-card-footer">
                        <span class="mosque-distance-metric">
                            📍 <?php echo ($km < 0.1) ? 'Under 100m' : number_format($km, 2) . ' km (' . number_format($mile, 2) . ' miles)'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </a>
        <?php
    }
    wp_die();
}


// ============================================
// 6. LOCAL BUSINESSES (NEAR MOSQUE OR CITY)
// ============================================
add_shortcode('niz_mfa_local_mosques', 'niz_mfa_local_mosques_shortcode');
function niz_mfa_local_mosques_shortcode() {
    global $wpdb;
    
    $post_id   = get_the_ID();
    $post_type = get_post_type($post_id);
    $item_id   = get_post_meta($post_id, 'item_id', true);
    
    $target_lat = '3.1390';
    $target_lng = '101.6869';
    $target_name = get_the_title($post_id);

    // UNIVERSAL LOCATION DETECTOR: Gets the exact lat/lng based on whatever page we are on
    $coords = null;
    
    if ($post_type === 'business' && $item_id) {
        $table = $wpdb->prefix . "jet_cct_business";
        $coords = $wpdb->get_row($wpdb->prepare("SELECT latitude, longitude FROM {$table} WHERE _ID = %d LIMIT 1", $item_id));
    //} elseif ($post_type === 'business' && $item_id) {
    //    $table = $wpdb->prefix . "jet_cct_mosque";
    //    $coords = $wpdb->get_row($wpdb->prepare("SELECT latitude, longitude FROM {$table} WHERE _ID = %d LIMIT 1", $item_id));
    } elseif ($post_type === 'city') {
        $coords = (object) [
            'latitude' => get_post_meta($post_id, 'latitude', true),
            'longitude' => get_post_meta($post_id, 'longitude', true)
        ];
    }

    // Apply exact coordinates if successfully found
    if (!empty($coords) && !empty($coords->latitude) && !empty($coords->longitude)) {
        $target_lat = $coords->latitude;
        $target_lng = $coords->longitude;
    }

    // Enqueue both Mosque JS (for logic) and Business CSS (for the cards)
    wp_enqueue_style('niz-business-css', plugin_dir_url(__FILE__) . '../assets/css/business-v2.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-mosque-js', plugin_dir_url(__FILE__) . '../assets/js/mosque.js', [], NIZ_MFA_VERSION, true);

    $unique_id = 'niz-mosque-dir-' . wp_generate_password(6, false);
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr($unique_id); ?>" class="niz-mosque-nearby-wrapper" 
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-biz-lat="<?php echo esc_attr($target_lat); ?>"
         data-biz-lng="<?php echo esc_attr($target_lng); ?>"
         data-biz-name="<?php echo esc_attr($target_name); ?>">

        <div class="niz-local-mosque-list niz-grid-canvas">
            <div class="niz-mosque-loading-placeholder" style="grid-column: 1/-1; text-align: center; padding: 40px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Finding businesses near <?php echo esc_html($target_name); ?>...
            </div>
        </div>
        
        <button class="niz-local-load-more-btn" style="display: none;">Load More Mosques</button>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================
// 7. Load Local Businesses (Near Mosque/City)
// ============================================
add_action('wp_ajax_niz_mfa_load_local_mosques', 'niz_mfa_load_local_mosques_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_local_mosques', 'niz_mfa_load_local_mosques_handler');

function niz_mfa_load_local_mosques_handler() {
    global $wpdb;
    
    // CHANGED: We are now pulling from the Business table!
    $table = $wpdb->prefix . "jet_cct_mosque";

    $bLat   = isset($_POST['lat']) ? floatval($_POST['lat']) : 3.1390;
    $bLng   = isset($_POST['lng']) ? floatval($_POST['lng']) : 101.6869;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit  = isset($_POST['limit']) ? intval($_POST['limit']) : 6;

    $where_clauses = ["listing_status IN ('Approved','Active')"];
    $params = [];

    if (!empty($search)) {
        // Also check category for businesses
        $where_clauses[] = "(name LIKE %s OR address LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }
 
    $where_sql = implode(" AND ", $where_clauses);

    // SQL Haversine Formula for Local Search
    $sql = $wpdb->prepare("
        SELECT _ID, cct_single_post_id, name, address, page_url,
        ( 6371 * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance
        FROM {$table}
        WHERE {$where_sql}
        ORDER BY distance ASC
        LIMIT %d OFFSET %d
    ", array_merge([$bLat, $bLng, $bLat], $params, [$limit, $offset]));

    $slice = $wpdb->get_results($sql, ARRAY_A);

    if (empty($slice)) { echo ''; wp_die(); }

    foreach ($slice as $m) {
        $km = floatval($m['distance']);
        $m_post_id = intval($m['cct_single_post_id'] ?? 0);
        $m_img = ($m_post_id ? get_the_post_thumbnail_url($m_post_id, 'medium') : '') ?: 'https://cdn.masjid4all.com/mosque/join-community.webp';
        ?>
        <!-- CHANGED: Outputting Business Cards instead of Mosque Cards -->
        <a href="<?php echo esc_url($m['page_url'] ?? '#'); ?>" class="business-card-link">
            <div class="business-card">
                <div class="web-card-img-box">
                    <img src="<?php echo esc_url($m_img); ?>" loading="lazy" alt="<?php echo esc_attr($m['name'] ?? 'Mosque'); ?>">
                </div>
                <div class="business-card-content">
                    <div class="business-meta-body">
                        <h3 class="business-name"><?php echo esc_html($m['name'] ?? 'Business Name'); ?></h3>
                        <p class="business-excerpt">
                            <?php if(!empty($m['category'])) echo '<strong>' . esc_html($m['category']) . '</strong><br>'; ?>
                            <?php echo esc_html($m['address'] ?? ''); ?>
                        </p>
                    </div>
                    <div class="business-card-footer">
                        <span class="business-distance-metric">📍 <?php echo ($km < 0.1) ? 'Under 100m' : number_format($km, 2) . ' km'; ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php
    }
    wp_die();
}


// ============================================
// BATCH UPDATE MOSQUE LISTINGS & CONTENT (AUTO-REDIRECT)
// Shortcode: [niz_batch_update_mosques batch_size="2500"]
// ============================================
add_shortcode('niz_batch_update_mosques', function($atts) {
    // SECURITY: Only let administrators run this heavy script
    if (!current_user_can('administrator')) {
        return 'You do not have permission to run this script.';
    }

    // Attempt to bypass PHP timeouts for this execution
    if (function_exists('set_time_limit')) {
        set_time_limit(0); 
    }

    // Set batch size (default 2500 is safe for most hosting. 10k might timeout)
    $atts = shortcode_atts(['batch_size' => '2500'], $atts);
    $batch_size = intval($atts['batch_size']);

    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_business";
    
    // Get the current offset from the URL (starts at 0)
    $offset = isset($_GET['business_offset']) ? intval($_GET['business_offset']) : 0;

    // Fetch the current batch of records
    $query = $wpdb->prepare(
        "SELECT _ID, cct_single_post_id, name, address, city, country FROM {$table} ORDER BY _ID ASC LIMIT %d OFFSET %d",
        $batch_size,
        $offset
    );
    $business = $wpdb->get_results($query);

    if (empty($business)) {
        return "<h3><strong>All batches complete! 100% finished.</strong></h3><br><a href='" . esc_url(remove_query_arg('business_offset')) . "'>Start over from 0</a>";
    }

    $count_rejected = 0;
    $count_approved = 0;
    $count_pending  = 0;

    // Suspend caching during the loop to prevent memory leaks on huge sites
    wp_suspend_cache_addition(true);

    foreach ($business as $business) {
        $post_id = $business->cct_single_post_id;

        // 1. If cct_single_post_id is empty, null, or 0
        if (empty($post_id)) {
            $wpdb->update(
                $table,
                ['listing_status' => 'Rejected'],
                ['_ID' => $business->_ID],
                ['%s'],
                ['%d']
            );
            $count_rejected++;
            continue;
        }

        // Get the associated WordPress Post
        $post = get_post($post_id);

        // Fallback: If the post was deleted but the ID was still in the CCT
        if (!$post) {
            $wpdb->update(
                $table,
                ['listing_status' => 'Rejected'],
                ['_ID' => $business->_ID],
                ['%s'],
                ['%d']
            );
            $count_rejected++;
            continue;
        }

        // Strip HTML tags to count the actual text characters
        $content_length = mb_strlen(strip_tags($post->post_content));

        // 2. If content is greater than 500 chars
        if ($content_length > 500) {
            $wpdb->update(
                $table,
                ['listing_status' => 'Approved'],
                ['_ID' => $business->_ID],
                ['%s'],
                ['%d']
            );
            $count_approved++;
        } 
        // 3. Else (Content <= 500 chars)
        else {
            // Update CCT listing status to Pending
            $wpdb->update(
                $table,
                ['listing_status' => 'Pending'],
                ['_ID' => $business->_ID],
                ['%s'],
                ['%d']
            );

            // Construct the new post content
            $new_content  = "<h1>" . esc_html($business->name) . "</h1>\n";
            $new_content .= esc_html($business->address) . "<br>\n";
            $new_content .= "City : " . esc_html($business->city) . "<br>\n";
            $new_content .= "Country : " . esc_html($business->country) . "<br>\n";
            $new_content .= "<p>We will review and update this business soon</p>";

            // Update the actual WordPress post
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $new_content
            ]);

            $count_pending++;
        }
    }

    // Turn caching back on
    wp_suspend_cache_addition(false);

    $records_processed = count($business);
    $next_offset = $offset + $batch_size;
    
    // Build output UI
    $output = sprintf(
        "<div style='background:#f8fafc; padding:20px; border:1px solid #cbd5e1; border-radius:8px; max-width:600px;'>
            <h3 style='margin-top:0;'>Processing business...</h3>
            <p><strong>Processed rows:</strong> %d to %d</p>
            <ul>
                <li><strong>Approved:</strong> %d</li>
                <li><strong>Pending (Content Updated):</strong> %d</li>
                <li><strong>Rejected (No Post):</strong> %d</li>
            </ul>",
        $offset + 1,
        $offset + $records_processed,
        $count_approved, $count_pending, $count_rejected
    );

    // If we fetched the full batch size, there are likely more remaining
    if ($records_processed == $batch_size) {
        $next_url = add_query_arg('business_offset', $next_offset);
        
        $output .= "<p style='color:#006B3E; font-weight:bold;'>Moving to next batch automatically in 2 seconds...</p>";
        $output .= "<a href='" . esc_url($next_url) . "' style='background:#006B3E; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px;'>Force Next Batch Now</a>";
        
        // JavaScript to automatically reload the page with the next offset
        $output .= "<script>
            setTimeout(function() {
                window.location.href = '" . esc_js($next_url) . "';
            }, 2000);
        </script>";
    } else {
        $output .= "<h3 style='color:#006B3E;'><strong>All Done! 100% Complete.</strong></h3>";
        $output .= "<a href='" . esc_url(remove_query_arg('business_offset')) . "'>Restart Process</a>";
    }

    $output .= "</div>";

    return $output;
});
