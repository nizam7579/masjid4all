<?php
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_qibla', 'niz_mfa_qibla_shortcode');

function niz_mfa_qibla_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    $instance_id = 'niz-qibla-' . wp_generate_password(8, false);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($instance_id); ?>" class="niz-qibla-app">

        <div class="niz-status-box">🧭 Press Start to align compass</div>

        <div class="niz-compass-container">
            <div class="niz-circle">
                <span class="niz-circle-kaaba" aria-hidden="true">🕋</span>
            </div>

            <div class="niz-arrow-container">
                <div class="niz-arrow">
                    <span class="niz-arrow-head"></span>
                </div>
            </div>
        </div>

        <div class="niz-button-wrap">
            <button class="niz-btn" type="button">Start Qibla Finder</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
