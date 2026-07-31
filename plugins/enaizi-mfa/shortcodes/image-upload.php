<?php

// Prevent direct access
if (!defined('ABSPATH')) exit;
 
/**
 * 1. IMAGE PROCESSING (Crop to 1200x630 & Convert to WebP)
 * Renamed specifically for the admin uploader to prevent function conflicts.
 */
if (!function_exists('niz_admin_process_and_crop_to_webp')) {
    function niz_admin_process_and_crop_to_webp($uploaded_file_path) {
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
        $output_name = 'masjid_featured_' . uniqid() . '_' . time() . '.webp';
        $output_path = $output_dir . '/' . $output_name;

        imagewebp($dst_image, $output_path, 85);
        imagedestroy($src_image);
        imagedestroy($dst_image);

        return [
            'path' => $output_path,
            'name' => $output_name
        ];
    }
}

/**
 * 2. MAIN SHORTCODE & ACTION HANDLER: [mosque-image-upload]
 */
add_shortcode('mosque-image-upload', 'niz_admin_mosque_image_upload_shortcode');

function niz_admin_mosque_image_upload_shortcode() {
    // Restrict access strictly to Editors and Administrators
    if (!is_user_logged_in() || (!current_user_can('editor') && !current_user_can('administrator'))) {
        return ''; // Return nothing if the user doesn't have permissions
    }

    $post_id = get_the_ID();
    $output  = '';

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['niz_admin_img_nonce']) && wp_verify_nonce($_POST['niz_admin_img_nonce'], 'niz_admin_img_action')) {
        
        if (isset($_FILES['mosque_admin_image']) && !empty($_FILES['mosque_admin_image']['name'])) {
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $uploaded_file    = $_FILES['mosque_admin_image'];
            $upload_overrides = ['test_form' => false];
            $movefile         = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // Process and crop to WebP
                $processed = niz_admin_process_and_crop_to_webp($movefile['file']);

                if (!is_wp_error($processed)) {
                    $file_path = $processed['path'];
                    $filename  = $processed['name'];

                    // Insert the WebP into the WP Media Library
                    $attachment = array(
                        'post_mime_type' => 'image/webp',
                        'post_title'     => sanitize_file_name($filename),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

                    if (!is_wp_error($attachment_id)) {
                        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                        wp_update_attachment_metadata($attachment_id, $attachment_data);
                        
                        // Set it directly as the featured image
                        set_post_thumbnail($post_id, $attachment_id);

                        $output .= '<div style="background:#d4edda; color:#155724; padding:12px; margin-bottom:15px; border-radius:4px; border: 1px solid #c3e6cb;">✅ Featured image successfully updated!</div>';
                        
                        // Delete any pending user submission meta to prevent conflicts
                        delete_post_meta($post_id, 'image_url');
                        
                    } else {
                        $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Database Error: ' . $attachment_id->get_error_message() . '</div>';
                    }
                } else {
                    $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Processing Error: ' . $processed->get_error_message() . '</div>';
                }

                // Clean up the original raw uploaded file from the server
                if (file_exists($movefile['file'])) {
                    @unlink($movefile['file']);
                }
            } else {
                $output .= '<div style="background:#f8d7da; color:#721c24; padding:12px; margin-bottom:15px; border-radius:4px;">Upload Error: ' . $movefile['error'] . '</div>';
            }
        }
    }

    // --- INTERFACE GENERATOR ---
    $output .= '<div style="background:#ffffff; border:1px solid #e5e5e5; padding:20px; border-radius:8px; max-width:500px; margin:20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">';
    $output .= '<h4 style="margin-top:0; margin-bottom:10px; color:#006B3E; display:flex; align-items:center; gap:8px;">⚙️ Admin Image Manager</h4>';
    
    // Show current image if it exists
    if (has_post_thumbnail($post_id)) {
        $output .= '<div style="margin-bottom:15px;">';
        $output .= '<img src="' . esc_url(get_the_post_thumbnail_url($post_id, 'medium')) . '" style="width:100%; height:auto; border-radius:6px; border:1px solid #ddd;" />';
        $output .= '<p style="font-size:12px; color:#666; margin-top:8px;">Uploading a new image will instantly replace the current featured image.</p>';
        $output .= '</div>';
    } else {
        $output .= '<p style="font-size:13px; color:#666; margin-bottom:15px;">No featured image set. Upload one below (Auto-crops to 1200x630 WebP).</p>';
    }

    // Upload Form
    $output .= '<form method="POST" enctype="multipart/form-data">';
    $output .= '<input type="file" name="mosque_admin_image" accept="image/*" required style="display:block; margin-bottom:15px; font-size:14px; width: 100%;" />';
    $output .= wp_nonce_field('niz_admin_img_action', 'niz_admin_img_nonce', true, false);
    $output .= '<button type="submit" style="background:#006B3E; color:#fff; font-weight:bold; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; width: 100%;">Upload & Set Featured Image</button>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}