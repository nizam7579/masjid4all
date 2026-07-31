<?php
// [niz_mfa_location] shortcode - Displays location from cookies reliably
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_location', 'niz_mfa_location_shortcode');

function niz_mfa_location_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    // Generate a quick unique reference string for this specific shortcode instance
    $instance_id = 'niz-geo-' . wp_generate_password(8, false);

    ob_start();
    ?>
    <!-- Using a unique ID container per instance, but classes for internal targets -->
    <div id="<?php echo esc_attr($instance_id); ?>" class="niz-geo-container" style="text-align: left; font-size:13px; line-height: 1.4;">
        <span class="niz-geo-text">Kuala Lumpur, Malaysia</span><br>
        📍 <span class="niz-geo-coords">3.20100, 101.71758</span>
    </div>

    <script>
    (function() {
        function readCookie(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            var value = match ? decodeURIComponent(match[2]) : null;
            // Clean up cookie formatting if necessary
            return value ? value.replace(/\+/g, ' ') : null;
        }

        window.nizSyncGeoDisplays = function() {
            var city = readCookie('city');
            var country = readCookie('country');
            var lat = readCookie('latitude') || readCookie('lat');
            var lng = readCookie('longitude') || readCookie('lng');

            // Limit looking up elements strictly inside THIS shortcode container instance
            var container = document.getElementById('<?php echo esc_js($instance_id); ?>');
            if (!container) return;

            var textEl = container.querySelector('.niz-geo-text');
            var coordsEl = container.querySelector('.niz-geo-coords');

            if (!textEl || !coordsEl) return;

            if (city && country && lat && lng) {
                textEl.textContent = city.replace(/-/g, ' ') + ', ' + country;
                coordsEl.textContent = parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5);
            }
        };

        // Execute immediately on script parse
        window.nizSyncGeoDisplays();
    })();
    </script>
    <?php
    return ob_get_clean();
}

