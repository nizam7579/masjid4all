<?php

/**
 * 1. niz_mfa_add_business
 */
global $directory_form_feedback;
$directory_form_feedback = '';


// ADD BUSINESS
add_shortcode('niz_mfa_add_business', 'directory_add_business_shortcode');

function directory_add_business_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>You must be logged in to add a business.</p>';
    }

    global $directory_form_feedback;
    $output = '';

    if ( !empty($directory_form_feedback) ) {
        $output .= $directory_form_feedback;
    }

    $api_key = 'AIzaSyCaONGaCSg0o34FQOXIYET9ZiqBMrOYYFk';
    wp_enqueue_script('google-legacy-places', 'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places', array(), null, true);
    $ajax_url = admin_url('admin-ajax.php');

    wp_add_inline_script('google-legacy-places', "
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.getElementById('standard-business-search');
            if (!input) return;

            var autocomplete = new google.maps.places.Autocomplete(input, { 
                types: ['establishment'],
                // Added 'business_status' to fetch permanently closed info
                fields: [
                    'place_id', 'name', 'geometry', 'formatted_address', 'address_components', 
                    'rating', 'user_ratings_total', 'price_level', 'opening_hours', 
                    'website', 'formatted_phone_number', 'types', 'business_status'
                ]
            });

            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                var feedbackEl = document.getElementById('selection-feedback');
                var submitBtn = document.getElementById('biz-submit-btn');

                if (!place.place_id || !place.geometry) {
                    alert('Please select from the dropdown.');
                    return;
                }

                // CHECK IF IT IS A PLACE OF WORSHIP
                if (place.types) {
                    var restrictedTypes = ['place_of_worship', 'mosque', 'church', 'hindu_temple', 'synagogue', 'cemetery'];
                    var isReligious = place.types.some(type => restrictedTypes.includes(type));

                    if (isReligious) {
                        feedbackEl.innerHTML = '<div style=\"color: #721c24; background: #f8d7da; padding: 10px; margin-top:10px; border-radius:4px;\"><strong>Not Allowed:</strong> You have selected a place of worship. This form is strictly for adding businesses.</div>';
                        document.getElementById('submit-container').style.display = 'none';
                        return;
                    }
                }
                
                // Existing Core Fields
                document.getElementById('wp-business-place-id').value = place.place_id || '';
                document.getElementById('wp-business-name').value = place.name || '';
                document.getElementById('wp-business-lat').value = place.geometry.location.lat();
                document.getElementById('wp-business-lng').value = place.geometry.location.lng();
                document.getElementById('wp-business-address').value = place.formatted_address || '';
                
                // FIXED: Extract primary type and join all types as a comma-separated string
                var primaryType = (place.types && place.types.length > 0) ? place.types[0] : '';
                var allTypes = place.types ? place.types.join(',') : '';
                document.getElementById('wp-business-type').value = primaryType;
                document.getElementById('wp-business-types').value = allTypes;

                // FIXED: Check if business is permanently closed (1 for Yes, 0 for No)
                var isPermanentlyClosed = (place.business_status === 'CLOSED_PERMANENTLY') ? 1 : 0;
                document.getElementById('wp-business-permanently-closed').value = isPermanentlyClosed;
                
                // Address Components (City / Country)
                var cityName = '', countryName = '';
                if (place.address_components) {
                    for (var i = 0; i < place.address_components.length; i++) {
                        var types = place.address_components[i].types;
                        if (types.includes('locality')) cityName = place.address_components[i].long_name;
                        if (types.includes('country')) countryName = place.address_components[i].long_name;
                    }
                }
                document.getElementById('wp-business-city').value = cityName;
                document.getElementById('wp-business-country').value = countryName;

                // RELEVANT INFO FIELDS
                document.getElementById('wp-business-rating').value = place.rating || 0;
                document.getElementById('wp-business-review-count').value = place.user_ratings_total || 0;
                document.getElementById('wp-business-price-level').value = place.price_level !== undefined ? place.price_level : '';
                document.getElementById('wp-business-website').value = place.website || '';
                document.getElementById('wp-business-phone').value = place.formatted_phone_number || '';

                // Extract Opening Hours
                var openNow = false;
                var weekdayText = [];
                if (place.opening_hours) {
                    openNow = typeof place.opening_hours.isOpen === 'function' ? place.opening_hours.isOpen() : false;
                    if (place.opening_hours.weekday_text) {
                        weekdayText = place.opening_hours.weekday_text;
                    }
                }
                document.getElementById('wp-business-open-now').value = openNow;
                document.getElementById('wp-business-opening-hours').value = JSON.stringify(weekdayText);

                // Async Check for duplicates
                var formData = new FormData();
                formData.append('action', 'directory_check_existing_place');
                formData.append('place_id', place.place_id);

                fetch('" . esc_url($ajax_url) . "', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.exists) {
                        feedbackEl.innerHTML = '<div style=\"color: #856404; background: #fff3cd; padding: 10px; margin-top:10px; border-radius:4px;\"><strong>Already listed!</strong> <a href=\"' + data.data.page_url + '\">View Page</a></div>';
                        submitBtn.style.display = 'none';
                    } else {
                        feedbackEl.innerHTML = '<div style=\"color: #155724; background: #d4edda; padding: 10px; margin-top:10px; border-radius:4px;\"><strong>Selected:</strong> ' + place.name + '<br><small>' + place.formatted_address + '</small></div>';
                        document.getElementById('submit-container').style.display = 'block';
                        submitBtn.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Add Business to Directory';
                    }
                });
            });

            // Prevent Enter from submitting early
            input.addEventListener('keydown', function(e) { if (e.keyCode === 13) e.preventDefault(); });
            
            // UX: Show loading state when form is submitted
            var form = document.getElementById('add-business-standard-form');
            form.addEventListener('submit', function() {
                var btn = document.getElementById('biz-submit-btn');
                btn.innerText = 'Updating Information. Please Wait...';
                btn.style.background = '#e6a800';
                btn.style.pointerEvents = 'none';
            });
        });
    ");

    ob_start();
    ?>
    <div class="directory-shortcode-wrapper" style="max-width: 600px; margin: 0 auto; padding: 10px;">
        <?php echo $output; ?>
        
        <form id="add-business-standard-form" method="POST" action="">
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="standard-business-search" style="display:block; font-weight:bold; margin-bottom: 8px;">Find Your Business on Google Maps:</label>
                <input type="text" id="standard-business-search" placeholder="Start typing business name..." required style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                <div id="selection-feedback"></div>
            </div>

            <input type="hidden" id="wp-business-place-id" name="business_place_id">
            <input type="hidden" id="wp-business-name" name="business_name">
            <input type="hidden" id="wp-business-lat" name="business_lat">
            <input type="hidden" id="wp-business-lng" name="business_lng">
            <input type="hidden" id="wp-business-address" name="business_address">
            <input type="hidden" id="wp-business-city" name="business_city">
            <input type="hidden" id="wp-business-country" name="business_country">

            <input type="hidden" id="wp-business-type" name="business_primary_type">
            <input type="hidden" id="wp-business-types" name="business_all_types">
            <input type="hidden" id="wp-business-permanently-closed" name="business_permanently_closed">

            <input type="hidden" id="wp-business-rating" name="business_rating">
            <input type="hidden" id="wp-business-review-count" name="business_review_count">
            <input type="hidden" id="wp-business-price-level" name="business_price_level">
            <input type="hidden" id="wp-business-open-now" name="business_open_now">
            <input type="hidden" id="wp-business-opening-hours" name="business_opening_hours">
            <input type="hidden" id="wp-business-website" name="business_website">
            <input type="hidden" id="wp-business-phone" name="business_phone">
            <input type="hidden" id="wp-business-editorial-summary" name="business_editorial_summary">

            <div id="submit-container" style="display: none; margin-top: 20px;">
                <button type="submit" id="biz-submit-btn" name="submit_business" class="button button-primary" style="padding: 12px 30px; background: #006B3E; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; width:100%;">Add Business to Directory</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean(); 
}

// =====================================================================
// 1. BACKEND FORM PROCESSING & AI GENERATION ENGINE
// =====================================================================

add_action('wp_loaded', 'directory_process_business_submission');

function directory_process_business_submission() {
    global $wpdb, $directory_form_feedback;
    $user_id = get_current_user_id();
    
    if ( isset($_POST['submit_business']) && is_user_logged_in() ) {
        $place_id           = sanitize_text_field($_POST['business_place_id']);
        $name               = sanitize_text_field($_POST['business_name']);
        $latitude           = sanitize_text_field($_POST['business_lat']);
        $longitude          = sanitize_text_field($_POST['business_lng']);
        $type               = isset($_POST['business_primary_type']) ? sanitize_text_field($_POST['business_primary_type']) : '';
        $types              = isset($_POST['business_all_types']) ? sanitize_text_field($_POST['business_all_types']) : ''; 
        $permanently_closed = isset($_POST['business_permanently_closed']) ? intval($_POST['business_permanently_closed']) : 0;
        
        $address            = sanitize_text_field($_POST['business_address']);
        $city               = sanitize_text_field($_POST['business_city']);
        $country            = sanitize_text_field($_POST['business_country']);
        $phone              = sanitize_text_field($_POST['business_phone']);
        $website            = esc_url_raw($_POST['business_website']); 
        
        $rating             = isset($_POST['business_rating']) ? sanitize_text_field($_POST['business_rating']) : ''; 
        $rating_count       = isset($_POST['business_review_count']) ? sanitize_text_field($_POST['business_review_count']) : ''; 
        $price_level        = isset($_POST['business_price_level']) ? sanitize_text_field($_POST['business_price_level']) : ''; 
        $summary            = isset($_POST['business_editorial_summary']) ? sanitize_textarea_field($_POST['business_editorial_summary']) : ''; 
  
        if ( empty($place_id) || empty($name) ) {
            $directory_form_feedback = '<div class="alert alert-danger" style="color:red; margin-bottom:15px;">Error: Missing Google Place ID or Name.</div>';
            return;
        }

        $opening_hours = [];
        if (!empty($_POST['business_opening_hours'])) {
            $raw_hours = json_decode(stripslashes($_POST['business_opening_hours']), true);
            if (is_array($raw_hours)) {
                foreach ($raw_hours as $hour_string) {
                    $opening_hours[] = sanitize_text_field($hour_string);
                }
            }
        }
        $opening_hours_db = !empty($opening_hours) ? wp_json_encode($opening_hours) : '';

        // 1. CHECK FOR EXISTING BUSINESS
        $table_name = $wpdb->prefix . 'jet_cct_business'; 
        $existing_business = $wpdb->get_row(
            $wpdb->prepare("SELECT _ID, page_url FROM `$table_name` WHERE place_id = %s", $place_id)
        );

        if ( $existing_business ) {
            $business_url = !empty($existing_business->page_url) ? $existing_business->page_url : '#';
            $directory_form_feedback = '<div class="alert alert-info" style="background-color: #e2e3e5; color: #383d41; padding: 15px; border-left: 5px solid #6c757d; margin-bottom: 20px;">';
            $directory_form_feedback .= 'This business is already in our directory!<br>';
            $directory_form_feedback .= '<a href="' . esc_url($business_url) . '">View: ' . esc_html($name) . '</a>';
            $directory_form_feedback .= '</div>';
            return;
        } 
        
        // ==========================================
        // 2. PASSED DIRECTLY TO PERPLEXITY
        // ==========================================
        $raw_info = array(
            'name'         => $business['name'],
            'address'      => $business['address'],
            'place_id'     => $business['place_id'],
            'latitude'     => $business['latitude'],
            'longitude'    => $business['longitude'],
            'email'        => $business['email'],
            'website'      => $business['website'],
            'phone'        => $business['phone'],
            'whatsapp'     => $business['whatsapp'],
            'city'         => $business['city'],
            'country'      => $business['country'],
            'introduction' => $business['introduction'],
            'fb'           => $business['fb'],
            'linkedin'     => $business['linkedin'],
            'insta'        => $business['insta'],
            'tiktok'       => $business['tiktok'],
            'rating'       => $business['rating'],
            'rating_count' => $business['rating_count'],
            'opening_hours'=> $opening_hours
        );
        
        $cct_intro = '';
        $cct_category = '';
        $cct_status = 'New';
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'place_id'          => $place_id,
                'name'              => $name,
                'latitude'          => $latitude,
                'longitude'         => $longitude,
                'type'              => $type,
                'types'             => $types,
                'permanently_closed'=> $permanently_closed, 
                'address'           => $address,
                'city'              => $city,
                'country'           => $country,
                'phone'             => $phone,
                'website'           => $website,
                'introduction'      => $cct_intro,
                'category'          => $cct_category,
                'price_level'       => $price_level,
                'rating'            => $rating,
                'rating_count'      => $rating_count,
                'opening_hours'     => $opening_hours_db,
                'listing_status'    => $cct_status,
                'cct_author_id'     => $user_id
            ),
            array('%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')
        );

        if ( $inserted ) {
            $new_cct_id = $wpdb->insert_id;
            
            // ==========================================
            // 4. CREATE WORDPRESS POST ASSET MAPPING
            // ==========================================
            $post_args = array(
                'post_title'   => $name,
                'post_content' => wp_kses_post($ai_data['content']),
                'post_status'  => 'publish',
                'post_type'    => 'business',
                'post_author'  => $user_id
            );
            
            $new_post_id = wp_insert_post($post_args);
            
            if ( ! is_wp_error($new_post_id) && $new_post_id > 0 ) {
                $post_full_url = get_permalink($new_post_id);
                
                update_post_meta($new_post_id, 'item_id', $new_cct_id);
                if (!empty($ai_data['title'])) update_post_meta($new_post_id, 'rank_math_title', sanitize_text_field($ai_data['title']));
                if (!empty($ai_data['metaDescription'])) update_post_meta($new_post_id, 'rank_math_description', sanitize_textarea_field($ai_data['metaDescription']));
                if (!empty($ai_data['keywords'])) update_post_meta($new_post_id, 'rank_math_focus_keyword', sanitize_text_field($ai_data['keywords']));

                $wpdb->update(
                    $table_name,
                    ['cct_single_post_id' => $new_post_id, 'page_url' => $post_full_url],
                    ['_ID' => $new_cct_id],
                    ['%d', '%s'],
                    ['%d']
                );
                
                // $post_full_url
                wp_redirect( $post_full_url );
                exit;

            } else {
                // Failsafe: if WP Post creation failed, delete the orphaned CCT record
                $wpdb->delete($table_name, ['_ID' => $new_cct_id]);
                $directory_form_feedback = '<div class="alert alert-danger">Error creating WordPress post.</div>';
            }
        } else {
            $directory_form_feedback = '<div class="alert alert-danger">Database error: Could not insert business record.</div>';
        }
    }
}


// =====================================================================
// 3. AJAX EXISTING PLACE CHECKER
// =====================================================================
add_action('wp_ajax_directory_check_existing_place', 'directory_ajax_check_existing_place');
add_action('wp_ajax_nopriv_directory_check_existing_place', 'directory_ajax_check_existing_place');

function directory_ajax_check_existing_place() {
    global $wpdb;
    $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
    
    if ( empty($place_id) ) wp_send_json_error();
    
    $table_name = $wpdb->prefix . 'jet_cct_business';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT page_url FROM `$table_name` WHERE place_id = %s LIMIT 1", $place_id));
    
    if ( $existing ) {
        wp_send_json_success(['exists' => true, 'page_url' => !empty($existing->page_url) ? esc_url($existing->page_url) : '#']);
    } else {
        wp_send_json_success(['exists' => false]);
    }
}





// =====================================================================
// 5. PERPLEXITY AI GENERATION ENGINE
// =====================================================================

function mfa_business_perplexity($info) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
    if (empty($api_key)) return json_encode(['error' => 'No API Key']);

    // Use HEREDOC syntax for clean, safe multi-line string definition without escaping nightmares
    $system_prompt = <<<EOT
You are an experienced SEO content writer for Masjid4All.com, a global Muslim-Friendly Business Directory.

## Grounding Requirements (STRICT)

You MUST ONLY use information that appears in the provided live search results.

Do NOT:
- Invent facts.
- Fill in missing information.
- Assume facts based on the business name, location, reviews, or that it is Muslim-owned.
- State or imply any certification unless it is explicitly mentioned in the search results.
- DO NOT write "Not available" if the information is not available. Omit the field
If a fact cannot be verified from the search results, say so or omit it.


CRITICAL INSTRUCTIONS:
1. JSON ONLY: You MUST respond ONLY with a valid, raw JSON object. Do not wrap the JSON in markdown (e.g., no ```json). Do not add any conversational text. 
2. NO CITATIONS ALLOWED: You must completely strip out and remove all inline numerical citation markers, reference brackets, and source links (e.g., NEVER output [1], [2], [3], etc.). The text must read naturally without any bracketed numbers.
3. SINGLE QUOTES: Use SINGLE QUOTES for all HTML attributes inside the "content" string to prevent JSON escaping errors (e.g., <a class='btn' href='url'>).
4. Halal Status (STRICT)

The term "Halal Certified" may ONLY be used when the live search results explicitly state that the business holds a valid halal certificate issued by an official halal certification authority (such as JAKIM, MUIS, MUI, CICOT, or the relevant authority for that country).

DO NOT infer certification because:
- the restaurant is Muslim-owned
- the restaurant serves halal food
- reviews say it is halal
- the restaurant is listed on Muslim websites
- the restaurant has halal signage
- it is commonly believed to be halal

If the live search results do not explicitly mention official halal certification, NEVER write:
- halal-certified
- certified halal
- JAKIM halal certified
- officially halal certified

Instead use one of:
- Muslim-owned (if supported by search results)
- Serves halal food (if supported)
- Halal status could not be verified from publicly available information.
- No halal certification information was found.

 
Generate exactly these JSON fields. Only include fields if data exists, otherwise omit them. DO NOT write "Not available".
{
  "title": "Short compelling title (50-70 chars) with Name, Category, City",
  "keywords": "comma-separated SEO keywords (8-15 items)",
  "metaDescription": "SEO meta description",
  "category": "Matched category from list",
  "halalStatus": "halal-certified OR assumed halal OR muslim-owned OR non-halal OR not-applicable",
  "shariahCompliance": "syaria-compliant OR non-syaria-compliant OR unverified",
  "introduction": "3-5 sentence intro",
  "listingStatus": "Approved OR Pending OR Rejected",
  "country": "Country Name",
  "city": "City Name",
  "website": "URL",
  "phone": "Phone",
  "email": "Email",
  "whatsapp": "WhatsApp",
  "facebook": "Facebook Page URL",
  "instagram": "Instagram Page URL",
  "linkedin": "Linked Page URL",
  "tiktok": "Tiktok Page URL",
    
  "content": "The full HTML article"
}

For "listingStatus": 
    - "Approved" -> default status for standard Islamic business. Fully aligns with Islamic principles, no obvious violations.
    - "Pending" -> contains controversial, sectarian, or highly ambiguous content requiring a human scholar's expert review. Do NOT use this status just for general caution.
    - "Rejected" -> contains clearly non-halal such as pork, alcohol or un-Islamic content (e.g., interest-based riba, music, gambling, inappropriate images).
- For "status_detail": Explain your reasoning.


CATEGORIES LIST: 
['Food & Beverage', 'Retail & Shopping', 'Health & Wellness', 'Professional Services', 'Trades & Home Services', 'Automotive', 'Beauty & Personal Care', 'Entertainment & Leisure', 'Education & Training', 'Manufacturing & Industrial', 'Hospitality & Travel', 'Real Estate & Property', 'Finance & Insurance', 'General Business']

HTML CONTENT RULES:
- Output "content" as a single continuous HTML string with H1, H2, H3.
- DO NOT use line breaks (\n) inside the JSON string.
- Structure the HTML exactly like this:

<h1>((Business Name))</h1>
<h2>Introduction</h2>
<p>3-5 sentences explaining the business, location, and target audience.</p>

<h2>About the Business</h2>
<p>Company overview, mission, and industries served.</p>

<h2>Location and Contact Information</h2>
<p><strong>Address:</strong> ((Address))</p>

<span class='location-btn'>
  <a class='btn btn-google-map' href='[https://www.google.com/maps/search/?api=1&query=Business&query_place_id=](https://www.google.com/maps/search/?api=1&query=Business&query_place_id=)((Place_ID))' target='_blank' rel='noopener'>Google Map</a>    
  <a class='btn btn-waze-map' href='[https://waze.com/ul?ll=](https://waze.com/ul?ll=)((Latitude)),((Longitude))&navigate=yes' target='_blank' rel='noopener'>Waze</a>
</span>

<p>2-4 sentences explaining how to reach the location.</p>

<h3>Contact Details</h3>
<p><strong>Telephone:</strong> ((Telephone))</p>
<p><strong>WhatsApp:</strong> <a href='[https://wa.me/](https://wa.me/)((WhatsApp_clean))' target='_blank' rel='noopener'>Chat on WhatsApp</a></p>
<p><strong>Website:</strong> <a href='((Website))' target='_blank' rel='noopener'>Official website</a></p>
<p><strong>Email:</strong> <a href='mailto:((Email))' target='_blank' rel='noopener'>Email Us</a></p>

<h2>Products and Services</h2>
<p>3-6 sentences describing core products/services.</p>

<h2>Muslim-Friendly & Halal Assurance</h2>

Write 2–4 factual sentences.

Every sentence MUST be directly supported by the live search results.

If official halal certification is not explicitly mentioned in the search results, state:

"Official halal certification could not be verified from publicly available information."

Never mention JAKIM or any other certification authority unless the search results explicitly say the business is certified.


FAQ Section
Include at least 5 FAQ items.
Format the FAQs as a numbered list using <ol>.
Each question MUST be inside <strong> tags.
Each answer MUST be on a new line inside its own <p> tag.

Example:
<h2 id="faq">Frequently Asked Questions</h2>
<ol>
  <li>
    <strong>What is Example Website?</strong>
    <p>Example Website is an online platform that provides...</p>
  </li>

  <li>
    <strong>Who is the website intended for?</strong>
    <p>The website serves...</p>
  </li>
</ol>

<div class="m4a-card">
    <h3>About Masjid4All</h3>

    <p>Masjid4All connects Muslims worldwide by uniting essential Islamic resources into one digital hub:</p>

    <div class="m4a-lists">
        <ul>
            <li><a href="/masjid/">Masjid Directory</a></li>
            <li><a href="/business/">Business Directory</a></li>
            <li><a href="/web/">Website Directory</a></li>
            <li><a href="/knowledge-hub/">Knowledge Hub</a></li>
        </ul>

        <ul>
            <li><a href="/prayer-times/">Prayer Time</a></li>
            <li><a href="/qibla-finder/">Qibla Finder</a></li>
            <li><a href="/quran/">Daily Quran</a></li>
            <li><a href="/member/">Member's Page</a></li>
        </ul>
    </div>
</div>
EOT;

    $business_info = is_array($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    $question    = "Generate a business profile using this data: " . $business_info . " and search their website and facebook (if available)";

    // CRITICAL FIX: The API URL is now a clean string, completely stripped of Markdown brackets.
    $response = wp_remote_post('https://api.perplexity.ai/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'sonar',
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
            'max_tokens'  => 2500,
            'temperature' => 0.1
        ]),
        'timeout' => 45
    ]);
 
    if (is_wp_error($response)) {
        return json_encode(['error' => 'API fail', 'reason' => $response->get_error_message()]);
    }

    $body        = json_decode(wp_remote_retrieve_body($response), true);
    $raw_content = $body['choices'][0]['message']['content'] ?? '';

    // Advanced regex to cleanly extract JSON even if AI adds conversational text
    if (preg_match('/\{.*\}/s', $raw_content, $matches)) {
        return $matches[0];
    }

    return $raw_content;
}



