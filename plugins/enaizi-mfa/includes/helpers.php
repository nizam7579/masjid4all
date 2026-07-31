<?php
// Helper functions used across multiple shortcodes

if (!defined('ABSPATH')) exit;

// Location cookie handler
if (!defined('ABSPATH')) exit;

// =======================================================
// 1. CORE GEOHASH CALCULATOR ENGINE (Unified)
// =======================================================
function niz_direct_post_geohash($lat, $lng, $precision = 9) {
    $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    $lat_interval = array(-90.0, 90.0);
    $lng_interval = array(-180.0, 180.0);
    $geohash = ''; $is_even = true; $bit = 0; $ch = 0;

    while (strlen($geohash) < $precision) {
        if ($is_even) {
            $mid = ($lng_interval[0] + $lng_interval[1]) / 2;
            if ($lng > $mid) { $ch |= (1 << (4 - $bit)); $lng_interval[0] = $mid; } 
            else { $lng_interval[1] = $mid; }
        } else {
            $mid = ($lat_interval[0] + $lat_interval[1]) / 2;
            if ($lat > $mid) { $ch |= (1 << (4 - $bit)); $lat_interval[0] = $mid; } 
            else { $lat_interval[1] = $mid; }
        }
        $is_even = !$is_even;
        if ($bit < 4) { $bit++; } 
        else { $geohash .= $base32[$ch]; $bit = 0; $ch = 0; }
    }
    return $geohash;
}

// =======================================================
// 2. JETENGINE CCT FORM INTERCEPTOR
// =======================================================
add_action('wp_ajax_jet_cct_update_item', 'niz_force_geohash_into_post', 1);
add_action('wp_ajax_jet_cct_insert_item', 'niz_force_geohash_into_post', 1);
add_action('admin_init', 'niz_force_geohash_into_post', 1);

function niz_force_geohash_into_post() {
    if (empty($_POST)) return;

    $current_cct = '';
    if (isset($_POST['cct_slug'])) {
        $current_cct = sanitize_key($_POST['cct_slug']);
    } elseif (isset($_POST['slug'])) {
        $current_cct = sanitize_key($_POST['slug']);
    }

    $parsed_item = [];
    $is_ajax = false;

    if (isset($_POST['item_data']) && is_string($_POST['item_data'])) {
        parse_str($_POST['item_data'], $parsed_item);
        $is_ajax = true;
        if (!empty($parsed_item['cct_slug'])) {
            $current_cct = sanitize_key($parsed_item['cct_slug']);
        }
    }

    $allowed_ccts = ['mosque', 'business']; 

    if (!empty($current_cct) && !in_array($current_cct, $allowed_ccts)) {
        return; 
    }

    if ($is_ajax) {
        $lat = isset($parsed_item['latitude']) ? floatval($parsed_item['latitude']) : 0;
        $lng = isset($parsed_item['longitude']) ? floatval($parsed_item['longitude']) : 0;
        
        if ($lat !== 0.0 && $lng !== 0.0) {
            $hash = niz_direct_post_geohash($lat, $lng, 9);
            $parsed_item['geohash'] = $hash;
            $_POST['item_data'] = http_build_query($parsed_item);
            file_put_contents(dirname(__FILE__) . '/geohash_debug.txt', "AJAX MATCH [CCT: $current_cct]! Lat: $lat, Lng: $lng, Hash: $hash \n", FILE_APPEND);
        }
    } 
    elseif (isset($_POST['latitude']) && isset($_POST['longitude'])) {
        $lat = floatval($_POST['latitude']);
        $lng = floatval($_POST['longitude']);
        
        if ($lat !== 0.0 && $lng !== 0.0) {
            $hash = niz_direct_post_geohash($lat, $lng, 9);
            $_POST['geohash'] = $hash;
            if (isset($_REQUEST)) $_REQUEST['geohash'] = $hash;
            file_put_contents(dirname(__FILE__) . '/geohash_debug.txt', "STANDARD MATCH [CCT: $current_cct]! Lat: $lat, Lng: $lng, Hash: $hash \n", FILE_APPEND);
        }
    }
}

// =======================================================
// 3. MOSQUE MIGRATION SHORTCODE
// =======================================================
add_shortcode('niz_run_mosque_migration', 'niz_run_mosque_migration_shortcode');

function niz_run_mosque_migration_shortcode() {
    if (!current_user_can('manage_options')) return 'Unauthorized Access.';

    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_mosque';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) return "Database table {$table} not found.";

    $is_active = isset($_GET['niz_run_loop']) && $_GET['niz_run_loop'] === '1';
    $processed_this_run = 0;

    if ($is_active) {
        $chunk_size = 1500; 
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT `_ID`, `latitude`, `longitude` 
            FROM `{$table}` 
            WHERE (`geohash` IS NULL OR `geohash` = '') 
              AND `latitude` != 0 
              AND `longitude` != 0
            LIMIT %d
        ", $chunk_size), ARRAY_A);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $id = intval($row['_ID']);
                $hash = niz_direct_post_geohash(floatval($row['latitude']), floatval($row['longitude']), 9);
                $wpdb->update($table, array('geohash' => $hash), array('_ID' => $id), array('%s'), array('%d'));
                $processed_this_run++;
            }
        }
    }

    $remaining = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE (`geohash` IS NULL OR `geohash` = '') AND `latitude` != 0 AND `longitude` != 0");
    $current_page_url = strtok(get_permalink(), '?');

    ob_start();
    ?>
    <div style="background:#fff; border:1px solid #ccd0d4; padding:25px; max-width:500px; margin:30px auto; font-family:sans-serif; border-radius:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0; color:#23282d;">Mosque Geohash Processing Engine</h3>
        <?php if ($remaining > 0): ?>
            <p style="font-size: 14px; color: #50575e;">Remaining records: <strong style="font-size:16px; color:#2271b1;"><?php echo number_format($remaining); ?></strong></p>
            <?php if ($is_active): ?>
                <meta http-equiv="refresh" content="1.5;url=<?php echo add_query_arg('niz_run_loop', '1', $current_page_url); ?>">
                <div style="background:#f0f2f5; padding:15px; border-left:4px solid #3498db; border-radius:4px; margin-bottom:15px;">
                    <span style="font-weight:bold; color:#2980b9;">⚡ Processing loop active...</span><br>
                    <small style="color:#72777c;">Processed <?php echo $processed_this_run; ?> items. Reloading next...</small>
                </div>
                <div style="text-align:center;"><a href="<?php echo $current_page_url; ?>" style="color:#d63638; text-decoration:none; font-size:13px;">[ Stop Processing ]</a></div>
            <?php else: ?>
                <a href="<?php echo add_query_arg('niz_run_loop', '1', $current_page_url); ?>" style="display:inline-block; background:#2271b1; color:#fff; text-decoration:none; padding:12px 20px; font-size:14px; border-radius:4px; font-weight:bold;">Start Processing Loop</a>
            <?php endif; ?>
        <?php else: ?>
            <div style="background:#ecfdf5; padding:15px; border-left:4px solid #10b981; border-radius:4px; color:#065f46;"><strong>🎉 All Done!</strong> Records successfully indexed.</div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// =======================================================
// 4. BUSINESS MIGRATION SHORTCODE
// =======================================================
add_shortcode('niz_run_business_migration', 'niz_run_business_migration_shortcode');

function niz_run_business_migration_shortcode() {
    if (!current_user_can('manage_options')) return 'Unauthorized Access.';

    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_business';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) return "Database table {$table} not found.";

    $is_active = isset($_GET['niz_run_loop']) && $_GET['niz_run_loop'] === '1';
    $processed_this_run = 0;

    if ($is_active) {
        $chunk_size = 1500; 
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT `_ID`, `latitude`, `longitude` 
            FROM `{$table}` 
            WHERE (`geohash` IS NULL OR `geohash` = '') 
              AND `latitude` != 0 
              AND `longitude` != 0
            LIMIT %d
        ", $chunk_size), ARRAY_A);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $id = intval($row['_ID']);
                $hash = niz_direct_post_geohash(floatval($row['latitude']), floatval($row['longitude']), 9);
                $wpdb->update($table, array('geohash' => $hash), array('_ID' => $id), array('%s'), array('%d'));
                $processed_this_run++;
            }
        }
    }

    $remaining = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE (`geohash` IS NULL OR `geohash` = '') AND `latitude` != 0 AND `longitude` != 0");
    $current_page_url = strtok(add_query_arg(array()), '?');

    ob_start();
    ?>
    <div style="background:#fff; border:1px solid #ccd0d4; padding:25px; max-width:500px; margin:30px auto; font-family:sans-serif; border-radius:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0; color:#23282d; border-bottom:1px solid #eee; padding-bottom:10px;">Business Geohash Processing Engine</h3>
        <?php if ($remaining > 0): ?>
            <p style="font-size: 14px; color: #50575e;">Remaining business records: <strong style="font-size:16px; color:#e67e22;"><?php echo number_format($remaining); ?></strong></p>
            <?php if ($is_active): ?>
                <meta http-equiv="refresh" content="1.2;url=<?php echo add_query_arg('niz_run_loop', '1', $current_page_url); ?>">
                <div style="background:#fdf6ec; padding:15px; border-left:4px solid #e67e22; border-radius:4px; margin-bottom:15px;">
                    <span style="font-weight:bold; color:#d35400;">⚡ Processing business loop active...</span><br>
                    <small style="color:#72777c;">Processed <?php echo $processed_this_run; ?> items. Reloading next...</small>
                </div>
                <div style="text-align:center;"><a href="<?php echo $current_page_url; ?>" style="color:#d63638; text-decoration:none; font-size:13px; font-weight:600;">[ Stop Processing ]</a></div>
            <?php else: ?>
                <a href="<?php echo add_query_arg('niz_run_loop', '1', $current_page_url); ?>" style="display:inline-block; background:#e67e22; color:#fff; text-decoration:none; padding:12px 20px; font-size:14px; border-radius:4px; font-weight:bold;">Start Indexing Loop</a>
            <?php endif; ?>
        <?php else: ?>
            <div style="background:#ecfdf5; padding:15px; border-left:4px solid #10b981; border-radius:4px; color:#065f46;"><strong>🎉 Business Mapping Complete!</strong> All valid locations converted.</div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * Initialize affiliate/location cookies on frontend
 */
add_action('init', 'niz_mfa_location_init');
function niz_mfa_location_init() {
    // Only run on frontend requests
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $ref_id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $aff_cookie   = isset($_COOKIE['affiliateid']) ? (int) $_COOKIE['affiliateid'] : 0;
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID ?: 0;

    $affiliate_id = $user_id ?: ($ref_id ?: $aff_cookie);
 
    if ($affiliate_id && $affiliate_id !== $aff_cookie) {
        setcookie('affiliateid', $affiliate_id, [
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_COOKIE['affiliateid'] = $affiliate_id;
    }
}

/**
 * Convert 24hr time to 12hr with AM/PM
 */
function niz_mfa_convert_to_12hr($time_24) {
    $parts = explode(':', $time_24);
    $hour = intval($parts[0]);
    $minute = $parts[1];
    $period = ($hour >= 12) ? 'PM' : 'AM';
    $hour_12 = $hour % 12;
    if ($hour_12 == 0) $hour_12 = 12;
    return sprintf("%d:%s %s", $hour_12, $minute, $period);
}

/**
 * Format countdown in hours and minutes
 */
function niz_mfa_format_countdown($total_minutes) {
    $hours = floor($total_minutes / 60);
    $minutes = $total_minutes % 60;
    
    if ($hours > 0 && $minutes > 0) {
        return $hours . ' hr ' . $minutes . ' min';
    } elseif ($hours > 0) {
        return $hours . ' hour' . ($hours > 1 ? 's' : '');
    } else {
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    }
}

/**
 * Fetch prayer times from Aladhan API
 */
function niz_mfa_fetch_prayer_times_api($latitude, $longitude) {
    $api_url = "https://api.aladhan.com/v1/timings?latitude={$latitude}&longitude={$longitude}&method=2";
    $response = wp_remote_get($api_url, ['timeout' => 10]);
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data && isset($data['data']['timings'])) {
            $t = $data['data']['timings'];
            return [
                'fajr' => $t['Fajr'],
                'sunrise' => $t['Sunrise'],
                'dhuhr' => $t['Dhuhr'],
                'asr' => $t['Asr'],
                'maghrib' => $t['Maghrib'],
                'isha' => $t['Isha']
            ];
        }
    }
    return false;
}

/**
 * Get current prayer based on time
 */
function niz_mfa_get_current_prayer($prayers_24hr) {
    $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
    $current_minutes = (int)$now->format('H') * 60 + (int)$now->format('i');
    
    $prayer_list = [
        ['name' => 'Fajr', 'time' => $prayers_24hr['Fajr']],
        ['name' => 'Sunrise', 'time' => $prayers_24hr['Sunrise']],
        ['name' => 'Dhuhr', 'time' => $prayers_24hr['Dhuhr']],
        ['name' => 'Asr', 'time' => $prayers_24hr['Asr']],
        ['name' => 'Maghrib', 'time' => $prayers_24hr['Maghrib']],
        ['name' => 'Isha', 'time' => $prayers_24hr['Isha']]
    ];
    
    for ($i = 0; $i < count($prayer_list); $i++) {
        $parts = explode(':', $prayer_list[$i]['time']);
        $prayer_minutes = (int)$parts[0] * 60 + (int)$parts[1];
        
        if ($prayer_list[$i]['name'] == 'Fajr' && $current_minutes < $prayer_minutes) {
            return null;
        }
        
        if ($current_minutes >= $prayer_minutes) {
            if ($i + 1 < count($prayer_list)) {
                $next_parts = explode(':', $prayer_list[$i + 1]['time']);
                $next_minutes = (int)$next_parts[0] * 60 + (int)$next_parts[1];
                if ($current_minutes < $next_minutes) {
                    return $prayer_list[$i];
                }
            } else {
                return $prayer_list[$i];
            }
        }
    }
    return null;
}

/**
 * Get next prayer with countdown
 */
function niz_mfa_get_next_prayer_with_countdown($prayer_data) {
    $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
    $current_time = $now->format('H:i');
    
    $prayers = [
        'Fajr' => $prayer_data['fajr'],
        'Dhuhr' => $prayer_data['dhuhr'],
        'Asr' => $prayer_data['asr'],
        'Maghrib' => $prayer_data['maghrib'],
        'Isha' => $prayer_data['isha']
    ];
    
    list($current_hour, $current_min) = explode(':', $current_time);
    $current_minutes = (int)$current_hour * 60 + (int)$current_min;
    
    foreach ($prayers as $name => $time) {
        list($hour, $min) = explode(':', $time);
        $prayer_minutes = (int)$hour * 60 + (int)$min;
        if ($prayer_minutes > $current_minutes) {
            return [
                'name' => $name,
                'time' => $time,
                'countdown' => niz_mfa_format_countdown($prayer_minutes - $current_minutes)
            ];
        }
    }
    
    list($hour, $min) = explode(':', $prayers['Fajr']);
    $fajr_minutes = (int)$hour * 60 + (int)$min;
    return [
        'name' => 'Fajr',
        'time' => $prayers['Fajr'],
        'countdown' => niz_mfa_format_countdown((24 * 60) - $current_minutes + $fajr_minutes)
    ];
}