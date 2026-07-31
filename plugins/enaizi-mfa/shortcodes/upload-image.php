<?php

// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * 1. CLOUDFLARE R2 DIRECT UPLOADER
 */
function upload_to_cloudflare_r2($file_path, $file_name, $folder_name) {
    // 🔌 CONFIGURATION BLOCK
    $account_id  = '7ccd342e6cf3589155846a8c3106086c'; 
    $bucket_name = 'masjid4all'; 
    $public_url  = 'https://cdn.staging.masjid4all.com'; 
    
    $access_key  = 'e5c256c28a7facb3a71ac368b103b55c';     
    $secret_key  = '9ec992ab7a11a436a543066f201b807df008427b540c5a08754b43a3dfc63127'; 

    $region      = 'auto';
    $service     = 's3';
    
    // Sanitize the folder name to prevent any weird URL characters
    $folder_name = sanitize_title($folder_name);
    
    $host        = "{$account_id}.r2.cloudflarestorage.com";
    $uri         = '/' . $bucket_name . '/' . $folder_name . '/' . $file_name; 
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

    $k_secret  = "AWS4" . $secret_key;
    $k_date    = hash_hmac('sha256', $date_stamp, $k_secret, true);
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

    return rtrim($public_url, '/') . '/' . $folder_name . '/' . $file_name;
}

/**
 * 2. IMAGE PROCESSING (Crop to 1200x630 & Convert to WebP)
 */
function process_and_crop_to_webp($uploaded_file_path, $file_prefix) {
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
    $safe_prefix = sanitize_title($file_prefix);
    $output_name = $safe_prefix . '_featured_' . uniqid() . '_' . time() . '.webp';
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
add_shortcode('cpt_image_manager', 'mim_shortcode_render'); 
add_shortcode('masjid_image_manager', 'mim_shortcode_render'); // Kept for backwards compatibility

function mim_shortcode_render() {
    // 1. Restriction Checklist: Logged In + Role restriction (Editor/Admin)
    if (!is_user_logged_in() || (!current_user_can('editor') && !current_user_can('administrator'))) {
        return '';
    }

    $post_id   = get_the_ID();
    $post_type = get_post_type($post_id);
    
    // Fallback if post type is missing
    if (!$post_type) {
        $post_type = 'general';
    }

    $output = '';

    // Handle Image Form Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mim_nonce']) && wp_verify_nonce($_POST['mim_nonce'], 'mim_action')) {
        
        if (isset($_FILES['cpt_upload_image']) && !empty($_FILES['cpt_upload_image']['name'])) {
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $uploaded_file    = $_FILES['cpt_upload_image'];
            $upload_overrides = ['test_form' => false];
            $movefile         = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // Resize and transform local asset to WebP, passing post_type for filename
                $processed = process_and_crop_to_webp($movefile['file'], $post_type);
                
                if (!is_wp_error($processed)) {
                    // Send to Cloudflare R2, passing post_type for the folder routing
                    $r2_url = upload_to_cloudflare_r2($processed['path'], $processed['name'], $post_type);

                    if (!is_wp_error($r2_url)) {
                        
                        // Register asset inside WordPress Database
                        $attachment = array(
                            'post_mime_type' => 'image/webp',
                            'post_title'     => sanitize_file_name($processed['name']),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );

                        $attachment_id = wp_insert_attachment($attachment, $processed['path'], $post_id);

                        if (!is_wp_error($attachment_id)) {
                            $attachment_data = wp_generate_attachment_metadata($attachment_id, $processed['path']);
                            wp_update_attachment_metadata($attachment_id, $attachment_data);

                            // Explicitly cache the remote R2 url so filter can catch it
                            update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

                            // 🛠️ SEO UPDATE: Fetch post title and assign it as the image's Alt Text
                            $parent_post_title = get_the_title($post_id);
                            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($parent_post_title));

                            // Commit attachment ID as Featured Image
                            set_post_thumbnail($post_id, $attachment_id);
                            
                            // Cleanup user request legacy field if it exists
                            delete_post_meta($post_id, 'image_url');

                            $output .= '<div style="background:#d4edda; color:#155724; padding:12px; margin-bottom:15px; border-radius:4px; border: 1px solid #c3e6cb;">✅ Image successfully processed, uploaded to R2 (/' . esc_html($post_type) . '/), and assigned as Featured Image with SEO Alt Text!</div>';
                        } else {
                            $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Database Error: ' . $attachment_id->get_error_message() . '</div>';
                        }
                    } else {
                        $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">R2 Connection Error: ' . $r2_url->get_error_message() . '</div>';
                    }

                    // 🧼 Enforce cleanup of processed local WebP
                    if (file_exists($processed['path'])) {
                        @unlink($processed['path']);
                    }
                } else {
                    $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Processing Error: ' . $processed->get_error_message() . '</div>';
                }

                // 🧼 Enforce cleanup of raw upload source file
                if (file_exists($movefile['file'])) {
                    @unlink($movefile['file']);
                }
            } else {
                $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Upload Error: ' . $movefile['error'] . '</div>';
            }
        }
    }

    // --- INTERFACE GENERATOR ---
    $ui_label = ucfirst($post_type); 
    
    $output .= '<div style="background:#ffffff; border:1px solid #e5e5e5; padding:20px; border-radius:8px; max-width:500px; margin:20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">';
    $output .= '<h4 style="margin-top:0; margin-bottom:10px; color:#006B3E; display:flex; align-items:center; gap:8px;">⚙️ Admin ' . esc_html($ui_label) . ' Image Manager</h4>';
    
    if (has_post_thumbnail($post_id)) {
        $output .= '<div style="margin-bottom:15px;">';
        $output .= '<img src="' . esc_url(get_the_post_thumbnail_url($post_id, 'medium')) . '" style="width:100%; height:auto; border-radius:6px; border:1px solid #ddd;" />';
        $output .= '<p style="font-size:12px; color:#666; margin-top:8px;">Uploading a new graphic instantly overwrites the R2 active featured image.</p>';
        $output .= '</div>';
    } else {
        $output .= '<p style="font-size:13px; color:#666; margin-bottom:15px;">No active banner image found. Upload below (Auto-crops to 1200x630 WebP and pushes directly to R2 /' . esc_html($post_type) . '/ folder).</p>';
    }

    $output .= '<form method="POST" enctype="multipart/form-data">';
    $output .= '<input type="file" name="cpt_upload_image" accept="image/*" required style="display:block; margin-bottom:15px; font-size:14px; width: 100%;" />';
    $output .= wp_nonce_field('mim_action', 'mim_nonce', true, false);
    $output .= '<button type="submit" style="background:#006B3E; color:#fff; font-weight:bold; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; width: 100%;">Upload & Sync directly to Cloudflare</button>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}

/**
 * 4. ROUTE ATTACHMENT REQUESTS TO CLOUDFLARE PUBLIC STORAGE
 */
add_filter('wp_get_attachment_url', 'serve_r2_integrated_attachment_url', 10, 2);
function serve_r2_integrated_attachment_url($url, $attachment_id) {
    $r2_cdn_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    if (!empty($r2_cdn_url)) {
        return $r2_cdn_url;
    }
    return $url;
}

add_filter('image_downsize', 'serve_r2_integrated_image_downsize', 10, 3);
function serve_r2_integrated_image_downsize($downsize, $attachment_id, $size) {
    $r2_cdn_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    
    if (!empty($r2_cdn_url)) {
        // Reverted back to serving the master image to fix the site
        return array($r2_cdn_url, 1200, 630, false);
    }
    
    return $downsize;
}