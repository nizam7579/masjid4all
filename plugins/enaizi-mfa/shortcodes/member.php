<?php

/**
 * 1. mfa_member_info
 * 2. mfa_member_profile
 * 3. mfa_member_todo
 * 4. mfa_member_update_field
 * 5. mfa_member_add_cct
 * 6. mfa_member_field_by_userid
 * 7. mfa_member_add_points
 */
 
 
// MEMBER INFO 
add_shortcode('mfa_member_info', 'mfa_member_info_shortcode');
function mfa_member_info_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id = $current_user->ID;
    $name    = !empty($current_user->first_name) ? esc_html($current_user->first_name) : 'Guest';
    $email   = $current_user->user_email;
    $phone   = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));
    $chg_pwd = get_user_meta($user_id, 'change_password', true);
    
    // Set phone cookies safely
    if (!empty($phone)) {
        setcookie(
            'user_phone',
            $phone,
            time() + 2592000,
            '/',
            '',
            is_ssl(),
            false // allow JS access
        );
    }
    
    $country     = isset($_COOKIE['country']) ? sanitize_text_field(wp_unslash($_COOKIE['country'])) : '';
    $partner_id  = isset($_COOKIE['partnerid']) ? intval($_COOKIE['partnerid']) : 0;
    $referrer_id = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 14270;

    if (empty($referrer_id)) {
        $referrer_id = 14270;
    }

    // Check CCT Data safely
    $item_id = absint(get_user_meta($user_id, 'item_id', true));

    if (empty($item_id)) {
        $chg_pwd = '';
        $rank    = 'Bronze';
        $status  = 'Member';
        $response = mfa_member_add_cct([
            'name'        => $name,
            'phone'       => $phone,
            'status'      => $status,
            'email'       => $email,
            'rank'        => $rank,
            'user_id'     => $user_id,
            'referrer_id' => $referrer_id,
            'partner_id'  => $partner_id,
            'country'     => $country
        ]);

        if ($response['success']) {
            $item_id = $response['insert_id'];
            update_user_meta($user_id, 'item_id', $item_id);
        } else {
            return 'Error: ' . esc_html($response['message']);
        }

    } else {
        $name    = mfa_member_field_by_userid($user_id, 'name');
        $country = mfa_member_field_by_userid($user_id, 'country');
        $status  = mfa_member_field_by_userid($user_id, 'status');
        $email   = mfa_member_field_by_userid($user_id, 'email');
        $rank    = mfa_member_field_by_userid($user_id, 'rank');
    }
    
    $points = mfa_member_field_by_userid($user_id, 'points');
    if (!$points){
        $points = 50;
        mfa_member_add_points($user_id, 'Welcome Bonus', $points);
    }
    

    ob_start();
    ?>

    <script>
        jQuery(document).ready(function($) {
            console.log('Member info loaded');
        });
    </script>

    <div style="text-align: center;">
        <b><?= esc_html(strtoupper($name)); ?></b><br>
        <span><?= $status; ?> | <?= esc_html($rank); ?> | <?= esc_html($points) . ' points'; ?></span>
    </div>

    <?php
    return ob_get_clean();
}


// MEMBER PROFILE 
add_shortcode('mfa_member_profile', 'mfa_member_profile_shortcode');
function mfa_member_profile_shortcode() {

    if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id    = $current_user->ID;
    $user_email = $current_user->user_email;
    $user_phone = get_user_meta($user_id, 'user_phone', true);

     
    $name       = mfa_member_field_by_userid($user_id, 'name');
    $email      = mfa_member_field_by_userid($user_id, 'email');
    $phone      = mfa_member_field_by_userid($user_id, 'phone');
    $sex        = mfa_member_field_by_userid($user_id, 'sex');
    $birthdate  = mfa_member_field_by_userid($user_id, 'birthdate');
    $country    = mfa_member_field_by_userid($user_id, 'country');
    $status     = mfa_member_field_by_userid($user_id, 'status');
    $rank       = mfa_member_field_by_userid($user_id, 'rank');
    $points     = mfa_member_field_by_userid($user_id, 'points');

    if (empty($phone)){
        if (!empty($user_phone)){
            $phone = $user_phone;
            // Update Phone
            mfa_member_update_field($user_id, 'phone', $user_phone);
        }
        if (empty($phone)){
            $phone = '<i style="color: #25988B;">Please update</i>';
        }
    }
    
    if (empty($email)){
        if (!empty($user_email)){
            $email = $user_email;
            // Update Email
            mfa_member_update_field($user_id, 'email', $user_email);
        }
        if (empty($email)){
            $email = '<i style="color: #25988B;">Please update</i>';
        }
    }

    
    if (empty($sex)){
        $sex = '<i style="color: #25988B;">Please update</i>';
    }
    if (empty($birthdate)){
        $birthdate = '<i style="color: #25988B;">Please update</i>';
    }
    if (empty($country)){
        $country = '<i style="color: #25988B;">Please update</i>';
    }
    
    ob_start();
    ?>

    <table>
        <tr><td width="100">Name</td><td><b><?= esc_html(strtoupper($name)); ?></b></td></tr>
        <tr><td>Email</td><td><b><?= $email; ?></b></td></tr>
        <tr><td>Phone</td><td><b><?= $phone; ?></b></td></tr>
        <tr><td>Sex</td><td><b><?= $sex; ?></b></td></tr>
        <tr><td>Birthdate</td><td><b><?= $birthdate; ?></b></td></tr>
        <tr><td>Country</td><td><b><?= $country; ?></b></td></tr>
        <tr><td>&nbsp;</td><td><b></td></tr>
        <tr><td>Status</td><td><b><?= esc_html($status); ?></b></td></tr>
        <tr><td>Rank</td><td><b><?= esc_html($rank); ?></b></td></tr>
        <tr><td>Points</td><td><b><?= esc_html($points); ?></b></td></tr>
    </table>

    <?php
    return ob_get_clean();
}

// UPDATE FIELD
function mfa_member_update_field($user_id, $field, $value) {
    global $wpdb;

    if (empty($user_id) || empty($field)) {
        return false;
    }

    return $wpdb->update(
        $wpdb->prefix . 'jet_cct_member',
        [ $field => $value ],
        [ 'user_id' => (int) $user_id ],
        [ '%s' ],           // Format for $data (status field - string)
        [ '%d' ]            // Format for $where (user_id - integer)
    );
}

// ADD CCT
function mfa_member_add_cct($data) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';
    $defaults = [
        'item_id'     => null,
        'name'        => '',
        'phone'       => '',
        'status'      => '',
        'rank'        => '',
        'points'      => 0,
        'user_id'     => 0,
        'referrer_id' => '',
        'partner_id'  => '',
        'country'     => '',
    ];

    $data = wp_parse_args($data, $defaults);

    $insert_data = [
        'name'        => sanitize_text_field($data['name']),
        'phone'       => sanitize_text_field($data['phone']),
        'status'      => sanitize_text_field($data['status']),
        'user_id'     => intval($data['user_id']),
        'rank'        => sanitize_text_field($data['rank']),
        'points'      => sanitize_text_field($data['points']),
        'country'     => sanitize_text_field($data['country']),
        'referrer_id' => sanitize_text_field($data['referrer_id']),
        'partner_id'  => sanitize_text_field($data['partner_id']),
        'cct_created' => current_time('mysql')
    ];

    $format = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];

    if (!empty($data['item_id'])) {
        $insert_data['item_id'] = intval($data['item_id']);
        $format[] = '%d';
    }

    $result = $wpdb->insert($table, $insert_data, $format);

    if ($result === false) {
        return [
            'success' => false,
            'message' => $wpdb->last_error
        ];
    }

    return [
        'success'   => true,
        'insert_id' => $wpdb->insert_id
    ];
}


// MEMBER TODO
add_shortcode('mfa_member_todo', 'mfa_member_todo_shortcode');
function mfa_member_todo_shortcode() {
        if (!is_user_logged_in()) {
        return 'Please login.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user) {
        return 'User error.';
    }

    $user_id = $current_user->ID;
    $email   = $current_user->user_email;
    $phone   = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));
    $chg_pwd = get_user_meta($user_id, 'change_password', true);
    
    $chk_update  = mfa_member_field_by_userid($user_id,'chk_update');
    $chk_premium = mfa_member_field_by_userid($user_id,'chk_premium');
    $chk_card    = mfa_member_field_by_userid($user_id,'chk_card');
    $chk_share   = mfa_member_field_by_userid($user_id,'chk_share');
    $chk_affiliate   = mfa_member_field_by_userid($user_id,'chk_affiliate');
    
    ob_start();
    ?>
    <span style="color: #25988B;">TODO</span>
    <ul>
    <?php if (empty($chk_update)) : ?>
        <li style="color: red;">Please update your profile</li>
    <?php endif; ?>

    <?php if (empty($chg_pwd)) : ?>
        <li style="color: red;">Please update your password</li>
    <?php endif; ?>
    
    <?php if (empty($chk_premium)) : ?>
        <li style="color: #25988B;">Upgrade to Premium Membership</li>
    <?php endif; ?>
    
    <?php if (empty($chk_card)) : ?>
        <li style="color: #25988B;">Create your Digital Namecard</li>
    <?php endif; ?>
    
    <?php if (empty($chk_share)) : ?>
        <li style="color: #25988B;">Start Share/Promote</li>
    <?php endif; ?>
    
    <?php if (empty($chk_affiliate)) : ?>
        <li style="color: #25988B;">Join our Affiliate Program</li>
    <?php endif; ?>
    
    </ul>

    <?php
    return ob_get_clean();

}

function mfa_member_field_by_userid($user_id,$field) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';
    $result = $wpdb->get_var(
        $wpdb->prepare("SELECT {$field} FROM {$table} WHERE user_id = %d", absint($user_id))
    );

    return ($result !== null) ? $result : null;
}


// Add Barakah Points
function mfa_member_add_points($user_id, $desc, $points) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'jet_cct_barakah'; 
    
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT user_id
             FROM {$table}
             WHERE description = %s
               AND user_id = %d
             LIMIT 1",
            $desc,
            $user_id
        )
    );
    
    if ( empty($exists) ) {
        // Sanitize/Cast inputs to ensure data integrity
        $insert_data = [
            'user_id'     => (int) $user_id,
            'description' => sanitize_text_field($desc),
            'points'      => (int) $points,
            'cct_created' => current_time('mysql')
        ];
    
        $insert_format = [
            '%d', // user_id
            '%s', // description
            '%d', // points
            '%s'  // cct_created
        ];
    
        $result = $wpdb->insert($table, $insert_data, $insert_format);
    
        if ($result === false) {
            return [
                'success' => false,
                'message' => $wpdb->last_error,
            ];
        }

        //niz_user_update_field($user_id, 'points', $points);
    
        return [
            'success'   => true,
            'insert_id' => (int) $wpdb->insert_id // Casted to int for clean API responses
        ];
    } 
} 


add_shortcode('mfa_member_logout', 'mfa_member_logout_shortcode');
function mfa_member_logout_shortcode() {
    if (!is_user_logged_in()) {
        return ''; // nothing shown if already logged out
    }
    $user = wp_get_current_user();
    return '<button id="niz_user_logout_btn" class="niz-user-logout-btn">➜] Logout</button>';
}



//SHARE
add_shortcode('mfa_member_share', 'mfa_member_share_shortcode');
function mfa_member_share_shortcode($atts) {

    // 1. Handle shortcode attributes (default post_id is empty)
    $attributes = shortcode_atts(array(
        'post_id' => '',
    ), $atts);

    // 2. Determine target post ID, URL, and Title
    if (!empty($attributes['post_id'])) {
        $target_id = intval($attributes['post_id']);
    } else {
        $target_id = get_the_ID();
    }

    if (!empty($target_id) && get_permalink($target_id)) {
        $current_url = get_permalink($target_id);
        $share_title = get_the_title($target_id);
    } else {
        // Fallback if no valid post ID (e.g. non-post pages, custom routes)
        global $wp;
        $current_url  = home_url(add_query_arg(array(), $wp->request));
        $share_title  = wp_get_document_title();
        $target_id    = 0;
    }

    // Featured image (fallback to site logo/placeholder if none set)
    if ($target_id && has_post_thumbnail($target_id)) {
        $featured_image = get_the_post_thumbnail_url($target_id, 'medium_large');
    } else {
        $featured_image = get_site_icon_url(300);
    }

    // Excerpt (fallback to trimmed content, then meta description)
    if ($target_id) {
        $raw_excerpt = get_the_excerpt($target_id);
        if (empty($raw_excerpt)) {
            $post_obj = get_post($target_id);
            $raw_excerpt = $post_obj ? wp_trim_words(wp_strip_all_tags($post_obj->post_content), 25) : '';
        }
    } else {
        $raw_excerpt = get_bloginfo('description');
    }
    $share_excerpt = esc_html($raw_excerpt);

    // 3. Check for the affiliateid cookie
    if (isset($_COOKIE['affiliateid']) && !empty($_COOKIE['affiliateid'])) {
        $affiliate_id = sanitize_text_field($_COOKIE['affiliateid']);
        // Append parameter correctly based on the URL structure
        if (strpos($current_url, '?') !== false) {
            $current_url .= '&id=' . $affiliate_id;
        } else {
            $current_url .= '/?id=' . $affiliate_id;
        }
    }

    // 4. Define the pre-filled text packages
    $wa_text   = "*Masjid4All* - Discover nearby mosques, connect with your local Muslim community, support Muslim-friendly businesses, and access trusted Islamic resources—all in one place.\n " . $current_url;
    $copy_text = "𝗠𝗮𝘀𝗷𝗶𝗱𝟰𝗔𝗹𝗹
Discover nearby mosques, connect with your local Muslim community, support Muslim-friendly businesses, and access trusted Islamic resources—all in one place.  " . $current_url;

    // URL encode for the WhatsApp link
    $wa_share_url = "https://api.whatsapp.com/send?text=" . urlencode($wa_text);

    // Start building layout
    ob_start();
    ?>
    <div class="share-inner-layout">

        <!-- Post Preview Card -->
        <div class="share-preview-card">
            <?php if (!empty($featured_image)) : ?>
                <div class="share-preview-image">
                    <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($share_title); ?>" loading="lazy">
                </div>
            <?php endif; ?>

            <div class="share-preview-body">
                <h3 class="share-preview-title"><?php echo esc_html($share_title); ?></h3>

                <?php if (!empty($share_excerpt)) : ?>
                    <p class="share-preview-excerpt"><?php echo esc_html($share_excerpt); ?></p>
                <?php endif; ?>

                <div class="share-preview-url">
                    <span class="url-icon">🔗</span>
                    <span class="url-text"><?php echo esc_html($current_url); ?></span>
                </div>
            </div>
        </div>

        <div class="share-options-grid">
            <!-- Option 1: WhatsApp Link -->
            <a href="<?php echo esc_url($wa_share_url); ?>" target="_blank" class="share-btn whatsapp-btn">
                <span class="btn-icon-wrap">
                    <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12.004 2c-5.514 0-9.997 4.48-9.997 9.997 0 1.763.46 3.484 1.334 4.997L2 22l5.14-1.35a9.96 9.96 0 0 0 4.864 1.24h.004c5.514 0 9.997-4.481 9.997-9.998C21.998 6.48 17.518 2 12.004 2zm0 18.174h-.003a8.19 8.19 0 0 1-4.174-1.144l-.3-.178-3.05.801.815-2.973-.196-.306a8.166 8.166 0 0 1-1.253-4.377c0-4.518 3.677-8.194 8.196-8.194 2.189 0 4.246.853 5.793 2.401a8.13 8.13 0 0 1 2.398 5.797c0 4.518-3.677 8.173-8.226 8.173z"/>
                    </svg>
                </span>
                <span class="btn-label">Share via WhatsApp</span>
                <span class="btn-arrow">→</span>
            </a>

            <!-- Option 2: Clipboard Copy -->
            <button id="copyShareBtn" class="share-btn copy-btn" data-copy-text="<?php echo esc_attr($copy_text); ?>">
                <span class="btn-icon-wrap">
                    <svg class="btn-icon-svg" id="copyIconDefault" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="9" y="9" width="13" height="13" rx="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    <svg class="btn-icon-svg btn-icon-check" id="copyIconCheck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </span>
                <span class="btn-label" id="copyBtnText">Copy Text &amp; Link</span>
            </button>

            <i class="share-modal-desc">Share it via your Social Media platform.</i>
        </div>

    </div>

    <!-- JavaScript for copying to clipboard -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyBtn = document.getElementById('copyShareBtn');
        const copyBtnText = document.getElementById('copyBtnText');
        const iconDefault = document.getElementById('copyIconDefault');
        const iconCheck = document.getElementById('copyIconCheck');

        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const textToCopy = this.getAttribute('data-copy-text');

                navigator.clipboard.writeText(textToCopy).then(function() {
                    // Update button UI temporarily
                    copyBtnText.textContent = 'Copied!';
                    copyBtn.classList.add('copied');
                    if (iconDefault) iconDefault.style.display = 'none';
                    if (iconCheck) iconCheck.style.display = 'block';

                    setTimeout(function() {
                        copyBtnText.textContent = 'Copy Text & Link';
                        copyBtn.classList.remove('copied');
                        if (iconDefault) iconDefault.style.display = 'block';
                        if (iconCheck) iconCheck.style.display = 'none';
                    }, 2000);
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                });
            });
        }
    });
    </script>

    <!-- Clean Layout Styles -->
    <style>
        .share-inner-layout {
            font-family: system-ui, sans-serif;
            text-align: center;
            padding: 10px;
        }

        .share-modal-title {
            margin: 0 0 10px 0;
            font-size: 1.4rem;
            color: #333;
            font-weight: 600;
        }

        .share-modal-desc {
            margin: 0 0 20px 0;
            color: #666;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        /* Preview card */
        .share-preview-card {
            display: flex;
            flex-direction: column;
            text-align: left;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 18px;
            background: #fafafa;
        }

        .share-preview-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background: #eee;
        }

        .share-preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .share-preview-body {
            padding: 12px 14px;
        }

        .share-preview-title {
            margin: 0 0 6px 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
        }

        .share-preview-excerpt {
            margin: 0 0 10px 0;
            font-size: 0.88rem;
            color: #666;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .share-preview-url {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #0f766e;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 6px 10px;
            overflow: hidden;
        }

        .share-preview-url .url-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Share buttons */
        .share-options-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .share-btn {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.98rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .share-btn:active {
            transform: scale(0.98);
        }

        .btn-icon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.22);
        }

        .btn-icon-svg {
            width: 18px;
            height: 18px;
        }

        .btn-label {
            flex: 1;
            text-align: left;
        }

        .btn-arrow {
            font-size: 1rem;
            opacity: 0.7;
            transform: translateX(0);
            transition: transform 0.18s ease, opacity 0.18s ease;
        }

        .share-btn:hover .btn-arrow {
            transform: translateX(3px);
            opacity: 1;
        }

        /* WhatsApp button — vibrant gradient + soft glow */
        .whatsapp-btn {
            background: linear-gradient(135deg, #2fe273 0%, #1fb855 100%);
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.35);
        }

        .whatsapp-btn:hover {
            box-shadow: 0 8px 22px rgba(37, 211, 102, 0.45);
            filter: brightness(1.03);
        }

        /* Copy button — clean neutral glass style */
        .copy-btn {
            background: #f4f5f4;
            color: #23282a;
            border: 1px solid #e6e8e7;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }

        .copy-btn .btn-icon-wrap {
            background: rgba(15, 118, 110, 0.1);
            color: #0f766e;
        }

        .copy-btn:hover {
            background: #ececec;
            border-color: #dcdedc;
        }

        .copy-btn.copied {
            background: #e6f6ee;
            border-color: #b7e6cc;
            color: #0f7a4b;
        }

        .copy-btn.copied .btn-icon-wrap {
            background: rgba(15, 122, 75, 0.15);
            color: #0f7a4b;
        }
    </style>
    <?php
    return ob_get_clean();
}

