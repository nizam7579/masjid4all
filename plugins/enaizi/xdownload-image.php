<?php

/*

function import_image_and_set_thumbnail($atts) {
    $atts = shortcode_atts([
        'post_id' => 0,
        'image_url' => ''
    ], $atts);

    $post_id = intval($atts['post_id']);
    $image_url = esc_url_raw($atts['image_url']);

    if (!$post_id || !$image_url) {
        return 'Missing post_id or image_url.';
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download the image
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return 'Error downloading image.';
    }

    // Prepare file array
    $file_array = [
        'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
        'tmp_name' => $tmp
    ];

    // Upload to Media Library
    $attachment_id = media_handle_sideload($file_array, $post_id);

    // Cleanup
    @unlink($tmp);

    if (is_wp_error($attachment_id)) {
        return 'Error saving image.';
    }

    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);

    return 'Image saved and set as featured image for post ID ' . $post_id;
}

add_shortcode('set_thumbnail_from_url', 'import_image_and_set_thumbnail');

*/
