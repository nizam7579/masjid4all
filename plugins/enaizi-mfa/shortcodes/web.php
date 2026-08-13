<?php

/**
 * 1. niz_web_header
 * 2. niz_web_carousel'
 * 3. niz_mfa_web_directory
 * 4. niz_mfa_load_more_web_handler
 */

if (!defined('ABSPATH')) exit;

// ============================================
// 1. HEADER IMAGE
// ============================================
add_shortcode('niz_web_header', function($atts) {
    // 1. Define the fallback default image
    $default_url = "https://cdn.masjid4all.com/web/website-owner.webp";

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

add_shortcode( 'niz_mfa_web_status', 'niz_user_web_status_shortcode' );
function niz_user_web_status_shortcode() {
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
        return '';
    }

    // 3. Fetch status and display if not Approved
    $web_table = $wpdb->prefix . 'jet_cct_web';
    $result = $wpdb->get_row( $wpdb->prepare( "SELECT status, status_detail FROM {$web_table} WHERE _ID = %d", $item_id ) );

    if ( $result && $result->status !== 'Approved' ) {
        // 4. Added the 'owner' class to the div wrapper
        $ret = '<div class="owner" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; font-size:14px; line-height:1.5;">';
        $ret .= '<strong>Status:</strong> ' . esc_html( $result->status ) . '<br>' . esc_html($result->status_detail) . '<br><br>';
        $ret .= '<b>Our team will manually review and assess the website for Islamic compliance and update the status soon.</b>';
        $ret .= '</div>';
    }

    return $ret;
}

// ============================================
// 2. WEB CAROUSEL SHORTCODE
// ============================================
add_shortcode('niz_web_carousel', function($atts) {
    $atts = shortcode_atts(['ids' => ''], $atts);
    if (empty($atts['ids'])) return '<p style="text-align:center; color:#94a3b8;">Please provide post IDs.</p>';

    $id_array = array_map('intval', explode(',', $atts['ids']));
    $id_array = array_slice($id_array, 0, 10);

    $query = new WP_Query([
        'post_type'           => 'web',
        'post__in'            => $id_array,
        'orderby'             => 'post__in',
        'posts_per_page'      => 10,
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) return '<p style="text-align:center; color:#94a3b8;">No matching websites found.</p>';

    wp_enqueue_style('swiper-cdn-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
    wp_enqueue_script('swiper-cdn-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);

    wp_enqueue_style('niz-web-css', plugin_dir_url(__FILE__) . '../assets/css/web-v3.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-web-js', plugin_dir_url(__FILE__) . '../assets/js/web.js', ['swiper-cdn-js'], NIZ_MFA_VERSION, true);

    $unique_carousel_id = 'niz-web-swiper-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <div class="niz-carousel-wrapper">
        <div id="<?php echo esc_attr($unique_carousel_id); ?>" class="swiper nizWebSwiper" data-post-count="<?php echo esc_attr($query->post_count); ?>">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post();
                    $img = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://cdn.masjid4all.com/media/placeholder.webp';
                    $url = get_post_meta(get_the_ID(), 'url', true);
                ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="niz-web-card">
                            <div class="web-card-img-box"><img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr(get_the_title()); ?>"></div>
                            <div class="web-card-content">
                                <h3 class="niz-card-title"><?php echo esc_html(get_the_title()); ?></h3>
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
// 3. MAIN WEB DIRECTORY
// ============================================
add_shortcode('niz_mfa_web_directory', function($atts) {
    global $wpdb;

    $atts = shortcode_atts(['columns' => '3'], $atts);
    $cols = intval($atts['columns']);
    if ($cols < 1 || $cols > 4) $cols = 3;

    $table = $wpdb->prefix . "jet_cct_web";
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $current_web_id = 0;

    // Set default filter bar class
    $filter_class = 'niz-web-filter-bar';

    // Hide the current post if we are viewing a single web entry
    // ALSO: Add 'is-web-post' class to force the 2-row layout
    if ($post_type === 'web') {
        $current_web_id = get_post_meta($post_id, 'item_id', true);
        $filter_class .= ' is-web-post';
    }

    // Cache country list - Sourced directly from your master Countries CCT table
    $country_list = get_transient('niz_mfa_web_countries_master_list');
    if (false === $country_list) {
        $countries_table = $wpdb->prefix . "jet_cct_countries";

        // Checks the schema to see if your field is named 'country' or 'name' dynamically
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$countries_table} LIKE 'country'");
        $target_column = $column_exists ? 'country' : 'name';

        $country_list = $wpdb->get_col("
            SELECT DISTINCT {$target_column}
            FROM {$countries_table}
            WHERE {$target_column} IS NOT NULL AND {$target_column} != ''
            ORDER BY {$target_column} ASC
        ");

        set_transient('niz_mfa_web_countries_master_list', $country_list, 12 * HOUR_IN_SECONDS);
    }
    if (empty($country_list)) {
        $country_list = ['Global', 'Malaysia'];
    }

    // 12 Universal Categories
    $categories = ["Islamic Resources", "Islamic Bodies", "Government", "Education", "Finance", "Business & Services", "Charity & Zakat", "Health & Wellness", "Family & Lifestyle", "Community & Social", "News & Media", "Technology & Tools", "E-Commerce & Shopping", "Travel & Directory", "Other" ];

    wp_enqueue_style('niz-web-css', plugin_dir_url(__FILE__) . '../assets/css/web-v3.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-web-js', plugin_dir_url(__FILE__) . '../assets/js/web.js', [], NIZ_MFA_VERSION, true);

    $widget_id = 'niz-web-widget-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <style>
        @media (min-width: 768px) {
            #<?php echo $widget_id; ?> #web-list {
                display: grid !important;
                grid-template-columns: repeat(<?php echo $cols; ?>, 1fr) !important;
            }
        }
    </style>

    <div id="<?php echo esc_attr($widget_id); ?>" class="niz-web-wrapper"
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-current-id="<?php echo esc_attr($current_web_id); ?>">

        <div class="<?php echo esc_attr($filter_class); ?>">
            <div class="niz-web-input-group">
                <label>Search Website</label>
                <input type="text" class="niz-web-search" placeholder="Type name or keyword...">
            </div>
            <div class="niz-web-input-group">
                <label>Category</label>
                <select class="niz-web-category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="niz-web-input-group">
                <label>Country</label>
                <select class="niz-web-country">
                    <option value="">Global (All)</option>
                    <?php foreach ($country_list as $c_name): ?>
                        <option value="<?php echo esc_attr($c_name); ?>"><?php echo esc_html($c_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="web-list" class="niz-grid-canvas">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Loading websites...
            </div>
        </div>
        <button class="load-more-web-btn" style="display: none;">Load More</button>
    </div>
    <?php
    return ob_get_clean();
});

// ============================================
// 4. AJAX HANDLER
// ============================================
add_action('wp_ajax_niz_mfa_load_more_web', 'niz_mfa_load_more_web_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_more_web', 'niz_mfa_load_more_web_handler');

function niz_mfa_load_more_web_handler() {
    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_web";

    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $country  = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $offset   = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit    = isset($_POST['limit']) ? intval($_POST['limit']) : 9;
    $exclude  = isset($_POST['current_web_id']) ? intval($_POST['current_web_id']) : 0;

        // Only reviewed listings are shown publicly - New/Pending stay hidden
    // from the directory until approved (2026-08-14, explicit user
    // direction: don't surface unreviewed website listings).
    $where_clauses = ["listing_status IN ('Approved','Verified','Premium')"];
    $params = [];

    if ($exclude > 0) {
        $where_clauses[] = "_ID != %d";
        $params[] = $exclude;
    }
    if (!empty($category)) {
        $where_clauses[] = "category = %s";
        $params[] = $category;
    }
    if (!empty($country)) {
        $where_clauses[] = "country = %s";
        $params[] = $country;
    }
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE %s OR url LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $where_sql = implode(" AND ", $where_clauses);

    // Fetch results with listing_status included, sorted by hierarchy then newest
    $sql = $wpdb->prepare("
        SELECT _ID, cct_single_post_id, name, url, category, introduction, country, listing_status
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
            _ID DESC
        LIMIT %d OFFSET %d
    ", array_merge($params, [$limit, $offset]));

    $slice = $wpdb->get_results($sql, ARRAY_A);
    if (empty($slice)) { echo ''; wp_die(); }

    foreach ($slice as $w) {
        $post_id = intval($w['cct_single_post_id']);
        $img = get_the_post_thumbnail_url($post_id, 'medium'); // Do not force fallback template string here
        $permalink = get_permalink($post_id) ?: '#';

        $intro = !empty($w['introduction'])? esc_html(mb_strimwidth($w['introduction'], 0, 300, '...')) : '';
        $cat_label = !empty($w['category']) ? esc_html($w['category']) : 'Uncategorized';
        $country_label = !empty($w['country']) ? esc_html($w['country']) : 'Global';
        $domain = !empty($w['url']) ? parse_url($w['url'], PHP_URL_HOST) : '';

        // Grab the status to display a badge on the UI
        $status_badge = !empty($w['listing_status']) ? esc_html($w['listing_status']) : '';
        ?>
        <a href="<?php echo esc_url($permalink); ?>" class="web-card-link">
            <div class="web-card">
                <?php if (!empty($img)) : ?>
                    <div class="web-card-img-box">
                        <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr($w['name']); ?>">
                    </div>
                <?php endif; ?>

                <div class="web-card-content">
                    <h3 class="web-name"><?php echo esc_html($w['name']); ?></h3>
                    <span class="web-intro"><?php echo $intro; ?></span>
                </div>
                <div class="web-card-footer">
                    <span class="web-country-metric">🌍 <?php echo $country_label; ?></span>
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


// CLAIM WEBSITE
add_shortcode('mfa_claim_web_listing', 'mfa_claim_web_listing_shortcode');

function mfa_claim_web_listing_shortcode() {
    // 1. Check if the user is logged in
    if ( ! is_user_logged_in() ) {
        return '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
                    Please <a href="/wp-login.php" style="font-weight: bold; text-decoration: underline;">log in</a> to claim this website.
                </div>';
    }

    global $wpdb;
    $post_id         = get_the_ID();
    $post_type       = get_post_type($post_id);
    $current_user    = wp_get_current_user();
    $current_user_id = $current_user->ID;

    $table_name = $wpdb->prefix . 'jet_cct_listing_owner';

    // 2. Check if the website is already claimed
    $existing_claim = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM `$table_name` WHERE post_id = %d LIMIT 1", $post_id));

    if ( $existing_claim ) {

        // CHECK: Is the current user the owner?
        if ( $existing_claim->user_id == $current_user_id ) {
            return '<div style="background-color: #d4edda; color: #155724; padding: 20px; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <strong style="font-size: 16px;">👋 Welcome back, ' . esc_html($current_user->display_name) . '!</strong><br><br>
                        You are the verified manager of this website.<br><br>
                        <a href="/member/business/" style="display: inline-block; background: #006B3E; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            Manage Your Website
                        </a>
                    </div>';
        }

        // If claimed by someone else:
        $owner_info = get_userdata($existing_claim->user_id);
        $owner_name = $owner_info ? $owner_info->display_name : 'another user';
        $owner_id = $owner_info ? $owner_info->id: 'another user';
        return '<div style="background-color: #e2e3e5; color: #383d41; padding: 15px; border-left: 4px solid #6c757d; border-radius: 4px; margin-bottom: 20px;">
                    This website has been claimed by <strong>User ' . esc_html($owner_id) . '.</strong><br>
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
                            You are now verified as the manager of this website.<br><br>
                            <a href="/member/business/" style="display: inline-block; background: #006B3E; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                Please go to Member\'s Page to update your website
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
    $output .= '<h4 style="margin-top: 0; color: #006B3E; display: flex; align-items: center; gap: 8px;">🛡️ Claim This Website</h4>';
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
    $output .= '<span style="line-height: 1.4;">Are you the website owner or authorised to manage this website?</span>';
    $output .= '</label>';

    $output .= '<button type="submit" name="niz_submit_claim" style="background: #006B3E; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; transition: background 0.3s ease;">';
    $output .= 'Claim this website';
    $output .= '</button>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}

/**
 * Triggers an AI content update for an existing Business listing.
 * Does not require a logged-in user or a form submission.
 * * @param int $post_id The WordPress Post ID of the business.
 * @return array|WP_Error Returns success message array or WP_Error on failure.
 */
function web_update_content( $post_id ) {
    global $wpdb;

    // 1. Validate Post & Get CCT Item ID
    if ( empty( $post_id ) || get_post_type( $post_id ) !== 'web' ) {
        return new WP_Error( 'invalid_post', 'Invalid Post ID or Post Type.' );
    }

    $item_id = get_post_meta( $post_id, 'item_id', true );

    if ( empty( $item_id ) ) {
        return new WP_Error( 'missing_cct', 'No linked JetEngine CCT item found for this post.' );
    }

    // 2. Fetch Existing Web Data from CCT Table
    $table_name = $wpdb->prefix . 'jet_cct_web';
    $web   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE _ID = %d", $item_id ), ARRAY_A );

    if ( ! $web ) {
        return new WP_Error( 'cct_not_found', 'Web record not found in the database.' );
    }

    // Decode opening hours if they are stored as JSON strings
    $opening_hours = !empty($web['opening_hours']) ? json_decode($web['opening_hours'], true) : [];

    // 3. Prepare Payload for Perplexity AI
    $raw_info = array(
        'name'         => $web['name'],
        'address'      => $web['address'],
        'email'        => $web['email'],
        'website'      => $web['website'],
        'phone'        => $web['phone'],
        'whatsapp'     => $web['whatsapp'],
        'city'         => $web['city'],
        'country'      => $web['country'],
        'introduction' => $web['introduction'],
        'fb'           => $web['fb'],
        'linkedin'     => $web['linkedin'],
        'insta'        => $web['insta'],
        'tiktok'       => $web['tiktok'],
        'opening_hours'=> $opening_hours
    );


    $old_status = $web['listing_status'];
    $name = $web['name'];
    // Strip out empty values to prevent AI crawler crashes
    $clean_info = array_filter($raw_info, function($value) {
        return $value !== '' && $value !== null && $value !== [];
    });

    // 4. Call the AI Function
    if ( ! function_exists( 'niz_perplexity_web' ) ) {
        return new WP_Error( 'missing_function', 'mfa_web_perplexity function is missing.' );
    }

    $url = 'https://pewarisan.my';
    $json_output = niz_perplexity_web($url);
    $data = json_decode($json_output, true);

    if (!$data || isset($data['error'])) {
        return new WP_Error('api_failure', isset($data['error']) ? $data['error'] : 'Data extraction schema parsing error from Perplexity API.');
    }

    // 3. Structure Ingestion Payload Properties
    $status   = !empty($data['status']) ? $data['status'] : 'Pending';
    $reason   = !empty($data['status_detail']) ? $data['status_detail'] : '';
    $keywords = isset($data['keywords']) ? $data['keywords'] : '';

    $update_fields = [
        'name'          => !empty($data['Name']) ? $data['Name'] : $base_url,
        'category'      => isset($data['Category']) ? $data['Category'] : '',
        'introduction'  => isset($data['Introduction']) ? $data['Introduction'] : '',
        'whatsapp'      => isset($data['whatsapp']) ? $data['whatsapp'] : '',
        'email'         => isset($data['email']) ? $data['email'] : '',
        'status'        => $status,
        'status_detail' => isset($data['status_detail']) ? $data['status_detail'] : '',
        'content'       => isset($data['Content']) ? $data['Content'] : '',
        'country'       => isset($data['country']) ? $data['country'] : '',
        'cct_modified'  => current_time('mysql'),
    ];

    // 4. Update or Insert CCT Record
//    if ($existing_cct_id) {
//        $db_action = $wpdb->update($table_name, $update_fields, ['_ID' => $existing_cct_id]);
//        $record_id = $existing_cct_id;
//    } else {
//        $update_fields['cct_author_id'] = $current_user_id;
//        $update_fields['cct_created']   = current_time('mysql');
//        $db_action = $wpdb->insert($table_name, $update_fields);
//        $record_id = $wpdb->insert_id;
//    }

//    if ($db_action === false) {
//        return new WP_Error('db_error', 'Database write operation execution failure.');
//    }

    // 5. Generate and Link Custom Directory Post Asset Mapping
//    $post_id = niz_create_wordpress_post($record_id, $base_url, $update_fields, $keywords);

    if (is_wp_error($post_id)) {
        if (!$existing_cct_id) { $wpdb->delete($table_name, ['_ID' => $record_id]); }
        return $post_id;
    }

//    $wpdb->update($table_name, ['cct_single_post_id' => $post_id], ['_ID' => $record_id]);

//    if ($is_admin_mode) {
//        return '<div class="alert alert-success">✓ <strong>Processed:</strong> ' . esc_html($base_url) . ' | 📝 <strong>Post ID:</strong> ' . intval($post_id) . '</div>';
//    }

/*

    $msg = '<div class="alert alert-success">';
    if ($status === 'Approved' && function_exists('niz_user_add_points')) {
        $desc = 'Add website - ' . $update_fields['name'];
//        niz_user_add_points($current_user_id, $desc, 10);

        $msg .= '✅ Website successfully analyzed and added to the directory!<br>';
        $msg .= 'Status : ' . esc_html($status) . '<br>';
        $msg .= 'Link : ' . '<a href="' . esc_url(get_permalink($post_id)) . '" target="_blank"><strong>' . esc_html($update_fields['name']) . '</strong></a><br>';

        $msg .= '<br><p style="color:#155724; font-weight:bold;">✨ Congratulations! You earned 10 Barakah points.</p>';
    } else {
        $msg .= '✅ Website NOT ADDED to the directory!<br>';
        $msg .= 'Status : ' . esc_html($status) . '<br>';
        $msg .= 'Reason : ' . esc_html($reason) . '<br>';
        $msg .= 'Link : <a href="' . esc_url(get_permalink($post_id)) . '" target="_blank"><strong>' . esc_html($update_fields['name']) . '</strong></a>';
        $msg .= '<br><p style="color:#155724; font-weight:bold;">You will earn 10 Barakah points if we approve the website.</p>';
    }
    $msg .= '</div>';

    return $msg;
*/


    /*

    $ai_data  = json_decode( $json_raw, true );

    // 5. Validate AI Response
    if ( ! $ai_data || isset( $ai_data['error'] ) || empty( $ai_data['content'] ) ) {
        $error_reason = isset( $ai_data['reason'] ) ? $ai_data['reason'] : 'API Parsing Failure';
        error_log( 'Web AI Update Failed for Post ID ' . $post_id . ': ' . $error_reason );
        return new WP_Error( 'ai_failure', 'AI generation failed: ' . $error_reason );
    }

    // 6. Update CCT Table Data
    $cct_update_data = array(
        'business_status' => 'Updated'
    );

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
            $desc = 'Add Web - ' . $name;
            niz_user_add_points($author_id, $desc, 10);
        }
    }

    */

    // 8. Return Success
    return array(
        'success' => true,
        'message' => $name . ' successfully updated.',
        'status'  => isset($cct_update_data['listing_status']) ? $cct_update_data['listing_status'] : 'Pending'
    );


}


// =====================================================================
// 1. FRONTEND SHORTCODE: [niz_web_ai_updater]
// =====================================================================
add_shortcode('niz_web_ai_updater', 'niz_web_ai_updater_shortcode');

function niz_web_ai_updater_shortcode() {
    $post_id = get_the_ID();
   // Only display this button on single web posts
    if (get_post_type($post_id) !== 'web') {
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
                        formData.append('action', 'niz_web_ai_update');
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
add_action('wp_ajax_niz_web_ai_update', 'niz_ajax_web_ai_update_handler');
add_action('wp_ajax_nopriv_niz_web_ai_update', 'niz_ajax_web_ai_update_handler'); // Allows non-logged-in users

function niz_ajax_web_ai_update_handler() {
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
    //$result = web_update_content($post_id);

    // Read the response from your AI function and return it to the JavaScript
    if ( is_wp_error($result) ) {
        wp_send_json_error( $result->get_error_message() );
    } else {
        wp_send_json_success( array('message' => $result['message']) );
    }
}


?>
