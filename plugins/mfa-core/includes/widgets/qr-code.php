<?php
if (!defined('ABSPATH')) exit;

add_shortcode('current_page_qr', 'wp_current_page_qrcode_shortcode');
function wp_current_page_qrcode_shortcode($atts) {
    $args = shortcode_atts(array(
        'size' => '150',
    ), $atts);

    global $wp;
    $current_url = home_url(add_query_arg(array(), $wp->request));

    if (is_front_page()) {
        $current_url = home_url('/');
    }

    $encoded_url = urlencode($current_url);

    $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$args['size']}x{$args['size']}&data={$encoded_url}";

    return '<div class="page-qrcode-wrapper">
                <img src="' . esc_url($qr_api_url) . '" alt="QR Code for ' . esc_attr(get_the_title()) . '" width="' . esc_attr($args['size']) . '" height="' . esc_attr($args['size']) . '" style="border:none;" />
            </div>';
}