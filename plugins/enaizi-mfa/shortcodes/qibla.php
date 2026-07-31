<?php
// shortcodes/qibla.php - Static Shell for Edge Caching
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
        
        <div class="niz-status-box" style="text-align: center; margin-bottom: 15px; font-size: 14px; font-weight: 600; padding: 10px; background: #f8fafc; border-radius: 8px; color: #334155; border: 1px solid #e2e8f0;">
            🧭 Press Start to align compass
        </div>

        <div class="niz-compass-container" style="position: relative; width: 250px; height: 250px; margin: 0 auto;">
            <div class="niz-circle" style="width: 100%; height: 100%; border: 4px solid #0f766e; border-radius: 50%; position: absolute; z-index: 1; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                <span style="font-size: 24px; opacity: 0.3;">🕋</span>
            </div>
            
            <div class="niz-arrow-container" style="width: 100%; height: 100%; position: absolute; z-index: 2;">
                <div class="niz-arrow" style="width: 6px; height: 120px; background: #006B3E; margin: 0 auto; position: relative; top: 5px; border-radius: 4px 4px 0 0;">
                     <div style="width: 0; height: 0; border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 20px solid #006B3E; position: absolute; top: -15px; left: -7px;"></div>
                </div>
            </div>
        </div>

        <div class="niz-button-wrap" style="text-align: center; margin-top: 20px;">
            <button class="niz-btn" style="padding: 10px 24px; background: #0f766e; color: #fff; border: none; border-radius: 24px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                Start Qibla Finder
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}