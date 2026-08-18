<?php
// shortcodes/set-cookies.php - Strict GPS Enforcement
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_set_cookies', 'niz_mfa_set_cookies_shortcode');

function niz_mfa_set_cookies_shortcode() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }

    $aff_id = isset($_COOKIE['affiliateid']) ? $_COOKIE['affiliateid'] : '';
    $instance_id = 'niz-geo-' . wp_generate_password(8, false);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($instance_id); ?>" class="niz-geo-master-container" style="font-size:13px; color:#333; text-align:center; line-height: 1.5; padding: 10px;">
        <div class="niz-geo-display-box">

            📍 <span class="niz-city-shell">Location required...</span>,
            <span class="niz-country-shell" style="font-weight:bold;"></span><br>
       </div>
        <div class="niz-geo-controls" style="margin-top: 8px;"></div>
    </div>

    <script>
    (function() {
        var containerId = '<?php echo esc_js($instance_id); ?>';
        var watchId = null;
        var isUpdating = false;
        var MFA_GEO_AJAX = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
        // A stored location used to be trusted forever, so a wrong country sat
        // there for the life of the cookie with only the manual button as a way
        // out. Re-check on a schedule instead.
        var MFA_GEO_MAX_AGE = 7 * 24 * 60 * 60;

        function getContainer() {
            return document.getElementById(containerId);
        }

        window.setCookie = function(name, value) {
            var expires = new Date();
            expires.setTime(expires.getTime() + 30 * 24 * 60 * 60 * 1000);
            document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; expires=' + expires.toUTCString() + '; SameSite=Lax';

            var wrapper = getContainer();
            if (!wrapper) return;

            if (name === 'country') {
                var el = wrapper.querySelector('.niz-country-shell');
                if (el) el.textContent = value;
            }
            if (name === 'city') {
                var el = wrapper.querySelector('.niz-city-shell');
                if (el) el.textContent = value.replace(/-/g, ' ');
            }
            if (name === 'latitude' || name === 'lat') {
                var el = wrapper.querySelector('.niz-lat-shell');
                if (el) el.textContent = parseFloat(value).toFixed(5);
            }
            if (name === 'longitude' || name === 'lng') {
                var el = wrapper.querySelector('.niz-lng-shell');
                if (el) el.textContent = parseFloat(value).toFixed(5);
            }

            if (typeof window.nizSyncGeoDisplays === 'function') {
                window.nizSyncGeoDisplays();
            }
        };

        // One coordinate, one country, one city - written together or not at
        // all. Writing them independently is what let the city and country
        // cookies drift apart and describe two different places.
        function applyLocation(lat, lon, country, city, hash) {
            window.setCookie('latitude', lat);
            window.setCookie('longitude', lon);
            window.setCookie('geohash', hash || encodeGeohash(lat, lon, 9));
            window.setCookie('country', country);
            // Cleared rather than left behind when the geocoder has no name for
            // the place, so it can never describe a different location.
            window.setCookie('city', city || '');
            window.setCookie('loc_updated', String(Math.floor(Date.now() / 1000)));
        }

        function locationIsStale() {
            if (!window.getCookie('latitude') || !window.getCookie('country')) { return true; }
            var updated = parseInt(window.getCookie('loc_updated') || '0', 10);
            if (!updated) { return true; } // stored before this field existed
            return (Math.floor(Date.now() / 1000) - updated) > MFA_GEO_MAX_AGE;
        }

        window.getCookie = function(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        };

        function initializeExistingCookies() {
            var wrapper = getContainer();
            if (!wrapper) return;

            var city = window.getCookie('city');
            var country = window.getCookie('country');
            var lat = window.getCookie('latitude') || window.getCookie('lat');
            var lng = window.getCookie('longitude') || window.getCookie('lng');

            if (city) {
                var el = wrapper.querySelector('.niz-city-shell');
                if (el) el.textContent = city.replace(/-/g, ' ');
            }
            if (country) {
                var el = wrapper.querySelector('.niz-country-shell');
                if (el) el.textContent = country;
            }
            if (lat) {
                var el = wrapper.querySelector('.niz-lat-shell');
                if (el) el.textContent = parseFloat(lat).toFixed(5);
            }
            if (lng) {
                var el = wrapper.querySelector('.niz-lng-shell');
                if (el) el.textContent = parseFloat(lng).toFixed(5);
            }
        }

        function encodeGeohash(lat, lon, precision) {
            precision = precision || 9;
            var bits = [16, 8, 4, 2, 1];
            var base32 = "0123456789bcdefghjkmnpqrstuvwxyz";
            var is_even = true;
            var lat_range = [-90.0, 90.0];
            var lon_range = [-180.0, 180.0];
            var geohash = "";
            var bit = 0;
            var ch = 0;

            while (geohash.length < precision) {
                var mid;
                if (is_even) {
                    mid = (lon_range[0] + lon_range[1]) / 2;
                    if (lon > mid) { ch |= bits[bit]; lon_range[0] = mid; }
                    else { lon_range[1] = mid; }
                } else {
                    mid = (lat_range[0] + lat_range[1]) / 2;
                    if (lat > mid) { ch |= bits[bit]; lat_range[0] = mid; }
                    else { lat_range[1] = mid; }
                }
                is_even = !is_even;
                if (bit < 4) { bit++; }
                else { geohash += base32[ch]; bit = 0; ch = 0; }
            }
            return geohash;
        }

        // Goes through our own endpoint rather than Nominatim directly: that
        // can send an identifying User-Agent, cache by geohash cell and rate
        // limit, none of which a browser can do. callback(err, data) is ALWAYS
        // invoked - the old version only fired on HTTP 200 with an address
        // present, so a throttled or malformed reply left the button disabled
        // and the cookies silently stale.
        function reverseGeocode(lat, lon, callback) {
            var url = MFA_GEO_AJAX + '?action=mfa_geo_locate&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon);
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 15000;
            xhr.onload = function() {
                var payload = null;
                try { payload = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status === 200 && payload && payload.success && payload.data && payload.data.country) {
                    callback(null, payload.data);
                } else {
                    var msg = (payload && payload.data && payload.data.message) ? payload.data.message : ('Lookup failed (' + xhr.status + ')');
                    callback(msg, null);
                }
            };
            xhr.onerror = function() { callback('Network error while looking up your location.', null); };
            xhr.ontimeout = function() { callback('Location lookup timed out.', null); };
            xhr.send();
        }

        function updateStatusIndicator(msg) {
            var wrapper = getContainer();
            if (!wrapper) return;
            var statusEl = wrapper.querySelector('.niz-geo-status-label');
            if (statusEl) statusEl.textContent = msg;
        }

        function manualUpdate() {
            if (isUpdating) return;
            isUpdating = true;

            var wrapper = getContainer();
            if (!wrapper) return;

            var btn = wrapper.querySelector('.niz-geo-refresh-btn');
            if (btn) {
                btn.textContent = '⏳ Waiting for GPS...';
                btn.disabled = true;
            }

            updateStatusIndicator('📍 Accessing device GPS satellites...');

            if (!navigator.geolocation) {
                alert('Your browser does not support GPS location. Please update your device.');
                updateStatusIndicator('❌ GPS Not Supported by Browser');
                if (btn) { btn.textContent = '🔄 Try Again'; btn.disabled = false; }
                isUpdating = false;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var lat = position.coords.latitude;
                    var lon = position.coords.longitude;
                    reverseGeocode(lat, lon, function(err, data) {
                        if (err) {
                            // Leave every cookie exactly as it was. A partial
                            // write is what produced mismatched pairs - a city
                            // from one place shown against another's country.
                            updateStatusIndicator('⚠️ ' + err);
                            if (btn) { btn.textContent = '🔄 Try Again'; btn.disabled = false; }
                            isUpdating = false;
                            return;
                        }

                        applyLocation(lat, lon, data.country, data.city, data.geohash);

                        updateStatusIndicator('✅ Precise GPS Location Saved');
                        if (btn) { btn.textContent = '🔄 Update Location'; btn.disabled = false; }
                        isUpdating = false;

                        // Force a hard reload so the directories fetch the new precise locations instantly
                        setTimeout(function() { window.location.reload(); }, 600);
                    });
                },
                function(error) {
                    var errorMsg = '❌ GPS Error';
                    var alertMsg = '';

                    if (error.code === error.PERMISSION_DENIED) {
                        errorMsg = '❌ Permission Denied';
                        alertMsg = "Location Access Denied!\n\nPlease allow this app to access your location in your browser settings so we can find the Mosques and Businesses nearest to you.";
                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                        errorMsg = '❌ GPS Signal Unavailable';
                        alertMsg = "GPS Unavailable!\n\nPlease make sure Location Services are turned ON in your phone's main settings.";
                    } else if (error.code === error.TIMEOUT) {
                        errorMsg = '❌ GPS Request Timed Out';
                        alertMsg = "GPS Timeout!\n\nIt took too long to get a signal. Try stepping outside or connecting to Wi-Fi to help the GPS lock on.";
                    }

                    updateStatusIndicator(errorMsg);
                    if (alertMsg) alert(alertMsg);

                    if (btn) { btn.textContent = '🔄 Update Location'; btn.disabled = false; }
                    isUpdating = false;
                },
                // ENFORCING STRICT GPS RULES:
                // High accuracy required, 15 seconds to lock, do not accept old cached positions
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }

        function buildUIControls() {
            var wrapper = getContainer();
            if (!wrapper) return;

            var controlBox = wrapper.querySelector('.niz-geo-controls');
            if (!controlBox) return;

            controlBox.innerHTML =
                '<button class="niz-geo-refresh-btn" style="margin:8px; padding:6px 12px; background:#006B3E; color:white; border:none; border-radius:20px; cursor:pointer; font-size:11px;">🔄 Update Location</button>' +
                '<br><div class="niz-geo-status-label" style="font-size:11px; margin-top:4px; color:#64748b;">📍 Standby</div>';

            controlBox.querySelector('.niz-geo-refresh-btn').onclick = manualUpdate;
        }

        function init() {
            initializeExistingCookies();
            buildUIControls();

            if (locationIsStale()) {
                // Wait 1 second before prompting on first load so it isn't too jarring
                setTimeout(manualUpdate, 1000);
            } else {
                updateStatusIndicator('✅ Location Loaded');
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}