<?php
 
/**
 * Web Carousel Shortcode
 * Usage: [niz_web_carousel ids="12,45,78,89,101"]
 */

// 1. Properly Register & Enqueue Swiper Assets
add_action('wp_enqueue_scripts', function() {
    wp_register_style('swiper-cdn-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
    wp_register_script('swiper-cdn-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);
});

add_shortcode('niz_web_carouselx', function($atts) {
    // Accept an array string of up to 10 comma-separated CPT Post IDs
    $atts = shortcode_atts([
        'ids' => '', 
    ], $atts);

    if (empty($atts['ids'])) {
        return '<p style="text-align:center; color:#94a3b8;">Please provide post IDs.</p>';
    }

    // Parse and sanitize string IDs into an array of integers
    $id_array = array_map('intval', explode(',', $atts['ids']));
    $id_array = array_slice($id_array, 0, 10); // Enforce strict 10-item cutoff boundary

    // Query the custom post type using native WP_Query
    $args = [
        'post_type'           => 'web',
        'post__in'            => $id_array,
        'orderby'             => 'post__in', // Keeps the exact order provided in the shortcode
        'posts_per_page'      => 10,
        'ignore_sticky_posts' => true,
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p style="text-align:center; color:#94a3b8;">No matching webs found.</p>';
    }

    // Enqueue registered assets safely now that we know the shortcode is running
    wp_enqueue_style('swiper-cdn-css');
    wp_enqueue_script('swiper-cdn-js');

    // Generate a unique ID per instance to support multiple web carousels on one page
    $unique_id = 'niz-web-swiper-' . uniqid();

    ob_start();
    ?>

    <div class="niz-carousel-wrapper">
        <div id="<?php echo esc_attr($unique_id); ?>" class="swiper nizWebSwiper">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $url = get_permalink();
                    
                    // Fetch the featured image URL, fallback to placeholder if empty
                    $img = get_the_post_thumbnail_url(get_the_ID(), 'large');
                    if (empty($img)) {
                        $img = 'https://cdn.staging.masjid4all.com/media/placeholder.webp';
                    }
                    
                    // Fetch native or auto-generated excerpt, cleanly truncated
                    $excerpt = get_the_excerpt();
                    if (strlen($excerpt) > 95) {
                        $excerpt = substr($excerpt, 0, 130) . '...';
                    }
                ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url($url); ?>" class="niz-web-card">
                            <div class="niz-card-img-box">
                                <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr(get_the_title()); ?>">
                            </div>
                            <div class="niz-card-content">
                                <h3 class="niz-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                                <p class="niz-card-excerpt"><?php echo esc_html($excerpt); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            
            <div class="swiper-pagination"></div>
        </div>
    </div>

    <style>
    .niz-carousel-wrapper { width: 100%; padding: 0px 0 45px 0; position: relative; overflow: hidden; }
    .nizWebSwiper { padding: 10px 10px 40px 10px !important; }
    
    /* Modern Card Layout Structure */
    .niz-web-card { 
        display: flex; 
        flex-direction: column; 
        background: #ffffff; 
        border-radius: 16px; 
        overflow: hidden; 
        text-decoration: none !important; 
        border: 1px solid #f1f5f9; 
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.02); 
        height: 100%; 
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); 
    }
    
    .niz-card-img-box { width: 100%; height: 210px; overflow: hidden; position: relative; background: #f8fafc; }
    .niz-card-img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
    
    .niz-card-content { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
    .niz-card-title { font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; line-height: 1.4; transition: color 0.2s; }
    .niz-card-excerpt { font-size: 13px; color: #64748b; line-height: 1.6; margin: 0 0 10px 0; flex-grow: 1; }
    
    .niz-card-action { font-size: 13px; font-weight: 700; color: #25988B; display: inline-flex; align-items: center; gap: 6px; margin-top: auto; transition: gap 0.2s; }
    
    /* Hover Interaction State Animations */
    .niz-web-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(37, 152, 139, 0.08); border-color: #e2e8f0; }
    .niz-web-card:hover .niz-card-img-box img { transform: scale(1.06); }
    .niz-web-card:hover .niz-card-title { color: #25988B; }
    .niz-web-card:hover .niz-card-action { gap: 10px; }

    /* Custom Swiper Controls Reskin */
    .nizWebSwiper .swiper-pagination-bullet { background: #cbd5e1; opacity: 1; width: 8px; height: 8px; transition: all 0.3s ease; }
    .nizWebSwiper .swiper-pagination-bullet-active { background: #25988B; width: 22px; border-radius: 4px; }
    </style>

    <script>
    (function() {
        function initWebSwiper() {
            if (typeof Swiper !== 'undefined') {
                new Swiper('#<?php echo $unique_id; ?>', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    loop: true,
                    autoplay: {
                        delay: 3500,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true
                    },
                    pagination: {
                        el: '#<?php echo $unique_id; ?> .swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        640: { slidesPerView: 2, spaceBetween: 20 },
                        1024: { slidesPerView: 3, spaceBetween: 24 }
                    }
                });
            } else {
                setTimeout(initWebSwiper, 50);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initWebSwiper);
        } else {
            initWebSwiper();
        }
    })();
    </script>

    <?php
    return ob_get_clean();
});