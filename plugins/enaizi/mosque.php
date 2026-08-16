<?php

/**
  MOSQUE INFO
  1. mosque_display_info
  2. Mosque AI Update Callback 
  3. Build HTML Content 
  4. PERPLEXITY API Call 
  5. Mosque Sidebar
  6. Google Map and Waze Btn
  7. Refresh Button
  
  MOSQUE LISTING
  1. Nearest Mosque Listing 
  2. Mosque Location Exist
  3. Serper Location Indexing 
  4. Serper Nearest Mosque
  
  OTHER FUNCTIONS
  1. Get Field From Masque CCT  
  2. Save CCT Mosque Data
  3. Save CCT Mosque Jetengine
*/

/////// NEAREST MOSQUE LISTING
add_shortcode('nearest_mosque', function() {

    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_mosque";
    
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    
    if ($post_type=='masjid'){
        $mode = 'mosque';
        $mosque_name = get_the_title();
        $item_id = get_post_meta($post_id, 'item_id', true);
        
        $country   = get_cct_mosque_data($item_id, 'country');
        $latitude  = get_cct_mosque_data($item_id, 'latitude');
        $longitude = get_cct_mosque_data($item_id, 'longitude');

    }else{
        $mode = 'user';
        $mosque_name = '';
        $item_id = 0;
        $country   = sanitize_text_field($_COOKIE['country'] ?? 'Malaysia');
        $latitude  = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 3.1390;
        $longitude = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 101.6869;
    } 

    ob_start();
    ?>
    
    <?php if ($mode === 'mosque'): ?>
        <style>
            /* Force single column on mosque page */
            #mosque-list {
                grid-template-columns: 1fr !important;
            }
        </style>
    <?php endif; ?>
    <style>
        #mosque-list {display: grid; grid-template-columns: 1fr;gap: 15px; margin-top: 15px;}
        @media (min-width: 768px) {#mosque-list {grid-template-columns: 1fr 1fr;} }
        .mosque-card {border-top: 2px solid #25988B; padding-top: 0px;}
        .mosque-top-row { display: flex; justify-content: space-between;align-items: center;}
        .mosque-view-btn {display: inline-block;background: #25988B;color: #fff !important; padding: 5px 10px; font-size: 11px;}
        .mosque-name { padding-top:10px; font-weight: bold; color: #125C59; }
        .mosque-address { font-size: 14px; color: #444; }
        .mosque-distance { font-size: 13px; color: #777; }
        #load-more-btn {display: block;margin: 15px auto; margin-top:50px; font-size: 13px; padding: 8px 16px; background: #25988B; color: #fff; border: none; border-radius: 6px; cursor: pointer;}
    
.mosque-cta {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.mosque-cta .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #25988B;
    color: #fff !important;
    padding: 8px 14px;
    border-radius: 999px; /* pill style */
    font-size: 13px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
    white-space: nowrap; /* prevent wrapping */
}

/* Hover */
.mosque-cta .btn:hover {
    background: #1f7f74;
    transform: translateY(-1px);
}

/* Second button */
.mosque-cta .btn:nth-child(2) {
    background: #256C98;
}

.mosque-cta .btn:nth-child(2):hover {
    background: #1e5679;
}
    </style>
    
    <?php if ($mode === 'mosque' && $mosque_name): ?>
        <div style="padding:10px;background:#f5f9f8;border-left:4px solid #25988B;">
            📍 Mosques near <b><?php echo esc_html($mosque_name); ?></b>
        </div>
    <?php else: ?>
        <div>📍 <b><?php echo esc_html($country); ?></b></div>
    <?php endif; ?>
    
    <div id="mosque-list"></div>
    <button id="load-more-btn">Load More Mosques</button>
    
    <script>
        var lastGeoHash = null;
        var hasLoaded = false;    
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        var offset = 0;
        var limit = 10;
    
        function getCookie(name){
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        }
    
        function loadMosques(forceReplace) {
            var formData = new FormData();
            formData.append('action','load_more_mosques');
            formData.append('offset', offset);
            formData.append('limit', limit);
            var mode = '<?php echo $mode; ?>';

            if (mode === 'mosque') {
                // ✅ use mosque location (from PHP)
                formData.append('lat', <?php echo $latitude; ?>);
                formData.append('lng', <?php echo $longitude; ?>);
            } else {
                // ✅ use user location (from cookies)
                formData.append('lat', getCookie('latitude'));
                formData.append('lng', getCookie('longitude'));
            }
            
            if (mode === 'mosque') {
                formData.append('country', '<?php echo esc_js($country); ?>');
            } else {
                formData.append('country', getCookie('country'));
            }
            
            formData.append('mode', '<?php echo $mode; ?>');
            formData.append('mosque_name', '<?php echo esc_js($mosque_name); ?>');
            formData.append('item_id', '<?php echo intval($item_id); ?>');
    
            fetch(ajaxurl, {
                method:'POST',
                body:formData
            })
            .then(function(res){ return res.text(); })
            .then(function(html){
    
                if(html.trim() === '') {
                    document.getElementById('load-more-btn').style.display = 'none';
                    return;
                }
    
                var list = document.getElementById('mosque-list');
    
                if (forceReplace || offset === 0) {
                    list.innerHTML = html; // ✅ no flicker
                } else {
                    list.insertAdjacentHTML('beforeend', html);
                }
    
                offset += limit;
            });
        }
    
        function encodeGeohash(lat, lon, precision) {
            var base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
        
            var latRange = [-90, 90];
            var lonRange = [-180, 180];
        
            var hash = '';
            var bit = 0;
            var ch = 0;
            var even = true;
        
            while (hash.length < precision) {
                if (even) {
                    var mid = (lonRange[0] + lonRange[1]) / 2;
                    if (lon > mid) {
                        ch |= (1 << (4 - bit));
                        lonRange[0] = mid;
                    } else {
                        lonRange[1] = mid;
                    }
                } else {
                    var mid = (latRange[0] + latRange[1]) / 2;
                    if (lat > mid) {
                        ch |= (1 << (4 - bit));
                        latRange[0] = mid;
                    } else {
                        latRange[1] = mid;
                    }
                }
        
                even = !even;
        
                if (bit < 4) {
                    bit++;
                } else {
                    hash += base32[ch];
                    bit = 0;
                    ch = 0;
                }
            }
        
            return hash;
        }
    
        // ✅ Initial load
        document.addEventListener('DOMContentLoaded', function(){
            if (!hasLoaded) {
                loadMosques(true);
                hasLoaded = true;
            }
    
            document.getElementById('load-more-btn').addEventListener('click', function(){
                loadMosques(false);
            });
        });
    
        // ✅ Listen to geo updates (from geo module)
        window.addEventListener('geoUpdated', function(){
    
            var lat = parseFloat(getCookie('latitude') || 0);
            var lng = parseFloat(getCookie('longitude') || 0);
        
            if (!lat || !lng) return;
        
            var newHash = encodeGeohash(lat, lng, 5); // ~5km precision
        
            // 🔥 only reload if area changed
            if (lastGeoHash && lastGeoHash === newHash) {
                return; // no change → no reload
            }
        
            lastGeoHash = newHash;
        
            offset = 0;
            loadMosques(true);
        });
    
    </script>
    
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_load_more_mosques', 'load_more_mosques');
add_action('wp_ajax_nopriv_load_more_mosques', 'load_more_mosques');

function load_more_mosques() {
    global $wpdb;

    $table = $wpdb->prefix . "jet_cct_mosque";

    // --------------------------
    // INPUT
    // --------------------------
    $latitude  = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $longitude = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

    if (!$latitude || !$longitude) {
        wp_die('Location missing');
    }

    $mode = (isset($_POST['mode']) && $_POST['mode'] === 'mosque') ? 'mosque' : 'user';
    $mosque_name = sanitize_text_field($_POST['mosque_name'] ?? '');
    $item_id = intval($_POST['item_id'] ?? 0);

    $offset = intval($_POST['offset'] ?? 0);
    $limit  = intval($_POST['limit'] ?? 10);

    // --------------------------
    // 🔥 GEOHASH CORE
    // --------------------------
    $user_hash = geohash_encode($latitude, $longitude, 6);

    // progressively expand search area
    $prefixes = [
        substr($user_hash, 0, 6), // ~1km
        substr($user_hash, 0, 5), // ~5km
        substr($user_hash, 0, 4), // ~20km
        substr($user_hash, 0, 3), // ~150km
    ];

    $results = [];

    foreach ($prefixes as $prefix) {

        $query = $wpdb->prepare(
            "SELECT name, address, page_url,
            (6371 * ACOS(
                COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
                COS(RADIANS(longitude) - RADIANS(%f)) +
                SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
            )) AS distance
            FROM $table
            WHERE geohash LIKE %s
            ORDER BY distance ASC
            LIMIT %d OFFSET %d",
            $latitude, $longitude, $latitude,
            $prefix . '%',
            $limit, $offset
        );

        $results = $wpdb->get_results($query);

        if ($results && count($results) >= 10) {
            break;
        }
    }

    if (!$results) wp_die();

    // --------------------------
    // OUTPUT
    // --------------------------
    $skip_first = ($mode === 'mosque');
    $i = 0;

    foreach ($results as $m) {

        if ($skip_first && $i === 0) {
            $i++;
            continue;
        }

        $dist = floatval($m->distance);
        $km = number_format($dist, 2);
        $miles = number_format($dist * 0.621371, 2);

        echo "<div class='mosque-card'>
            <div class='mosque-top-row'>
                <div class='mosque-distance'>📍 {$km} km ({$miles} mi)</div>
                <a class='mosque-view-btn' href='{$m->page_url}'>Visit Page</a>
            </div>
            <div class='mosque-name'>" . esc_html($m->name) . "</div>
            <div class='mosque-address'>" . esc_html($m->address) . "</div>
        </div>";
    }

    wp_die();
}


////////////
// to speech up mosque search
function geohash_encode($lat, $lng, $precision = 6) {
    $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    
    $lat_range = [-90.0, 90.0];
    $lng_range = [-180.0, 180.0];

    $hash = '';
    $bit = 0;
    $ch = 0;
    $even = true;

    while (strlen($hash) < $precision) {
        if ($even) {
            $mid = ($lng_range[0] + $lng_range[1]) / 2;
            if ($lng > $mid) {
                $ch |= (1 << (4 - $bit));
                $lng_range[0] = $mid;
            } else {
                $lng_range[1] = $mid;
            }
        } else {
            $mid = ($lat_range[0] + $lat_range[1]) / 2;
            if ($lat > $mid) {
                $ch |= (1 << (4 - $bit));
                $lat_range[0] = $mid;
            } else {
                $lat_range[1] = $mid;
            }
        }

        $even = !$even;

        if ($bit < 4) {
            $bit++;
        } else {
            $hash .= $base32[$ch];
            $bit = 0;
            $ch = 0;
        }
    }

    return $hash;
}
 
// 1. mosque_display_info
add_shortcode('mosques_display_info', function() {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);
    $status  = get_cct_mosque_data($item_id, 'business_status');
    $content = get_the_content();
    $name    = get_the_title($post_id);
    
    ob_start(); ?>
    <div id="mosque-info-container" class="mosque-info-wrapper">
        <?php if ($status === 'Updated' || $content = '') : ?>
            <div class="mosque-actual-content">
                <?php echo apply_filters('the_content', $content); ?>
            </div>
        <?php else : ?>
            <div class="update-prompt-box" style="padding:15px;border:1px dashed #ccc;border-radius:10px;text-align:center;background:#fafafa;">
                <div id="update-spinner">
                    <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#28a745;"></i><br><br>
                    <b><?= esc_html($name); ?></b><br>
                    <span style="color:#28a745;font-weight:600;">
                        Please wait...<br>
                        We are updating the mosque information.
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        <?php if ($status !== 'Updated') : ?>
        // Auto-trigger update
        (function(){
            var postId = <?php echo $post_id; ?>;
            var mosqueName = "<?php echo esc_js(get_the_title()); ?>";
            var spinner = $('#update-spinner');

            spinner.show();

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'process_mosque_ai_update',
                    post_id: postId,
                    mosque_name: mosqueName
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        console.error('Error: ' + (response.data || 'Unknown error'));
                        spinner.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    spinner.hide();
                }
            });
        })();
        <?php endif; ?>
    });
    </script>
    <?php
    return ob_get_clean();
});


// 2. Mosque AI Update Callback
add_action('wp_ajax_process_mosque_ai_update', 'handle_mosque_ai_update_callback');
add_action('wp_ajax_nopriv_process_mosque_ai_update', 'handle_mosque_ai_update_callback'); // Support logged-out users

function handle_mosque_ai_update_callback() {
    // === BOT GUARD: Skip expensive AI calls for bots ===
    if (m4a_is_bot_request()) {
        wp_send_json_success('Skipped (bot)'); // Silent success for bots
        return;
    }
    // ===================================================

    // Check if ID exists
    if (!isset($_POST['post_id'])) {
        wp_send_json_error('Missing Post ID');
    }

    $post_id = intval($_POST['post_id']);
    $item_id = get_post_meta($post_id, 'item_id', true);
    if (!$item_id) {
        wp_send_json_error('Data mapping error.');
    }

    $name     = get_cct_mosque_data($item_id, 'name');
    $address  = get_cct_mosque_data($item_id, 'address');
    $city     = get_cct_mosque_data($item_id, 'city');
    $country  = get_cct_mosque_data($item_id, 'country');
    $latitude = get_cct_mosque_data($item_id, 'latitude');
    $longitude= get_cct_mosque_data($item_id, 'longitude');
    $website  = get_cct_mosque_data($item_id, 'website');
    $place_id = get_cct_mosque_data($item_id, 'place_id');
    $data     = "Name: $name, Address: $address, Latitude: $latitude, Longitude: $longitude, Website: $website, City: $country, Country: $country, PlaceID: $place_id";
    
    $result = mosques_perplexity($data);
    $parsed = json_decode($result, true);
 
    if ( ! is_array( $parsed ) || empty( $parsed['content'] ) ) {
        wp_send_json_error( 'Invalid AI content received.' );
    }
    
    $html_content = $parsed['content']; // <-- use AI content
    $rm_title    = $parsed['title'];
    $rm_excerpt  = $parsed['excerpt'];
    $rm_keywords = $parsed['keywords'];
    $status      = $parsed['status'];
    $country     = $parsed['country'];
    $city        = $parsed['city'];
    $website     = $parsed['website'];
    $email       = $parsed['email'];
    $phone       = $parsed['phone'];
    $whatsapp    = $parsed['whatsapp'];
    
    error_log( 'HTML CONTENT FROM AI: ' . $html_content );
    
    $post_update = wp_update_post([
        'ID'           => $post_id,
        'post_content' => $html_content,
    ], true);
    
    update_post_meta($post_id, 'rank_math_title', $rm_title ?? '');
    update_post_meta($post_id, 'rank_math_description', $rm_excerpt ?? '');
    update_post_meta($post_id, 'rank_math_focus_keyword', $rm_keywords ?? '');

    if ( is_wp_error( $post_update ) ) {
        wp_send_json_error( 'WP Update Failed: ' . $post_update->get_error_message() );
    }
    
    save_cct_mosque_data( $item_id, 'business_status', 'Updated' );
    save_cct_mosque_data( $item_id, 'listing_status', $status );

    if (!empty(trim($country))) {
        save_cct_mosque_data($item_id,'country',sanitize_text_field($country));
    }
    if (!empty(trim($city))) {
        save_cct_mosque_data($item_id,'city',sanitize_text_field($city));
    }    
    if (!empty(trim($website))) {
        save_cct_mosque_data($item_id,'website',sanitize_text_field($website));
    }    
    if (!empty(trim($email))) {
        save_cct_mosque_data($item_id,'email',sanitize_text_field($email));
    }    
    if (!empty(trim($phone))) {
        save_cct_mosque_data($item_id,'phone',sanitize_text_field($phone));
    }    
    if (!empty(trim($whatsapp))) {
        save_cct_mosque_data($item_id,'whatsapp',sanitize_text_field($whatsapp));
    }    

    wp_send_json_success( 'Updated successfully (AI content saved)' );
}


// Masjid4All: Bot detection to prevent AI content triggers
function m4a_is_bot_request(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) {
        return true;
    }
    $ua_lower = strtolower($ua);
    
    // 2026-relevant patterns: search crawlers + AI/LLM bots + HTTP libs [web:24][web:18]
    $bot_patterns = [
        'bot', 'spider', 'crawl', 'crawler', 'slurp', 'archiver',
        'gptbot', 'chatgpt', 'oai-searchbot', 'claudebot', 'anthropic',
        'perplexitybot', 'google-extended', 'bard', 'gemini',
        'bingbot', 'googlebot', 'duckduckbot', 'facebookexternalhit',
        'twitterbot', 'linkedinbot', 'curl', 'python-urllib', 'httpclient', 'java/',
        'wget', 'libwww', 'headlesschrome' // common headless browsers
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (strpos($ua_lower, $pattern) !== false) {
            return true;
        }
    }
    return false;
}


// 4. PERPLEXITY API Call
// PERPLEXITY_API_KEY is resolved in keys.php (wp-config constant, then DB option).
 
function mosques_perplexity($info) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
    if (empty($api_key)) return json_encode(['error' => 'No API Key']);

    /*
    
    $role = "
        You are an experienced SEO content writer for Masjid4All.com, a global mosque directory.
        Generate a valid JSON object with exactly these fields:
        
        {
        \"title\": \"\",
        \"keywords\": \"\",
        \"metaDescription\": \"\",
        \"status\": \"\",
        \"content\": \"\"
        }
        
        Input Data
        You may receive:
        - Mosque Name
        - Address
        - City
        - State
        - Country
        - Latitude
        - Longitude
        - Google PlaceID

        Some fields may be empty.
        
        General Rules
        Output valid JSON only.
        Do not include explanations outside JSON.
        Do not use Markdown.
        Use natural English.
        Write in a factual and neutral tone.
        Never invent facts.
        Never guess facilities, prayer services, affiliations, capacity, or programs.
        Only mention information that is provided or can be reasonably inferred from the mosque type.
        Omit sections when there is insufficient information.
        Do not write \"unknown\", \"not available\", \"N/A\", or similar phrases.

        Status Classification
        Determine one status:
        
        Approved
        Use when the listing is clearly a:
        - Mosque
        - Masjid
        - Surau
        - Musolla
        - Islamic Centre
        - Prayer Hall

        Pending
        Use when:
        - The place may be Islamic but the mosque status is unclear.
        - Insufficient information exists.
        
        Rejected
        Use when:
        - The place is clearly not a mosque.
        - The place is a business, school, hotel, residence, office, or unrelated organization.
        
        
        SEO Requirements
        Focus Keyword
        Focus keyword = full mosque name.
        
        Requirements:
        Include the full mosque name in:
        - Title
        - Meta description
        - H1
        - First paragraph
        - At least one H2 section
        - Mention city and country naturally in the introduction.
        
        Title
        - Length: 50–70 characters.
        
        Format:
        - ((Mosque Name)) | Mosque in ((City)), ((Country))
        - Adjust naturally if city or country is unavailable.
        
        Keywords
        Generate 8–15 keywords.
        
        Include:
        - Mosque name
        - Mosque in city
        - Mosque in state
        - Mosque in country
        - Friday prayer (if applicable)
        - Islamic centre keywords (if applicable)
        - Local worship keywords
        
        Use comma-separated format.
        
        Meta Description
        Length: 150–160 characters.
        
        Must:
        - Include mosque name.
        - Mention city and country.
        - Mention mosque role.
        - Mention Friday prayer if applicable.
        
        Write naturally and encourage visits.
        
        Content Structure
        Generate content as a single HTML string.
        
        Use only:
        
        Section 1
        Section 2
        
        Write 3–5 sentences.
        
        Include:
        
        Mosque name
        City
        State
        Country
        Mosque type if known
        Community served
        Friday prayer if known
        
        The focus keyword must appear in the first two sentences.
        
        Section 3
        
        Include address if available.
        
        Add a short paragraph describing the location.
        <!-- If Place ID / Lat / Lng given -->
        <span class=\"location-btn\">
            <a class=\"btn btn-google-map\" href=\"https://www.google.com/maps/place/?q=place_id:((Google Map Place ID))\" target=\"_blank\" rel=\"noopener\">Google Map</a>    
            <a class=\"btn btn-waze-map\" href=\"https://waze.com/ul?ll=((Latitude)),((Longitude))&navigate=yes\" target=\"_blank\" rel=\"noopener\">Waze</a></p>
        </span>
        
        Section 4
        
        Include only fields that exist.
        - Telephone
        - WhatsApp
        - Website
        - Facebook
        - Instagram
        - YouTube
        - TikTok
        - LinkedIn
        
        For WhatsApp:
        - Remove spaces, brackets, and "+" before creating the wa.me link.
        
        Section 5
        
        Describe only facilities based on information from online resources:
        - Prayer areas
        - Ablution facilities
        - Community space
        
        If capacity is provided, mention it.
        
        Do not invent specific facilities.
        
        Section 6
        
        Write 2–4 paragraphs about:
        
        Daily prayers
        Friday congregational prayers (if applicable)
        Islamic learning
        Community activities
        Role in serving worshippers
        
        Avoid exaggerated claims.
        
        Section 7
        
        Only include when Note or extra information exists.
        
        Section 8
        Final Output
        
        Return valid JSON only.
        
        Required fields:
        
        {
        \"title\": \"...\",
        \"keywords\": \"...\",
        \"metaDescription\": \"...\",
        \"status\": \"...\",
        \"content\": \"...\"
        }
    
    ";
    */
    
    // SEO‑optimized mosque profile prompt (for Malaysian Muslim directory + Rank Math)
    $role = "Prompt (SEO‑friendly mosque profile with Maps, Waze, and H1/H2/H3)

        You are an experienced SEO content writer for Masjid4All.com, a global mosque directory.
        Generate a valid JSON object with exactly these fields:
       
        You must generate a **JSON response** with exactly these fields:
        {\n \"title\": \"...\",\n \"keywords\": \"...\",\n \"metaDescription\": \"...\",\n \"status\": \"...\",\n \"content\": \"...\"\n,\n \"Country\": \"...\"\n,\n \"city\": \"...\"\n,\n \"website\": \"...\"\n,\n \"phone\": \"...\"\n,\n \"email\": \"...\"\n,\n \"whatsapp\": \"...\"\n}
        
        **Input fields you will receive (some may be empty; only output if data exists):**
        - Mosque name
        - Address
        - City
        - State
        - Country
        - Phone
        - WhatsApp
        - Website
        - Google Map Place ID
        - Latitude
        - Longitude
        - Friday prayer (Yes/No, if known)
        - Capacity (optional; e.g., \"Can accommodate several hundred worshippers\")
        - Note (optional; e.g., \"Islamic centre at UiTM Puncak Perdana for students and staff\")
        
        Populate the following fields whenever the information is available:
        - country
        - city
        - website
        - phone
        - whatsapp
        - email
        
        **Rules:**
        - Only include fields in the output when the input is not empty.
        - DO NOT WRITE \"Not available\".
        - Use natural English with clear SEO‑friendly sentences.
        - Use HTML headings H1, H2, H3; do not use markdown or bullet points.
        
        ---
        
        **1. SEO ELEMENTS (top‑level JSON keys)**
        
        title
        Create a short, compelling title (50–70 characters) that includes:
        - Mosque name
        - City
        - Country
        
        keywords
        A comma‑separated list of SEO keywords (8–15 items) including:
        - ((mosque name))
        - mosque in ((city))
        - mosque in ((state))
        - mosque in ((country))
        - main keywords from the content
        
        Include the **focus keyword** (the full mosque name) in the first or second sentence.
        Do not use quotation marks or markdown.
        
        Status 
        Determine the status:
        
        Approved
        Use when the listing is clearly a:
        - Mosque
        - Masjid
        - Surau
        - Musolla
        - Islamic Centre
        - Prayer Hall

        Pending
        Use when:
        - The place may be Islamic but the mosque status is unclear.
        - Insufficient information exists.
        
        Rejected
        Use when:
        - The place is clearly not a mosque.
        - The place is a business, school, hotel, residence, office, or unrelated organization.
  
        
        **2. Focus keyword requirement**
        
        - Let the **focus keyword** = the full mosque name.
        - Use this keyword **at least once in the first 10% of the article body** (inside the H2 Introduction section).
        - Also mention the **city** and **country** in the first 1–2 sentences.
        
        ---
        
        **3. Article body (\"content\" HTML string)**
        
        Output \"content\" as a **single HTML string** with H1/H2/H3, structured like this:
        
        <h1>Mosque Name</h1>
        
        <h2>Introduction</h2>
        <p>Write 3–5 sentences that clearly explain:
        - The mosque name, city, state, and country.
        - Whether this is a community mosque, campus mosque, surau, or Islamic centre.
        - If it serves students, staff, or local residents.
        - Whether it conducts Friday prayers (if \"Friday prayer: Yes\").
        - That it welcomes visitors and is open to the wider community.
        
        Ensure the full **mosque name** appears in the first 1–2 sentences and that the text is SEO‑friendly for terms like \"((mosque name))\", \"mosque in ((city))\", and \"Friday prayer\" (where applicable).</p>
        
        <h2>Location and How to Get There</h2>
        <p><strong>Address:</strong> ((Address))</p>
        <!-- If Place ID / Lat / Lng given -->
        
         <span class=\"location-btn\">
            <a class=\"btn btn-google-map\" href=\"https://www.google.com/maps/search/?api=1&query=Mosque&query_place_id=((Place_ID))\" target=\"_blank\" rel=\"noopener\">Google Map</a>    
            <a class=\"btn btn-waze-map\" href=\"https://waze.com/ul?ll=((Latitude)),((Longitude))&navigate=yes\" target=\"_blank\" rel=\"noopener\">Waze</a>
        </span>
        
        <h3>How to Reach ((mosque name))</h3>
        <p>Write 2–4 sentences explaining:
        - The city or nearby town.
        - If it is along a main road or within a campus / residential area.
        - How visitors can get there (car, motorcycle, public transport).
        - If it is commonly visited by students or staff (if applicable).</p>
        
        <h2>Contact and Online Presence</h2>
        <!-- Only if each field exists. DO NOT USE NOT AVAILABLE ETC -->
        <p><strong>Telephone:</strong> ((Telephone))</p>
        <p><strong>WhatsApp:</strong> <a href=\"https://wa.me/((WhatsApp_clean))\" target=\"_blank\" rel=\"noopener\">Chat on WhatsApp</a></p>
        (Remove + and spaces from the number.)
        <p><strong>Website:</strong> <a href=\"((Website))\" target=\"_blank\" rel=\"noopener\">Official website</a></p>
        <p><strong>Facebook:</strong> <a href=\"((Facebook))\" target=\"_blank\" rel=\"noopener\">Visit Facebook Page/Group</a></p>
        <p><strong>Instagram:</strong> <a href=\"((Instagram))\" target=\"_blank\" rel=\"noopener\">Follow on Instagram</a></p>
        <p><strong>YouTube:</strong> <a href=\"((YouTube))\" target=\"_blank\" rel=\"noopener\">Watch on YouTube</a></p>
        <p><strong>TikTok:</strong> <a href=\"((TikTok))\" target=\"_blank\" rel=\"noopener\">Follow on TikTok</a></p>
        <p><strong>LinkedIn:</strong> <a href=\"((LinkedIn))\" target=\"_blank\" rel=\"noopener\">Profile on LinkedIn</a></p>
        
        <h2>Facilities at ((Mosque name))</h2>
        <p>Write 3–6 sentences about:
        - Prayer halls for men and women.
        - Ablution and wuduk areas.
        - Parking and accessibility.
        - If this is an Islamic centre, mention lecture halls, meeting rooms, or office space.
        - If capacity is provided, mention roughly how many worshippers it can accommodate (e.g., \"can accommodate several hundred\" or \"large Islamic centre with multiple prayer and event areas\").</p>
        
        <h2>Friday Prayer and Community Role</h2>
        <p>(Only if Friday prayer is given)
        <strong>Friday prayer:</strong> ((Yes / No))</p>
        
        <h3>Role in the Local Community</h3>
        <p>Write 3–5 sentences about:
        - How the mosque functions as a centre for daily prayers and Friday sermons (if applicable).
        - Its role in serving students, staff, or local residents (e.g., Islamic lectures, Ramadan programs, community iftar, Quran circles, youth programs).
        - If it is officially linked to an organization (e.g., university, government department, or Islamic council).
        - How it strengthens the Islamic identity and social cohesion of the community.</p>
        
        <h2>Additional Information</h2>
        <p>Write 1–3 short, positive sentences, only if extra information is available. You may mention:
        - That the mosque is open to visitors or non‑Muslim guests for educational or cultural visits.
        - Any special programs such as halaqah, tarawih, community iftar, student activities, or community events.
        - If it serves as an Islamic centre or multi‑purpose hub beyond just prayer.</p>
       
        <div class='m4a-card'>
            <h3>About Masjid4All</h3>
            Masjid4All connects Muslims worldwide by uniting essential Islamic resources into one digital hub:
            <ul>
                <li><a href=\"/masjid/\">Masjid Directory</a></li>
                <li><a href=\"/business/\">Business Directory</a></li>
                <li><a href=\"/web/\">Islamic Web Directory</a></li>
                <li><a href=\"/knowledge-hub/\">Knowledge Hub</a></li>
                <li><a href=\"/prayer-times/\">Prayer Times</a></li>
                <li><a href=\"/qibla-finder/\">Qibla Finder</a></li>
                <li><a href=\"/quran/\">Daily Quran</a></li>
            </ul>
        </div>
        
        excerpt
        A concise meta description (150–160 characters) based on the introduction
        - Mentions the mosque name, city, and country.

        Your final output must be a valid JSON string with exactly these fields:
        {\n \"title\": \"...\",\n \"keywords\": \"...\",\n \"metaDescription\": \"...\",\n \"status\": \"...\",\n \"content\": \"...\"\n,\n \"Country\": \"...\"\n,\n \"city\": \"...\"\n,\n \"website\": \"...\"\n,\n \"phone\": \"...\"\n,\n \"email\": \"...\"\n,\n \"whatsapp\": \"...\"\n}

    ";
    
    
    $system_prompt = $role;
    $system_prompt .= " You must provide accurate data based on available information.
    Always respond in valid JSON format only, with no extra text outside the JSON.";   

    $mosque_info = is_array($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    $question    = "Generate a mosque profile using this data: " . $mosque_info;

    $response = wp_remote_post('https://api.perplexity.ai/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'sonar', // or 'sonar-pro' if available
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role'    => 'user',
                    'content' => $question
                ]
            ],
            'max_tokens'  => 1500,
            'temperature' => 0.1
        ]),
        'timeout' => 45
    ]); 

    if (is_wp_error($response)) {
        return json_encode(['error' => 'API fail']);
    }

    $body        = json_decode(wp_remote_retrieve_body($response), true);
    $raw_content = $body['choices'][0]['message']['content'] ?? '';

    // Extract first valid JSON block from the raw content
    if (preg_match('/\\{.*\\}/s', $raw_content, $matches)) {
        return $matches[0];
    }

    return $raw_content;
} 


// 6 - Google Map and Waze Btn
add_shortcode('mosque_location_btn', function () {
    $post_id   = get_the_ID();
    $item_id   = get_post_meta($post_id, 'item_id', true);
    
    // Using your business data function since we are adapting for business/mosque
    $place_id  = get_cct_mosque_data($item_id, 'place_id');
    $latitude  = get_cct_mosque_data($item_id, 'latitude');
    $longitude = get_cct_mosque_data($item_id, 'longitude');

    if (!$place_id || !$latitude || !$longitude) {
        return '';
    }

    $google_maps_url = 'https://www.google.com/maps/search/?api=1&query=Google%20Maps&query_place_id=' . urlencode($place_id);
    $waze_url        = 'https://waze.com/ul?ll=' . $latitude . ',' . $longitude . '&navigate=yes';

    ob_start();
    ?>
    <style>
        .mosque-location-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .mosque-btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform 0.2s ease, opacity 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .mosque-btn-icon:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .mosque-btn-icon img {
            width: 24px;
            height: 24px;
        }
        .google-map-bg {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
        }
        .waze-bg {
            background-color: #33CCFF;
        }
    </style>

    <div class="mosque-location-buttons">
        <a href="<?php echo esc_url($google_maps_url); ?>"
           target="_blank"
           rel="noopener"
           class="mosque-btn-icon google-map-bg"
           title="Navigate with Google Maps">
           <img src="https://upload.wikimedia.org/wikipedia/commons/a/aa/Google_Maps_icon_%282020%29.svg" alt="Google Maps">
        </a>

        <a href="<?php echo esc_url($waze_url); ?>"
           target="_blank"
           rel="noopener"
           class="mosque-btn-icon waze-bg"
           title="Navigate with Waze">
           <img src="https://upload.wikimedia.org/wikipedia/commons/8/8b/Tabler-icons_brand-waze.svg" alt="Waze">
        </a>
    </div>
    <?php
    return ob_get_clean();
});


// 7. Refresh Button
add_shortcode('refresh_page_btn', function () {
    ob_start();
    ?>
    <style>
        .btn-refresh-inline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px; /* Space between icon and text */
            padding: 2px 8px;
            margin-left: 8px;
            vertical-align: middle;
            background: #ECFFD1;
            border: 1px solid #67AD00;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            color: #444;
            font-size: 12px; /* Small text to match your coordinates */
            font-weight: 600;
            line-height: 1;
        }
        .btn-refresh-inline:hover {
            background: #91F200;
            color: #444;
            border-color: #3A6100;
        }
        .btn-refresh-inline i {
            font-size: 12px;
        }
    </style>
    <button onclick="window.location.reload();" class="btn-refresh-inline" title="Refresh Page">
        <i class="fas fa-sync-alt"></i> <span>Refresh</span>
    </button>
    <?php
    return ob_get_clean();
});


// 2. Mosque Location Exist
function mosque_location_exists($latitude, $longitude) {
    global $wpdb;
    
    //if (!$latitude || !$longitude) return false;
    if (!$latitude || !$longitude ) {
        return false;
    }

    // Check strict 2 decimal places as requested
    $lat_key = number_format(floor($latitude * 100) / 100, 2, '.', '');
    $lng_key = number_format(floor($longitude * 100) / 100, 2, '.', '');
    $location_key = $lat_key . '|' . $lng_key;

    $table = $wpdb->prefix . 'jet_cct_location';
    
    return (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT 1 
             FROM {$table} 
             WHERE location = %s
               AND mosque IS NOT NULL
               AND mosque != ''
             LIMIT 1",
            $location_key
        )
    );
}

// 3. Serper Location Indexing
add_action('wp_ajax_serper_location_index', 'serper_location_index_ajax');
add_action('wp_ajax_nopriv_serper_location_index', 'serper_location_index_ajax');

function serper_location_index_ajax() {
    global $wpdb;
    
    $country   = sanitize_text_field($_POST['country'] ?? '');
    $latitude  = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);

    if (!$latitude || !$longitude) wp_send_json_error(['message' => 'Invalid location']);

    // --- 1. SET A TRANSIENT LOCK (Prevent simultaneous runs) ---
    $lat_key = number_format(floor($latitude * 100) / 100, 2, '.', '');
    $lng_key = number_format(floor($longitude * 100) / 100, 2, '.', '');
    $lock_key = 'lock_idx_' . md5($lat_key . '|' . $lng_key);
    
    //if (get_transient($lock_key)) {
    //    wp_send_json_error(['message' => 'Update already in progress...']);
    //}
    //set_transient($lock_key, true, 5); // Lock for 45 seconds

    // --- 2. RUN THE API SYNC ---
    $num = 0;
    if (function_exists('serper_nearest_mosques')) {
        $num = serper_nearest_mosques($latitude, $longitude);
    }
 
    // --- 3. MARK LOCATION AS INDEXED ---
    $lat_key = number_format(floor($latitude * 100) / 100, 2, '.', '');
    $lng_key = number_format(floor($longitude * 100) / 100, 2, '.', '');
    $location_key = $lat_key . '|' . $lng_key;
    
    $table = $wpdb->prefix . 'jet_cct_location';

    $exists = (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE location = %s LIMIT 1",
            $location_key
        )
    );
    
    if ($exists) {
        // UPDATE
        $wpdb->update(
            $table,
            [
                'mosque' => $num,
                'country'  => $country
            ],
            [
                'location' => $location_key
            ],
            ['%s', '%s'],
            ['%s']
        );
    } else { 
        // INSERT
        $created = current_time('mysql'); // e.g. 2026-01-01 19:05:00 (KL time)

        $wpdb->insert(
            $table,
            [
                'country'      => $country,
                'location'     => $location_key,
                'mosque'       => $num,
                'cct_created'  => $created
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
    
    //delete_transient($lock_key);
    wp_send_json_success(['message' => 'Finished']);
}

  
function serper_nearest_mosques($latitude, $longitude) {
    global $wpdb;

    /* ----------------------------------------------------
     * 1. Validation and Setup
     * ---------------------------------------------------- */
    if (empty($latitude) || empty($longitude)) {
        return "Error : Location";
    }

    $lat = floatval($latitude);
    $lng = floatval($longitude);

    //$api_key = defined('SERPER_API_KEY') ? SERPER_API_KEY : '';
    $api_key = '96d2c4458179ef645bbcebe48e434dd50734ab7c';
    if (empty($api_key)) {
        error_log('SERPER_API_KEY is not defined.');
        return "Erro : Serper";
    }

    /* ----------------------------------------------------
     * 2. API Request (Cascading Zoom Levels)
     * ---------------------------------------------------- */
    $fetch_mosques = function ($zoom) use ($lat, $lng, $api_key) {
        return wp_remote_post(
            'https://google.serper.dev/maps',
            [
                'headers' => [
                    'X-API-KEY'    => $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'q'  => 'mosque',
                    'll' => "@{$lat},{$lng},{$zoom}z",
                ]),
                'timeout' => 30,
            ]
        );
    };
  
    $response = $fetch_mosques(10);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(15);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}

    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(14);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}
    
    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(11);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}

    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(10);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}

    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(11);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}
    
    //if (empty($data['places']) || count($data['places']) < 10) {
    //    $response = $fetch_mosques(10);
    //    $body = wp_remote_retrieve_body($response);
    //    $data = json_decode($body, true);
    //}

    if (is_wp_error($response)) {
        return 'ERROR : API';
    }

    if (empty($data['places'])) {
        return "No mosques";
    }

    /* ----------------------------------------------------
     * 3. Process Results
     * ---------------------------------------------------- */
    $table = $wpdb->prefix . 'jet_cct_mosque';
    $ret   = '';
    foreach ($data['places'] as $item) {
 
        $cid      = $item['cid'] ?? '';
        $place_id = $item['placeId'] ?? '';
        $name     = strtoupper($item['title'] ?? '');
        $address  = $item['address'] ?? '';
        $lat_item = round(floatval($item['latitude'] ?? 0), 6);
        $lng_item = round(floatval($item['longitude'] ?? 0), 6);
        $type     = $item['type'] ?? 'Mosque';
        $types    = $item['types'] ?? '';
        $phone    = $item['phoneNumber'] ?? '';
        $desc     = $item['description'] ?? '';
        $website  = $item['website'] ?? '';
        $rating   = $item['rating'] ?? '';
        $r_count  = $item['rating_count'] ?? '';
        

        if (!$cid || !$place_id || $type !== 'Mosque') {
            continue;
        }
         
        // Create a Malaysia Timezone object
        $msia_timezone = new DateTimeZone('Asia/Kuala_Lumpur');
        
        // Get today's date in YYYY-MM-DD format
        $today = wp_date('Y-m-d', null, $msia_timezone);

        $cct_data = [
            'cid'          => $cid,
            'place_id'     => $place_id,
            'name'         => $name,
            'address'      => $address,
            'website'      => $website,
            'phone'        => $phone,
            'rating'       => $rating,
            'latitude'     => $lat_item,
            'longitude'    => $lng_item,
            'website'      => $website,
            'rating'       => $rating,
            'rating_count' => $r_count,
            'type'         => $type,
            'cct_created'  => $today,
            'cct_modified' => $today,
            'types'        => $types 
        ];
 
        /* ----------------------------------------------------
         * 4. HARD GUARD — CCT (NO DUPLICATES)
         * ---------------------------------------------------- */
        $item_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT _ID FROM {$table} WHERE place_id = %s LIMIT 1",
                $place_id
            )
        );

        if ($item_id) {
            $wpdb->update(
                $table,
                $cct_data,
                ['_ID' => $item_id]
            );
            $stat = 'CCT Updated';
        } else {
            $wpdb->insert($table, $cct_data);
            $item_id = $wpdb->insert_id;
            $stat = 'CCT Inserted';
        }

        $name    = get_cct_mosque_data($item_id, 'name');
        $post_id = get_cct_mosque_data($item_id, 'cct_single_post_id');
        $status  = get_cct_mosque_data($item_id, 'business_status');
        $city    = get_cct_mosque_data($item_id, 'city');
        $country = get_cct_mosque_data($item_id, 'country');


        // 2. Check if post exists
        $need_create = false;
        if (empty($post_id)) {
            $need_create = true;
        } else {
            $post = get_post($post_id);
            if (!$post || $post->post_status === 'trash') {
                $need_create = true;
            }
        }
        
        // 3. Create post if needed
        if ($need_create) {
            $post_id = wp_insert_post([
                'post_title'  => $name,
                'post_type'   => 'masjid',
                'post_status' => 'publish'
            ]);
        }
        
        update_post_meta($post_id, 'item_id', $item_id);
        
        $rm_title    = $name . ' | ' . $city . ' | ' . $country;
        $rm_excerpt  = $name . ', ' . $city . ', ' . $country;
        $rm_keywords = $name . ', Nearby mosque in ' . $city . ', Nearby Masjid in ' . $country;
        $rm_content  = '<h2>' . $name . '</h2>' . $address . '<br><br>';
        //$rm_content .= 'Nearest mosque in ' . $city . '<br>Nearest mosque in ' . $country . '<br><br>';
        //$rm_content .= '<p><a href="https://pewarisan.my/">Pewarisan - Online Inheritance Planning</a></p>';
        //$rm_content .= '<p><a href="https://masjid4all.com/">Masjid4All - Global Mosque Directory</a></p>';
 
        update_post_meta($post_id, 'rank_math_title', $rm_title ?? '');
        update_post_meta($post_id, 'rank_math_description', $rm_excerpt ?? '');
        update_post_meta($post_id, 'rank_math_focus_keyword', $rm_keywords ?? '');

        // update content
        $current_content = get_post_field('post_content', $post_id);
        // Check if empty (trim to ignore spaces)
        if (empty(trim($current_content))) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $rm_content,
            ]);
        }
        
        // Optional: canonical / OG
        update_post_meta($post_id, 'rank_math_robots', 'index,follow');
        
        // 4. Update CCT with post_id and page_url
        if ($post_id && !is_wp_error($post_id)) {
    
            // 2. Only perform the update if the status is NOT 'Updated'
            if ( $status !== 'Updated' ) {
                $wpdb->update(
                    $table,
                    [
                        'business_status'    => 'Listed', // Or keep as is
                        'cct_single_post_id' => $post_id,
                        'page_url'           => get_permalink($post_id),
                    ],
                    ['_ID' => $item_id],
                    ['%s', '%d', '%s'],
                    ['%d']
                );
            } else {
                // Optional: Log that update was skipped because it's already verified
                error_log("Mosque ID {$item_id} skip update: Status is already 'Updated'.");
            }
        }

        //$ret .= "{$name} | {$stat} {$post_stat}<br>";
        //$num .= 1;
    }

    $num   = count($data['places']);
    return $num;
}

// MOSQUE DISTANCE
add_shortcode('mosque_distance_shortcode', function($atts, $content = null) {
    $obj = jet_engine()->listings->data->get_current_object();

    if (empty($obj->latitude) || empty($obj->longitude)) {
        return;
    }

    if (!isset($_COOKIE['latitude']) || !isset($_COOKIE['longitude'])) {
        return;
    }

    $distance = calculate_distance_km(
        (float)$_COOKIE['latitude'],
        (float)$_COOKIE['longitude'],
        (float)$obj->latitude,
        (float)$obj->longitude
    );

    return sprintf(
        '<i style="font-size:14px;">%s km (%s miles) away</i>',
        number_format($distance, 1),
        number_format($distance * 0.621371, 1)
    );
});


function calculate_distance_km($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // KM
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// Enqueue Font Awesome once
function masjid4all_fontawesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');
}
add_action('wp_enqueue_scripts', 'masjid4all_fontawesome');


/////////////////////////////

// 1. Get Field From Masque CCT
function get_cct_mosque_data($item_id, $field) {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT $field FROM wp_jet_cct_mosque WHERE _ID = %d",
        $item_id
    );
    $result = $wpdb->get_var($query);
    return $result ? $result : "";
}

// 2. Save CCT Mosque Data
function save_cct_mosque_data($item_id, $field, $value) {
    global $wpdb;
    
    // Validate input
    if (!is_numeric($item_id) || empty($field)) {
        return false;
    }
    
    $table_name = $wpdb->prefix . 'jet_cct_mosque';
    
    // Verify the field exists in the table
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
            $table_name,
            $field
        )
    );
    
    if (!$column_exists) {
        error_log("Field $field does not exist in $table_name");
        return false;
    }
    
    // Determine the format for wpdb
    $format = '%s'; // default to string
    if (is_int($value)) {
        $format = '%d';
    } elseif (is_float($value)) {
        $format = '%f';
    }
    
    // Perform the update
    $result = $wpdb->update(
        $table_name,
        array($field => $value),
        array('_ID' => $item_id),
        array($format),
        array('%d')
    );
    
    if (false === $result) {
        error_log("Failed to update CCT: " . $wpdb->last_error);
        return false;
    }
    
    return $result;
} 

// 3. Save CCT Mosque Jetengine
function save_cct_mosque_data_jetengine($item_id, $field, $value) {
    // Verify JetEngine is active
    if (!function_exists('jet_engine')) {
        return new WP_Error('jetengine_missing', 'JetEngine plugin is not active');
    }
    
    // Get the CCT instance
    $cct = jet_engine()->cct->get_items('mosque'); // Replace with your CCT slug
    
    if (!$cct) {
        return new WP_Error('cct_not_found', 'Mosque CCT not found');
    }
    
    // Prepare update data
    $update_data = array(
        $field => $value
    );
    
    // Update the item
    $result = $cct->update_item($item_id, $update_data);
    
    if (is_wp_error($result)) {
        error_log("JetEngine CCT update failed: " . $result->get_error_message());
        return $result;
    }
    
    // Clear JetEngine cache for this item
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete('jet_cct_mosque_' . $item_id, 'jet-engine');
    }
    
    return true;
}


//////////////////////////
add_shortcode('sync_country_filter', function () {

    ob_start(); ?>
    
    <script>
    (function () {

        function syncCountryFilter() {
            const match = document.cookie.match(/search_country=([^;]+)/);
            if (!match) return;

            const country = decodeURIComponent(match[1]);

            document.querySelectorAll('.jet-select__control').forEach(select => {
                if ([...select.options].some(o => o.value === country)) {
                    if (select.value !== country) {
                        select.value = country;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }

        // If JetEngine already initialized
        if (window.JetSmartFilters) {
            syncCountryFilter();
        }

        // If JetEngine initializes later
        document.addEventListener('jet-smart-filters/inited', syncCountryFilter);

    })();
    </script>

    <?php
    return ob_get_clean();
});



// UPDATE MOSQUE INFO
add_shortcode('mosques_search_info', 'mosques_search_info_shortcode');

function mosques_search_info($post_id) {
    global $wpdb;

    $ret = '';
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM wp_jet_cct_mosque WHERE post_id = %d",
            $post_id
        )
    );
    
    if (!empty($results)) {
        foreach ($results as $mosque) {
            $name  = $mosque->name;
            $place_id  = $mosque->place_id;
            $address  = $mosque->address;
            $country = $mosque->country;
            $phone  = $mosque->phone;
            $types  = $mosque->types;
            $maps_url = $mosque->maps_url;
            $photo_url = $mosque->photo_url ;
            
            // search info on the business
            $search = $name . ' ' . $address ;
            $result = mosque_search_shortcode($search,$country);
            
            // generate content            
            $response = $info . '<br>' . $result;
            $content = ask_gemini_mosque($response);
            
            $ret .= $content;
        }
    } else {
        $ret .= 'No record<br>';
    }
    
    //$ret .= 'ID: ' . $post_id;
    return $ret;
}    


//ADMIN - DISPLAY MOSQUE INFO
add_shortcode('mosque_full_info', 'mosque_full_info_shortcode');

function mosque_full_info_shortcode() {
    $item_id = $_GET['id'];
    $name = get_cct_mosque_data($item_id, 'name');
    $tags = get_cct_mosque_data($item_id, 'tags');
    $phone = get_cct_mosque_data($item_id, 'phone');
    $address = get_cct_mosque_data($item_id, 'address');
    $city = get_cct_mosque_data($item_id, 'city');
    $country = get_cct_mosque_data($item_id, 'country');
    
    $ret.= 'Name : <b>'. $name . '</b><br>';
    $ret.= 'Phone : <b>'. $phone . '</b><br>';
    $ret.= 'Address : <b>'. $address . '</b><br>';
    $ret.= 'City : <b>'. $city . '</b><br>';
    $ret.= 'Country : <b>'. $country . '</b><br>';
    $ret.= 'Tags : <b>'. $tags . '</b><br>';
    
    return $ret;
}

//FLUENTFORM SHORTCODE (CCT MOSQUE)
//NAME
add_filter('fluentform/editor_shortcode_callback_mname', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'name');
    
    return $dynamicValue;
    
}, 10, 2);

//INTRO
add_filter('fluentform/editor_shortcode_callback_mintro', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'introduction');
    
    return $dynamicValue;
    
}, 10, 2);

//URL
add_filter('fluentform/editor_shortcode_callback_murl', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'page_url');
    
    return $dynamicValue;
    
}, 10, 2);

//TYPE
add_filter('fluentform/editor_shortcode_callback_mtype', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'type');
    
    return $dynamicValue;
    
}, 10, 2);

//TAGS
add_filter('fluentform/editor_shortcode_callback_mtags', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'tags');
    
    return $dynamicValue;
    
}, 10, 2);

//ADDRESS
add_filter('fluentform/editor_shortcode_callback_madd', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'address');
    
    return $dynamicValue;
    
}, 10, 2);

//CITY
add_filter('fluentform/editor_shortcode_callback_mcity', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'city');
    
    return $dynamicValue;
    
}, 10, 2);

//COUNTRY
add_filter('fluentform/editor_shortcode_callback_mcountry', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'country');
    
    return $dynamicValue;
    
}, 10, 2);

//EMAIL
add_filter('fluentform/editor_shortcode_callback_memail', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'email');
    
    return $dynamicValue;
    
}, 10, 2);

//PHONE
add_filter('fluentform/editor_shortcode_callback_mphone', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'phone');
    
    return $dynamicValue;
    
}, 10, 2);

//WHATSAPP
add_filter('fluentform/editor_shortcode_callback_mws', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'whatsapp');
    
    return $dynamicValue;
    
}, 10, 2);

//WEBSITE
add_filter('fluentform/editor_shortcode_callback_mweb', function ($value, $form) {
    
    $item_id = $_GET['id'];
    $dynamicValue = get_cct_mosque_data($item_id, 'website');
    
    return $dynamicValue;
    
}, 10, 2);

//FLUENTFORM UPDATE MOSQUE CCT (FORM ID : 12)
add_action('fluentform/submission_inserted', 'update_mosque_info', 20, 3);
function update_mosque_info($entryId, $formData, $form) {
    global $wpdb;

    $targetFormId = 13;
    if ($form->id != $targetFormId) {
        return;
    }
    
    $item_id = $formData['itemID'];
    $mname = wp_kses_post($formData['mname']) ?? '';
    $mintro = $formData['mintro'] ?? '';
    $murl = $formData['murl'] ?? '';
    $mtype = $formData['mtype'] ?? '';
    $mtags = $formData['mtags'] ?? '';
    $madd = $formData['madd'] ?? '';
    $mcity = $formData['mcity'] ?? '';
    $mcountry = $formData['mcountry'] ?? '';
    $mphone = $formData['mphone'] ?? '';
    $mws = $formData['mws'] ?? '';
    $memail = $formData['memail'] ?? '';
    $mweb = $formData['mweb'] ?? '';
    
    $wpdb->update(
        'wp_jet_cct_mosque',
        [
            'name' => $mname,
            'introduction' => $mintro,
            'page_url' => $murl,
            'type' => $mtype,
            'tags' => $mtags,
            'address' => $madd,
            'city' => $mcity,
            'country' => $mcountry,
            'phone' => $mphone,
            'whatsapp' => $mws,
            'email' => $memail,
            'website' => $mweb,
        ],
        ['_ID' => $item_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );
    
    /*
    //Check last $wpdb error
    if (!empty($wpdb->last_error)) {
        echo "<script>console.error('wpdb error: " . esc_js($wpdb->last_error) . "');</script>";
    }
    */
}


// Hook for AJAX background crawler
add_action('wp_ajax_nopriv_get_masjid_info', 'get_masjid_info');
add_action('wp_ajax_get_masjid_info', 'get_masjid_info');
 
function get_masjid_info() {

    if (!isset($_GET['itemid']) || !isset($_GET['info']) || !isset($_GET['postid'])) {
        wp_die('Missing parameters');
    }

    $item_id = floatval($_GET['itemid']);
    $post_id = floatval($_GET['postid']);
    $info = sanitize_text_field($_GET['info']);
    $country = sanitize_text_field($_GET['country'] ?? 'my');

    // Construct search prompt
    $search = 'Information, review and history of ' . $info;

    // Call SERPER API
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://google.serper.dev/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'q' => $search,
            'gl' => $country
        ]),
        CURLOPT_HTTPHEADER => array(
            'X-API-KEY: 8015ce8dddce3cd0fc1c880bb3eafee9b15544c7',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if (!$response) {
        wp_die('No response from SERPER');
    }

    // Optional: parse or format the response
    $website = ' Website : ' . get_cct_business_data($item_id, 'phone');
    $phone = ' Phone : ' . get_cct_business_data($item_id, 'phone');
    $rating = ' Rating : ' . get_cct_business_data($item_id, 'rating');
    $rating_count = ' Rating Count : ' . get_cct_business_data($item_id, 'rating');
    $opening_hours = ' Opening hours : ' . get_cct_business_data($item_id, 'opening_hours');
 
    $content_input = $info . $website . $phone . $rating . $rating_count . $opening_hours . '<br>' . $response;

    // Generate readable content (if using Gemini or similar AI)
    $content = ask_gemini_mosque($content_input); // assumes this returns HTML/text
    $content = removeCodeBlockTags($content); 
 
    // UPDATE POST 
    if ($content <> '') {
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ], true);
    
        if (is_wp_error($result)) {
            error_log('Post update failed: ' . $result->get_error_message());
        }
        //update_post_meta($post_id, 'updated', 'Update3');
    }
    
    wp_die('Done');
}

// SEARCH MOSQUE ////////
function mosque_search_shortcode($search,$country) {
    $prompt = 'Information, review and history of ';
    $search = $prompt . ' ' . $search;
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://google.serper.dev/search',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>'{"q":"' . $search . '","gl":"' . $country . '"}',
      CURLOPT_HTTPHEADER => array(
        'X-API-KEY: 8015ce8dddce3cd0fc1c880bb3eafee9b15544c7',
        'Content-Type: application/json'
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    return $response;
}


// SOLAT PRAYER TIME
add_shortcode('solat_times', 'get_solat_times');

function get_solat_times() {
    
    $loc = get_user_latitude_longitude();
    $lat = $loc['latitude'];
    $lng = $loc['longitude'];
    $method = 3;
    
    $url = "https://api.aladhan.com/v1/timings?latitude={$lat}&longitude={$lng}&method={$method}";

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return '<p>Unable to retrieve solat times. Please try again later.</p>';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['data']['timings'])) {
        return '';
    }

    $timings = $data['data']['timings'];
    $timezone = $data['data']['meta']['timezone'];
    $today = $data['data']['date']['readable'];
    $hday = $data['data']['date']['hijri']['day'];
    $hmth = $data['data']['date']['hijri']['month']['en'];
    $hyear = $data['data']['date']['hijri']['year'];
    
    $fajr = $timings['Fajr'];
    $date = DateTime::createFromFormat('H:i', $fajr);
    $fajr = $date ? $date->format('g:i A') : 'Invalid time';

    $sunrise = $timings['Sunrise'];
    $date = DateTime::createFromFormat('H:i', $sunrise);
    $sunrise = $date ? $date->format('g:i A') : 'Invalid time';
    
    $dhuhr = $timings['Dhuhr'];
    $date = DateTime::createFromFormat('H:i', $dhuhr);
    $dhuhr = $date ? $date->format('g:i A') : 'Invalid time';
    
    $maghrib = $timings['Maghrib'];
    $date = DateTime::createFromFormat('H:i', $maghrib);
    $maghrib = $date ? $date->format('g:i A') : 'Invalid time';
    
    $asr = $timings['Asr'];
    $date = DateTime::createFromFormat('H:i', $asr);
    $asr = $date ? $date->format('g:i A') : 'Invalid time';
    
    $isha = $timings['Isha'];
    $date = DateTime::createFromFormat('H:i', $isha);
    $isha = $date ? $date->format('g:i A') : 'Invalid time';

    //$ret = '<b>Prayer Times for ' . $timezone . '</b>';
    $ret .=  $today . ' (' . $hday . ' ' . $hmth . ' ' . $hyear . ')<br>';
    $ret .= '<table style="border-collapse: collapse; width: 100%; font-size: 14px; text-align: center;">';
    $ret .= '<tr><td style="border: 1px solid gray">Fajr</td><td style="border: 1px solid gray">Dhuhr</td><td style="border: 1px solid gray">Asr</td><td style="border: 1px solid gray">Magrib</td><td style="border: 1px solid gray">Isha</td></tr>';
    $ret .= '<tr><td style="border: 1px solid gray"><b>' . esc_html($fajr) . '</b></td>';
    //$ret .= '<td><b>' . esc_html($sunrise) . '</b></td>';
    $ret .= '<td style="border: 1px solid gray"><b>' . esc_html($dhuhr) . '</b></td>';
    $ret .= '<td style="border: 1px solid gray"><b>' . esc_html($asr) . '</b></td>';
    $ret .= '<td style="border: 1px solid gray"><b>' . esc_html($maghrib) . '</b></td>';
    $ret .= '<td style="border: 1px solid gray"><b>' . esc_html($isha) . '</b></td>';
    $ret .= '</tr></table>';

    return $ret;
}

 

// UPDATE LOCATION 
add_shortcode('mosque_update_location', 'mosque_update_location_shortcode');

function mosque_update_location_shortcode() {
    // Check if latitude and longitude are in cookies
    $latitude = isset($_COOKIE['latitude']) ? $_COOKIE['latitude'] : null;
    $longitude = isset($_COOKIE['longitude']) ? $_COOKIE['longitude'] : null;
    $latitude = number_format($latitude, 5);
    $longitude = number_format($longitude, 5);
    
    ob_start();
    ?>
    <div id="location-section">
        <button style="font-size: 13px;background-color: #D4591E; color: white;" id="updateMosqueBtn">Update Location</button>
        <p id="mosqueDisplay">
            <?php if ($latitude && $longitude): ?>
                Location : <?= esc_html($latitude) ?>,   <?= esc_html($longitude) ?> <br><br>
            <?php else: ?>
                Location not set.
            <?php endif; ?>
        </p>
    </div>
 
    <script>
        document.getElementById('updateMosqueBtn').addEventListener('click', function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    let lat = position.coords.latitude;
                    let lon = position.coords.longitude;
        
                    document.cookie = "latitude=" + encodeURIComponent(lat) + "; path=/; max-age=" + (60 * 60 * 24 * 7);
                    document.cookie = "longitude=" + encodeURIComponent(lon) + "; path=/; max-age=" + (60 * 60 * 24 * 7);
        
                    // Slight delay to ensure cookies are written
                    setTimeout(() => {
                        location.reload();
                    }, 200);
                }, function (error) {
                    alert("Location access denied or unavailable.");
                });
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        });
    </script>
 
    <?php
    // If we have coordinates, call the existing PHP function
    if ($latitude && $longitude) {
        //echo mosque_nearest_list_shortcode($latitude, $longitude);
    }

    return ob_get_clean();
}


//////////////////////////////////
// MOSQUE SUMMARY               //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('mosque_summary_country', 'mosque_summary_country_shortcode');
}); 
 
function mosque_summary_country_shortcode() {
    ob_start();

    global $wpdb;

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }

    $results = $wpdb->get_results("
        SELECT country, COUNT(*) AS mosque_count
        FROM wp_jet_cct_mosque
        WHERE business_status IN ('Listed', 'Updated') AND country <> '' AND type = 'Mosque'
        " . $date_query . "
        GROUP BY country
        ORDER BY mosque_count DESC
    ");
    
    if ($results) {
        $total_mosques = 0; // Initialize total count
        $total_country = 0;
        $tbl = '<table>';
        $cnt = 0;
        foreach ($results as $row) {
            $cnt = $cnt + 1;
            $tot = number_format($row->mosque_count);
            if ($cnt < 200){
                $tbl.= '<tr>';
                $tbl .= "<td>{$row->country}</td><td  style='text-align: right'>{$tot}</td>";
                $tbl.= '</tr>';
            }
            //$ret .= "{$row->country} ({$row->mosque_count}) &#9679; ";
      
            $total_mosques += $row->mosque_count; // Add to total count
            $total_country += 1;
        }
        $tbl.= '</table>';
          
        //$summ.= 'Total Mosques : <b>' . number_format($total_mosques) . '<br><br>' ;
        //$summ.= '<b>Total Mosques : ' . $total_mosques . '</b><br><br> ';
        
        //$ret = $summ . $ret;;
     
     } else {
        $ret = "No data found.";
    }
    
    $ret = 'Total Mosques : <b>' . number_format($total_mosques) . '</b><br><br>';
    //$ret .= 'Total Countries : <b>' . number_format($total_country) . '</b><br><br>';

    return $ret . $tbl . ob_get_clean();
}

//////////////////////////////////
// MOSQUE SUMMARY BY REGION     //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('mosque_summary_continent', 'mosque_summary_continent_shortcode');
}); 

function mosque_summary_continent_shortcode() {
    ob_start();
    global $wpdb;

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }
    
    $results = $wpdb->get_results("
        SELECT continent, COUNT(*) AS mosque_count
        FROM {$wpdb->prefix}jet_cct_mosque
        WHERE continent IS NOT NULL AND continent <> ''
        " . $date_query . "
        GROUP BY continent
        ORDER BY mosque_count DESC
    ");
    
    $total_mosque = 0;
    $total_regions = 0;

    // Header Info (Teal Theme)
    echo '<div style="padding: 15px; background: #e6f2f1; border-left: 5px solid #125C59; border-radius: 4px; margin-bottom:15px;">';
    
    if ($results) {
        foreach ($results as $row) {
            $total_mosque += $row->mosque_count; 
            $total_regions += 1;
        }
    }
    echo 'Total Global Mosques: <b>' . number_format($total_mosque) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if ($results) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Mosques</th>
                 </tr>';
        
        foreach ($results as $row) {
            $tot = number_format($row->mosque_count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($row->continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-mosque-drilldown-btn' data-continent='" . esc_attr($row->continent) . "' style='text-decoration: underline; color: #125C59; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo "<p>No mosque regional data found.</p>";
    }

    // HTML Modal Mosque
    ?>
    <div id="m4aMosqueDrilldownModal" class="m4a-modal-overlay">
        <div class="m4a-modal-content">
            <span class="m4a-close-modal m4a-mosque-close">&times;</span>
            <h2 id="m4aMosqueModalTitle" style="margin-top:0; color:#125C59; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>
            
            <div id="m4aMosqueModalLoader" style="text-align:center; padding:50px 0; display:none;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:30px; color:#125C59;"></i>
                <p style="margin-top:10px; color:#555;">Compiling data...</p>
            </div>

            <div id="m4aMosqueModalBody" class="m4a-modal-body" style="display:none;">
                <div class="m4a-modal-chart-wrap">
                    <h4 style="margin: 0 0 15px 0; color:#444;">Top 10 Countries</h4>
                    <div class="m4a-chart-canvas-box">
                        <canvas id="m4aMosqueDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap">
                    <div id="m4aMosqueModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var lat = parseFloat(getCookie('latitude') || 0);
        var lng = parseFloat(getCookie('longitude') || 0);
        
        if (lat && lng) {
            lastGeoHash = encodeGeohash(lat, lng, 5);
        }
        
        const modal = document.getElementById("m4aMosqueDrilldownModal");
        if (modal && !modal.closest('body > .m4a-modal-overlay')) { document.body.appendChild(modal); }

        const closeBtn = document.querySelector(".m4a-mosque-close");
        const drillBtns = document.querySelectorAll(".m4a-mosque-drilldown-btn");
        let chartInst = null; 

        drillBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aMosqueModalTitle").innerText = "Mosques in " + continent;
                modal.style.display = "block";
                document.getElementById("m4aMosqueModalBody").style.display = "none";
                document.getElementById("m4aMosqueModalLoader").style.display = "block";

                const formData = new FormData();
                formData.append('action', 'm4a_get_mosque_drilldown');
                formData.append('continent', continent);

                fetch("<?php echo admin_url('admin-ajax.php'); ?>", { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById("m4aMosqueModalLoader").style.display = "none";
                        document.getElementById("m4aMosqueModalBody").style.display = "flex";
                        document.getElementById("m4aMosqueModalTableContent").innerHTML = data.data.table_html;

                        const ctx = document.getElementById("m4aMosqueDrilldownChart").getContext("2d");
                        if(chartInst != null) { chartInst.destroy(); }

                        chartInst = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: data.data.chart_labels,
                                datasets: [{
                                    data: data.data.chart_data,
                                    backgroundColor: ['#125C59', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e', '#FF6384'],
                                    borderWidth: 2, borderColor: '#ffffff'
                                }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11} } } } }
                        });
                    }
                });
            });
        });

        const closeModal = function() { modal.style.display = "none"; };
        if(closeBtn) closeBtn.onclick = closeModal;
        window.addEventListener('click', function(e) { if (e.target == modal) closeModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

// AJAX BACKEND: MOSQUE
add_action('wp_ajax_m4a_get_mosque_drilldown', 'm4a_ajax_mosque_drilldown_callback');
function m4a_ajax_mosque_drilldown_callback() {
    global $wpdb;
    $continent = sanitize_text_field($_POST['continent'] ?? '');

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }
    
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT country, COUNT(*) as count 
        FROM {$wpdb->prefix}jet_cct_mosque
        WHERE continent = %s AND country IS NOT NULL AND country <> ''
        " . $date_query . "
        GROUP BY country ORDER BY count DESC
    ", $continent));

    $chart_labels = []; $chart_data = [];
    $html = '<table style="width:100%; border-collapse: collapse;">';
    $html .= '<tr style="background:#f1f1f1; text-align:left;"><th style="padding:8px; border-bottom:2px solid #ccc;">Country</th><th style="padding:8px; border-bottom:2px solid #ccc; text-align:right;">Total</th></tr>';

    $i = 0;
    foreach ($results as $row) {
        $html .= '<tr><td style="padding:8px; border-bottom:1px solid #eee;">' . esc_html($row->country) . '</td><td style="padding:8px; border-bottom:1px solid #eee; text-align:right; font-weight:bold;">' . intval($row->count) . '</td></tr>';
        if ($i < 10) { $chart_labels[] = $row->country; $chart_data[] = (int)$row->count; }
        $i++;
    }
    $html .= '</table>';
    wp_send_json_success(['table_html' => $html, 'chart_labels' => $chart_labels, 'chart_data' => $chart_data]);
}

// CONTENT
add_filter('fluentform/editor_shortcode_callback_mcontent', function ($value, $form) {
    $item_id = $_GET['id'];
    $post_id = get_cct_mosque_data($item_id, 'post_id');

    // Normalize content
    $content = get_post_field( 'post_content', $post_id ); // Optional: ensures paragraphs
    $content = str_replace(array("\r\n", "\r", "\n"), '', $content); // Remove line breaks

    return $content;
}, 10, 2);

// 1. Reset kiraan setiap kali grid baru bermula (supaya tak kacau grid lain)
add_action('jet-engine/listing/grid/before-grid', function() {
    global $m4a_ad_counter;
    $m4a_ad_counter = 0;
});

// 2. Suntik iklan SELEPAS item dipaparkan
add_action('jet-engine/listing/grid/after-item', function($render_class, $post) {
    global $m4a_ad_counter;
    
    // Tingkatkan kiraan
    $m4a_ad_counter++;

    // Jika kiraan mencecah 3 (bermaksud iklan akan keluar SELEPAS masjid ke-3)
    if ($m4a_ad_counter === 3) {
        
        $ad_folder_path = WP_PLUGIN_DIR . '/enaizi/iklan/';
        $ad_folder_url  = plugins_url('/enaizi/iklan/');
        $ad_files = array();

        // Cari gambar dalam folder
        if (is_dir($ad_folder_path)) {
            $files = scandir($ad_folder_path);
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
                    $ad_files[] = $file;
                }
            }
        }

        // Paparkan gambar secara rawak jika ada
        if (!empty($ad_files)) {
            $random_ad_file = $ad_files[array_rand($ad_files)];
            $image_url = $ad_folder_url . $random_ad_file;
            $target_link = 'https://pewarisan.my';

            // HTML kotak iklan (class 'jet-listing-grid__item' pastikan ia tersusun dalam grid)
            echo '
            <div class="jet-listing-grid__item mosque-ad-item" style="display:flex; flex-direction:column;">
                <a href="' . esc_url($target_link) . '" target="_blank" rel="nofollow" class="mosque-ad-link" style="display:block; height:100%; text-decoration:none;">
                    <div class="mosque-ad-container" style="height:100%; border:1px solid #e1e1e1; overflow:hidden; border-radius:8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                        <img src="' . esc_url($image_url) . '" alt="Iklan Tajaan" style="width:100%; height:100%; object-fit:cover; display:block; min-height:250px;">
                    </div>
                </a>
            </div>';
        }

        // Reset kiraan kepada 0 semula 
        // (Ini membolehkan iklan keluar setiap 3 item. Jika anda mahu iklan keluar SEKALI sahaja, padam baris di bawah)
        $m4a_ad_counter = 0;
    }
}, 10, 2);



//////////
add_shortcode( 'process_single_mosque', 'mfa_process_single_mosque_shortcode' );

function mfa_process_single_mosque_shortcode() {
    global $wpdb;

    // 1. Get the correct table name including your WordPress prefix
    $table_name = $wpdb->prefix . 'jet_cct_mosque';

    // 2. Query for ONE record where business_status = 'Updated' AND listing_status is '' or NULL
    $row = $wpdb->get_row( "
        SELECT * FROM $table_name 
        WHERE business_status = 'Updated' 
        AND (listing_status = '' OR listing_status IS NULL) 
        LIMIT 1
    " );

    // If no records match, stop the loop gracefully and inform the user
    if ( ! $row ) {
        return '<div class="m4a-card" style="border-left: 4px solid #1b4d3e;">
                    <h3>🎉 All Done!</h3>
                    <p>No more mosques pending processing in the queue.</p>
                </div>';
    }

    // 3. Extract IDs from the database row record
    $item_id = $row->_ID ; 
    $post_id = $row->cct_single_post_id; 

    // If IDs are missing, skip this broken row so it doesn't freeze your entire automated loop
    if ( ! $item_id || ! $post_id ) {
        error_log( 'MFA Queue Warning: Skipping row because mapping IDs are missing. Item ID found: ' . ($item_id ?? 'None') . ' | Post ID found: ' . ($post_id ?? 'None') );
        
        // Mark this broken row so the next refresh skips it completely
        if ( $item_id ) {
            save_cct_mosque_data( $item_id, 'listing_status', 'failed_mapping' );
        }

        // Auto-refresh in 1 second to move past the broken row
        return '
        <div class="m4a-card" style="border-left: 4px solid #ff4444;">
            <h3>⚠️ Structural Error</h3>
            <p>Skipping record (Item ID: ' . ($item_id ?? 'Unknown') . ') due to missing Post ID connections.</p>
            <p style="color: #666; font-style: italic;">Skipping to next record in 1 second...</p>
        </div>
        <script type="text/javascript">
            setTimeout(function() { window.location.reload(); }, 1000);
        </script>
        ';
    }

    // 4. Retrieve data strings using your helper function
    $name      = get_cct_mosque_data( $item_id, 'name' );
    $address   = get_cct_mosque_data( $item_id, 'address' );
    $city      = get_cct_mosque_data( $item_id, 'city' );
    $country   = get_cct_mosque_data( $item_id, 'country' );
    $latitude  = get_cct_mosque_data( $item_id, 'latitude' );
    $longitude = get_cct_mosque_data( $item_id, 'longitude' );
    $website   = get_cct_mosque_data( $item_id, 'website' );
    $place_id  = get_cct_mosque_data( $item_id, 'place_id' );
    
    $data = "Name: $name, Address: $address, Latitude: $latitude, Longitude: $longitude, Website: $website, City: $city, Country: $country, PlaceID: $place_id";
    
    // 5. Send payload to your Perplexity AI function
    $result = mosques_perplexity( $data );
    $parsed = json_decode( $result, true );
 
    if ( ! is_array( $parsed ) || empty( $parsed['content'] ) ) {
        error_log( 'MFA Error: 1. Invalid AI content received for Item ID ' . $item_id );
        return '
        <div class="m4a-card" style="border-left: 4px solid #ff4444;">
            <h3>⚠️ 1. Invalid AI content received</h3>
            <p>Skipping record (Item ID: ' . ($item_id ?? 'Unknown') . ') due to missing Post ID connections.</p>
            <p style="color: #666; font-style: italic;">Skipping to next record in 1 second...</p>
        </div>
        <script type="text/javascript">
            setTimeout(function() { window.location.reload(); }, 1000);
        </script>
        ';
    }
    
    // 6. Map the parsed AI array variables
    $html_content = $parsed['content']; 
    $rm_title     = $parsed['title'];
    $rm_excerpt   = $parsed['excerpt'];
    $rm_keywords  = $parsed['keywords'];
    $status       = $parsed['status'];
    $country      = $parsed['country'];
    $city         = $parsed['city'];
    $website      = $parsed['website'];
    $email        = $parsed['email'];
    $phone        = $parsed['phone'];
    $whatsapp     = $parsed['whatsapp'];
    
    error_log( 'HTML CONTENT FROM AI: ' . $html_content );
    
    // 7. Update the core WordPress Post
    $post_update = wp_update_post([
        'ID'           => $post_id,
        'post_content' => $html_content,
    ], true);
    
    if ( is_wp_error( $post_update ) ) {
        save_cct_mosque_data( $item_id, 'listing_status', 'Error' );

        error_log( '2. MFA WP Update Failed: ' . $post_update->get_error_message() );
        return '
        <div class="m4a-card" style="border-left: 4px solid #ff4444;">
            <h3>⚠️ 2. Invalid AI content received</h3>
            <p>Skipping record (Item ID: ' . ($item_id ?? 'Unknown') . ') due to missing Post ID connections.</p>
            <p style="color: #666; font-style: italic;">Skipping to next record in 1 second...</p>
        </div>
        <script type="text/javascript">
            setTimeout(function() { window.location.reload(); }, 1000);
        </script>
        ';
    } 

    // 8. Update Rank Math SEO Meta Fields
    update_post_meta( $post_id, 'rank_math_title', $rm_title ?? '' );
    update_post_meta( $post_id, 'rank_math_description', $rm_excerpt ?? '' );
    update_post_meta( $post_id, 'rank_math_focus_keyword', $rm_keywords ?? '' );
    
    // 9. Update JetEngine CCT tracking states and content fields
    save_cct_mosque_data( $item_id, 'business_status', 'Updated' );
    save_cct_mosque_data( $item_id, 'listing_status', $status );

    if ( ! empty( trim( $country ) ) ) {
        save_cct_mosque_data( $item_id, 'country', sanitize_text_field( $country ) );
    }
    if ( ! empty( trim( $city ) ) ) {
        save_cct_mosque_data( $item_id, 'city', sanitize_text_field( $city ) );
    }    
    if ( ! empty( trim( $website ) ) ) {
        save_cct_mosque_data( $item_id, 'website', sanitize_text_field( $website ) );
    }    
    if ( ! empty( trim( $email ) ) ) {
        save_cct_mosque_data( $item_id, 'email', sanitize_text_field( $email ) );
    }    
    if ( ! empty( trim( $phone ) ) ) {
        save_cct_mosque_data( $item_id, 'phone', sanitize_text_field( $phone ) );
    }    
    if ( ! empty( trim( $whatsapp ) ) ) {
        save_cct_mosque_data( $item_id, 'whatsapp', sanitize_text_field( $whatsapp ) );
    }

    // 10. Output success message and inject the 1-second JS automation handler
    $output = '
    <div class="m4a-card">
        <h3>Processing Queue Active</h3>
        <p>Successfully processed: <strong>' . esc_html( $name ) . '</strong> (Post ID: ' . $post_id . ')</p>
        <p style="color: #666; font-style: italic;">Moving to next record in 1 second...</p>
    </div>
    
    <script type="text/javascript">
        setTimeout(function() {
            window.location.reload();
        }, 1000); // 1000 milliseconds = 1 second
    </script>
    ';

    return $output;
}