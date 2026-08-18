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
        // Position is checked on every page load, because the whole point of
        // this is nearest-mosque: someone who travels must see mosques near
        // where they are now, not where they were. Precision 5 is about a 5km
        // cell - move within it and only the coordinates change; cross into a
        // new one and the city/country are looked up again. Precision 6 is about
        // 1.2km, chosen so the displayed place keeps up with someone travelling
        // rather than only updating every few kilometres.
        var MFA_GEO_CELL = 6;

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
        //
        // 'cell' records which ~5km geohash cell the country/city belong to, so
        // a later position can be compared against it without another lookup.
        function applyLocation(lat, lon, country, city, hash, cell) {
            window.setCookie('latitude', lat);
            window.setCookie('longitude', lon);
            window.setCookie('geohash', hash || encodeGeohash(lat, lon, 9));
            window.setCookie('country', country);
            // Cleared rather than left behind when the geocoder has no name for
            // the place, so it can never describe a different location.
            window.setCookie('city', city || '');
            window.setCookie('cell', cell || encodeGeohash(lat, lon, MFA_GEO_CELL));
            window.setCookie('loc_updated', String(Math.floor(Date.now() / 1000)));
        }

        // Moving inside the same cell cannot change the city or country, so only
        // the coordinates need refreshing - no lookup, no reload, but the next
        // page still gets accurate nearest-mosque distances.
        function updateCoordsOnly(lat, lon) {
            window.setCookie('latitude', lat);
            window.setCookie('longitude', lon);
            window.setCookie('geohash', encodeGeohash(lat, lon, 9));
            window.setCookie('loc_updated', String(Math.floor(Date.now() / 1000)));
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
                    handlePosition(position.coords.latitude, position.coords.longitude, true);
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

        // Decides what a new fix means. Same cell as the stored location -> the
        // city and country still hold, so only the coordinates move. Different
        // cell -> look the place up again and write everything as one unit; if
        // that lookup fails, nothing is written, so a failed refresh can never
        // leave a location describing somewhere the visitor is not.
        function handlePosition(lat, lon, isManual) {
            var newCell = encodeGeohash(lat, lon, MFA_GEO_CELL);
            var oldCell = window.getCookie('cell');

            if (!isManual && oldCell && oldCell === newCell) {
                updateCoordsOnly(lat, lon);
                updateStatusIndicator('✅ Location current');
                finishUpdate();
                return;
            }

            reverseGeocode(lat, lon, function(err, data) {
                if (err) {
                    updateStatusIndicator('⚠️ ' + err);
                    finishUpdate(true);
                    return;
                }

                var movedPlace = (data.country !== window.getCookie('country')) || (data.city !== window.getCookie('city'));
                applyLocation(lat, lon, data.country, data.city, data.geohash, newCell);
                updateStatusIndicator('✅ ' + (data.city ? data.city + ', ' : '') + data.country);
                finishUpdate();

                // Reload only when the place actually changed, so the listings on
                // screen match the new location. A silent in-cell refresh must not
                // reload - that would bounce the page on every single visit.
                if (movedPlace || isManual) {
                    if (!isManual && sessionStorage.getItem('mfaGeoReloaded')) { return; }
                    try { sessionStorage.setItem('mfaGeoReloaded', '1'); } catch (e) {}
                    setTimeout(function() { window.location.reload(); }, 600);
                }
            });
        }

        function finishUpdate(failed) {
            var wrapper = getContainer();
            var btn = wrapper ? wrapper.querySelector('.niz-geo-refresh-btn') : null;
            if (btn) { btn.textContent = failed ? '🔄 Try Again' : '🔄 Update Location'; btn.disabled = false; }
            isUpdating = false;
        }

        // Runs on every page load. Once permission has been granted the browser
        // does not prompt again, so this is silent for returning visitors; if it
        // was denied we do not ask again unasked.
        function autoRefresh() {
            if (!navigator.geolocation || isUpdating) { return; }

            var go = function() {
                isUpdating = true;
                navigator.geolocation.getCurrentPosition(
                    function(pos) { handlePosition(pos.coords.latitude, pos.coords.longitude, false); },
                    function() { finishUpdate(); },
                    // Cheaper than the manual path on purpose: a cached fix up to
                    // 5 minutes old is fine for deciding which city you are in.
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
                );
            };

            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' }).then(function(status) {
                    if (status.state === 'denied') {
                        updateStatusIndicator('📍 Location off');
                        return;
                    }
                    // 'prompt' still asks, which is what a first-time visitor needs.
                    go();
                }).catch(go);
            } else {
                go();
            }
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

            // Always check where the visitor is now. The old code only ever
            // detected once, so someone who travelled kept seeing mosques near
            // wherever they first opened the site.
            setTimeout(autoRefresh, 800);
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