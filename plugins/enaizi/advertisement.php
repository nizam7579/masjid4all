<?php

//add_shortcode('random_images', 'random_images_shortcode');
//function random_images_shortcode($atts) {
// [advertisement_display count="2"] 
add_shortcode('advertisement_display', 'advertisement_display_shortcode');

function advertisement_display_shortcode($atts) {

    // Get shortcode attribute
    $atts = shortcode_atts([
        'count' => 2, // default
    ], $atts);

    $count = intval($atts['count']);

    // Safety limits
    if ($count < 1) $count = 1;
    if ($count > 6) $count = 6; // prevent layout breaking

    // 🔧 Your images + URLs
    $items = [
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Perancangan-Pewarisan-Digital.jpeg',
            'url' => 'https://pewarisan.my/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Rancang-Pewarisan-tanpa-Pening.jpeg',
            'url' => 'https://pewarisan.my/rancang/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Urus-Pusaka.jpeg',
            'url' => 'https://pewarisan.my/pusaka/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Hibah-Harta-Bercagar.jpeg',
            'url' => 'https://pewarisan.my/hibah/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Program-Affiliate.jpeg',
            'url' => 'https://pewarisan.my/affiliate/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/KhairatPlus.jpeg',
            'url' => 'https://pewarisan.my/khairatplus/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Faraid-Calculator.jpeg',
            'url' => 'https://pewarisan.my/faraid/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Hibah.jpeg',
            'url' => 'https://pewarisan.my/hibah/'
        ],
    ];

    // Shuffle and pick N items
    shuffle($items);
    $selected = array_slice($items, 0, $count);

    ob_start();
    ?>

    <style>
    .random-image-grid {
        display: grid;
        gap: 12px;
    }

    .random-image-grid img {
        width: 100%;
        height: auto;
        border-radius: 8px;
        transition: transform 0.3s ease;
    }

    .random-image-grid img:hover {
        transform: scale(1.03);
    }

    /* Mobile = always 1 column */
    .random-image-grid {
        grid-template-columns: 1fr;
    }

    /* Desktop = dynamic columns */
    @media (min-width: 768px) {
        .random-image-grid.columns-1 { grid-template-columns: 1fr; }
        .random-image-grid.columns-2 { grid-template-columns: 1fr 1fr; }
        .random-image-grid.columns-3 { grid-template-columns: 1fr 1fr 1fr; }
        .random-image-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }
        .random-image-grid.columns-5 { grid-template-columns: repeat(5, 1fr); }
        .random-image-grid.columns-6 { grid-template-columns: repeat(6, 1fr); }
    }
    </style>

    <div class="random-image-grid columns-<?php echo esc_attr($count); ?>">
        <?php foreach ($selected as $item): ?>
            <a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo esc_url($item['img']); ?>" alt="">
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('advertisement_displayx', 'advertisement_displayx_shortcode');

function advertisement_displayx_shortcode() {

    // 🔧 Add your images + URLs here
    $items = [
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Perancangan-Pewarisan-Digital.jpeg',
            'url' => 'https://pewarisan.my/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Rancang-Pewarisan-tanpa-Pening.jpeg',
            'url' => 'https://pewarisan.my/rancang/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Urus-Pusaka.jpeg',
            'url' => 'https://pewarisan.my/pusaka/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Hibah-Harta-Bercagar.jpeg',
            'url' => 'https://pewarisan.my/hibah/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Program-Affiliate.jpeg',
            'url' => 'https://pewarisan.my/affiliate/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/KhairatPlus.jpeg',
            'url' => 'https://pewarisan.my/khairatplus/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Faraid-Calculator.jpeg',
            'url' => 'https://pewarisan.my/faraid/'
        ],
        [
            'img' => 'https://masjid4all.com/wp-content/uploads/2026/04/Hibah.jpeg',
            'url' => 'https://pewarisan.my/hibah/'
        ],
    ];

    // Shuffle & pick 2
    shuffle($items);
    $selected = array_slice($items, 0, 2);

    ob_start();
    ?>

    <style>
    .random-image-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .random-image-grid img {
        width: 100%;
        height: auto;
        border-radius: 8px;
        transition: transform 0.3s ease;
    }

    .random-image-grid img:hover {
        transform: scale(1.03);
    }

    @media (min-width: 768px) {
        .random-image-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    </style>

    <div class="random-image-grid">
        <?php foreach ($selected as $item): ?>
            <a href="<?php echo esc_url($item['url']); ?>" target="_blank">
                <img src="<?php echo esc_url($item['img']); ?>" alt="">
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    return ob_get_clean();
}

/*

add_shortcode('place_cards_in_ads', 'shortcode_place_cards_in_placeholders');
 
function shortcode_place_cards_in_placeholders() {
    $arr = [36363, 36402, 36404, 36464]; // Your post IDs
    $current_id = (int) get_queried_object_id(); // More reliable than get_the_ID()

    $arr = array_map('intval', $arr);
    $filtered_ids = array_values(array_filter($arr, function($id) use ($current_id) {
        return $id !== $current_id;
    }));

    shuffle($filtered_ids);
    $post_ids = array_slice($filtered_ids, 0, 3);

    $output = '<div id="card-container" style="display: none;">';
    
        foreach ($post_ids as $i => $post_id) {
            if (!get_post_status($post_id)) continue;
    
            $title   = get_the_title($post_id);
            $url     = get_permalink($post_id);
            $excerpt = get_the_excerpt($post_id);
            $img     = get_the_post_thumbnail_url($post_id, 'full');
            if (!$img) $img = 'https://via.placeholder.com/800x600?text=No+Image';
    
            $card_html  = '<a href="' . esc_url($url) . '" target="_self" style="text-decoration: none;">';
            $card_html .= '<div class="clickable-card">';
            $card_html .= '<img src="' . esc_url($img) . '" alt="' . esc_attr($title) . '">';
            $card_html .= '<div class="card-content">';
            $card_html .= '<h3>' . esc_html($title) . '</h3>';
            $card_html .= '<p>' . esc_html($excerpt) . '</p>';
            $card_html .= '</div></div></a>';
    
            $output .= '<div id="card' . ($i + 1) . '" class="hidden-card">' . $card_html . '</div>';
        }
        $output .= '</div>';
    
        // JavaScript to inject each card into its placeholder
        $output .= <<<EOD
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        for (let i = 1; i <= 3; i++) {
            const card = document.getElementById('card' + i);
            const target = document.getElementById('ads' + i);
            if (card && target) {
                target.innerHTML = card.innerHTML;
            }
        }
    });
    </script>
    EOD;

    return $output;
}

*/

