<?php
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_prayer_times', 'niz_mfa_prayer_times_shortcode');

function niz_mfa_prayer_times_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    $instance_id = 'niz-prayer-' . wp_generate_password(8, false);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($instance_id); ?>"
         class="niz-mfa-prayer-times-wrapper"
         data-prayers='{}'>

        <div class="niz-prayer-location-header">
            📍 <span class="js-niz-prayer-geo">Detecting location...</span>
        </div>

        <div class="niz-prayer-grid-container">
            <?php foreach (['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $name) : ?>
                <div class="niz-prayer-item" data-prayer="<?php echo esc_attr($name); ?>" data-rawtime="">
                    <div class="niz-label"><?php echo esc_html($name); ?></div>
                    <div class="niz-time-val">--:--</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="niz-countdown-card">
            <div class="niz-cd-title">Next Prayer</div>
            <div class="niz-next-name">—</div>
            <div class="niz-countdown-clock">calculating...</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('niz_mfa_loc', 'niz_mfa_loc_shortcode');

function niz_mfa_loc_shortcode() {
    if (is_admin()) return '';

    $instance_id = 'niz-loc-' . wp_generate_password(4, false);
    ob_start();
    ?>
    <span id="<?php echo esc_attr($instance_id); ?>" class="niz-live-location-display niz-loc-target">Detecting...</span>
    <?php
    return ob_get_clean();
}
