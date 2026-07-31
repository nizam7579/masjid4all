<?php

/**
 * Knowledge Hub Directory Shortcode
 */
if (!defined('ABSPATH')) exit;

////////////////
// ============================================
// KNOWLEDGE CAROUSEL SHORTCODE (10 Random)
// ============================================
add_shortcode('niz_knowledge_carousel', function() {
    // Query 10 completely random published knowledge articles
    $query = new WP_Query([
        'post_type'           => 'knowledge',
        'post_status'         => 'publish',
        'posts_per_page'      => 10,
        'orderby'             => 'rand',
        'ignore_sticky_posts' => true,
    ]);

    if (!$query->have_posts()) {
        return '<p style="text-align:center; color:#94a3b8;">No knowledge articles found.</p>';
    }

    wp_enqueue_style('swiper-cdn-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
    wp_enqueue_script('swiper-cdn-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);
    
    wp_enqueue_style('niz-knowledge-css', plugin_dir_url(__FILE__) . '../assets/css/knowledge.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-knowledge-js', plugin_dir_url(__FILE__) . '../assets/js/knowledge.js', ['swiper-cdn-js'], NIZ_MFA_VERSION, true);

    $unique_carousel_id = 'niz-know-swiper-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <div class="niz-carousel-wrapper">
        <div id="<?php echo esc_attr($unique_carousel_id); ?>" class="swiper nizKnowledgeSwiper" data-post-count="<?php echo esc_attr($query->post_count); ?>">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $post_id = get_the_ID();
                    $img = get_the_post_thumbnail_url($post_id, 'medium') ?: 'https://cdn.staging.masjid4all.com/media/placeholder.webp';
                    $excerpt = wp_trim_words(get_the_excerpt(), 15, '...');
                    
                    $terms = get_the_terms($post_id, 'knowledge_category');
                    $cat_label = (!is_wp_error($terms) && !empty($terms)) ? esc_html($terms[0]->name) : 'Knowledge';
                ?>
                    <div class="swiper-slide">
                        <!-- Reusing your exact Knowledge grid card styling -->
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="knowledge-card-link">
                            <div class="knowledge-card">
                                <div class="knowledge-card-img-box">
                                    <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr(get_the_title()); ?>">
                                </div>
                                <div class="knowledge-card-content">
                                    <h3 class="knowledge-name"><?php echo esc_html(get_the_title()); ?></h3>
                                    <p class="knowledge-excerpt"><?php echo esc_html($excerpt); ?></p>
                                </div>
                                <div class="knowledge-card-footer">
                                    <span class="knowledge-date-metric">📅 <?php echo get_the_date('M d, Y'); ?></span>
                                    <span class="knowledge-read-btn">Read ➔</span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});


//////////////
add_shortcode('niz_mfa_knowledge_directory', function($atts) {
    $atts = shortcode_atts([
        'columns' => '3'
    ], $atts);
    
    $cols = intval($atts['columns']);
    if ($cols < 1 || $cols > 4) $cols = 3;

    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $current_knowledge_id = ($post_type === 'knowledge') ? $post_id : 0;

    // Locked 16 Knowledge Categories
    $categories = [
        "Islamic Beliefs", "Quran & Tafsir", "Hadith & Sunnah", "Seerah & History",
        "Fiqh & Ibadah", "Salah (Prayer)", "Fasting (Sawm)", "Zakat & Charity",
        "Hajj & Umrah", "Duas & Adhkar", "Family & Social Life", "Halal Lifestyle",
        "Tazkiyah", "Contemporary Issues", "Da’wah & Community", "Finance & Wealth"
    ];

    wp_enqueue_style('niz-knowledge-css', plugin_dir_url(__FILE__) . '../assets/css/knowledge.css', [], NIZ_MFA_VERSION);
    wp_enqueue_script('niz-knowledge-js', plugin_dir_url(__FILE__) . '../assets/js/knowledge.js', [], NIZ_MFA_VERSION, true);

    $widget_id = 'niz-knowledge-widget-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <style>
        @media (min-width: 768px) {
            #<?php echo $widget_id; ?> #knowledge-list {
                display: grid !important;
                grid-template-columns: repeat(<?php echo $cols; ?>, 1fr) !important;
            }
        }
    </style>

    <div id="<?php echo esc_attr($widget_id); ?>" class="niz-knowledge-wrapper" 
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-current-id="<?php echo esc_attr($current_knowledge_id); ?>">
         
        <div class="niz-knowledge-filter-bar">
            <div class="niz-knowledge-input-group">
                <label>Search Knowledge Hub</label>
                <input type="text" class="niz-knowledge-search" placeholder="Search topics, articles, answers...">
            </div>
            <div class="niz-knowledge-input-group">
                <label>Topic / Category</label>
                <select class="niz-knowledge-category">
                    <option value="">All Topics</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div id="knowledge-list" class="niz-grid-canvas">
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #64748b;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Loading knowledge directory...
            </div>
        </div>
        <button class="load-more-knowledge-btn" style="display: none;">Load More Articles</button>
    </div>
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_niz_mfa_load_more_knowledge', 'niz_mfa_load_more_knowledge_handler');
add_action('wp_ajax_nopriv_niz_mfa_load_more_knowledge', 'niz_mfa_load_more_knowledge_handler');

function niz_mfa_load_more_knowledge_handler() {
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $offset   = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit    = isset($_POST['limit']) ? intval($_POST['limit']) : 9;
    $exclude  = isset($_POST['current_knowledge_id']) ? intval($_POST['current_knowledge_id']) : 0;

    $args = [
        'post_type'           => 'knowledge',
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'offset'              => $offset,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
    ];

    if ($exclude > 0) {
        $args['post__not_in'] = [$exclude];
    }

    if (!empty($search)) {
        $args['s'] = $search;
    }

    if (!empty($category)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'knowledge-category',
                'field'    => 'name',
                'terms'    => $category,
            ]
        ];
    }

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        //echo '';
        wp_die();
    }

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $img = get_the_post_thumbnail_url($post_id, 'medium');
        $excerpt = wp_trim_words(get_the_excerpt(), 50, '...');

        // Pull array of attached topics to display as labels
        $terms = get_the_terms($post_id, 'knowledge-category');
        $cat_label = (!is_wp_error($terms) && !empty($terms)) ? esc_html($terms[0]->name) : 'Knowledge';
        ?>
        <a href="<?php echo esc_url(get_permalink()); ?>" class="knowledge-card-link">
            <div class="knowledge-card">
                
                <?php if (!empty($img)) : ?>
                    <div class="knowledge-card-img-box">
                        <img src="<?php echo esc_url($img); ?>" loading="lazy" alt="<?php echo esc_attr(get_the_title()); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="knowledge-card-content">
                    <h3 class="knowledge-name"><?php echo esc_html(get_the_title()); ?></h3>
                    <span class="knowledge-inline-cat-text">📚 <?php echo $cat_label; ?></span>
                    <p class="knowledge-excerpt"><?php echo esc_html($excerpt); ?></p>
                </div>
                
            </div>
        </a>
        <?php
    }
    wp_reset_postdata();
    wp_die();
}

