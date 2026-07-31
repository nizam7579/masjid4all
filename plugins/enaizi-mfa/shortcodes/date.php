<?php
// [niz_mfa_date] shortcode - Safe, performant, and self-contained
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_date', 'niz_mfa_date_shortcode');

function niz_mfa_date_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    // 1. GENERATE IMMEDIATE SERVER-SIDE BACKUP CALCULATIONS (PHP)
    $timestamp = time();
    $gregorian_date = date('j M Y', $timestamp);

    // Precise Algorithmic Hijri Calculation (Kuwaiti Algorithm Equivalent)
    $jd = floor($timestamp / 86400) + 2440588;
    $l = $jd - 1948440 + 10632;
    $n = floor(($l - 1) / 10631);
    $l = $l - 10631 * $n + 354;
    $j = (floor((10985 - $l) / 5316)) * (floor((50 * $l) / 17719)) + (floor($l / 5670)) * (floor((43 * $l) / 15238));
    $l = $l - (floor((30 - $j) / 15)) * (floor((17719 * $j) / 50)) - (floor($j / 16)) * (floor((15238 * $j) / 43)) + 29;
    $month = floor((24 * $l) / 709);
    $day = $l - floor((709 * $month) / 24);
    $year = 30 * $n + $j - 30;

    $hijri_months = ['Muharram', 'Safar', "Rabi' al-Awwal", "Rabi' al-Thani", 'Jumada al-Awwal', 'Jumada al-Thani', 'Rajab', "Sha'ban", 'Ramadan', 'Shawwal', "Dhu al-Qi'dah", 'Dhu al-Hijjah'];
    $hijri_date = $day . ' ' . $hijri_months[$month - 1] . ' ' . $year;

    // Generate unique wrapper ID per shortcode instance
    $instance_id = 'niz-date-' . wp_generate_password(8, false);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($instance_id); ?>" class="niz-date-container" style="text-align: right; font-size:13px; line-height: 1.4;">
        <div class="niz-hijri-date"><?php echo esc_html($hijri_date); ?></div>
        <div class="niz-gregorian-date"><?php echo esc_html($gregorian_date); ?></div>
    </div>

    <script>
    (function() {
        function getGeoCookie(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }

        function verifyAndCorrectDate() {
            var container = document.getElementById('<?php echo esc_js($instance_id); ?>');
            if (!container) return;

            var hijriEl = container.querySelector('.niz-hijri-date');
            if (!hijriEl) return;

            var now = new Date();
            var lat = getGeoCookie('latitude') || getGeoCookie('lat') || '3.20100';
            var lng = getGeoCookie('longitude') || getGeoCookie('lng') || '101.71758';
            
            var dayStr = String(now.getDate()).padStart(2, '0');
            var monthStr = String(now.getMonth() + 1).padStart(2, '0');
            var yearStr = now.getFullYear();
            var apiDate = dayStr + '-' + monthStr + '-' + yearStr;

            var url = 'https://api.aladhan.com/v1/gToH/' + apiDate + '?latitude=' + lat + '&longitude=' + lng;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.data && res.data.hijri) {
                            var h = res.data.hijri;
                            hijriEl.textContent = h.day + ' ' + h.month.en + ' ' + h.year;
                        }
                    } catch(e) {}
                }
            };
            xhr.send();
        }

        // Execute natively within safe lifecycle steps without mutating external variables
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', verifyAndCorrectDate);
        } else {
            verifyAndCorrectDate();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}