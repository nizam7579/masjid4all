<?php


// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * 1. CLOUDFLARE R2 DIRECT UPLOADER (AWS4 SIGNATURE CORRECTED)
 */
function upload_to_cloudflare_r2x($file_path, $file_name) {
    // 🔌 CONFIGURATION BLOCK
    $account_id  = NIZ_R2_ACCOUNT_ID; 
    $bucket_name = 'masjid4all'; 
    $public_url  = 'https://cdn.masjid4all.com'; 
    
    $access_key  = NIZ_R2_ACCESS_KEY_ID;     
    $secret_key  = NIZ_R2_SECRET_ACCESS_KEY; 

    $region      = 'auto';
    $service     = 's3';
    
    $host        = "{$account_id}.r2.cloudflarestorage.com";
    $uri         = '/' . $bucket_name . '/mosque/' . $file_name; 
    $endpoint    = "https://{$host}{$uri}";

    if (!file_exists($file_path)) {
        return new WP_Error('r2_error', 'Source file does not exist.');
    }

    $file_data    = file_get_contents($file_path);
    $content_type = 'image/webp';
    $content_size = strlen($file_data);

    $timestamp    = gmdate('Ymd\THis\Z');
    $date_stamp   = gmdate('Ymd');

    // Create Canonical Request
    $hashed_payload    = hash('sha256', $file_data);
    $canonical_headers = "host:$host\n" .
                         "x-amz-content-sha256:$hashed_payload\n" .
                         "x-amz-date:$timestamp\n";
                         
    $signed_headers    = "host;x-amz-content-sha256;x-amz-date";
    $canonical_request = "PUT\n" . $uri . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $hashed_payload;

    // Create String to Sign
    $credential_scope = "$date_stamp/$region/$service/aws4_request";
    $string_to_sign   = "AWS4-HMAC-SHA256\n$timestamp\n$credential_scope\n" . hash('sha256', $canonical_request);

    /**
     * 🛠️ CRITICAL FIX: To sign R2 API requests correctly using a plain-text hex string secret key, 
     * you must pass the raw binary key variant into the signature initialization tree.
     */
    $binary_secret = pack('H*', $secret_key);
    $k_date    = hash_hmac('sha256', $date_stamp, "AWS4" . $binary_secret, true);
    $k_region  = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', $service, $k_region, true);
    $k_signing = hash_hmac('sha256', "aws4_request", $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $authorization = "AWS4-HMAC-SHA256 Credential=$access_key/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

    $response = wp_remote_request($endpoint, [
        'method'    => 'PUT',
        'headers'   => [
            'Authorization'        => $authorization,
            'Host'                 => $host,
            'x-amz-date'           => $timestamp,
            'x-amz-content-sha256' => $hashed_payload,
            'Content-Type'         => $content_type,
            'Content-Length'       => $content_size,
        ],
        'body'      => $file_data,
        'timeout'   => 30,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $response_body = wp_remote_retrieve_body($response);
        return new WP_Error('r2_error', "R2 rejection (Code $response_code). Details: " . esc_html($response_body));
    }

    return rtrim($public_url, '/') . '/mosque/' . $file_name;
}

/**
 * 2. IMAGE PROCESSING (Crop to 1200x630 & Convert to WebP)
 */
function process_and_crop_to_webpx($uploaded_file_path) {
    $target_width  = 1200;
    $target_height = 630;
    
    if (!file_exists($uploaded_file_path) || !is_readable($uploaded_file_path)) {
        return new WP_Error('image_error', 'Source file missing or unreadable.');
    }

    list($orig_width, $orig_height, $source_type) = getimagesize($uploaded_file_path);
    
    switch ($source_type) {
        case IMAGETYPE_GIF:  $src_image = imagecreatefromgif($uploaded_file_path);  break;
        case IMAGETYPE_JPEG: $src_image = imagecreatefromjpeg($uploaded_file_path); break;
        case IMAGETYPE_PNG:  $src_image = imagecreatefrompng($uploaded_file_path);  break;
        case IMAGETYPE_WEBP: $src_image = imagecreatefromwebp($uploaded_file_path); break;
        default: return new WP_Error('image_error', 'Unsupported image type. Please upload a JPG, PNG, or WebP.');
    }

    if (!$src_image) {
        return new WP_Error('image_error', 'Failed to process image file.');
    }

    $dst_image = imagecreatetruecolor($target_width, $target_height);
    imagealphablending($dst_image, false);
    imagesavealpha($dst_image, true);

    $src_ratio = $orig_width / $orig_height;
    $dst_ratio = $target_width / $target_height;

    if ($src_ratio >= $dst_ratio) {
        $new_height = $orig_height;
        $new_width  = $orig_height * $dst_ratio;
        $src_x      = ($orig_width - $new_width) / 2;
        $src_y      = 0;
    } else {
        $new_width  = $orig_width;
        $new_height = $orig_width / $dst_ratio;
        $src_x      = 0;
        $src_y      = ($orig_height - $new_height) / 2;
    }

    imagecopyresampled($dst_image, $src_image, 0, 0, $src_x, $src_y, $target_width, $target_height, $new_width, $new_height);

    $output_dir  = wp_upload_dir()['path'];
    $output_name = 'masjid_' . uniqid() . '_' . time() . '.webp';
    $output_path = $output_dir . '/' . $output_name;

    imagewebp($dst_image, $output_path, 85);
    imagedestroy($src_image);
    imagedestroy($dst_image);

    return [
        'path' => $output_path,
        'name' => $output_name
    ];
}

/**
 * 3. MAIN SHORTCODE & ACTION HANDLER
 */
add_shortcode('masjid_image_managerx', 'mim_shortcode_render');
function mim_shortcode_renderx() {
    if (!is_user_logged_in()) {
        return '<p class="mim-msg">Please log in to manage images for this mosque.</p>';
    }

    $post_id   = get_the_ID();
    $is_admin  = current_user_can('manage_options');
    $output    = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mim_nonce']) && wp_verify_nonce($_POST['mim_nonce'], 'mim_action')) {
        
        // --- ADMIN PROCESS (Approve / Reject) ---
        if ($is_admin && isset($_POST['mim_admin_action'])) {
            $pending_url = get_post_meta($post_id, 'image_url', true);

            if ($_POST['mim_admin_action'] === 'approve' && !empty($pending_url)) {
                $response = wp_remote_get($pending_url, array('timeout' => 30));

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $image_data = wp_remote_retrieve_body($response);
                    $upload_dir = wp_upload_dir();
                    $filename   = 'masjid_featured_' . $post_id . '_' . time() . '.webp';
                    $file_path  = $upload_dir['path'] . '/' . $filename;

                    global $wp_filesystem;
                    if (empty($wp_filesystem)) {
                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                        WP_Filesystem();
                    }
                    $wp_filesystem->put_contents($file_path, $image_data);

                    $attachment = array(
                        'post_mime_type' => 'image/webp',
                        'post_title'     => sanitize_file_name($filename),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

                    if (!is_wp_error($attachment_id)) {
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                        wp_update_attachment_metadata($attachment_id, $attachment_data);

                        set_post_thumbnail($post_id, $attachment_id);
                        delete_post_meta($post_id, 'image_url');
                        $output .= '<div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">Image approved and set as Featured Image!</div>';
                    } else {
                        $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Database Attachment Error: ' . $attachment_id->get_error_message() . '</div>';
                    }
                } else {
                    $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP Code ' . wp_remote_retrieve_response_code($response);
                    $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Failed to fetch image from CDN: ' . esc_html($error_msg) . '</div>';
                }
            } elseif ($_POST['mim_admin_action'] === 'reject') {
                delete_post_meta($post_id, 'image_url');
                $output .= '<div style="background:#fff3cd; color:#856404; padding:10px; margin-bottom:15px; border-radius:4px;">Submission rejected. Meta field cleared.</div>';
            }
        }

        // --- SUBSCRIBER/USER PROCESS (Upload & Clean Up) ---
        if (!$is_admin && isset($_FILES['masjid_image']) && !empty($_FILES['masjid_image']['name'])) {
            if (!has_post_thumbnail($post_id)) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                
                $uploaded_file = $_FILES['masjid_image'];
                $upload_overrides = ['test_form' => false];
                $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    $processed = process_and_crop_to_webp($movefile['file']);
                    
                    if (!is_wp_error($processed)) {
                        $r2_url = upload_to_cloudflare_r2($processed['path'], $processed['name']);

                        if (!is_wp_error($r2_url)) {
                            update_post_meta($post_id, 'image_url', $r2_url);
                            $output .= '<div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">Image uploaded successfully! Awaiting administrator review.</div>';
                        } else {
                            $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">R2 Upload Error: ' . $r2_url->get_error_message() . '</div>';
                        }

                        // 🧼 Enforce cleanup of the intermediate cropped WebP file
                        if (file_exists($processed['path'])) {
                            @unlink($processed['path']);
                        }
                    } else {
                        $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Processing Error: ' . $processed->get_error_message() . '</div>';
                    }

                    // 🧼 Enforce cleanup of original raw temporary upload image
                    if (file_exists($movefile['file'])) {
                        @unlink($movefile['file']);
                    }
                } else {
                    $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Upload Error: ' . $movefile['error'] . '</div>';
                }
            } else {
                $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Error: This masjid post already contains an assigned featured image.</div>';
            }
        }
    }

    // --- INTERFACE GENERATOR ---
    $meta_image_url = get_post_meta($post_id, 'image_url', true);

    if ($is_admin) {
        if (!empty($meta_image_url)) {
            $output .= '<div style="border:2px solid #ffb900; background:#fff; padding:20px; border-radius:6px; max-width:600px; margin:20px 0;">';
            $output .= '<h4 style="margin-top:0; color:#ffb900;">⚠️ Admin: Review Pending Mosque Image</h4>';
            $output .= '<img src="' . esc_url($meta_image_url) . '" style="width:100%; height:auto; border-radius:4px; display:block; margin-bottom:15px; border:1px solid #ddd;" />';
            $output .= '<form method="POST">';
            $output .= wp_nonce_field('mim_action', 'mim_nonce', true, false);
            $output .= '<button type="submit" name="mim_admin_action" value="approve" style="background:#135e96; color:#fff; border:none; padding:10px 18px; border-radius:4px; cursor:pointer; margin-right:10px;">Approve & Apply</button>';
            $output .= '<button type="submit" name="mim_admin_action" value="reject" style="background:#b32d2e; color:#fff; border:none; padding:10px 18px; border-radius:4px; cursor:pointer;">Reject & Delete</button>';
            $output .= '</form>';
            $output .= '</div>';
        } else {
            $output .= '<p style="color:#777; font-style:italic;">[Admin Mode: No pending user-images to review on this post]</p>';
        }
    } else {
        if (has_post_thumbnail($post_id)) {
            $output .= '<p style="color:#666; font-style:italic;">✓ This mosque page already contains an active featured graphic.</p>';
        } elseif (!empty($meta_image_url)) {
            $output .= '<div style="background:#e2f0d9; border-left:4px solid #70ad47; padding:12px; color:#385723;">Your uploaded mosque image is waiting for administrator verification. Thank you!</div>';
        } else {
            $output .= '<div style="background:#f9f9f9; border:1px solid #e5e5e5; padding:25px; border-radius:8px; max-width:500px; margin:15px 0;">';
            $output .= '<h4 style="margin-top:0; margin-bottom:8px; font-size:18px;">Help Complete This Mosque Page</h4>';
            $output .= '<p style="font-size:13px; color:#666; margin-bottom:18px;">Upload a cover photo. Images are auto-cropped to landscape format (1200x630 px WebP).</p>';
            $output .= '<form method="POST" enctype="multipart/form-data">';
            $output .= '<input type="file" name="masjid_image" accept="image/*" required style="display:block; margin-bottom:20px; font-size:14px;" />';
            $output .= wp_nonce_field('mim_action', 'mim_nonce', true, false);
            $output .= '<input type="submit" value="Upload Image" style="background:#00a699; color:#fff; font-size:14px; font-weight:bold; padding:11px 24px; border:none; border-radius:4px; cursor:pointer; width:100%;" />';
            $output .= '</form>';
            $output .= '</div>';
        }
    }

    return $output;
}


