<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes
 * - Login or Register User
 * - Display User Info
 * - Displays a logout button
 */

/**
 * [niz_user_login] was REMOVED on 2026-08-21.
 *
 * It rendered the .niz-user-box phone login/register form, driven by
 * assets/js/niz-user.js against four unauthenticated AJAX endpoints
 * (niz_user_check / _register / _login / _reset). Those endpoints verified no
 * nonce and were deleted as a security fix; niz_user_reset in particular let
 * anyone reset any member's password. With them gone the form could not work,
 * and it rendered on no published page on either environment anyway - every
 * occurrence was a draft, a revision, an unregistered kadence_element, or a
 * reusable block whose only parents were drafts.
 *
 * The live login is [niz_login] (enaizi-identity), reached through
 * [mfa_auth_tabs] on /member/. Do not confuse the two.
 */





////////////////////////////
add_shortcode('niz_user_share', 'wapi_popup_inner_content_shortcode');
function wapi_popup_inner_content_shortcode($atts) {
    // 1. Handle shortcode attributes (default post_id is empty)
    $attributes = shortcode_atts(array(
        'post_id' => '',
    ), $atts);

    // 2. Determine target URL and Title
    if (!empty($attributes['post_id'])) {
        $target_id   = intval($attributes['post_id']);
        $current_url = get_permalink($target_id);
        
        // Fallback if an invalid post ID was provided
        if (!$current_url) {
            global $wp;
            $current_url = home_url(add_query_arg(array(), $wp->request));
        }
    } else {
        // No post_id set, use the current page/post
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
    }

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
    
    // 3. Define the pre-filled text packages
    $wa_text   = "*Masjid4All* – Connecting Faith, Family, Business and Community.\n " . $current_url;
    $copy_text = "*Masjid4All* – Connecting Faith, Family, Business and Community. " . $current_url;

    // URL encode for the WhatsApp link
    $wa_share_url = "https://api.whatsapp.com/send?text=" . urlencode($wa_text);

    // Start building layout
    ob_start();
    ?>
    <div class="share-inner-layout">
 
        <div class="share-options-grid">
            <!-- Option 1: WhatsApp Link -->
            <a href="<?php echo esc_url($wa_share_url); ?>" target="_blank" class="share-btn whatsapp-btn">
                <span class="btn-icon">💬</span> Share via WhatsApp
            </a>

            <!-- Option 2: Clipboard Copy -->
            <button id="copyShareBtn" class="share-btn copy-btn" data-copy-text="<?php echo esc_attr($copy_text); ?>">
                <span class="btn-icon">📋</span> <span id="copyBtnText">Copy Text & Link</span>
            </button>
            <i class="share-modal-desc">Share it via your Social Media platform.</i>
        </div>
    </div>

    <!-- JavaScript for copying to clipboard -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyBtn = document.getElementById('copyShareBtn');
        const copyBtnText = document.getElementById('copyBtnText');

        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const textToCopy = this.getAttribute('data-copy-text');
                
                navigator.clipboard.writeText(textToCopy).then(function() {
                    // Update button UI temporarily
                    copyBtnText.textContent = '✓ Copied!';
                    copyBtn.classList.add('copied');
                    
                    setTimeout(function() {
                        copyBtnText.textContent = 'Copy Text & Link';
                        copyBtn.classList.remove('copied');
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
    .share-inner-layout { font-family: system-ui, sans-serif; text-align: center; padding: 10px; }
    .share-modal-title { margin: 0 0 10px 0; font-size: 1.4rem; color: #333; font-weight: 600; }
    .share-modal-desc { margin: 0 0 20px 0; color: #666; font-size: 0.95rem; line-height: 1.4; }
    
    .share-options-grid { display: flex; flex-direction: column; gap: 12px; }
    .share-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; border-radius: 6px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; font-size: 1rem; transition: background 0.2s, color 0.2s; }
    
    .whatsapp-btn { background: #25D366; color: white; }
    .whatsapp-btn:hover { background: #1ebd58; }
    
    .copy-btn { background: #f0f0f0; color: #333; }
    .copy-btn:hover { background: #e0e0e0; }
    .copy-btn.copied { background: #e6f4ea; color: #137333; }
    </style>
    <?php
    return ob_get_clean();
}

/////////////


add_shortcode('niz_user_namecard', 'niz_user_namecard_shortcode');

function niz_user_namecard_shortcode() {
    
    ob_start();

    global $post;

    $user_id = 0;
  
    // If viewing a Card post, use the post author
    if ( isset($post->post_type) && $post->post_type === 'post' ) {
        $user_id = (int) $post->post_author;
    } else {
        // Otherwise fallback to logged in user
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        ob_end_clean();
        return '';
    }
    
    $post_id   = niz_user_field_by_userid($user_id, 'post_id');

    // The banner (featured image) + avatar are rendered by the card page
    // template (single.php, category 39) now, so the shortcode no longer emits
    // the featured image itself - avoids showing the banner twice.
    $ret       = '';

    $namecard  = niz_user_field_by_userid($user_id, 'namecard');
    $name      = niz_user_field_by_userid($user_id, 'name');
    $job_title = niz_user_field_by_userid($user_id, 'job_title');
    $intro     = niz_user_field_by_userid($user_id, 'introduction');
    
    $phone     = niz_user_field_by_userid($user_id, 'affiliate_phone');
    $whatsapp  = niz_user_field_by_userid($user_id, 'affiliate_wa');

    $email     = niz_user_field_by_userid($user_id, 'affiliate_email');
    $website   = niz_user_field_by_userid($user_id, 'affiliate_website');

    $facebook  = niz_user_field_by_userid($user_id, 'affiliate_fb');
    $linkedin  = niz_user_field_by_userid($user_id, 'affiliate_linkedin');

    $twitter   = niz_user_field_by_userid($user_id, 'affiliate_x');
    $tiktok    = niz_user_field_by_userid($user_id, 'affiliate_tiktok');
    $youtube   = niz_user_field_by_userid($user_id, 'affiliate_youtube');
    $instagram = niz_user_field_by_userid($user_id, 'affiliate_instagram');


    // Default to empty so the foreach loops below are no-ops for everyone
    // except the hardcoded 'nizamx' case - previously unset entirely
    // ($external_links) or clobbered back to a string right before its own
    // foreach ($products = ''; below), which threw a PHP warning for every
    // other namecard and silently broke the 'nizamx' case's product list too.
    $products       = array();
    $external_links = array();

    if ($namecard=='nizamx'){
        // For Premium Card only
        $products = array(
            array(
                'title' => 'Pewarisan',
                'image' => 'https://staging.masjid4all.com/wp-content/uploads/2026/05/website-14.jpg',
                'desc'  => 'Islamic Inheritance System. Islamic Inheritance System. Islamic Inheritance System. ',
                'url'   => 'https://pewarisan.my'
            ),
            array(
                'title' => 'Masjid4All',
                'image' => 'https://staging.masjid4all.com/wp-content/uploads/2026/05/website-14.jpg',
                'desc'  => 'Global Mosque Directory',
                'url'   => 'https://staging.masjid4all.com'
            ),
    
        );
        
        $external_links = array(
            array('title' => 'Visit Pewarisan Youtube Channel', 'url' => 'https://www.youtube.com/@pewarisandotcom'),
            array('title' => 'Visit my Lazada Store', 'url' => 'https://lazada.com'),
            array('title' => 'Visit my Mudah Store',  'url' => 'https://mudah.my')
        );
    }

    // --- START HTML GENERATION ---
    $ret  .= '<div class="niz-namecard-container">';
    
    // Header Section
    $ret .= '  <div class="niz-card-header">';
    $ret .= '     <h2>' . esc_html($name) . '</h2>';
    if ( ! empty($job_title) ) {
        $ret .= ' <p>' . esc_html($job_title) . '</p>';
    }
    $ret .= '  </div>';
     
    // Introduction (rendered above the social buttons).
    if ( ! empty($intro) ) {
        $ret .= '<div class="niz-card-intro">' . wpautop(wp_kses_post($intro)) . '</div>';
    }

    // Social Buttons
    $buttons_html = '';
    if ( ! empty($phone) ) {
        $buttons_html .= '<a href="tel:' . esc_attr($phone) . '" class="niz-btn niz-btn-phone"><i class="fa-solid fa-phone"></i><span>Phone</span></a>';
    }
    if ( ! empty($whatsapp) ) {
        $wa_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp);
        $buttons_html .= '<a href="' . esc_url($wa_url) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-wa"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>';
    }
    
    if ( ! empty($email) ) {
        $buttons_html .= '<a href="mailto:' . esc_url($email) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-email"><i class="fa-solid fa-envelope"></i><span>Email</span></a>';
    } 
   
    if ( ! empty($website) ) {
        $buttons_html .= '<a href="' . esc_attr($website) . '" class="niz-btn niz-btn-website"><i class="fa-solid fa-globe"></i><span>Website</span></a>';
    }
   
     
    if ( ! empty($linkedin) ) {
        $buttons_html .= '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-linkedin"><i class="fa-brands fa-linkedin-in"></i><span>LinkedIn</span></a>';
    }
    if ( ! empty($facebook) ) {
        $buttons_html .= '<a href="' . esc_url($facebook) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-fb"><i class="fa-brands fa-facebook-f"></i><span>Facebook</span></a>';
    }
    
    if ( ! empty($twitter) ) {
        $buttons_html .= '<a href="' . esc_url($twitter) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-twitter"><i class="fa-brands fa-x-twitter"></i><span>Twitter</span></a>';
    }
    
    if ( ! empty($youtube) ) {
        $buttons_html .= '<a href="' . esc_url($youtube) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-youtube"><i class="fa-brands fa-youtube"></i><span>YouTube</span></a>';
    }
 
    if ( ! empty($tiktok) ) {
        $buttons_html .= '<a href="' . esc_url($tiktok) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-tiktok"><i class="fa-brands fa-tiktok"></i><span>TikTok</span></a>';
    }
    
    if ( ! empty($instagram) ) {
        $buttons_html .= '<a href="' . esc_url($instagram) . '" target="_blank" rel="noopener noreferrer" class="niz-btn niz-btn-instagram"><i class="fa-brands fa-instagram"></i><span>Instagram</span></a>';
    }
    
    if ( ! empty($buttons_html) ) {
        $ret .= '  <div class="niz-buttons-wrapper">' . $buttons_html . '</div>';
    }
    
    // (Introduction is now rendered above the social buttons.)

    // Featured Products (No Title, Top-Aligned, 40/60 Split)
    $featured_html = '';
    foreach ( $products as $prod ) {
        if ( empty($prod['title']) || empty($prod['image']) ) {
            continue;
        }
        
        $has_url = ! empty($prod['url']);
        $card_attr  = $has_url ? 'href="' . esc_url($prod['url']) . '" target="_blank" rel="noopener noreferrer"' : 'href="javascript:void(0);"';
        $link_class = $has_url ? 'niz-prod-link-active' : 'niz-prod-link-inactive';
        
        // CRITICAL: Keep all elements tight on one line to block WordPress wpautop filters
        $featured_html .= '<a ' . $card_attr . ' class="niz-product-row ' . $link_class . '">';
        $featured_html .= '<div class="niz-product-img" style="background-image: url(\'' . esc_url($prod['image']) . '\');"></div>';
        $featured_html .= '<div class="niz-product-content"><div class="niz-product-title">' . esc_html($prod['title']) . '</div><div class="niz-product-desc">' . esc_html($prod['desc']) . '</div></div>';
        $featured_html .= '</a>';
    }

    if ( ! empty($featured_html) ) {
        $ret .= '  <div class="niz-products-list">' . $featured_html . '</div>';
    }

    // External Tree Links
    $links_html = '';
    foreach ( $external_links as $link ) {
        if ( empty($link['title']) || empty($link['url']) ) {
            continue;
        }
        $links_html .= '<a href="' . esc_url($link['url']) . '" target="_blank" rel="noopener noreferrer" class="niz-tree-link">';
        $links_html .= esc_html($link['title']);
        $links_html .= '</a>';
    }

    if ( ! empty($links_html) ) {
        $ret .= '  <div class="niz-tree-links-list">' . $links_html . '</div>';
    }

    $ret .= '</div>';
    
    // --- DATABASE UPDATE ---
    $updated_card = array(
        'ID'           => $post_id, 
        'post_title'   => $name,
        'post_excerpt' => $job_title . '. ' . $intro,
        'post_content' => $ret 
    );
    
    if ( ! empty($post_id) ) {
        $updated_post_id = wp_update_post( $updated_card, true );
    }
    
    // Output everything inside the buffer safely
    echo $ret;

    // Securely flush, clear the active output cache state, and return clean execution
    return ob_get_clean();
}





// current_page_qr moved to mfa-core/includes/widgets/qr-code.php

