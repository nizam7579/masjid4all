<?php
/**
 * Plugin Name: Affiliate + Auto‑Updating Location Tracker
 * Description: Tracks affiliate ID and continuously updates location (country, city, lat/lon) as the user moves.
 * Version:     3.0
 */

// ------------------------------------------------------------------
// 1. HOOK: Handle affiliate cookie BEFORE any output
// ------------------------------------------------------------------

/*

add_action('init', 'affiliate_location_init');
function affiliate_location_init() {
    // Only run on frontend requests (but keep it light anyway)
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $ref_id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $aff_cookie   = isset($_COOKIE['affiliateid']) ? (int) $_COOKIE['affiliateid'] : 0;
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID ?: 0;

    $affiliate_id = $user_id ?: ($ref_id ?: $aff_cookie);
 
    if ($affiliate_id && $affiliate_id !== $aff_cookie) {
        setcookie('affiliateid', $affiliate_id, [
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_COOKIE['affiliateid'] = $affiliate_id;
    }
}

// ------------------------------------------------------------------
// 2. SHORTCODE: display location + JavaScript geo watcher
// ------------------------------------------------------------------
add_shortcode('cookies_set_cookies', 'cookies_set_cookies_shortcode');
function cookies_set_cookies_shortcode() {
    
    // Do nothing in admin, REST API, AJAX, or cron
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || defined('REST_REQUEST')) {
        return '';
    }
    
    static $script_printed = false;
    ob_start();

    // Read current location cookies (may be empty initially)
    $country   = $_COOKIE['country']   ?? '';
    $city      = $_COOKIE['city']      ?? '';
    $aff_id    = $_COOKIE['affiliateid']      ?? '';
    $latitude  = isset($_COOKIE['latitude'])  ? (float) $_COOKIE['latitude']  : 0;
    $longitude = isset($_COOKIE['longitude']) ? (float) $_COOKIE['longitude'] : 0;
    $geo_time  = isset($_COOKIE['geo_time'])  ? (int)   $_COOKIE['geo_time']  : 0;

    // Refresh if missing any data OR older than 10 minutes
    $should_refresh = (
        empty($country) ||
        empty($city) ||
        $latitude == 0 ||
        $longitude == 0 ||
        (time() - $geo_time) > 600
    );

    // ------------------------------------------------------------------
    // 3. JavaScript: WATCH position + reverse geocoding
    // ------------------------------------------------------------------
    if ($should_refresh && !$script_printed) {
        $script_printed = true;
        ?>
        <script>
        (function() {
            // ---------- helper: set cookie with 30 days expiry ----------
            function setCookie(name, value) {
                var expires = new Date();
                expires.setTime(expires.getTime() + 30 * 24 * 60 * 60 * 1000);
                document.cookie = name + '=' + encodeURIComponent(value) +
                    '; path=/' +
                    '; expires=' + expires.toUTCString();
            }

            function getCookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : null;
            }

            // ---------- update timestamp ----------
            function markTime() {
                var now = Math.floor(Date.now() / 1000);
                setCookie('geo_time', now);
            }

            // ---------- notify other modules ----------
            function fireGeoUpdated() {
                if (window.dispatchEvent) {
                    var event;
                    try {
                        event = new Event('geoUpdated');
                    } catch(e) {
                        event = document.createEvent('Event');
                        event.initEvent('geoUpdated', true, true);
                    }
                    window.dispatchEvent(event);
                }
                updateDisplayedLocation();
            }

            // ---------- reverse geocode using Nominatim ----------
            var pendingGeocode = false;
            function reverseGeocode(lat, lon, callback) {
                if (pendingGeocode) return; // avoid overlapping requests
                pendingGeocode = true;
                var url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lon + '&zoom=18&addressdetails=1';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        pendingGeocode = false;
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                var country = data.address.country || '';
                                var city = data.address.city || data.address.town || data.address.village || '';
                                callback(country, city);
                            } catch(e) {
                                callback('', '');
                            }
                        } else {
                            callback('', '');
                        }
                    }
                };
                xhr.send();
            }

            // ---------- update location, country, city (only if changed) ----------
            var lastLat = parseFloat(getCookie('latitude') || 0);
            var lastLon = parseFloat(getCookie('longitude') || 0);

            function updateLocation(lat, lon, country, city) {
                var moved = Math.hypot(lat - lastLat, lon - lastLon) > 0.01; // ~1km threshold
                var countryChanged = (country && country !== getCookie('country'));
                var cityChanged = (city && city !== getCookie('city'));

                if (moved || lastLat === 0 || lastLon === 0 || countryChanged || cityChanged) {
                    setCookie('latitude', lat);
                    setCookie('longitude', lon);
                    if (country) setCookie('country', country);
                    if (city) setCookie('city', city);
                    fireGeoUpdated();
                    lastLat = lat;
                    lastLon = lon;
                } else {
                    markTime(); // refresh timestamp only
                }
            }

            // ---------- update the page content ----------
            function updateDisplayedLocation() {
                var country = getCookie('country');
                var city = getCookie('city');
                var lat = getCookie('latitude');
                var lng = getCookie('longitude');
                var container = document.getElementById('geo-location-display');
                if (container) {
                    var html = '';
                    if (country && city) {
                        html = '<b>' + escapeHtml(country) + '</b><br>' + escapeHtml(city);
                    } else {
                        html = 'Detecting location...';
                    }
                    if (lat && lng) {
                        var latNum = parseFloat(lat).toFixed(5);
                        var lngNum = parseFloat(lng).toFixed(5);
                        html += '<br>(' + latNum + ', ' + lngNum + ')';
                    }
                    container.innerHTML = html;
                }
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function(m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            // ---------- IP fallback (used only if GPS fails or not supported) ----------
            function ipFallback() {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'https://ipapi.co/json/', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.latitude && data.longitude) {
                                var lat = data.latitude;
                                var lon = data.longitude;
                                reverseGeocode(lat, lon, function(country, city) {
                                    updateLocation(lat, lon, country, city);
                                });
                            } else {
                                markTime();
                            }
                        } catch(e) { markTime(); }
                    }
                };
                xhr.send();
            }

            // ---------- manual retry button ----------
            function showRetryButton() {
                var container = document.getElementById('geo-location-display');
                if (container && !document.getElementById('geo-retry-btn')) {
                    var btn = document.createElement('button');
                    btn.id = 'geo-retry-btn';
                    btn.textContent = 'Enable location for accurate auto‑updates';
                    btn.style.margin = '5px';
                    btn.onclick = function() {
                        startWatching(); // try again
                        this.remove();
                    };
                    container.appendChild(btn);
                }
            }

            // ---------- success handler (GPS watch) ----------
            var watchId = null;
            function onNewPosition(position) {
                var lat = position.coords.latitude;
                var lon = position.coords.longitude;
                reverseGeocode(lat, lon, function(country, city) {
                    updateLocation(lat, lon, country, city);
                });
            }

            function onWatchError(error) {
                if (error.code === error.PERMISSION_DENIED) {
                    showRetryButton();
                } else {
                    // Fallback to IP if GPS fails for other reasons
                    ipFallback();
                }
            }

            function startWatching() {
                if (!navigator.geolocation) {
                    ipFallback();
                    return;
                }
                if (watchId !== null) return; // already watching
                watchId = navigator.geolocation.watchPosition(onNewPosition, onWatchError, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            }

            // ---------- main ----------
            startWatching();

            // Also update display from existing cookies immediately
            updateDisplayedLocation();
        })();
        </script>
        <?php
    }

    // ------------------------------------------------------------------
    // 4. DISPLAY LOCATION (country, city, coordinates) – static fallback
    // ------------------------------------------------------------------
    $lat_disp = $latitude ? number_format($latitude, 5) : '';
    $lng_disp = $longitude ? number_format($longitude, 5) : '';

    echo '<div id="geo-location-display" style="font-size:13px; color:#333; text-align:center;">';
    if (!empty($country) && !empty($city)) {
        echo '<b>' . esc_html($country) . '</b><br>' . esc_html($city);
        if ($lat_disp && $lng_disp) {
            echo '<br>(' . esc_html($lat_disp) . ', ' . esc_html($lng_disp) . ')';
        }
    } else {
        echo 'Detecting location...';
    }
    echo '<br>ID : ' . $aff_id;
    echo '</div>';

    return ob_get_clean();
}


add_action('plugins_loaded', function () {
    add_shortcode('cookies_set_cookies', 'cookies_set_cookies_shortcode');
});

function cookies_set_cookies_shortcode() {
    ob_start();

    // --------------------------
    // CURRENT USER
    // --------------------------
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID ?? 0;

    // --------------------------
    // AFFILIATE HANDLING
    // --------------------------
    $ref_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $affiliate_cookie = isset($_COOKIE['affiliateid']) ? intval($_COOKIE['affiliateid']) : 0;

    $affiliate_id = $user_id ?: ($ref_id ?: $affiliate_cookie);

    if ($affiliate_id && $affiliate_id !== $affiliate_cookie) {
        setcookie("affiliateid", $affiliate_id, [
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_COOKIE['affiliateid'] = $affiliate_id;
    }

    // --------------------------
    // LOCATION COOKIES
    // --------------------------
    $country   = $_COOKIE['country'] ?? '';
    $city      = $_COOKIE['city'] ?? '';
    $latitude  = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 0;
    $longitude = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 0;
    $geo_time  = isset($_COOKIE['geo_time']) ? intval($_COOKIE['geo_time']) : 0;

    // --------------------------
    // SHOULD REFRESH?
    // --------------------------
    $should_refresh = false;

    if (
        empty($country) ||
        empty($latitude) ||
        empty($longitude) ||
        (time() - $geo_time > 600)
    ) {
        $should_refresh = true;
    }

    // --------------------------
    // GEO SCRIPT (CLEAN)
    // --------------------------
    if ($should_refresh) {

        echo "<script>
        (function(){
        
            function setCookie(name, value){
                var expires = new Date();
                expires.setTime(expires.getTime() + (30*24*60*60*1000));

                document.cookie = name + '=' + value +
                    '; path=/' +
                    '; expires=' + expires.toUTCString();
            }

            function getCookie(name){
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? match[2] : null;
            }

            function markTime(){
                var now = Math.floor(new Date().getTime() / 1000);
                setCookie('geo_time', now);
            }

            function done(){
                markTime();

                // ✅ ONLY notify (no mosque logic)
                if (window.dispatchEvent) {
                    var event;

                    try {
                        event = new Event('geoUpdated');
                    } catch(e) {
                        event = document.createEvent('Event');
                        event.initEvent('geoUpdated', true, true);
                    }

                    window.dispatchEvent(event);
                }
            }

            function distance(lat1, lon1, lat2, lon2){
                return Math.sqrt(
                    Math.pow(lat1 - lat2, 2) +
                    Math.pow(lon1 - lon2, 2)
                );
            }

            function updateLocation(lat, lon, country, city){

                var oldLat = parseFloat(getCookie('latitude') || 0);
                var oldLon = parseFloat(getCookie('longitude') || 0);
                var oldCountry = getCookie('country') || '';

                var moved = distance(lat, lon, oldLat, oldLon) > 0.01;

                if (moved || !oldLat || !oldLon || (country && country !== oldCountry)) {

                    setCookie('latitude', lat);
                    setCookie('longitude', lon);

                    if (country) setCookie('country', country);
                    if (city) setCookie('city', city);

                    done();
                } else {
                    markTime();
                }
            }

            function ipFallback(){

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'https://ipapi.co/json/', true);

                xhr.onreadystatechange = function(){
                    if (xhr.readyState === 4 && xhr.status === 200){
                        try {
                            var d = JSON.parse(xhr.responseText);

                            updateLocation(
                                d.latitude || 0,
                                d.longitude || 0,
                                d.country_name || '',
                                d.city || ''
                            );

                        } catch(e){
                            markTime();
                        }
                    }
                };

                xhr.send();
            }

            if (navigator.geolocation) {

                navigator.geolocation.getCurrentPosition(function(pos){

                    var lat = pos.coords.latitude;
                    var lon = pos.coords.longitude;

                    var country = '';
                    var city = '';

                    if (lat >= 0.8 && lat <= 7.5 && lon >= 99 && lon <= 120) {
                        country = 'Malaysia';
                    }

                    updateLocation(lat, lon, country, city);

                }, ipFallback, {timeout:4000});

            } else {
                ipFallback();
            }

        })();
        </script>";
    }

    // --------------------------
    // DISPLAY
    // --------------------------
    $lat_disp = $latitude ? number_format($latitude, 5) : '';
    $lng_disp = $longitude ? number_format($longitude, 5) : '';

    echo '<div style="font-size:13px; color:#737170; text-align:center;">';
    if (!empty($country)) {
        echo '<b>' . esc_html($country) . '</b><br>';
    } else { 
        echo 'Detecting location...';
    }

    if ($lat_disp && $lng_disp) {
        echo "({$lat_disp}, {$lng_disp})";
    }

    echo '</div>';

    return ob_get_clean();
}



// JS FOR LOCATION
add_action('wp_footer', function () {
    ?>
    <script>
    if (!document.cookie.includes('latitude=')) {
        navigator.geolocation.getCurrentPosition(function(p){
            document.cookie = "latitude=" + p.coords.latitude + "; path=/; max-age=" + (86400*30);
            document.cookie = "longitude=" + p.coords.longitude + "; path=/; max-age=" + (86400*30);
            // Optional: send to AJAX for reverse city lookup
        });
    }
    </script>
    <?php
});


// COUNTRY FILTER
add_action('init', function() {
    if (!empty($_GET['country'])) {
        $country = sanitize_text_field($_GET['country']);
        $country = ucfirst($country);
        setcookie('country', $country, time() + (86400 * 365), '/'); // 365-day cookie
        $_COOKIE['country'] = $country; // Set it manually for immediate access
    }else{
        $country = $_COOKIE['country'] ?? '';
        
        if ($country==''){
            // If location not set, fetch from IP API and set cookies
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $response = wp_remote_get("http://ip-api.com/json/{$ip}");
        
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
        
                if (!empty($data['country'])) {
                    $country = sanitize_text_field($data['country']);
                    // Set location cookies via JavaScript
                    echo "<script>
                        document.cookie = 'country=' + encodeURIComponent('$country') + '; path=/';
                    </script>";
                }
            }
        }
 
    }
}); 
  
add_shortcode('cookie_country_filter', function() {

    // Priority order: GET > search_country cookie > country cookie
    $country = filter_input(INPUT_GET, 'country', FILTER_SANITIZE_SPECIAL_CHARS)
        ?: ($_COOKIE['search_country'] ?? '')
        ?: ($_COOKIE['country'] ?? '');

    return esc_html($country);
});

add_filter('jet-engine/listings/dynamic-tags/custom-tags', function($tags) {
    $tags['country_cookie'] = [
        'label' => 'Country Cookie',
        'cb'    => function() {
            return sanitize_text_field($_COOKIE['country'] ?? '');
        }
    ];
    return $tags;
});


// Register shortcode
add_shortcode('get_user_locationX', 'user_locationX_shortcode');

function user_locationX_shortcode() {
    ob_start();
    ?>
    
    <script>
    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days*24*60*60*1000));
        let expires = "expires=" + d.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
    }
    
    function fallbackToIP() {
        fetch("https://ip-api.com/json/?fields=lat,lon")
            .then(response => response.json())
            .then(data => {
                setCookie('latitude', data.lat, 7);
                setCookie('longitude', data.lon, 7);
            });
    }
    
    function getLocation(firstTry = true) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    setCookie('latitude', position.coords.latitude, 7);
                    setCookie('longitude', position.coords.longitude, 7);
                },
                function(error) {
                    if (firstTry && error.code === error.PERMISSION_DENIED) {
                        // Show retry button
                        document.getElementById('geo-retry').style.display = 'block';
                    } else {
                        fallbackToIP();
                    }
                }
            );
        } else {
            fallbackToIP();
        }
    }
    
    document.addEventListener("DOMContentLoaded", function () {
        getLocation();
    });
    </script>
    
    <!-- Retry button -->
    <div id="geo-retry" style="display:none; margin-top:10px;">
        <p>We couldn't access your location. Please allow access for better accuracy.</p>
        <button onclick="getLocation(false)">Retry Location Access</button>
    </div>
    
    <?php
    return ob_get_clean();
}

// Helper function to retrieve lat/lon from cookies (if set)

function get_user_latitude_longitude() {
    $lat = isset($_COOKIE['latitude']) ? sanitize_text_field($_COOKIE['latitude']) : null;
    $lon = isset($_COOKIE['longitude']) ? sanitize_text_field($_COOKIE['longitude']) : null;

    if ($lat && $lon) {
        return [
            'latitude' => $lat,
            'longitude' => $lon
        ];
    }
    return null;
}


*/


