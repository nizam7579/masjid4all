<?php
if (!defined('ABSPATH')) exit;

add_shortcode('mfa_member_share', 'mfa_member_share_shortcode');
function mfa_member_share_shortcode($atts) {

    $attributes = shortcode_atts(array(
        'post_id' => '',
    ), $atts);

    if (!empty($attributes['post_id'])) {
        $target_id = intval($attributes['post_id']);
    } else {
        $target_id = get_the_ID();
    }

    if (!empty($target_id) && get_permalink($target_id)) {
        $current_url = get_permalink($target_id);
        $share_title = get_the_title($target_id);
    } else {
        global $wp;
        $current_url  = home_url(add_query_arg(array(), $wp->request));
        $share_title  = wp_get_document_title();
        $target_id    = 0;
    }

    if ($target_id && has_post_thumbnail($target_id)) {
        $featured_image = get_the_post_thumbnail_url($target_id, 'medium_large');
    } else {
        $featured_image = get_site_icon_url(300);
    }

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

    if (isset($_COOKIE['affiliateid']) && !empty($_COOKIE['affiliateid'])) {
        $affiliate_id = sanitize_text_field($_COOKIE['affiliateid']);
        if (strpos($current_url, '?') !== false) {
            $current_url .= '&id=' . $affiliate_id;
        } else {
            $current_url .= '/?id=' . $affiliate_id;
        }
    }

    $wa_text   = "*Masjid4All* - Discover nearby mosques, connect with your local Muslim community, support Muslim-friendly businesses, and access trusted Islamic resources—all in one place.\n " . $current_url;
    $copy_text = "𝗠𝗮𝘀𝗷𝗶𝗱𝟰𝗔𝗹𝗹
" . "Discover nearby mosques, connect with your local Muslim community, support Muslim-friendly businesses, and access trusted Islamic resources—all in one place.  " . $current_url;

    $wa_share_url = "https://api.whatsapp.com/send?text=" . urlencode($wa_text);

    ob_start();
    ?>
    <div class="share-inner-layout">

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

            <button class="share-btn copy-btn" data-copy-text="<?php echo esc_attr($copy_text); ?>">
                <span class="btn-icon-wrap">
                    <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="9" y="9" width="13" height="13" rx="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    <svg class="btn-icon-svg btn-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </span>
                <span class="btn-label">Copy Text &amp; Link</span>
            </button>

            <i class="share-modal-desc">Share it via your Social Media platform.</i>
        </div>

    </div>
    <?php
    return ob_get_clean();
}