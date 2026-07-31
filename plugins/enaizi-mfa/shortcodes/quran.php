<?php
// shortcodes/daily-quran.php - Highly Optimized, Edge-Safe Quran Loader
if (!defined('ABSPATH')) exit;

// Register Shortcodes
add_shortcode('daily_quran', 'm4a_daily_quran_shortcode');
add_shortcode('quran_surah_selector', 'm4a_quran_surah_selector_shortcode');

// Register AJAX Handlers for Edge-Safe Dynamic Loading
add_action('wp_ajax_m4a_load_single_quran_surah', 'm4a_load_single_quran_surah_handler');
add_action('wp_ajax_nopriv_m4a_load_single_quran_surah', 'm4a_load_single_quran_surah_handler');

/**
 * Shortcode: Core Display Shell
 */
function m4a_daily_quran_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    $current_post_id = get_the_ID();
    $meta_surah = $current_post_id ? get_post_meta($current_post_id, 'surah', true) : '';
    $instance_id = 'm4a-quran-' . wp_generate_password(8, false);

    ob_start(); 
    ?>
    <style>
        .m4a-quran-container { 
            max-width: 900px; 
            margin: 20px auto; 
            font-family: system-ui, -apple-system, sans-serif; 
            border: 1px solid #e2e8f0; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            background: #fff; 
            min-height: 200px; 
        }
        .m4a-quran-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 150px; 
            color: #64748b;
            font-size: 16px;
            gap: 10px;
        }
        .m4a-quran-columns { display: flex; flex-direction: column; gap: 30px; }
        @media (min-width: 768px) {
            .m4a-quran-columns { flex-direction: row; }
            .m4a-column-left, .m4a-column-right { flex: 1; width: 50%; }
            .m4a-column-left { border-right: 1px solid #f1f5f9; padding-right: 25px; }
        }
        .m4a-spinner {
            animation: m4a-spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes m4a-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div id="<?php echo esc_attr($instance_id); ?>" 
         class="m4a-quran-container" 
         data-ajaxurl="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" 
         data-surah="<?php echo esc_attr($meta_surah); ?>">
        
        <div class="m4a-quran-loading">
            <span class="m4a-spinner">⏳</span> Loading Surah Reflections...
        </div>
        <div class="m4a-quran-loaded-content" style="display: none;"></div>
    </div>

    <script>
    (function() {
        var instanceId = '<?php echo esc_js($instance_id); ?>';
        function initQuranFetch() {
            var el = document.getElementById(instanceId);
            if (!el) return;

            var ajaxUrl = el.getAttribute('data-ajaxurl');
            var surahId = el.getAttribute('data-surah');

            var formData = new FormData();
            formData.append('action', 'm4a_load_single_quran_surah');
            formData.append('surah_id', surahId);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(res) { return res.text(); })
                .then(function(html) {
                    var loading = el.querySelector('.m4a-quran-loading');
                    var content = el.querySelector('.m4a-quran-loaded-content');
                    if (loading) loading.style.display = 'none';
                    if (content) {
                        content.innerHTML = html;
                        content.style.display = 'block';
                    }
                })
                .catch(function(err) { console.error('Quran Widget Error:', err); });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initQuranFetch);
        } else {
            initQuranFetch();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler: Processes and renders exactly ONE Surah
 */
function m4a_load_single_quran_surah_handler() {
    global $wpdb;

    $surah_id = isset($_POST['surah_id']) ? intval($_POST['surah_id']) : 0;

    // If no specific post meta surah matches, pick ONE random fallback surah entirely in PHP
    if ($surah_id <= 0) {
        $random_pool = [112, 108, 110, 103, 113, 114, 106, 109, 105, 111, 94, 97, 107, 102, 104, 95];
        $surah_id = $random_pool[array_rand($random_pool)];
    }

    $cache_key = 'm4a_alquran_v5_' . $surah_id;
    $data = get_transient($cache_key);

    if (false === $data) {
        $url = sprintf('https://api.alquran.cloud/v1/surah/%d/editions/quran-uthmani,en.transliteration,en.sahih', $surah_id);
        $response = wp_remote_get($url, ['timeout' => 8, 'headers' => ['Accept' => 'application/json']]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($json['data']) && is_array($json['data']) && count($json['data']) >= 3) {
                $data = $json['data'];
                set_transient($cache_key, $data, DAY_IN_SECONDS);
            }
        }
    }

    if (empty($data)) {
        echo '<p style="text-align:center;color:#ef4444;">Unable to load translation. Please refresh.</p>';
        wp_die();
    }

    $name        = $data[2]['englishName'] ?? 'Surah Reading';
    $arabic      = $data[0]['ayahs'] ?? [];
    $translit    = $data[1]['ayahs'] ?? [];
    $translation = $data[2]['ayahs'] ?? [];
    $audio_url   = sprintf('https://cdn.islamic.network/quran/audio-surah/128/ar.alafasy/%d.mp3', $surah_id);
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007cba; padding-bottom: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0; color: #1e293b; font-size: 1.25rem;">📖 Surah <?php echo esc_html($name); ?></h3>
    </div>

    <div class="m4a-quran-columns">
        <div class="m4a-column-left">
            <h4 style="margin: 10px 0 8px 0; color: #007cba; font-size: 15px; font-weight: 600;">🎧 Listen (Mishary Alafasy)</h4>
            <div class="m4a-audio-player" style="margin-bottom: 20px;">
                <audio class="m4a-audio-element" controls style="width: 100%;" preload="none">
                    <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                </audio>
            </div>
            <div class="m4a-arabic" dir="rtl" style="font-size: 26px; line-height: 2.3; text-align: right; margin-bottom: 25px; font-family: 'Traditional Arabic', serif; background: #fafafa; padding: 18px; border: 1px solid #f1f5f9; border-radius: 6px; color: #0f172a;">
                <?php foreach ($arabic as $ayah) { 
                    echo esc_html($ayah['text']) . ' ﴿' . esc_html($ayah['numberInSurah']) . '﴾ '; 
                } ?>
            </div>
        </div>

        <div class="m4a-column-right">
            <h4 style="padding-top:10px; margin: 0 0 5px 0; color: #007cba; font-size: 15px; font-weight: 600;">Transliteration</h4>
            <div class="m4a-transliteration" style="font-style: italic; color: #475569; margin-bottom: 25px; line-height: 1.6; font-size: 14px;">
                <?php foreach ($translit as $ayah) { 
                    echo '<p style="margin: 8px 0;"><strong>' . esc_html($ayah['numberInSurah']) . '.</strong> ' . wp_kses_post($ayah['text']) . '</p>'; 
                } ?>
            </div>

            <h4 style="margin: 15px 0 5px 0; color: #007cba; font-size: 15px; font-weight: 600;">Translation (Saheeh International)</h4>
            <div class="m4a-translation" style="margin-bottom: 20px; line-height: 1.6; color: #1e293b; font-size: 14px;">
                <?php foreach ($translation as $ayah) { 
                    echo '<p style="margin: 8px 0;"><strong>' . esc_html($ayah['numberInSurah']) . '.</strong> ' . wp_kses_post($ayah['text']) . '</p>'; 
                } ?>
            </div>
        </div>
    </div>
    <?php
    wp_die();
}

/**
 * Shortcode: Surah Selector Dropdown
 */
function m4a_quran_surah_selector_shortcode() {
    $levels = [
        'LEVEL 1: Super Short & Familiar (3 to 4 Verses)' => [
            'Al-Ikhlas'   => 'al-ikhlas',
            'Al-Kawthar'  => 'al-kawthar',
            'An-Nasr'     => 'an-nasr',
            'Al-Asr'      => 'al-asr',
            'Al-Falaq'    => 'al-falaq',
            'An-Nas'      => 'an-nas',
            'Quraysh'     => 'quraysh',
            'Al-Kafirun'  => 'al-kafirun',
        ],
        'LEVEL 2: Highly Common Daily Prayers (5 to 8 Verses)' => [
            'Al-Fil'       => 'al-fil',
            'Al-Masad'     => 'al-masad',
            'Ash-Sharh'    => 'ash-sharh',
            'Al-Qadr'      => 'al-qadr',
            'Al-Ma’un'     => 'al-maun',
            'At-Takathur'  => 'at-takathur',
            'Al-Humazah'   => 'al-humazah',
            'At-Tin'       => 'at-tin',
        ],
        'LEVEL 3: Medium Length & Narrative (8 to 11 Verses)' => [
            'Ad-Duha'      => 'ad-duha',
            'Al-Qari’ah'   => 'al-qariaah',
            'Az-Zalzalah'  => 'az-zalzalah',
            'Al-Bayyinah'  => 'al-bayyinah',
            'Al-Adiyat'    => 'al-adiyat',
            'Ash-Shams'    => 'ash-shams',
            'Al-Layl'      => 'al-layl',
        ],
        'LEVEL 4: Deeper, Extended Reflection (15+ Verses)' => [
            'Al-A’la'       => 'al-ala',
            'Al-Alaq'      => 'al-alaq',
            'At-Tariq'     => 'at-tariq',
            'Al-Balad'     => 'al-balad',
            'Al-Ghashiyah' => 'al-ghashiyah',
            'Al-Inshiqaq'  => 'al-inshiqaq',
            'Al-Infitar'   => 'al-infitar',
            'Al-Buruj'     => 'al-buruj',
            'Al-Fajr'      => 'al-fajr',
            'Al-Mutaffifin'=> 'al-mutaffifin',
        ]
    ];

    ob_start();
    ?>
    <div class="m4a-surah-selector-wrapper" style="max-width: 400px; margin: 20px 0; font-family: system-ui, sans-serif;">
        <select id="m4a-surah-dropdown" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #fff; font-size: 15px; color: #333; cursor: pointer;">
            <option value="" disabled selected>-- Choose a Surah --</option>
            <?php foreach ($levels as $level_title => $surahs) : ?>
                <optgroup label="<?php echo esc_attr($level_title); ?>" style="font-style: normal; font-weight: bold; color: #007cba; background: #f8fafc;">
                    <?php foreach ($surahs as $name => $slug) : ?>
                        <option value="<?php echo esc_url(home_url('/quran/' . $slug . '/')); ?>" style="color: #333; background: #fff; padding: 4px 8px; font-weight: normal;">
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var dropdown = document.getElementById('m4a-surah-dropdown');
        if (dropdown) {
            dropdown.addEventListener('change', function() {
                var targetUrl = this.value;
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}