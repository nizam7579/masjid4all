<?php

// SHARE BUTTONS
add_shortcode('social_share', 'wp_social_share_buttons_shortcode');

function wp_social_share_buttons_shortcode() {
    if (!is_singular()) return '';

    $post_url   = urlencode(get_permalink());
    $post_title = urlencode(get_the_title());

    $buttons = '<i>Please Share</i><div class="social-share-buttons" style="display: flex; gap: 10px;">';

    // Facebook
    $buttons .= '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $post_url . '" target="_blank" rel="noopener noreferrer">
                    <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Share on Facebook">
                 </a>';

    // Twitter (X)
    $buttons .= '<a href="https://twitter.com/intent/tweet?url=' . $post_url . '&text=' . $post_title . '" target="_blank" rel="noopener noreferrer">
                    <img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" alt="Tweet">
                 </a>';

    // LinkedIn
    $buttons .= '<a href="https://www.linkedin.com/shareArticle?mini=true&url=' . $post_url . '&title=' . $post_title . '" target="_blank" rel="noopener noreferrer">
                    <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="Share on LinkedIn">
                 </a>';

    // WhatsApp
    $buttons .= '<a href="https://api.whatsapp.com/send?text=' . $post_title . '%20' . $post_url . '" target="_blank" rel="noopener noreferrer">
                    <img src="https://cdn-icons-png.flaticon.com/24/733/733585.png" alt="Share on WhatsApp">
                 </a>';

    $buttons .= '</div>';

    return $buttons;
}



