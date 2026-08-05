<?php
/**

 * 1. niz_business_header
 * 2. niz_mfa_business_info
 * 3. niz_business_carousel
 * 4. niz_mfa_nearest_business
 * 5. niz_mfa_load_more_businesses_handler
 * 6. niz_mfa_local_business
 * 7. niz_mfa_load_local_businesses_handler
 * 8. mfa_claim_business_listing
 */
 
 
if (!defined('ABSPATH')) exit; 

// ============================================
// 1. HEADER IMAGE
// ============================================
add_shortcode('niz_business_header', function($atts) {
    // 1. Define the fallback default image
    $default_url = "https://cdn.staging.masjid4all.com/business/business-owner.webp";
    
    // 2. Check if the current post/page has a featured image
    if ( has_post_thumbnail() ) {
        // Grab the 'full' size URL of the featured image
        $img_url = get_the_post_thumbnail_url(null, 'full');
    } else {
        // Otherwise, use the default CDN image
        $img_url = $default_url;
    }

    // 3. Output the image tag
    return '<img src="' . esc_url($img_url) . '" class="niz-mfa-header-img" style="width: 100%; height: auto; object-fit: cover;">';
});


// ============================================
// 2. BUSINESS INFO
// ============================================
add_shortcode('niz_mfa_business_info', function() {
    
    global $wpdb;
    
    $post_id = get_the_ID();
    $item_id = get_post_meta( $post_id, 'item_id', true );
    $ret = '';
    
    if ( empty( $item_id ) ) {
        return $ret;
    }

    // 1. Determine if the current user is authorized to see this
    $is_authorized = false;

    if ( is_user_logged_in() ) {
        $current_user_id = get_current_user_id();
        
        // Check if Admin or Editor
        if ( current_user_can('editor') || current_user_can('administrator') ) {
            $is_authorized = true;
        } else {
           // Check if user is the listing owner in the CCT table
            $owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
            $is_owner = $wpdb->get_var( $wpdb->prepare( 
                "SELECT user_id FROM {$owner_table} WHERE post_id = %d AND user_id = %d LIMIT 1", 
                $post_id, 
                $current_user_id 
            ) );
            
            if ( $is_owner ) {
                $is_authorized = true;
            }
        }
    }

    // 2. If not authorized, hide the message completely
    if ( ! $is_authorized ) {
        echo '<style>
        .owner {
            display: none !important;
        }
        </style>';
        
    }
    
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);
    $content = get_the_content();
    $name    = mfa_get_business_field($item_id, 'name');
    $status  = mfa_get_business_field($item_id, 'listing_status');
    $address = mfa_get_business_field($item_id, 'address');
    $city    = mfa_get_business_field($item_id, 'city');
    $country = mfa_get_business_field($item_id, 'country');
    $intro   = mfa_get_business_field($item_id, 'introduction');
        
    if ($status == 'Rejected'){
        $content  = '<h1>' . $name . '</h1>';
        $content .= '<h3>Status : <b>Rejected</b></h3>';
        $content .= '<p>After review, we are unable to approve this business listing for inclusion in our directory at this time. Listings may be rejected if the information provided is incomplete, inaccurate, does not meet our directory guidelines, or cannot be sufficiently verified.</p>';
        $content .= '<p>If you are the business owner and believe your business should be included in our directory, please contact us with any additional information or supporting documents. We will be happy to review your submission again.</p>';
    }elseif ($status == 'Pending'){
        $content  = '<h1>' . $name . '</h1>';
        $content .= '<h3>Status : <b>Pending</b></h3>';
        $content .= '<p>This business is currently under review by our team to ensure that the information provided is accurate, complete, and meets our directory guidelines.</p>';
        $content .= '<p>The review process may take some time, depending on the volume of submissions. Once the review is complete, your listing will either be approved and published or we may contact you if additional information is required.</p>';
        $content .= '<p>If you are the business owner and would like to provide supporting documents or update your submission, please contact us. We appreciate your patience and look forward to reviewing your listing.</p>';
        
    }elseif ( $status === 'New' || $status === ''){
        $content  = '<h1>' . $name . '</h1>';

        $address = mfa_get_business_field($item_id, 'address');
        $city    = mfa_get_business_field($item_id, 'city');
        $country = mfa_get_business_field($item_id, 'country');
        $intro   = mfa_get_business_field($item_id, 'introduction');
    
        $content .= $address . '<br>City : ' . $city . '<br>Country : ' . $country . '<br>';
        $content .= '<h3>Status : <b>New</b></h3>';
        $content .= '<p>If you are the owner, please Claim Your Business after updating </p>';
        $content .= '<p>Please click the button below to update the content.</p>';

        // ✅ ADD SHORTCODE HERE (NOT echo)
        $content .= do_shortcode('[niz_business_ai_updater]');
        
    }else{
        // If Approved
        $biz_status  = mfa_get_business_field($item_id, 'business_status');
        if ($biz_status==''){
            //$content = 'Updating..<br><br>' . $content;
            //business_update_content( $post_id );
            
        }
    }
    
    return $content;
});



// ============================================
// 3. BUSINESS CAROUSEL
// ============================================
add_shortcode('niz_business_carousel', function($atts) {
    $atts = shortcode_atts(['ids' => ''], $atts);
    if (empty($atts['ids'])) return '<p style="text-align:center; color:#94a3b8;">Please provide post IDs.</p>';

    $id_array = array_map('intval', explode(',', $atts['ids']));
    $id_array = array_slice($id_array, 0, 10); 

    $query = new WP_Query([
        'post_type'           => 'business',
        'post__in'            => $id_array,
        'orderby'             => 'post__in',
        'posts_per_page'      => 10,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) return '<p style="text-align:center; color:#94a3b8;">No matching businesses found.</p>';

    wp_enqueue_style('swiper-cdn-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
    wp_enqueue_script('swiper-cdn-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);
    
    wp_enqueue_style('niz-business-css', plugin_dir_url(__FILE__) . '../assets/css/business-v2.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-business-js', plugin_dir_url(__FILE__) . '../assets/js/business.js', ['swiper-cdn-js'], NIZ_MFA_VERSION, true);
 
    $unique_carousel_id = 'niz-biz-swiper-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <div class="niz-carousel-wrapper">
        <div id="<?php echo esc_attr($unique_carousel_id); ?>" class="swiper nizBusinessSwiper" data-post-count="<?php echo esc_attr($query->post_count); ?>">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $img = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://cdn.staging.masjid4all.com/media/placeholder.webp';
                    $excerpt = wp_trim_words(get_the_excerpt(), 18, '...');
                ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="niz-business-card">
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
});
 
// ============================================
// 4. NEAREST BUSINESS DIRECTORY
// ============================================
add_shortcode('niz_mfa_nearest_business', function($atts) {
    global $wpdb;
    
    // Accept user-defined columns, default to 3
    $atts = shortcode_atts(['columns' => '3'], $atts);
    $cols = intval($atts['columns']);
    if ($cols < 1 || $cols > 4) $cols = 3;

    $table = $wpdb->prefix . "jet_cct_business";
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $current_business_id = 0;
    
    // Set default filter bar class
    $filter_class = 'niz-business-filter-bar';

    // Detect if we are on a Business post to grab its ID and hide it
    // ALSO: Add 'is-business-post' class to force the 2-row layout
    if ($post_type === 'business') {
        $current_business_id = get_post_meta($post_id, 'item_id', true);
        $filter_class .= ' is-business-post';
    }

    $country_list = get_transient('niz_mfa_biz_country_list');
    if (false === $country_list) {
        $country_list = $wpdb->get_col("SELECT DISTINCT country FROM $table WHERE country IS NOT NULL AND country != '' ORDER BY country ASC");
        set_transient('niz_mfa_biz_country_list', $country_list, 12 * HOUR_IN_SECONDS);
    }
    if (empty($country_list)) $country_list = ['Malaysia'];

    wp_enqueue_style('niz-business-css', plugin_dir_url(__FILE__) . '../assets/css/business-v2.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-business-js', plugin_dir_url(__FILE__) . '../assets/js/business.js', [], NIZ_MFA_VERSION, true);

    // GENERATE A UNIQUE WIDGET ID
    $widget_id = 'niz-biz-widget-' . wp_generate_password(6, false);

    ob_start();
    ?>
    
    <style>
        @media (min-width: 768px) {
            #<?php echo $widget_id; ?> #business-list {
                display: grid !important;
                grid-template-columns: repeat(<?php echo $cols; ?>, 1fr) !important;
            }
        }
    </style>

    <div id="<?php echo esc_attr($widget_id); ?>" class="niz-business-wrapper" 
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-current-id="<?php echo esc_attr($current_business_id); ?>">
         
        <div class="<?php echo esc_attr($filter_class); ?>">
            <div class="niz-business-input-group">
                <label for="niz-business-search-input">Search Business</label>
                <input type="text" id="niz-business-search-input" placeholder="Type name or address...">
            </div>
            <div class="niz-business-input-group">
                <label for="niz-business-country-select">Country</label>
                <select id="niz-business-country-select">
                    <?php foreach ($country_list as $c_name): ?>
                        <option value="<?php echo esc_attr($c_name); ?>"><?php echo esc_html($c_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div id="business-list" class="niz-grid-canvas">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Detecting your location...
            </div>
        </div>
        <button id="load-more-business-btn" style="display: none;">Load More Businesses</button>
    </div>
    <?php
    return ob_get_clean();
});


// ============================================
// 5. Load More Businesses (Global Directory)
// ============================================
add_action('wp_ajax_niz_mfa_load_more_businesses', 'niz_mfa_load_more_businesses_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_more_businesses', 'niz_mfa_load_more_businesses_handler');

function niz_mfa_load_more_businesses_handler() {
    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_business";

    $latitude  = isset($_POST['lat']) ? floatval($_POST['lat']) : 3.1390;
    $longitude = isset($_POST['lng']) ? floatval($_POST['lng']) : 101.6869;
    $country   = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'Malaysia';
    $search    = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset    = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit     = isset($_POST['limit']) ? intval($_POST['limit']) : 9;

    $where_clauses = ["country = %s"];
    $params = [$country];

    if (!empty($search)) {
        $where_clauses[] = "(name LIKE %s OR address LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $where_sql = implode(" AND ", $where_clauses);

    // FIX 1: Added cct_single_post_id to the SELECT query
    $sql = $wpdb->prepare("
        SELECT _ID, cct_single_post_id, name, introduction, address, page_url, listing_status,
        ( 6371 * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance
        FROM {$table}
        WHERE {$where_sql}
        ORDER BY 
            CASE listing_status
                WHEN 'Premium' THEN 1
                WHEN 'Verified' THEN 2
                WHEN 'Approved' THEN 3
                WHEN 'Pending' THEN 4
                WHEN 'New' THEN 5
                ELSE 6 
            END ASC,
            distance ASC
        LIMIT %d OFFSET %d
    ", array_merge([$latitude, $longitude, $latitude], $params, [$limit, $offset]));

    $slice = $wpdb->get_results($sql, ARRAY_A);
    if (empty($slice)) { echo ''; wp_die(); }

    foreach ($slice as $b) {
        // FIX 2: Changed $w to $b
        $post_id = intval($b['cct_single_post_id']);
        $img = get_the_post_thumbnail_url($post_id, 'medium'); 
        $permalink = get_permalink($post_id) ?: '#';
        
        $km_distance   = floatval($b['distance']);
        $mile_distance = $km_distance * 0.621371;
        $destination   = !empty($b['page_url']) ? esc_url($b['page_url']) : '#';
         
        $status_badge = !empty($b['listing_status']) ? esc_html($b['listing_status']) : '';
        ?>
        <a href="<?php echo $destination; ?>" class="web-card-link">
            <div class="web-card">
                <?php if (!empty($img)) : ?>
                    <div class="web-card-img-box">
                        <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr($b['name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="web-card-content">
                    <h3 class="web-name"><?php echo esc_html($b['name'] ?? 'Business'); ?></h3>
                     <?php if ($status_badge === 'Premium') : ?>
                        <p class="web-intro"><?php echo esc_html($b['introduction'] ?? ''); ?></p>
                    <?php endif; ?>
                    <?php if ($status_badge <> 'Premium') : ?>
                        <p class="web-intro"><?php echo esc_html($b['address'] ?? ''); ?></p>
                    <?php endif; ?>
                    
                </div>
                <div class="web-card-footer">
                    <span class="business-distance-metric">
                        📍 <?php echo ($km_distance < 0.1) ? 'Under 100m' : number_format($km_distance, 2) . ' km (' . number_format($mile_distance, 2) . ' mi)'; ?>
                    </span>
                    <?php if ($status_badge === 'Premium') : ?>
                        <span class="status-badge" style="font-size: 11px; background: #fff; color: #666; padding: 2px 6px; border-radius: 4px; margin-bottom: 8px; display: inline-block;">
                                ⭐ <?php echo $status_badge; ?> ⭐
                        </span>
                    <?php endif; ?>
                    <?php if ($status_badge === 'Verified') : ?>
                        <span class="status-badge" style="font-size: 11px; background: #fff; color: #666; padding: 4px 10px; border-radius: 10px; margin-bottom: 8px; display: inline-block;">
                                ⭐ <?php echo $status_badge; ?> ⭐
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        
        
        
        <?php
    }
    wp_die();
}

// ============================================
// 6. LOCAL MOSQUE NEAR BUSINESS CPT)
// ============================================
add_shortcode('niz_mfa_local_business', function() {
    global $wpdb;
    
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $item_id = get_post_meta($post_id, 'item_id', true);
    
    $target_lat = '3.1390';
    $target_lng = '101.6869';
    $target_name = get_the_title($post_id);

    // Detect if we are on a Mosque or City Custom Post Type
    if ($post_type === 'masjid' && $item_id) {
        $table = $wpdb->prefix . "jet_cct_mosque";
        $coords = $wpdb->get_row($wpdb->prepare("SELECT latitude, longitude FROM {$table} WHERE _ID = %d LIMIT 1", $item_id));
        if ($coords && !empty($coords->latitude) && !empty($coords->longitude)) {
            $target_lat = $coords->latitude;
            $target_lng = $coords->longitude;
        }
    } elseif ($post_type === 'city') {
        $city_lat = get_post_meta($post_id, 'latitude', true);
        $city_lng = get_post_meta($post_id, 'longitude', true);
        if (!empty($city_lat) && !empty($city_lng)) {
            $target_lat = $city_lat;
            $target_lng = $city_lng;
        }
    }

    wp_enqueue_style('niz-business-css', plugin_dir_url(__FILE__) . '../assets/css/business-v2.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-business-js', plugin_dir_url(__FILE__) . '../assets/js/business.js', [], NIZ_MFA_VERSION, true);

    $unique_local_id = 'niz-local-biz-' . wp_generate_password(6, false);
    ob_start();
    ?>
    <div id="<?php echo esc_attr($unique_local_id); ?>" class="niz-local-wrapper"
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-biz-lat="<?php echo esc_attr($target_lat); ?>"
         data-biz-lng="<?php echo esc_attr($target_lng); ?>"
         data-biz-name="<?php echo esc_attr($target_name); ?>">
        
        <div class="local-business-list-canvas niz-grid-canvas">
            <div class="loading-placeholder" style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Finding businesses near <?php echo esc_html($target_name); ?>...
            </div>
        </div>
        <button class="load-more-local-btn" style="display: none; margin: 40px auto 20px auto; font-size: 14px; padding: 12px 28px; background: #ffffff; color: #1e3a5f; border: 2px solid #1e3a5f; border-radius: 8px; cursor: pointer; font-weight: 700;">Load More Businesses</button>
    </div>
    <?php
    return ob_get_clean();
});


// ============================================
// 7. Load Local Businesses (Near Mosque or City)
// ============================================
add_action('wp_ajax_niz_mfa_load_local_businesses', 'niz_mfa_load_local_businesses_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_local_businesses', 'niz_mfa_load_local_businesses_handler');

function niz_mfa_load_local_businesses_handler() {
    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_business";

    $bLat   = isset($_POST['lat']) ? floatval($_POST['lat']) : 3.1390;
    $bLng   = isset($_POST['lng']) ? floatval($_POST['lng']) : 101.6869;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit  = isset($_POST['limit']) ? intval($_POST['limit']) : 6;

    // Added cct_single_post_id to SELECT for the featured image
    $sql = $wpdb->prepare("
        SELECT _ID, cct_single_post_id, name, address, introduction, page_url, listing_status,
        ( 6371 * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance
        FROM {$table}
        ORDER BY 
            CASE listing_status
                WHEN 'Premium' THEN 1
                WHEN 'Verified' THEN 2
                WHEN 'Approved' THEN 3
                WHEN 'Pending' THEN 4
                WHEN 'New' THEN 5
                ELSE 6 
            END ASC,
            distance ASC
        LIMIT %d OFFSET %d
    ", [$bLat, $bLng, $bLat, $limit, $offset]);

    $slice = $wpdb->get_results($sql, ARRAY_A);
    if (empty($slice)) { echo ''; wp_die(); }

    foreach ($slice as $b) {
        $post_id      = intval($b['cct_single_post_id']);
        $img          = get_the_post_thumbnail_url($post_id, 'medium'); 
        $km_distance  = floatval($b['distance']);
        $mile_distance = $km_distance * 0.621371;
        $destination  = !empty($b['page_url']) ? esc_url($b['page_url']) : '#';
        $status_badge = !empty($b['listing_status']) ? esc_html($b['listing_status']) : '';
        ?>
        <a href="<?php echo $destination; ?>" class="web-card-link">
            <div class="web-card">
                <?php if (!empty($img)) : ?>
                    <div class="web-card-img-box">
                        <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr($b['name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="web-card-content">
                    <h3 class="web-name"><?php echo esc_html($b['name'] ?? 'Business'); ?></h3>
                     <?php if ($status_badge === 'Premium') : ?>
                        <p class="web-intro"><?php echo esc_html($b['introduction'] ?? ''); ?></p>
                    <?php endif; ?>
                    <?php if ($status_badge <> 'Premium') : ?>
                        <p class="web-intro"><?php echo esc_html($b['address'] ?? ''); ?></p>
                    <?php endif; ?>
                </div>
                <div class="web-card-footer">
                    <span class="business-distance-metric">
                        📍 <?php echo ($km_distance < 0.1) ? 'Under 100m' : number_format($km_distance, 2) . ' km (' . number_format($mile_distance, 2) . ' mi)'; ?>
                    </span>
                    <?php if ($status_badge === 'Premium') : ?>
                        <span class="status-badge" style="font-size: 11px; background: #daa520; color: #fff; padding: 2px 6px; border-radius: 4px; margin-bottom: 8px; display: inline-block;">
                                <?php echo $status_badge; ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($status_badge === 'Verified') : ?>
                        <span class="status-badge" style="font-size: 11px; background: #daa520; color: #fff; padding: 2px 6px; border-radius: 4px; margin-bottom: 8px; display: inline-block;">
                                <?php echo $status_badge; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php
    }
    wp_die();
}
////////////////////////////
// 8. CLAIM BUSINESS
///////////////////////////
add_shortcode('mfa_claim_business_listing', 'mfa_claim_business_listing_shortcode');

function mfa_claim_business_listing_shortcode() {
    // 1. Check if the user is logged in
    if ( ! is_user_logged_in() ) {
        return '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
                    Please <a href="/wp-login.php" style="font-weight: bold; text-decoration: underline;">log in</a> to claim this business.
                </div>';
    }

    global $wpdb;
    $post_id         = get_the_ID();
    $post_type       = get_post_type($post_id);
    $current_user    = wp_get_current_user();
    $current_user_id = $current_user->ID;
    
    $table_name = $wpdb->prefix . 'jet_cct_listing_owner';
    
    // 2. Check if the business is already claimed
    $existing_claim = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM `$table_name` WHERE post_id = %d LIMIT 1", $post_id));
    
    if ( $existing_claim ) {
        
        // CHECK: Is the current user the owner?
        if ( $existing_claim->user_id == $current_user_id ) {
            return '<div style="background-color: #d4edda; color: #155724; padding: 20px; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <strong style="font-size: 16px;">👋 Welcome back, ' . esc_html($current_user->display_name) . '!</strong><br><br>
                        You are the verified manager of this business.<br><br>
                        <a href="/member/business/" style="display: inline-block; background: #006B3E; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            Manage Your Business
                        </a>
                    </div>';
        }
        
        // If claimed by someone else:
        $owner_info = get_userdata($existing_claim->user_id);
        $owner_name = $owner_info ? $owner_info->display_name : 'another user';
        $owner_id = $owner_info ? $owner_info->id: 'another user';
        return '<div style="background-color: #e2e3e5; color: #383d41; padding: 15px; border-left: 4px solid #6c757d; border-radius: 4px; margin-bottom: 20px;">
                    This business has been claimed by <strong>User ' . esc_html($owner_id) . '.</strong><br>
                    If you believe this is incorrect or would like to dispute this claim, please contact our support team for assistance.
                </div>';
    }
    
    // 3. Handle Form Submission
    $error_msg = '';
    if ( isset($_POST['niz_submit_claim']) && isset($_POST['niz_claim_nonce']) && wp_verify_nonce($_POST['niz_claim_nonce'], 'niz_claim_action_' . $post_id) ) {
        
        if ( empty($_POST['niz_claim_confirm']) ) {
            $error_msg = '<div style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 15px;">Please check the authorization box to proceed.</div>';
        } else {
            // Insert the claim into your JetEngine CCT table
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'post_type'   => $post_type,
                    'post_id'     => $post_id,
                    'user_id'     => $current_user_id,
                    'cct_created' => current_time('mysql')
                ),
                array('%s', '%d', '%d', '%s')
            );
            
            if ( $inserted ) {
                return '<div style="background-color: #d4edda; color: #155724; padding: 20px; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <strong style="font-size: 16px;">🎉 Listing Successfully Claimed!</strong><br><br>
                            You are now verified as the manager of this business.<br><br>
                            <a href="/member/business/" style="display: inline-block; background: #006B3E; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                Please go to Member\'s Page to update your business
                            </a>
                        </div>';
            } else {
                $error_msg = '<div style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 15px;">Database Error: Could not process your claim. Please try again later.</div>';
            }
        }
    }
    
    // 4. Generate The Interface (If not claimed and not submitted)
    $user_phone = get_user_meta($current_user_id, 'user_phone', true);
    $display_phone = !empty($user_phone) ? esc_html($user_phone) : '<em style="color:#999;">Not provided in profile</em>';
    
    $output = '<div style="background: #ffffff; border: 1px solid #e5e5e5; padding: 25px; border-radius: 8px; margin-bottom: 20px; max-width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">';
    $output .= '<h4 style="margin-top: 0; color: #006B3E; display: flex; align-items: center; gap: 8px;">🛡️ Claim This Business</h4>';
    $output .= '<p style="font-size: 14px; color: #555; margin-bottom: 20px;">Verify your details below to take official control of this listing.</p>';
    
    if ( !empty($error_msg) ) {
        $output .= $error_msg;
    }
    
    // User Info Display Card
    $output .= '<div style="background: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">';
    $output .= '<div style="margin-bottom: 5px;"><strong>Claiming as:</strong> ' . esc_html($current_user->display_name) . '</div>';
    $output .= '<div><strong>Phone:</strong> ' . $display_phone . '</div>';
    $output .= '</div>';
    
    // Claim Form
    $output .= '<form method="POST" action="">';
    $output .= wp_nonce_field('niz_claim_action_' . $post_id, 'niz_claim_nonce', true, false);
    
    $output .= '<label style="display: flex; gap: 12px; align-items: flex-start; margin-bottom: 20px; font-size: 14px; cursor: pointer; color: #444;">';
    $output .= '<input type="checkbox" name="niz_claim_confirm" required style="margin-top: 3px; width: 16px; height: 16px; cursor: pointer;">';
    $output .= '<span style="line-height: 1.4;">Are you the business owner or authorised to manage this business?</span>';
    $output .= '</label>';
    
    $output .= '<button type="submit" name="niz_submit_claim" style="background: #006B3E; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; transition: background 0.3s ease;">';
    $output .= 'Claim this business';
    $output .= '</button>';
    $output .= '</form>';
    $output .= '</div>';
    
    return $output;
}


// CLAIM LISTING
add_shortcode('niz_member_claimed_listings', 'niz_member_claimed_listings_shortcode');

function niz_member_claimed_listings_shortcode() {
    // 1. Ensure the user is logged in
    if ( ! is_user_logged_in() ) {
        return '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px;">
                    Please <a href="/wp-login.php" style="font-weight: bold; text-decoration: underline;">log in</a> to view your claimed businesses.
                </div>';
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $table_name      = $wpdb->prefix . 'jet_cct_listing_owner';
    $output          = '';

    // 2. Process "Unclaim" Request (Must happen BEFORE we fetch the list)
    if ( isset($_POST['niz_unclaim_submit']) && isset($_POST['niz_unclaim_post_id']) ) {
        // Verify the nonce for security
        if ( isset($_POST['niz_unclaim_nonce']) && wp_verify_nonce($_POST['niz_unclaim_nonce'], 'niz_unclaim_action_' . $_POST['niz_unclaim_post_id']) ) {
            
            $unclaim_post_id = intval($_POST['niz_unclaim_post_id']);
            
            // Delete the exact record matching this user and post
            $deleted = $wpdb->delete(
                $table_name,
                array(
                    'post_id' => $unclaim_post_id,
                    'user_id' => $current_user_id
                ),
                array('%d', '%d')
            );

            if ( $deleted ) {
                $output .= '<div style="background-color: #d4edda; color: #155724; padding: 12px 15px; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 20px;">
                                ✅ Listing successfully unclaimed and removed from your account.
                            </div>';
            } else {
                $output .= '<div style="background-color: #f8d7da; color: #721c24; padding: 12px 15px; border-left: 4px solid #dc3545; border-radius: 4px; margin-bottom: 20px;">
                                ⚠️ Could not unclaim the listing. Please try again later.
                            </div>';
            }
        }
    }

    // 3. Fetch the User's Claimed Listings
    $claimed_items = $wpdb->get_results(
        $wpdb->prepare("SELECT post_id, post_type FROM `$table_name` WHERE user_id = %d ORDER BY cct_created DESC", $current_user_id)
    );

    // 4. Generate the UI
    $output .= '<div class="niz-claimed-listings-wrapper" style="max-width: 800px; margin: 0 auto;">';

    if ( empty($claimed_items) ) {
        $output .= '<div style="background: #f9f9f9; border: 1px solid #e5e5e5; padding: 30px; text-align: center; border-radius: 8px; color: #666;">
                        You have not claimed any business or website yet.
                    </div>';
    } else {
        foreach ( $claimed_items as $item ) {
            $post_id    = $item->post_id;
            $post_type  = $item->post_type;
            
            // Check if the post actually still exists in WordPress
            $post_title = get_the_title($post_id);
            if ( empty($post_title) ) continue; 
            
            $post_link  = get_permalink($post_id);
            $type_label = str_replace('_', ' ', $post_type); // Clean up the post type name nicely

            // Build a clean flexbox card for each listing
            $output .= '<div style="background: #ffffff; border: 1px solid #e5e5e5; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02); flex-wrap: wrap; gap: 15px;">';
            
            // Left side: Type, Title, and Link
            $output .= '<div style="flex: 1; min-width: 250px;">';
            $output .= '<span style="font-size: 11px; font-weight: bold; color: #666; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; display: inline-block;">' . esc_html($type_label) . '</span><br>';
            $output .= '<a href="' . esc_url($post_link) . '" target="_blank" style="font-size: 18px; font-weight: bold; color: #006B3E; text-decoration: none; display: inline-block; margin-bottom: 5px;">' . esc_html($post_title) . '</a>';
            $output .= '</div>';

            // Right side: Action Buttons Wrapper (Update & Unclaim)
            $output .= '<div style="display: flex; gap: 10px; align-items: center; flex-shrink: 0;">';
            
            // Update Info Button (Opens the post in the same tab)
            $output .= '<a href="' . esc_url($post_link) . '" style="background: #006B3E; color: #fff; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: bold; text-decoration: none; border: 1px solid #006B3E; transition: background 0.2s;" onmouseover="this.style.background=\'#00522f\';" onmouseout="this.style.background=\'#006B3E\';">Update Business</a>';

            // Unclaim Button & Form
            $output .= '<form method="POST" action="" onsubmit="return confirm(\'Do you wish to unclaim this business?\');" style="margin: 0;">';
            $output .= wp_nonce_field('niz_unclaim_action_' . $post_id, 'niz_unclaim_nonce', true, false);
            $output .= '<input type="hidden" name="niz_unclaim_post_id" value="' . esc_attr($post_id) . '">';
            $output .= '<button type="submit" name="niz_unclaim_submit" style="background: #fff; color: #dc3545; border: 1px solid #dc3545; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background=\'#dc3545\'; this.style.color=\'#fff\';" onmouseout="this.style.background=\'#fff\'; this.style.color=\'#dc3545\';">Unclaim</button>';
            $output .= '</form>';
            
            $output .= '</div>'; // Close Action Buttons Wrapper
            
            $output .= '</div>'; // Close Card
        }
    }

    $output .= '</div>'; // Close Wrapper

    return $output;
}


// UPDATE CONTENT
function business_update_content( $post_id ) {
    global $wpdb;

    // 1. Validate Post & Get CCT Item ID
    if ( empty( $post_id ) || get_post_type( $post_id ) !== 'business' ) {
        return new WP_Error( 'invalid_post', 'Invalid Post ID or Post Type.' );
    }

    $item_id = get_post_meta( $post_id, 'item_id', true );
    
    if ( empty( $item_id ) ) {
        return new WP_Error( 'missing_cct', 'No linked JetEngine CCT item found for this post.' );
    }

    // 2. Fetch Existing Business Data from CCT Table
    $table_name = $wpdb->prefix . 'jet_cct_business';
    $business   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE _ID = %d", $item_id ), ARRAY_A );

    if ( ! $business ) {
        return new WP_Error( 'cct_not_found', 'Business record not found in the database.' );
    }

    // Decode opening hours if they are stored as JSON strings
    $opening_hours = !empty($business['opening_hours']) ? json_decode($business['opening_hours'], true) : [];

    // 3. Prepare Payload for Perplexity AI
    $raw_info = array(
        'name'         => $business['name'],
        'address'      => $business['address'],
        'place_id'     => $business['place_id'],
        'latitude'     => $business['latitude'],
        'longitude'    => $business['longitude'],
        'email'        => $business['email'],
        'website'      => $business['website'],
        'phone'        => $business['phone'],
        'whatsapp'     => $business['whatsapp'],
        'city'         => $business['city'],
        'country'      => $business['country'],
        'introduction' => $business['introduction'],
        'fb'           => $business['fb'],
        'linkedin'     => $business['linkedin'],
        'insta'        => $business['insta'],
        'tiktok'       => $business['tiktok'],
        'rating'       => $business['rating'],
        'rating_count' => $business['rating_count'],
        'opening_hours'=> $opening_hours
    );
   
    $old_status = $business['listing_status'];
    $name = $business['name'];

    $clean_info = array_filter($raw_info, function($value) {
        return $value !== '' && $value !== null && $value !== [];
    });

    // 4. Call the AI Function
    if ( ! function_exists( 'mfa_business_perplexity' ) ) {
        return new WP_Error( 'missing_function', 'mfa_business_perplexity function is missing.' );
    }

    $json_raw = mfa_business_perplexity( $clean_info ); 
    $ai_data  = json_decode( $json_raw, true );

    // 5. Validate AI Response
    if ( ! $ai_data || isset( $ai_data['error'] ) || empty( $ai_data['content'] ) ) {
        $error_reason = isset( $ai_data['reason'] ) ? $ai_data['reason'] : 'API Parsing Failure';
        error_log( 'Business AI Update Failed for Post ID ' . $post_id . ': ' . $error_reason );
        return new WP_Error( 'ai_failure', 'AI generation failed: ' . $error_reason );
    }

    // 6. Update CCT Table Data
    //$cct_update_data = array(
    //    'business_status' => 'Updated'
    //);

    $listing_status = $ai_data['listingStatus'];

    if ( ! empty( $ai_data['listingStatus'] ) ) {
        $cct_update_data['listing_status'] = sanitize_text_field( $ai_data['listingStatus'] );
    }
    if ( ! empty( $ai_data['category'] ) ) {
        $cct_update_data['category'] = sanitize_text_field( $ai_data['category'] );
    }
    if ( ! empty( $ai_data['introduction'] ) ) {
        $cct_update_data['introduction'] = sanitize_text_field( $ai_data['introduction'] );
    }

    $wpdb->update(
        $table_name,
        $cct_update_data,
        array( '_ID' => $item_id )
    );

    // 7. Update WordPress Post Content & SEO Meta
    $post_update = array(
        'ID'           => $post_id,
        'post_content' => wp_kses_post( $ai_data['content'] )
    );
    wp_update_post( $post_update );

    if ( ! empty( $ai_data['title'] ) ) {
        update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $ai_data['title'] ) );
    }
    if ( ! empty( $ai_data['metaDescription'] ) ) {
        update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $ai_data['metaDescription'] ) );
    }
    if ( ! empty( $ai_data['keywords'] ) ) {
        update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $ai_data['keywords'] ) );
    }
    
    // Barakah Point, if Approved
    if ($old_status === 'New' || $old_status === 'Pending' || empty($old_status)){
        if ($listing_status == 'Approved'){
            // Give Baraqah Point
            $author_id = get_post_field('post_author', $post_id);
            $desc = 'Add Business : ' . $name;
            niz_user_add_points($author_id, $desc, 10);
        }
    }
    
    // 8. Return Success
    return array(
        'success' => true,
        'message' => $name . ' successfully updated.',
        'status'  => isset($cct_update_data['listing_status']) ? $cct_update_data['listing_status'] : 'Pending'
    );
    
}


// =====================================================================
// 1. FRONTEND SHORTCODE: [niz_business_ai_updater]
// =====================================================================
add_shortcode('niz_business_ai_updater', 'niz_business_ai_updater_shortcode');

function niz_business_ai_updater_shortcode() {
    $post_id = get_the_ID();
    
    // Only display this button on single business posts
    if (get_post_type($post_id) !== 'business') {
        return ''; 
    }

    $nonce    = wp_create_nonce('niz_ai_update_nonce_' . $post_id);
    $ajax_url = admin_url('admin-ajax.php');

    ob_start();
    ?>
    <div class="niz-ai-update-wrapper" style="margin: 20px 0;">
        <button type="button" class="niz-ai-update-btn" 
                data-post-id="<?php echo esc_attr($post_id); ?>" 
                data-nonce="<?php echo esc_attr($nonce); ?>" 
                data-ajaxurl="<?php echo esc_url($ajax_url); ?>" 
                style="background: #e6a800; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; transition: all 0.3s ease;">
            🔄 Update Content
        </button>
        <div class="niz-ai-update-msg" style="margin-top: 10px; font-size: 14px; font-weight: bold;"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find all update buttons on the page (just in case there are multiple)
            const updateBtns = document.querySelectorAll('.niz-ai-update-btn');
            
            updateBtns.forEach(btn => {
                btn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    const postId = this.dataset.postId;
                    const nonce = this.dataset.nonce;
                    const ajaxUrl = this.dataset.ajaxurl;
                    const msgDiv = this.nextElementSibling;
                    const originalText = this.innerText;

                    // UI Loading State
                    this.innerText = 'Generating content. Please wait...';
                    this.disabled = true;
                    this.style.opacity = '0.7';
                    this.style.cursor = 'wait';
                    msgDiv.innerHTML = '';

                    try {
                        // Prepare the payload
                        const formData = new FormData();
                        formData.append('action', 'niz_trigger_ai_update');
                        formData.append('post_id', postId);
                        formData.append('nonce', nonce);

                        // Ping the server
                        const response = await fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();

                        // Handle the result
                        if (result.success) {
                            msgDiv.innerHTML = '<span style="color: #155724; background: #d4edda; padding: 5px 10px; border-radius: 4px;">✅ ' + result.data.message + ' Refreshing page...</span>';
                            this.innerText = 'Success!';
                            this.style.background = '#28a745'; // Turn green
                            
                            // Reload the page to show the new AI content!
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            msgDiv.innerHTML = '<span style="color: #721c24; background: #f8d7da; padding: 5px 10px; border-radius: 4px;">⚠️ ' + (result.data || 'Unknown error occurred.') + '</span>';
                            this.innerText = originalText;
                            this.disabled = false;
                            this.style.opacity = '1';
                            this.style.cursor = 'pointer';
                        }
                    } catch (err) {
                        msgDiv.innerHTML = '<span style="color: #721c24; background: #f8d7da; padding: 5px 10px; border-radius: 4px;">⚠️ Network error. Please check your connection.</span>';
                        this.innerText = originalText;
                        this.disabled = false;
                        this.style.opacity = '1';
                        this.style.cursor = 'pointer';
                    }
                });
            });
        });
    </script>
    <?php
    
    $content = ob_get_clean();

    return $content ;
}


// =====================================================================
// 2. BACKEND AJAX HANDLER (Fires the AI Function)
// =====================================================================
add_action('wp_ajax_niz_trigger_ai_update', 'niz_ajax_trigger_ai_update_handler');
add_action('wp_ajax_nopriv_niz_trigger_ai_update', 'niz_ajax_trigger_ai_update_handler'); // Allows non-logged-in users

function niz_ajax_trigger_ai_update_handler() {
    // Check missing variables
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    // Security check to prevent unauthorized spam clicks
    if ( ! wp_verify_nonce($nonce, 'niz_ai_update_nonce_' . $post_id) ) {
        wp_send_json_error('Security validation failed.');
    }

    if ( empty($post_id) ) {
        wp_send_json_error('Invalid Post ID.');
    }

    // FIRE THE FUNCTION!
    $result = business_update_content($post_id);

    // Read the response from your AI function and return it to the JavaScript
    if ( is_wp_error($result) ) {
        wp_send_json_error( $result->get_error_message() );
    } else {
        wp_send_json_success( array('message' => $result['message']) );
    }
}

?>