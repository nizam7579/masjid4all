<?php

/**
  BUSINESS DISPLAY
  1. Business Display Info
  2. Business AI Update Callback
  3. Build HTML Content 
  4. Perplexity Business API
  5. Business Sidebar
  
  NEAREST BUSINESS LISTING
  1. Nearest Business Listing 
  2. Business Location Exist
  3. Serper Location Indexing 
  4. Serper Nearest Business
  
  OTHER FUNCTIONS
  1. Get Field From Business CCT  
  2. Save CCT Business Data
  3. Save CCT Business Jetengine
  
*/

function serper_nearest_business($latitude, $longitude) {

    global $wpdb;

    if (empty($latitude) || empty($longitude)) {
        return [
            'processed' => 0,
            'inserted'  => 0,
            'updated'   => 0,
            'list'      => ''
        ];
    }

    $lat = floatval($latitude);
    $lng = floatval($longitude);

    $api_key = defined('SERPER_API_KEY') ? SERPER_API_KEY : '';
    if (empty($api_key)) {
        return [
            'processed' => 0,
            'inserted'  => 0,
            'updated'   => 0,
            'list'      => 'API KEY ERROR'
        ];
    }

    // -----------------------------------
    // 🔥 CURL FETCH FUNCTION
    // -----------------------------------
    $fetch_business = function ($zoom) use ($lat, $lng, $api_key) {

        $curl = curl_init();

        $payload = json_encode([
            'q'  => 'halal',
            'll' => "@{$lat},{$lng},{$zoom}z"
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://google.serper.dev/maps',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'X-API-KEY: ' . $api_key,
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('cURL Error: ' . curl_error($curl));
            curl_close($curl);
            return [
                'processed' => 0,
                'inserted'  => 0,
                'updated'   => 0,
                'list'      => 'CURL ERROR'
            ];
            
        }

        curl_close($curl);

        return json_decode($response, true);
    };

    // -----------------------------------
    // 🔥 FETCH DATA (ZOOM FALLBACK)
    // -----------------------------------
    $data = $fetch_business(14);

    if (!$data || empty($data['places'])) {
        $data = $fetch_business(10);
    }

    $table = $wpdb->prefix . 'jet_cct_business';
    $today = current_time('Y-m-d');

    $processed = 0;
    $inserted  = 0;
    $updated   = 0;
    $list = '';

    foreach ($data['places'] as $item) {

        $cid      = $item['cid'] ?? '';
        $place_id = $item['placeId'] ?? '';

        if (!$cid || !$place_id) continue;

        $name = trim($item['title'] ?? '');
        $type = $item['type'] ?? '';
        $address = $item['address'] ?? '';
       
        $types_arr = is_array($item['types'] ?? null) ? $item['types'] : [];
        $types     = json_encode($types_arr);
        $ttypes    = implode(' ', $types_arr);
        
       
        // 🔥 HALAL SCORE (DO NOT FILTER HERE)
        $score = get_halal_score($name, $type, $types_arr);
        
        $list .= $name . ' - ' . $type . ' - ' . $ttypes . ' * ' . $score  . '<br>';
        if ($score < 40){
            continue;
        }
        $processed++;

        // -----------------------------------
        // 🔥 DATA PREP
        // -----------------------------------
        $cct_data = [
            'cid'            => $cid,
            'place_id'       => $place_id,
            'name'           => $name,
            'address'        => $address,
            'website'        => $item['website'] ?? '',
            'phone'          => $item['phoneNumber'] ?? '',
            'rating'         => $item['rating'] ?? 0,
            'rating_count'   => $item['ratingCount'] ?? 0,
            'latitude'       => round(floatval($item['latitude'] ?? 0), 6),
            'longitude'      => round(floatval($item['longitude'] ?? 0), 6),
            'type'           => $type,
            'types'          => $types,
            'cct_created'    => $today,
            'cct_modified'   => $today
        ];
 
        // -----------------------------------
        // 🔥 DYNAMIC PREPARE FORMATS
        // -----------------------------------
        $formats = [];
        $values  = [];

        foreach ($cct_data as $value) {
            if (is_numeric($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
            $values[] = $value;
        }

        $fields = array_keys($cct_data);

        $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ")
                VALUES (" . implode(',', $formats) . ")
                ON DUPLICATE KEY UPDATE ";

        $updates = [];
        foreach ($fields as $field) {
            if ($field !== 'place_id') {
                $updates[] = "$field = VALUES($field)";
            }
        }

        $sql .= implode(',', $updates);

        $result = $wpdb->query($wpdb->prepare($sql, $values));

        if ($result === 1) {
            $inserted++;
        } else {
            $updated++;
        }

        // -----------------------------------
        // 🔥 GET ITEM ID
        // -----------------------------------
        $item_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT _ID FROM {$table} WHERE place_id = %s LIMIT 1",
                $place_id
            )
        );

        if (!$item_id) continue;

        // -----------------------------------
        // 🔥 GET / CREATE POST
        // -----------------------------------
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cct_single_post_id FROM {$table} WHERE _ID = %d",
                $item_id
            )
        );

        $need_create = false;

        if (empty($post_id)) {
            $need_create = true;
        } else {
            $post = get_post($post_id);
            if (!$post || $post->post_status === 'trash') {
                $need_create = true;
            }
        }

        if ($need_create) {
            $post_id = wp_insert_post([
                'post_title'  => $name,
                'post_type'   => 'business',
                'post_status' => 'publish'
            ]);
        }

        // -----------------------------------
        // 🔥 UPDATE POST
        // -----------------------------------
        if ($post_id && !is_wp_error($post_id)) {

            update_post_meta($post_id, 'item_id', $item_id);

            if (empty(trim(get_post_field('post_content', $post_id)))) {

                $content = '<h2>' . esc_html($name) . '</h2>';
                $content .= '<p>' . esc_html($address) . '</p>';
                $content .= '<p><a href="https://pewarisan.my/">Pewarisan</a></p>';
                $content .= '<p><a href="https://masjid4all.com/">Masjid4All</a></p>';

                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ]);
            }

            // SEO
            update_post_meta($post_id, 'rank_math_title', $name);
            update_post_meta($post_id, 'rank_math_description', $name);
            update_post_meta($post_id, 'rank_math_focus_keyword', $name);

            // UPDATE TABLE LINK
            $wpdb->update(
                $table,
                [
                    'business_status'    => 'Listed',
                    'cct_single_post_id' => $post_id,
                    'page_url'           => get_permalink($post_id),
                ],
                ['_ID' => $item_id]
            );
        }

        // light throttle
        usleep(100000);
    }

    return [
        'processed' => $processed,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'list'      => $list
    ];

}



function get_halal_score($name, $type, $types_arr) {

    $text = strtolower($name . ' ' . $type . ' ' . implode(' ', $types_arr));
    $name_lower = strtolower($name);

    $score = 0;

    // ------------------------
    // ❌ HARD EXCLUDE
    // ------------------------

    if (preg_match('/\b(non[\s-]?halal|not halal|serve pork|pork menu)\b/', $text)) {
        return 0;
    }

    if (preg_match('/\b(mosque|masjid|surau)\b/', $text)) {
        return 0;
    }

    // ------------------------
    // 🧠 CATEGORY DETECTION
    // ------------------------

    $is_restaurant = preg_match('/\b(restaurant|cafe|eatery|bistro|warung|stall|food court|hawker)\b/', $text);
    
    $is_grocery = preg_match('/\b(grocery|mart|mini market|supermarket|hypermarket|convenience store)\b/', $text);
    
    $is_supply = preg_match('/\b(halal supplier|food supplier|distributor|wholesale|manufacturer|factory|food processing|frozen food)\b/', $text);

    // If NONE → reject
    if (!$is_restaurant && !$is_grocery && !$is_supply) {
        return 0;
    }

    // ------------------------
    // ❗ NEGATIVE PROTECTION
    // ------------------------
    $has_no_pork = preg_match('/\b(no|without)\s+(pork|bacon|ham|lard)\b/', $text);

    // ------------------------
    // ✅ STRONG POSITIVE SIGNALS
    // ------------------------

    if (preg_match('/\bhalal\b/', $text)) $score += 60;

    if (preg_match('/\b(jakim|halal certified|muslim owned)\b/', $text)) $score += 80;

    if (preg_match('/\b(muslim|islam|islamic)\b/', $text)) $score += 40;

    // Malay / Muslim culture
    if (preg_match('/\b(malay|melayu|mamak|warung|nasi kandar)\b/', $text)) {
        $score += 50;
    }

    // Middle Eastern
    if (preg_match('/\b(arab|middle eastern|turkish|yemeni|syrian|lebanese|kebab|shawarma)\b/', $text)) {
        $score += 60;
    }

    // South Asian Muslim
    if (preg_match('/\b(pakistani|bangladeshi|indian muslim)\b/', $text)) {
        $score += 50;
    }

    // ------------------------
    // 🛒 GROCERY / PRODUCT BOOST
    // ------------------------

    if ($is_grocery) {
        $score += 30;
    }

    if (preg_match('/\b(halal mart|halal grocery|halal shop|halal butcher|daging halal)\b/', $text)) {
        $score += 60;
    }

    // ------------------------
    // 🏭 SUPPLY CHAIN BOOST
    // ------------------------

    if ($is_supply) {
        $score += 30;
    }

    if (preg_match('/\b(halal supplier|halal manufacturer|halal certified factory)\b/', $text)) {
        $score += 70;
    }

    // ------------------------
    // 🧠 NAME PATTERN BOOST
    // ------------------------

    if (preg_match('/\b(ali|ahmad|muhammad|abu|al-|bin|binti)\b/', $name_lower)) {
        $score += 20;
    }

    // ------------------------
    // ⚖️ WEAK POSITIVE SIGNALS
    // ------------------------

    if (preg_match('/\b(frozen food|chicken|fresh meat)\b/', $text)) {
        $score += 15;
    }

    // ------------------------
    // ❌ NEGATIVE SIGNALS
    // ------------------------

    if (preg_match('/\b(bar|pub|wine|beer|liquor|alcohol)\b/', $text)) {
        $score -= 70;
    }

    if (!$has_no_pork && preg_match('/\b(pork|bacon|ham|lard)\b/', $text)) {
        $score -= 90;
    }

    if (preg_match('/\b(non[-\s]?halal)\b/', $text)) {
        return 0;
    }

    // Western / mixed (soft penalty)
    if (preg_match('/\b(mexican|italian|steakhouse|bbq)\b/', $text)) {
        $score -= 20;
    }

    // ------------------------
    // 🎯 NORMALIZE
    // ------------------------

    $score = max(0, min(100, $score));

    return $score;
}

// Serper key: see mfa_serper_key() in mfa-core (MFA_SERPER_API_KEY constant).


/*

// 1. UNIQUE Business Display Info Shortcode
add_shortcode('business_display_info', function() {
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);
    // Uses the business-specific CCT function
    $status = get_cct_business_data($item_id, 'business_status');
    $content = get_the_content();
  
    ob_start(); ?>
    <div id="biz-only-container" class="biz-info-wrapper">
        <?php if ($status === 'Updated') : ?>
            <div class="mosque-actual-content">
                <?php echo apply_filters('the_content', $content); ?>
            </div>
        <?php else : ?>
            <div class='update-prompt-box' style='padding: 20px; background: #F6D1C1;border: 1px #ccc; border-radius: 20px; text-align: center; '>
               <p><b style='color:black; font-size:18px'>This business has not been updated.</b><br>Help us update the information by triggering our <b>AI Agent</b>. It only takes seconds to generate a comprehensive profile for the benefit of all future visitors</p>
                <button id="btn-trigger-biz-ai" style=" background:#D4591E; color:#fff;" class="update-btn"  
                        data-id="<?php echo $post_id; ?>" 
                        data-name="<?php echo esc_attr(get_the_title()); ?>">
                    Update Business Information
                </button>
                <div id="biz-only-spinner" style="display:none; margin-top:10px;">
                    <span class="spinner is-active" style="float:none;"></span><b style="color:black;">Please wait...<br>Our AI Agent is updating this business.</b><br>
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#btn-trigger-biz-ai').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            btn.hide();
            $('#biz-only-spinner').show();

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ajax_biz_ai_update', // UNIQUE ACTION
                    post_id: btn.data('id'),
                    business_name: btn.data('name')
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('AI Error: ' + (response.data || 'Empty Response'));
                        btn.show();
                        $('#biz-only-spinner').hide();
                    }
                },
                error: function(xhr, status, error) {
                    alert('Server Error: ' + error);
                    btn.show();
                    $('#biz-only-spinner').hide();
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});
*/






// 3. Build HTML Content
function biz_build_html_layout($data) {
    // Handle nested JSON structure
    if (isset($data['basic_info']) && is_array($data['basic_info'])) {
        $data = array_merge($data, $data['basic_info']);
    }
    
    $content = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />';
    
    $content .= '<p><b>' . esc_html($data['basic_info']['introduction'] ?? '') . '</b></p>';


    if (isset($data['halal_status']['status'])) {
        $halal_status = $data['halal_status']['status'];
        $halal_icon = match($halal_status) {
            'Halal certified' => '<i class="fas fa-check-circle text-success" style="color:#28a745; font-size:1.2em;"></i>',
            'Assumed halal (Muslim-owned)' => '<i class="fas fa-handshake text-info" style="color:#17a2b8;"></i>',
            'Muslim-friendly (no pork & alcohol)' => '<i class="fas fa-utensils text-warning" style="color:#ffc107;"></i>',
            default => '<i class="fas fa-question-circle text-danger" style="color:#dc3545;"></i>'
        };
        
        $content .= '<b>Halal Status : <br>';
        $content .= $halal_icon . ' ' . esc_html($halal_status) . '</b><br>';
        $content .= esc_html($data['halal_status']['halal_evidence'] ?? 'N/A') . '<br><br>';
    }

    // 3. BASIC INFO TABLE
    //$content .= '<table style="border:none; border-collapse: collapse; margin-bottom: 25px; width: 100%; max-width: 600px; font-size: 15px;">';
    
        // --- Start Information Table ---
    $content .= '<table style="border:none; border-collapse: collapse; margin-top: 15px; width: 100%; max-width: 600px; font-size: 15px; line-height: 1.6;">';

    // Define all rows: Label/Icon => Data Key
    $rows = [
        '📍' => 'address',
        '📞' => 'telephone',
        '🌐' => 'website',
        '<i class="fab fa-facebook-f"></i>'  => 'facebook',
        '<i class="fab fa-instagram"></i>' => 'instagram',
        '<i class="fab fa-youtube"></i>'   => 'youtube'
    ];

    foreach ($rows as $icon => $key) {
        $raw_value = $data[$key] ?? '';
        
        // Clean citations [1] and parentheses text for URLs
        $clean_value = preg_replace('/\[\d+\]/', '', $raw_value);
        
        // Only strip parentheses if it's one of the social/website keys
        if (in_array($key, ['website', 'facebook', 'instagram', 'youtube'])) {
            $clean_value = preg_replace('/\s*\(.*\)\s*/', '', $clean_value);
        }
        
        $clean_value = trim($clean_value);

        $content .= '<tr>';
        // Left Column: Icon (fixed width for alignment)
        $content .= '<td style="padding: 4px 12px 8px 0; width: 35px; vertical-align: top; border:none; text-align: center;">' . $icon . '</td>';
        
        // Right Column: Content
        $content .= '<td style="padding: 4px 0; vertical-align: top; border:none;">';
        
        if (!empty($clean_value) && strtolower($clean_value) !== 'not available' && $clean_value !== 'N/A') {
            
            // If it's a URL key, make it a link
            if (in_array($key, ['website', 'facebook', 'instagram', 'youtube']) && filter_var($clean_value, FILTER_VALIDATE_URL)) {
                $content .= '<a href="' . esc_url($clean_value) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none; color:#0073aa; font-weight: 500;">' . esc_html($clean_value) . '</a>';
            } else {
                // Otherwise, display as plain text (Address/Telephone)
                $content .= esc_html($clean_value);
            }
            
        } else {
            $content .= '<span style="color:#999; font-style:italic;">Not Available</span>';
        }
        
        $content .= '</td></tr>';
    }
    $content .= '</table>';
    
    $content .= '<b>Specialities</b><br>';
    $content .= esc_html($data['speciality']) . '<br><br>';
    
    $content .= '<b>Operations</b><br>';
    $content .= esc_html($data['operations']) . '<br><br>';

    $content .= '<b>Reviews</b><br>';
    $content .= esc_html($data['reviews']) . '<br><br>';

    $content .= '<b>Facilities</b><br>';
    $content .= esc_html($data['facilities']) . '<br><br>';

    $content .= '<b>Notes for Muslim Users</b><br>';
    $content .= esc_html($data['notes_for_muslim_users']) . '<br><br>';

    //$content .= '</div>';
     
    return $content;
}

// 4. Perplexity Business API

// PERPLEXITY_API_KEY is resolved in keys.php (wp-config constant, then DB option).

function perplexity_business($info) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
    if (empty($api_key)) return json_encode(['error' => 'No API Key']);


    $role = '
        [SYSTEM INSTRUCTIONS] 
        You are an expert real-time business data extraction agent and an automated Shariah/Halal screening engine. 
        Your task is to perform live web research based on the provided business details, 
        verify the operational details of the business, screen its compliance status, and format the output into a precise, clean JSON structure with zero conversational prose. 
        
        [COMPLIANCE SCREENING RULES] 
        1. HALAL STATUS (Apply only to Food, Beverages, Supermarkets, Bakeries, Cosmetics, or Manufacturing businesses handling consumables): - "halal-certified": 
        There is verifiable online confirmation or registry data indicating an official Halal certification (e.g., JAKIM, MUIS, etc.). 
        - "muslim-owned": The establishment is explicitly identified as Muslim-owned or features a verified Muslim-run kitchen/menu without carrying an official regulatory certificate. 
        - "non-halal": The business explicitly serves or manufactures pork, non-halal meats, or alcohol. 
        - "not-applicable": The business does not sell or handle food, beverages, or raw consumables (e.g., an online garment shop or a tech service). 
        
        2. SHARIAH COMPLIANCE STATUS (Apply to all businesses, with a focus on Finance, Services, and Corporate operations): 
        - "syaria-compliant": The business runs standard ethical operations. This includes Islamic banks, Takaful, or general benign trades (e.g., online garment shops, digital marketing, IT services) whose core revenue does not come from forbidden avenues. 
        - "non-syaria-compliant": The business must be marked as non-compliant if its core operations involve: Conventional interest-bearing financial services (Riba), gambling/casinos (Maysir), adult entertainment, bars/nightclubs, or tobacco/liquor manufacturing. 
        - "unverified": There is a total lack of public data regarding their corporate debt structure or operational activities to make an definitive assessment. 
        
        [CATEGORY MAPPING LIST] 
        You must match the business strictly to exactly ONE of these precise macro strings: 
        - "Food & Beverage" - "Retail & Shopping" - "Health & Wellness" - "Professional Services" - "Trades & Home Services" - "Automotive" - "Beauty & Personal Care" - "Entertainment & Leisure" - "Education & Training" - "Manufacturing & Industrial" - "Hospitality & Travel" - "Real Estate & Property" - "Finance & Insurance" - "General Business" 
        
        [CONTENT GENERATION RULES] Generate an "seo_content" field as an HTML string. - It must include an <h2> header containing the business name. - It must contain two paragraphs (<p>) describing what the business does, their specialty, and their target market based on live web facts. - It must include an unordered list (<ul>) with 3-4 bullet points (<li>) highlighting their key offerings or selling points. - Do NOT include markdown tags inside the JSON string value (e.g., do not write \`\`\`html). Write pure text with escaped HTML characters. 
        
        [JSON OUTPUT SCHEMA REQUIREMENT] You must return nothing but a valid JSON object matching this structure exactly. 
        No conversational preambles or post-scripts. 
        { "name": "String - Verified official name", "address": "String - Found street address", "city": "String", "country": "String", "phone": "String - E.164 format or null", "website": "String - URL or null", "assigned_category": "String - Must match Category List exactly", "halal_status": "String - Only use: halal-certified | non-halal | muslim-owned | not-applicable", "shariah_status": "String - Only use: syaria-compliant | non-syaria-compliant | unverified", "seo_content": "String - Pure HTML text escaped safely inside the JSON value" } 
        
        ';
    

    /*
    
    $role = 'You are an Expert Muslim-friendly Directory Researcher for GLOBAL businesses.
        Your goal is to search the web (Google, Google Maps, Facebook, Instagram, YouTube, TripAdvisor, Yelp, delivery apps) to create comprehensive profiles for Muslim-friendly businesses worldwide.

        ### INPUT DATA
        You will receive: Business Name, Address, Google Map Link, Website, Social Media (if provided)
        
         ### YOUR MISSION
        1. **Search Deeply**: Look for official pages, recent reviews, halal certification portals
        2. **Be Descriptive**: Write detailed, helpful sentences (2-4 sentences per field)
        3. **Global Halal Classification** - Use EXACTLY ONE of these 4 categories:
           - "Halal certified" (official certificate from recognised body)
           - "Assumed halal (Muslim-owned)" (Muslim owners, no haram evidence, no certificate)
           - "Muslim-friendly (no pork & alcohol)" (no pork/alcohol claimed, no certificate)
           - "Not halal / unclear" (pork/alcohol served or info insufficient)
        4. **Format**: Return ONLY valid JSON with exact fields below.
     ';
    */
    
    $business_info = is_array($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    
    // Updated Global Muslim-friendly JSON schema
    $system_prompt  = $role;
    $system_prompt .= " You must provide accurate data based on available information.";
    $system_prompt .= " Do not include any citation. Always respond in valid JSON format only with these exact fields: 
        {
        \"basic_info\": {
            \"introduction\": \"Write an SEO-friendly business introduction (150–200 characters) for a restaurant. Mention the restaurant name, cuisine type, ambience and key specialty dishes. Use clear, appealing language, include 1–2 location-based keywords, and avoid keyword stuffing. The tone should be warm and inviting, suitable for Google Business Profile and website meta description.\",
            \"address\": \"full street address\",
            \"city\": \"city name only\",
            \"postcode\": \"postcode/ZIP only\",
            \"state\": \"state/province only\",
            \"country\": \"country name only\",
            \"telephone\": \"phone number or 'Not available'\",
            \"email\": \"email or ''\",
            \"website\": \"official website or ''\"
            \"facebook\": \"Facebook URL or ''\",
            \"instagram\": \"Instagram URL or ''\",
            \"youtube\": \"YouTube URL or ''\",
            \"x_twitter\": \"URL or ''\",
            \"ordering_links\": [\"GrabFood URL\", \"Foodpanda URL\"]
        },
        
        \"speciality\": \"main products or services, signature items, price range, suitable_for : Family dining, Quick lunch, Business\",
      
        \"halal_status\": {
            \"status\": \"Halal certified|Assumed halal (Muslim-owned)|Muslim-friendly (no pork & alcohol)|Not halal / unclear\",
            \"halal_certifying_body\": \"JAKIM/MUI/HMC/None\",
            \"halal_evidence\": \"2-4 sentences explaining your classification with sources\",
            \"syariah_compliance_notes\": \"2-4 sentences on Shariah alignment\"
        },

        \"operations\": \"opening_hours, peak_hours, busy periods\",

        \"facilities\": \"Prayer Space : (on-site surau/nearby mosque/none), Parking : (2-3 sentences on availability), Seating Capacity, family friendly, accessibility \",

        \"reviews\": \"review summary (3-5 sentences on positives/negatives), popular_praises (clean, friendly staff)\",
    
        \"notes_for_muslim_users\": \"overall_safety_assessment (2-4 sentences advising Muslim travellers), things_to_check (check halal logo,ask about kitchen )\",

    }";  
    
    // Same question format as before
    $question = "Info: {$business_info}\n\nAnalyze this business. Provide complete Muslim-friendly directory information in the exact JSON format requested.";

    $response = wp_remote_post('https://api.perplexity.ai/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'sonar', // sonar-pro is recommended for better reasoning if available
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $question
                )
            ),
            'max_tokens' => 2000, // Increased for richer global data
            'temperature' => 0.1
        ]),
        'timeout' => 45
    ]);
 
    if (is_wp_error($response)) return json_encode(['error' => 'API fail']);
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $raw_content = $body['choices'][0]['message']['content'] ?? '';

    // Clean JSON extraction (UNCHANGED)
    if (preg_match('/\{.*\}/s', $raw_content, $matches)) {
        return $matches[0];
    }
    return $raw_content;
}


/*

function xperplexity_business($info) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
    if (empty($api_key)) return json_encode(['error' => 'No API Key']);

    $role = 'You are an Expert Muslim-friendly Directory Researcher and Data Curator.
        Your goal is to search the web (Google, Google Maps, Facebook, Instagram, YouTube) to create a comprehensive, detailed, and practical profile for a muslim-friendly businesses.

        ### INPUT DATA
        You will receive:
        1. Business Name
        2. Address
        3. Google Map Link
        
         ### YOUR MISSION
        1. **Search Deeply**: Look for official Facebook pages, recent Google reviews, and community posts.
        2. **Be Descriptive**: Do NOT return short 1-word answers. Write detailed, helpful sentences.
           - *Bad:* "Parking: Limited."
           - *Good:* "Parking is limited inside the compound, but visitors can park at the nearby shop lots along Jalan 2. It gets very congested during Friday prayers, so arrive by 12:30 PM."
        3. **Format**: Return ONLY valid JSON.
     ';
   
    $business_info = is_array($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    
    // Updated Prompt with strict Jumu'ah definitions
    $system_prompt  = $role;
    $system_prompt .= " You must provide accurate data based on available information.";
    $system_prompt .= " Please do not add any citation. Always respond in valid JSON format only with these exact fields: 
        {
        \"introduction\": \"SEO-friendly introduction (150-200 chars). Include year established (if found, otherwise omit to mention the year established), neighborhood vibe, and capacity.\",
        \"address\": \"full address\",
        \"city\": \"name of city only\",
        \"postcode\": \"postcode only\",
        \"country\": \"country only\",
        \"jumaat_prayer\": \"'Yes' or 'No'. Return 'Yes' if the place conducts the congregational weekly Friday prayer with a Khutbah (sermon). If unsure, default to 'No'.\",
        \"activities\": \"Key activities (e.g., Daily 5 prayers, Tahlil, Fardu Ain classes)\",
        \"telephone\": \"Contact number or 'Not available'\",
        \"website\": \"Official website URL or ''\",
        \"facebook\": \"Facebook URL or ''\",
        \"instagram\": \"Instagram URL or ''\",
        \"youtube\": \"YouTube URL or ''\",
        \"facilities\": [\"List of facilities e.g., 'Van Jenazah', 'Cooking Area', 'Hall', 'Funeral Arrangement', 'Classes' \"],
        \"community_role\": \"Community role e.g., 'Community Centre', 'Transit Prayer Stop'. Describe its social role (e.g., 'Active center for university students', 'Focuses on elderly education', 'Famous for free Iftar')\",
        \"public_transport\": \"Nearest stations/routes or if no information, ask user to use the Google Map or Waze buttons on top right of this page.\",
        \"additional_info\": \"Relevant info (donations, zakat counter).  List specific recent activities (e.g., 'Kuliah Subuh every weekend', 'Fardu Ain classes for kids').\"
    }";  
    
    // Added specific instruction to the user question to reinforce the rule
    $question = "Info: {$business_info}\n\nAnalyze this place. Provide complete Masjid4All directory information in the exact JSON format requested.";

    $response = wp_remote_post('https://api.perplexity.ai/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'sonar', // sonar-pro is recommended for better reasoning if available
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $question
                )
            ),
            'max_tokens' => 1500,
            'temperature' => 0.1
        ]),
        'timeout' => 45
    ]);

    if (is_wp_error($response)) return json_encode(['error' => 'API fail']);
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $raw_content = $body['choices'][0]['message']['content'] ?? '';

    // Clean JSON extraction
    if (preg_match('/\{.*\}/s', $raw_content, $matches)) {
        return $matches[0];
    }
    return $raw_content;
}
*/


function xbiz_api_call_perplexity($info) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
 
 
    $response = wp_remote_post('https://api.perplexity.ai/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'sonar',
            'messages' => [
                ['role' => 'system', 'content' => 'Return ONLY JSON for business profile. introduction, address, telephone, website.'],
                ['role' => 'user', 'content' => $info]
            ],
            'timeout' => 45
        ])
    ]);

    if (is_wp_error($response)) return json_encode(['error' => 'API Timeout']);
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $raw_content = $body['choices'][0]['message']['content'] ?? '';

    if (preg_match('/\{.*\}/s', $raw_content, $matches)) {
        return $matches[0];
    }
    return $raw_content;
}


//////////////////////////////////////////

// 1. Nearest Business Listing

add_shortcode('nearest_business', function () {

    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_business";

    // --- Default (user mode) ---
    $country   = sanitize_text_field($_COOKIE['country'] ?? 'Malaysia');
    $latitude  = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 3.1390;
    $longitude = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 101.6869;

    $mode = 'user';
    $business_name = '';
    $item_id = 0;

    // --- Detect business page ---
    if (is_singular('business')) {

        $post_id = get_the_ID();

        $item_id = get_post_meta($post_id, 'item_id', true);
        if (!$item_id) {
            $item_id = get_post_meta($post_id, '_item_id', true);
        }

        if ($item_id) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT latitude, longitude, name, country 
                     FROM $table 
                     WHERE item_id = %d 
                     LIMIT 1",
                    $item_id
                )
            );

            if ($row && $row->latitude && $row->longitude) {
                $latitude  = floatval($row->latitude);
                $longitude = floatval($row->longitude);
                $business_name = $row->name ?? '';
                $country = $row->country ?? $country;
                $mode = 'business';
            }
        }
    }

    ob_start();
    ?>

    <style>
        #business-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        @media (min-width: 768px) {
            #business-list {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .business-card {
            border-top: 2px solid #25988B;
            padding: 12px;
            background: #fff;
        }
        
        .business-name { font-weight: bold; color: #125C59; }
        .business-address { font-size: 14px; color: #444; }
        .business-distance { font-size: 13px; color: #777; }
        
        #load-more-btn {
            margin-top: 15px;
            padding: 10px;
            background: #25988B;
            color: #fff;
            border: 0;
            cursor: pointer;
        }
    </style>

    <div style="padding:10px;background:#f5f9f8;border-left:4px solid #25988B;">
        <?php if ($mode === 'business' && $business_name): ?>
            📍 Nearby businesses of <b><?php echo esc_html($business_name); ?></b>
        <?php else: ?>
            📍 Showing businesses in <b><?php echo esc_html($country); ?></b>
        <?php endif; ?>
    </div>

    <div id="business-list"></div>
    <button id="load-more-btn">Load More Businesses</button>

    <script>
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    var offset = 0;
    var limit = 10;

    function loadBusiness() {

        var formData = new FormData();
        formData.append('action','load_more_business');
        formData.append('offset', offset);
        formData.append('limit', limit);
        formData.append('lat', <?php echo $latitude; ?>);
        formData.append('lng', <?php echo $longitude; ?>);
        formData.append('mode', '<?php echo esc_js($mode); ?>');
        formData.append('business_name', '<?php echo esc_js($business_name); ?>');
        formData.append('item_id', '<?php echo intval($item_id); ?>');
        formData.append('country', '<?php echo esc_js($country); ?>');

        fetch(ajaxurl, {
            method:'POST',
            body:formData
        })
        .then(res => res.text())
        .then(html => {
            if(html.trim() === '') {
                document.getElementById('load-more-btn').style.display = 'none';
                return;
            }

            document.getElementById('business-list').insertAdjacentHTML('beforeend', html);
            offset += limit;
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        loadBusiness();
        document.getElementById('load-more-btn').addEventListener('click', loadBusiness);
    });
    </script>

    <?php
    return ob_get_clean();
});

///////////////
add_action('wp_ajax_load_more_business', 'load_more_business');
add_action('wp_ajax_nopriv_load_more_business', 'load_more_business');

function load_more_business() {

    global $wpdb;
    $table = $wpdb->prefix . "jet_cct_business";

    $latitude  = floatval($_POST['lat'] ?? 3.1390);
    $longitude = floatval($_POST['lng'] ?? 101.6869);

    $mode = sanitize_text_field($_POST['mode'] ?? 'user');
    if ($mode !== 'business') $mode = 'user';

    $business_name = sanitize_text_field($_POST['business_name'] ?? '');
    $item_id = intval($_POST['item_id'] ?? 0);
    $country = sanitize_text_field($_POST['country'] ?? 'Malaysia');

    $offset = intval($_POST['offset'] ?? 0);
    $limit  = intval($_POST['limit'] ?? 10);

    // --- Dynamic bounding box (20km) ---
    $radius_km = 20;

    $lat_range = $radius_km / 111;
    $lng_range = $radius_km / (111 * cos(deg2rad($latitude)));

    $lat_min = $latitude - $lat_range;
    $lat_max = $latitude + $lat_range;
    $lng_min = $longitude - $lng_range;
    $lng_max = $longitude + $lng_range;

    // --- FAST query (no trig) ---
    if ($mode === 'business') {

        $query = $wpdb->prepare(
            "SELECT name, address, page_url, item_id, latitude, longitude
             FROM $table
             WHERE country = %s
             AND latitude BETWEEN %f AND %f
             AND longitude BETWEEN %f AND %f
             AND item_id != %d
             AND latitude IS NOT NULL
             AND longitude IS NOT NULL
             LIMIT 200",
            $country,
            $lat_min, $lat_max,
            $lng_min, $lng_max,
            $item_id
        );

    } else {

        $query = $wpdb->prepare(
            "SELECT name, address, page_url, latitude, longitude
             FROM $table
             WHERE country = %s
             AND latitude BETWEEN %f AND %f
             AND longitude BETWEEN %f AND %f
             AND latitude IS NOT NULL
             AND longitude IS NOT NULL
             LIMIT 200",
            $country,
            $lat_min, $lat_max,
            $lng_min, $lng_max
        );
    }

    $results = $wpdb->get_results($query);
    if (!$results) wp_die();

    // --- Haversine (PHP) ---
    function calc_distance($lat1, $lng1, $lat2, $lng2) {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earth * $c;
    }

    foreach ($results as &$row) {
        $row->distance = calc_distance($latitude, $longitude, $row->latitude, $row->longitude);
    }

    // --- Sort by distance ---
    usort($results, fn($a,$b) => $a->distance <=> $b->distance);

    // --- Pagination AFTER sort ---
    $results = array_slice($results, $offset, $limit);

    foreach ($results as $m) {

        $km = number_format($m->distance, 2);
        $miles = number_format($m->distance * 0.621371, 2);

        if ($mode === 'business' && $business_name) {
            $label = "📍 {$km} km ({$miles} mi) from " . esc_html($business_name);
        } else {
            $label = "📍 {$km} km ({$miles} mi) away from you";
        }

        echo "<div class='business-card'>
            <div class='business-distance'>{$label}</div>
            <div class='business-name'>" . esc_html($m->name) . "</div>
            <div class='business-address'>" . esc_html($m->address) . "</div>
            <a href='{$m->page_url}'>Visit</a>
        </div>";
    }

    wp_die();
}


////////
add_shortcode('nearest_businessx', 'shortcode_nearest_businessx');

function shortcode_nearest_businessx() {
    global $wpdb;

    // --- 1. Get Coordinates from Cookies ---
    $country   = sanitize_text_field($_COOKIE['country']   ?? '');
    $latitude  = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 0;
    $longitude = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 0;

    // --- 2. Client-Side Geolocation (Optimized & Error Handled) ---
    $output = "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusDiv = document.getElementById('m4a-location-status');

        if (!navigator.geolocation) {
            if (statusDiv) statusDiv.innerHTML = '<p style=\"color:red;\">Your browser does not support geolocation features.</p>';
            return;
        }

        function getCookie(name) {
            const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
            return v ? parseFloat(v[2]) : null;
        }

        navigator.geolocation.getCurrentPosition(
            // SUCCESS CALLBACK
            function(position) {
                let lat = position.coords.latitude;
                let lng = position.coords.longitude;
                let savedLat = getCookie('latitude');
                let savedLng = getCookie('longitude');

                // Threshold: 0.005 degrees (~500m). Prevents refresh on micro-movements.
                let diffLat = Math.abs(lat - savedLat);
                let diffLng = Math.abs(lng - savedLng);
                let needsUpdate = (savedLat === null || diffLat > 0.005 || diffLng > 0.005);

                if (needsUpdate) {
                    let lastRun = localStorage.getItem('business_loc_last_ts');
                    let now = new Date().getTime();

                    // Only allow reload if 30 seconds have passed since last reload
                    if (!lastRun || (now - lastRun) > 30000) {
                        document.cookie = 'latitude=' + lat + '; path=/; max-age=86400; SameSite=Lax';
                        document.cookie = 'longitude=' + lng + '; path=/; max-age=86400; SameSite=Lax';
                        localStorage.setItem('business_loc_last_ts', now);
                        
                        if (statusDiv) statusDiv.innerHTML = '<p style=\"color:green;\">Location found! Refreshing page...</p>';
                        window.location.reload();
                    }
                }
            }, 
            // ERROR CALLBACK
            function(error) {
                if (!statusDiv) return;
                let errorMsg = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg = '<b>Location access denied.</b> Please allow GPS/Location access in your browser settings to view nearby businesses.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg = '<b>Location unavailable.</b> We could not retrieve your location. Please check your network or GPS connection.';
                        break;
                    case error.TIMEOUT:
                        errorMsg = '<b>Request timed out.</b> It took too long to detect your location. Please refresh the page and try again.';
                        break;
                    default:
                        errorMsg = '<b>Unknown error.</b> Failed to detect your location.';
                        break;
                }
                // Overwrite the 'Detecting location' message
                statusDiv.innerHTML = '<div style=\"padding:15px; border:1px solid red; background:#ffeeee; color:red; border-radius:8px;\">' + errorMsg + '</div>';
            }, 
            // OPTIONS
            {
                enableHighAccuracy: false, 
                timeout: 10000,
                maximumAge: 300000 // 5-minute cache to stop jittering
            }
        );
    });
    </script>";

    // Wrap loading message with ID for JS to manipulate
    if (!$latitude || !$longitude) {
        return $output . '<div id="m4a-location-status" style="padding:20px; text-align:center; font-family:Arial, sans-serif;">
            <p><i class="fa-solid fa-spinner fa-spin"></i> Detecting location...</p>
        </div>'; 
    }

    $is_indexed = business_location_exists($latitude, $longitude);

    $output .= "
    <style>
        .mosque-header { font-size: 24px; font-weight: bold; margin-bottom: 12px; font-family: Arial, sans-serif; }
        .location-small { font-size: 17px; color: #555; margin-bottom: 18px; font-family: Arial, sans-serif; }
        .mosque-card { background: #fff; padding: 15px 18px; margin-bottom: 15px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.10); font-family: Arial, sans-serif; }
        .mosque-name { font-size: 16px; font-weight: bold; color: #256C98; margin-bottom: 4px; }
        .mosque-address { font-size: 14px; color: #444; margin-bottom: 6px; }
        .mosque-bottom-row { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .mosque-distance { font-size: 14px; color: #777; }
        .mosque-view-btn { display: inline-block; background: #256C98; color: #fff !important; padding: 4px 14px; font-size: 13px; font-weight: bold; border-radius: 6px; text-decoration: none; }
        .update-btn { background:#D4591E; color:#fff; padding:10px 16px; border:0; border-radius:8px; font-weight:bold; cursor:pointer; margin-bottom:15px; }
    </style>";

    $output .= "
        <div class='location-small'>📍 <b>$country</b><small> (" . number_format($latitude, 4) . ", " . number_format($longitude, 4) . ")</small> " . do_shortcode('[refresh_page_btn]') . "</div>";

    if (!$is_indexed) {
        $output .= "
        <div class='update-prompt-box' style='padding: 20px; border: 1px dashed #ccc; text-align: center; background: #fafafa;'>
            <p><b style='color:green'>Your current location has not been indexed yet.</b><br>
            The system will automatically index this area and add nearby businesses to our directory.</p>
            <div id='serper-update-msg' style='color:#555;margin-bottom:15px'>Please wait...<br>Compiling nearby businesses.<br><i class='fa-solid fa-spinner fa-spin'></i></div>
        </div>
        <script>
        (function(){
            const msg = document.getElementById('serper-update-msg');
            if(!msg) return;
    
            // Auto-trigger indexing immediately
            const data = new FormData();
            data.append('action','serper_location_business');
            data.append('country','{$country}');
            data.append('latitude','{$latitude}');
            data.append('longitude','{$longitude}');
    
            fetch('".admin_url('admin-ajax.php')."', { method:'POST', body:data })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    msg.innerHTML = '✅ Success! Refreshing...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = '❌ ' + (res.data.message || 'Error occurred');
                }
            })
            .catch(err => {
                msg.innerHTML = '❌ Error occurred. Please try again.';
                console.error(err);
            });
        })();
        </script>";

    } else {
        // --- DISPLAY LOGIC (Query results from DB) ---
        $business_table = $wpdb->prefix . "jet_cct_business";
        $lat_min = $latitude - 0.2; $lat_max = $latitude + 0.2;
        $lng_min = $longitude - 0.2; $lng_max = $longitude + 0.2;

        $statuses = ['Listed', 'Updated'];
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $query = $wpdb->prepare(
            "SELECT * FROM $business_table 
             WHERE latitude BETWEEN %f AND %f
               AND longitude BETWEEN %f AND %f
               AND business_status != ''
               AND business_status IN ($placeholders)",
            array_merge([$lat_min, $lat_max, $lng_min, $lng_max], $statuses)
        );
        
        $results = $wpdb->get_results($query);        

        if (!$results) {
            $output .= "<p>No businesses found near you.</p>";
        } else {
            $data = [];
            foreach ($results as $item) {
                // Distance Calc
                $theta = $longitude - floatval($item->longitude);
                $dist = sin(deg2rad($latitude)) * sin(deg2rad($item->latitude)) + cos(deg2rad($latitude)) * cos(deg2rad($item->latitude)) * cos(deg2rad($theta));
                $km = rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
                
                $data[] = [
                    'name' => $item->name,
                    'address' => $item->address,
                    'status' => $item->business_status,
                    'url' => $item->page_url,
                    'dist' => $km
                ];
            }
            usort($data, fn($a, $b) => $a['dist'] <=> $b['dist']);
            foreach (array_slice($data, 0, 20) as $m) {
                
                if ($m['status']=='Updated'){
                   $emoji = '✔️' ;
                }else{
                   $emoji = '🔘️' ; 
                }
                
                $output .= "
                <div class='mosque-card'>
                    <div class='mosque-name'>".$emoji.' '.strtoupper($m['name'])."</div>
                    <div class='mosque-address'>{$m['address']}</div>
                    <div class='mosque-bottom-row'>
                        <div class='mosque-distance'>".number_format($m['dist'], 2)." km away</div>
                        <a class='mosque-view-btn' href='{$m['url']}'>View</a>
                    </div>
                </div>";
            }
            $output .= '<span>🔘 Not updated&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✔️ Updated</span>';
        }
    }
    return $output;
}

// 2. Business Location Exist
function business_location_exists($latitude, $longitude) {
    global $wpdb;
    
    //if (!$latitude || !$longitude) return false;
    if (!$latitude || !$longitude ) {
        return false;
    }
    
    $lat_key = number_format(floor($latitude * 100) / 100, 2, '.', '');
    $lng_key = number_format(floor($longitude * 100) / 100, 2, '.', '');

    $location_key = $lat_key . '|' . $lng_key;

    $table = $wpdb->prefix . 'jet_cct_location';
    
    return (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT 1 
             FROM {$table} 
             WHERE location = %s
               AND business IS NOT NULL
               AND business != ''
             LIMIT 1",
            $location_key
        )
    );
}

// 3. Serper Location Indexing
add_action('wp_ajax_serper_location_business', 'serper_location_business_ajax');
add_action('wp_ajax_nopriv_serper_location_business', 'serper_location_business_ajax');

function serper_location_business_ajax() {
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
    if (function_exists('serper_nearest_business')) {
        $num = serper_nearest_business($latitude, $longitude);
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
                'business' => $num,
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
                'business'     => $num,
                'cct_created'  => $created
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
    
    //delete_transient($lock_key);
    wp_send_json_success(['message' => 'Finished']);
}
 

// 4. Serper Nearest Business


// 5. Business Sidebar
add_shortcode('nearest_business_sidebar', 'shortcode_nearest_business_sidebar');

function shortcode_nearest_business_sidebar() {
    global $wpdb;

    /* ---------------------------------------
     * 1. Current Business (Post Context)
     * --------------------------------------- */
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);

    if (!$item_id) {
        return '';
    }
  
    // Business coordinates (CENTER for nearby search)
    $business_lat = floatval(get_cct_business_data($item_id, 'latitude'));
    $business_lng = floatval(get_cct_business_data($item_id, 'longitude'));

    if (!$business_lat || !$business_lng) {
        return '';
    }

    /* ---------------------------------------
     * 2. User Location (for distance display)
     * --------------------------------------- */
    $user_lat = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 0;
    $user_lng = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 0;

    /* ---------------------------------------
     * 3. UI
     * --------------------------------------- */
    $output = "
    <style>
        .mosque-header { font-size: 22px; font-weight: bold; margin-bottom: 12px; }
        .mosque-card { background:#fff; padding:14px; margin-bottom:14px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
        .mosque-name { font-size:15px; font-weight:bold; color:#256C98; }
        .mosque-address { font-size:13px; color:#555; margin:6px 0; }
        .mosque-bottom-row { display:flex; justify-content:space-between; align-items:center; }
        .mosque-distance { font-size:13px; color:#777; }
        .business-view-btn { background:#256C98; color:#fff !important; padding:4px 12px; border-radius:6px; font-size:12px; text-decoration:none; }
    </style>";

    $output .= "<div class='mosque-header'>Nearby Businesses</div>";

    /* ---------------------------------------
     * 4. Query Nearby Businesses
     * --------------------------------------- */
    $business_table = $wpdb->prefix . 'jet_cct_business';

    $lat_min = $business_lat - 0.2;
    $lat_max = $business_lat + 0.2;
    $lng_min = $business_lng - 0.2;
    $lng_max = $business_lng + 0.2;

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM $business_table
             WHERE latitude BETWEEN %f AND %f
               AND longitude BETWEEN %f AND %f
               AND business_status IN ('Listed','Updated')
               AND _ID != %d
               AND page_url IS NOT NULL",
            $lat_min, $lat_max,
            $lng_min, $lng_max,
            $item_id
        )
    );
    
    if (!$results) {
        return $output . "<p>No nearby business found.</p>";
    }

    /* ---------------------------------------
     * 5. Distance Calculation
     * --------------------------------------- */
    $business = [];

    foreach ($results as $item) {

        // Distance FROM USER (if available)
        if ($user_lat && $user_lng) {
            $theta = $user_lng - $item->longitude;
            $dist = sin(deg2rad($user_lat)) * sin(deg2rad($item->latitude))
                  + cos(deg2rad($user_lat)) * cos(deg2rad($item->latitude))
                  * cos(deg2rad($theta));
            $km = rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
        } else {
            $km = null;
        }

        $business[] = [
            'name'    => $item->name,
            'address' => $item->address,
            'status'  => $item->business_status,
            'url'     => $item->page_url,
            'dist'    => $km
        ];
    }

    // Sort by distance (if available)
    usort($business, fn($a, $b) => ($a['dist'] ?? 999) <=> ($b['dist'] ?? 999));

    /* ---------------------------------------
     * 6. Output
     * --------------------------------------- */
    foreach (array_slice($business, 0, 20) as $m) {

        $distance_text = $m['dist']
            ? number_format($m['dist'], 2) . " km away from you"
            : "Nearby";
        
        if ($m['status']=='Updated'){
           $emoji = '✔️' ;
        }else{
           $emoji = '🔘️ ' ; 
        }
        
        $output .= "
        <div class='mosque-card'>
            <div class='mosque-name'>$emoji {$m['name']} </div>
            <div class='mosque-address'>{$m['address']}</div>
            <div class='mosque-bottom-row'>
                <div class='mosque-distance'>{$distance_text}</div>
                <a class='mosque-view-btn' href='{$m['url']}'>View</a>
            </div>
        </div>";
    }
    
    $output .= '<span>🔘 Not updated&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✔️ Updated</span>';
    //$output .= '🔘 Not updated&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✔️ Updated';


    return $output;
}

// 6 - Google Map and Waze Btn
add_shortcode('business_location_btn', function () {
    $post_id   = get_the_ID();
    $item_id   = get_post_meta($post_id, 'item_id', true);
    
    // Using your business data function since we are adapting for business/mosque
    $place_id  = get_cct_business_data($item_id, 'place_id');
    $latitude  = get_cct_business_data($item_id, 'latitude');
    $longitude = get_cct_business_data($item_id, 'longitude');

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


// GET FIELD FROM CCT MOSQUE
function get_cct_business_data($item_id, $field) {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT $field FROM wp_jet_cct_business WHERE _ID = %d",
        $item_id
    );
    $result = $wpdb->get_var($query);
    //return $result ? $result : "";
    return ($result !== null) ? $result : null;
}

function save_cct_business_data($item_id, $field, $value) {
    global $wpdb;
    
    // Validate input
    if (!is_numeric($item_id) || empty($field)) {
        return false;
    }
    
    $table_name = $wpdb->prefix . 'jet_cct_business';
    
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


// Business Sidebar
add_shortcode('xnearest_business_sidebar', 'xshortcode_nearest_business_sidebar');

function xshortcode_nearest_business_sidebar() {
    global $wpdb;

    /* ---------------------------------------
     * 1. Current Mosque (Post Context)
     * --------------------------------------- */
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);

    if (!$item_id) {
        return '';
    }

    // Business coordinates (CENTER for nearby search)
    $business_lat = floatval(get_cct_business_data($item_id, 'latitude'));
    $business_lng = floatval(get_cct_business_data($item_id, 'longitude'));

    if (!$business_lat || !$business_lng) {
        return '';
    }

    /* ---------------------------------------
     * 2. User Location (for distance display)
     * --------------------------------------- */
    $user_lat = isset($_COOKIE['latitude']) ? floatval($_COOKIE['latitude']) : 0;
    $user_lng = isset($_COOKIE['longitude']) ? floatval($_COOKIE['longitude']) : 0;

    /* ---------------------------------------
     * 3. UI
     * --------------------------------------- */
    $output = "
    <style>
        .mosque-header { font-size: 22px; font-weight: bold; margin-bottom: 12px; }
        .mosque-card { background:#fff; padding:14px; margin-bottom:14px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
        .mosque-name { font-size:15px; font-weight:bold; color:#125C59; }
        .mosque-address { font-size:13px; color:#555; margin:6px 0; }
        .mosque-bottom-row { display:flex; justify-content:space-between; align-items:center; }
        .mosque-distance { font-size:13px; color:#777; }
        .mosque-view-btn { background:#25988B; color:#fff !important; padding:4px 12px; border-radius:6px; font-size:12px; text-decoration:none; }
    </style>";

    $output .= "<div class='mosque-header'>🕌 Nearby Businesses</div>";

    /* ---------------------------------------
     * 4. Query Nearby Business
     * --------------------------------------- */
    $business_table = $wpdb->prefix . 'jet_cct_business';

    $lat_min = $business_lat - 0.2;
    $lat_max = $business_lat + 0.2;
    $lng_min = $business_lng - 0.2;
    $lng_max = $business_lng + 0.2;

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM $business_table
             WHERE latitude BETWEEN %f AND %f
               AND longitude BETWEEN %f AND %f
               AND _ID != %d
               AND page_url IS NOT NULL",
            $lat_min, $lat_max,
            $lng_min, $lng_max,
            $item_id
        )
    );

    if (!$results) {
        return $output . "<p>No nearby businesses found.</p>";
    }

    /* ---------------------------------------
     * 5. Distance Calculation
     * --------------------------------------- */
    $business = [];

    foreach ($results as $item) {

        // Distance FROM USER (if available)
        if ($user_lat && $user_lng) {
            $theta = $user_lng - $item->longitude;
            $dist = sin(deg2rad($user_lat)) * sin(deg2rad($item->latitude))
                  + cos(deg2rad($user_lat)) * cos(deg2rad($item->latitude))
                  * cos(deg2rad($theta));
            $km = rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
        } else {
            $km = null;
        }

        $businesses[] = [
            'name'    => $item->name,
            'address' => $item->address,
            'url'     => $item->page_url,
            'dist'    => $km
        ];
    }

    // Sort by distance (if available)
    usort($businesses, fn($a, $b) => ($a['dist'] ?? 999) <=> ($b['dist'] ?? 999));

    /* ---------------------------------------
     * 6. Output
     * --------------------------------------- */
    foreach (array_slice($businesses, 0, 20) as $m) {
 
        $distance_text = $m['dist']
            ? number_format($m['dist'], 2) . " km away from you"
            : "Nearby";

        $output .= "
        <div class='mosque-card'>
            <div class='mosque-name'>🕌 {$m['name']}</div>
            <div class='mosque-address'>{$m['address']}</div>
            <div class='mosque-bottom-row'>
                <div class='mosque-distance'>{$distance_text}</div>
                <a class='mosque-view-btn' href='{$m['url']}'>View</a>
            </div>
        </div>";
    }

    return $output;
}





// ============================================
// SHORTCODE: Display Nearest Business (Mobile App UI)
// ============================================
add_shortcode('nearest_businessx', 'nearest_businessx_shortcode');

function nearest_businessx_shortcode() {
    global $wpdb;

    // --- Get Lat/Lng from cookies ---
    $country   = sanitize_text_field($_COOKIE['country']   ?? '');
    $city      = sanitize_text_field($_COOKIE['city']      ?? '');
    $latitude  = floatval($_COOKIE['latitude']  ?? 0);
    $longitude = floatval($_COOKIE['longitude'] ?? 0);

    //$solat_times = do_shortcode('[solat_times]');

    $user_lat = $latitude;
    $user_lng = $longitude;

    // --- Table name ---
    $business_table = $wpdb->prefix . "jet_cct_business";

    // Query matching floor(lat/lng)
    $query = $wpdb->prepare(
        "SELECT * FROM $business_table 
         WHERE FLOOR(latitude) = %d 
           AND FLOOR(longitude) = %d
           AND cid IS NOT NULL",
        floor($user_lat),
        floor($user_lng)
    );

    $results = $wpdb->get_results($query);

    if (empty($results)) {
        return "<p>No business found near your location.</p>";
    }

    // --- Distance calculation ---
    $data = array();

    foreach ($results as $item) {

        $lat = floatval($item->latitude);
        $lng = floatval($item->longitude);

        if (!$lat || !$lng) continue;

        // Haversine
        $theta = ($user_lng - $lng);
        $distance = sin(deg2rad($user_lat)) * sin(deg2rad($lat)) +
                    cos(deg2rad($user_lat)) * cos(deg2rad($lat)) *
                    cos(deg2rad($theta));
        $distance = acos($distance);
        $distance = rad2deg($distance);
        $distance = $distance * 60 * 1.853159616;

        $data[] = [
            'name'     => esc_html($item->name),
            'address'  => esc_html($item->address),
            'page_url' => esc_url($item->page_url),
            'distance' => $distance
        ];
    }

    usort($data, fn($a, $b) => $a['distance'] <=> $b['distance']);
    $data = array_slice($data, 0, 20);
    $city = str_replace('+', ' ', $city);

    // ---------------------------------------
    // MOBILE APP UI CSS
    // ---------------------------------------
    $output = "
    <style>
        .mosque-header {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 12px;
            font-family: Arial, sans-serif;
        }
        .location-small {
            font-size: 17px;
            color: #555;
            margin-bottom: 18px;
            font-family: Arial, sans-serif;
        }
        .mosque-card {
            background: #fff;
            padding: 15px 18px;
            margin-bottom: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.10);
            font-family: Arial, sans-serif;
        }
        .mosque-name {
            font-size: 16px;
            font-weight: bold;
            color: #125C59;
            margin-bottom: 4px;
        }
        .solat-times {
            font-size: 14px;
            color: #125C59;
            margin-bottom: 4px;
        }
        .mosque-address {
            font-size: 14px;
            color: #444;
            margin-bottom: 6px;
        }

        /* --- NEW ROW FOR DISTANCE + BUTTON --- */
        .mosque-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .mosque-distance {
            font-size: 14px;
            color: #777;
        }
        .mosque-view-btn {
            display: inline-block;
            background: #256C98;
            color: #fff !important;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 6px;
            text-decoration: none;
        }
    </style>
    ";

    // ---------------------------------------
    // HEADER
    // ---------------------------------------
    $latitude  = $latitude ? number_format((float)$latitude, 5) : '0.00000';
    $longitude = $longitude ? number_format((float)$longitude, 5) : '0.00000';

    $output .= "
        <div class='mosque-header'>🕌 Nearest Business</div>
        <div class='location-small'>📍 <b>$country</b> <small>($latitude, $longitude)</small></div>
    ";

    // ---------------------------------------
    // LIST RESULTS
    // ---------------------------------------
    foreach ($data as $item) {

        $output .= "
        <div class='mosque-card'>
            <div class='mosque-name'>🕌 " . strtoupper($item['name']) . "</div>
            <div class='mosque-address'>" . $item['address'] . "</div>

            <div class='mosque-bottom-row'>
                <div class='mosque-distance'>" . number_format($item['distance'], 2) . " km away</div>
                <a class='mosque-view-btn' href='" . $item['page_url'] . "' target='_self'>View</a>
            </div>
        </div>";
    }

    return $output;
}



// GET FIELD VALUE FROM BUSINESS CCT
function business_cct_get_field($item_id, $field) {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT $field FROM wp_jet_cct_business WHERE _ID = %d",
        $item_id
    );
    $result = $wpdb->get_var($query);
    //return $result ? $result : "";
    return ($result !== null) ? $result : null;
}

// UPDATE CCT BUSINESS FIELD
function business_cct_update_field($item_id, $field, $value) {
    global $wpdb;

    $result = $wpdb->update(
        'wp_jet_cct_business',                // Table name
        [$field => $value],                   // Data to update
        ['_ID' => $item_id],                  // WHERE condition
        null,                                 // Data format (optional)
        ['%d']                                // WHERE format
    );

    return ($result !== false); // returns true if update succeeded, false on error
}

// ADD NEW BUSINESS
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    global $wpdb;
    if ((int) $form->id !== 18) {
        return;
    }

    // Sanitize phone and name inputs
    $user_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'user_id'));
    $name = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'name'));

    $business = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'business'));
    $address = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'address'));
    $country = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'country'));
    $gmap = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'gmap'));
    $phone = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone'));
    $website = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'website'));
    $facebook = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'facebook'));

    // INSERT BUSINESS POST
    $post_id = wp_insert_post([
        'post_title'  => $business,
        'post_type'   => 'business',
        'post_status' => 'publish',
    ]);
    // get page url
    $post_url = get_permalink( $post_id );
    
     // CREATE BUSINESS CCT
    $data = [
        'name' => $business,
        'address' => $address,
        'country' => $country,
        'maps_url' => $gmap,
        'phone' => $phone,
        'website' => $website,
        'fb' => $facebook,
        'owner_id' => $user_id,
        'page_url' => $page_url,
        'post_id' => $post_id,
        'cct_single_post_id' => $post_id
    ];
    
    // Insert into the CCT table
    $result = $wpdb->insert(
        'wp_jet_cct_business', 
        $data
    );
    $item_id = $wpdb->insert_id;
    
    update_post_meta($post_id, 'item_id', $item_id);
    update_post_meta($post_id, 'owner_id', $user_id);
    update_post_meta($post_id, 'business_status', 'Claimed');

}, 10, 3);


// UPDATE CCT BUSINESS
function update_cct_business($post_id, $user_data) {
    global $wpdb;

    // Ensure user_id and data are provided
    //if (empty($post_id) || empty($user_data)) {
    //    return "Error: item_id and user_data are required.";
    //}
 
    // Perform the update
    $result = $wpdb->update(
        'wp_jet_cct_business', // Table name
        $user_data,           // Data to update
        array('post_id' => $post_id), // Where condition
        array_fill(0, count($user_data), '%s'), // Data format
        array('%s') // post_id format
    ); 

    // Debugging output
    if ($result === false) {
        return "Error updating record: " . $wpdb->last_error;
    } elseif ($result === 0) {
        return "No changes made or record not found.";
    } else {
        return "Record updated successfully.";
    }
}



//FLUENTFORM SHORTCODE (CCT BUSINESS)
//NAME
add_filter('fluentform/editor_shortcode_callback_bname', function ($value, $form) {
    
    $item_id = 0; //$_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'name');
    
    return $dynamicValue;
    
}, 10, 2);

//INTRO
add_filter('fluentform/editor_shortcode_callback_bintro', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'introduction');
    
    return $dynamicValue;
    
}, 10, 2);

// BUSINESS EXCERPT
add_filter('fluentform/editor_shortcode_callback_bexcerpt', function ($value, $form) {
    $item_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
    $post_id = get_cct_business_data($item_id, 'post_id');
    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'business') {
            $excerpt = $post->post_excerpt;
        }
    }
    
    return $excerpt;
    
}, 10, 2);

//URL
add_filter('fluentform/editor_shortcode_callback_burl', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'page_url');
    
    return $dynamicValue;
    
}, 10, 2);

//TYPE
add_filter('fluentform/editor_shortcode_callback_btype', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'type');
    
    return $dynamicValue;
    
}, 10, 2);

//TAGS
add_filter('fluentform/editor_shortcode_callback_btags', function ($value, $form) {
    $item_id = $_GET['pid'];
    $post_id = business_cct_get_field($item_id, 'post_id');

    $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
    return implode(', ', $tags);
}, 10, 2);

//ADDRESS
add_filter('fluentform/editor_shortcode_callback_badd', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'address');
    
    return $dynamicValue;
    
}, 10, 2);

//OWNER ID
add_filter('fluentform/editor_shortcode_callback_bowner', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'owner_id');
      
    return $dynamicValue;
    
}, 10, 2);

//STATUS
add_filter('fluentform/editor_shortcode_callback_bizstatus', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'business_status');
      
    return $dynamicValue;
    
}, 10, 2);

// WHATSAPP
add_filter('fluentform/editor_shortcode_callback_bwhatsapp', function ($value, $form) {
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'whatsapp');
    
    return $dynamicValue;
}, 10, 2);

// CONTENT
add_filter('fluentform/editor_shortcode_callback_bcontent', function ($value, $form) {
    $item_id = $_GET['pid'];
    $post_id = get_cct_business_data($item_id, 'post_id');
    $post = get_post($post_id);

    // Normalize content
    $content = $post->post_content;
    $content = wpautop($content); // Optional: ensures paragraphs
    $content = str_replace(array("\r\n", "\r", "\n"), '', $content); // Remove line breaks

    return $content;
}, 10, 2);

/*
add_filter('fluentform/editor_shortcode_callback_bcontent', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $post_id = get_cct_business_data($item_id, 'post_id');
    $post = get_post($post_id);

    if ($post) {
        $content = $post->post_content; // Process shortcodes & formatting
        //$content = apply_filters('the_content', $post->post_content); // Process shortcodes & formatting
    }
    
    $dynamicValue = $content; 

    return $dynamicValue;
    
}, 10, 2);
*/

//CITY
add_filter('fluentform/editor_shortcode_callback_bcity', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'city');
    
    return $dynamicValue;
    
}, 10, 2);

//COUNTRY
add_filter('fluentform/editor_shortcode_callback_bcountry', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'country');
    
    return $dynamicValue;
    
}, 10, 2);

//EMAIL
add_filter('fluentform/editor_shortcode_callback_bemail', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'email');
    
    return $dynamicValue;
    
}, 10, 2);

//PHONE
add_filter('fluentform/editor_shortcode_callback_bphone', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'phone');
    
    return $dynamicValue;
    
}, 10, 2);

//WHATSAPP
add_filter('fluentform/editor_shortcode_callback_bws', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'whatsapp');
    
    return $dynamicValue;
    
}, 10, 2);

//WEBSITE
add_filter('fluentform/editor_shortcode_callback_bweb', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'website');
    
    return $dynamicValue;
    
}, 10, 2);

//FACEBOOK
add_filter('fluentform/editor_shortcode_callback_bfb', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'facebook');
    
    return $dynamicValue;
    
}, 10, 2);

//FACEBOOK
add_filter('fluentform/editor_shortcode_callback_bfb', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'fb');
    
    return $dynamicValue;
    //return !empty($dynamicValue) ? $dynamicValue : null; // This triggers fallback to placeholder

    //return $dynamicValue !== '' ? $dynamicValue : false; // or use false
}, 10, 2);

//TIKTOK
add_filter('fluentform/editor_shortcode_callback_btiktok', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'tiktok');
    
    return $dynamicValue;
    
}, 10, 2);

//INSTAGRAM
add_filter('fluentform/editor_shortcode_callback_binsta', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'insta');
    
    return $dynamicValue;
    
}, 10, 2);

//LINKEDIN
add_filter('fluentform/editor_shortcode_callback_blinkedin', function ($value, $form) {
    
    $item_id = $_GET['pid'];
    $dynamicValue = get_cct_business_data($item_id, 'linkedin');
    
    return $dynamicValue;
    
}, 10, 2);

//FLUENTFORM UPDATE BUSINESS CCT (FORM ID : 12)
add_action('fluentform/submission_inserted', 'update_business_info', 20, 3);
function update_business_info($entryId, $formData, $form) {
    global $wpdb;

    $targetFormId = 12;
    if ($form->id != $targetFormId) {
        return;
    }
    
    $item_id = $formData['itemID'];
    $bname = wp_kses_post($formData['bname']) ?? '';
    $bintro = $formData['bintro'] ?? '';
    $burl = $formData['burl'] ?? '';
    $badd = $formData['badd'] ?? '';
    $bstatus = $formData['bizstatus'] ?? '';
    $bowner = $formData['bowner'] ?? '';
    $bcountry = $formData['bcountry'] ?? '';
    $bphone = $formData['bphone'] ?? '';
    $bws = $formData['bws'] ?? '';
    $bemail = $formData['bemail'] ?? '';
    $bweb = $formData['bweb'] ?? '';
    
    $wpdb->update(
        'wp_jet_cct_business',
        [
            'name' => $bname,
            'address' => $badd,
            'country' => $bcountry,
            'phone' => $bphone,
            'whatsapp' => $bws,
            'business_status' => $bstatus,
            'owner_id' => $bowner,
            'email' => $bemail,
            'website' => $bweb,
        ],
        ['_ID' => $item_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s','%s','%s'],
        ['%d']
    );
    
    // UPDATE POST
    $post_id = get_cct_business_data($item_id, 'post_id');
    $updated_post = array(
        'ID'         => $post_id,
        'post_title' => $bname, // New title
    );
    $result = wp_update_post($updated_post);
}

// DISPLAY BUSINESS POST INFO 
add_shortcode('xbusiness_display_info', 'xbusiness_display_info_shortcode');
  
function xbusiness_display_info_shortcode() {
    if (is_admin() && !wp_doing_ajax()) {
        return '';
    }
    global $wpdb, $post;
    $post_id = get_the_ID();
    $post = get_post($post_id);
        
    $name = get_the_title($post_id);
    //$name = get_cct_business_data($item_id, 'name');
    $content = apply_filters('the_content', $post->post_content);
    $updated = get_post_meta($post_id, 'updated', true);
    $item_id = get_post_meta($post_id, 'item_id', true);
    $place_id = get_post_meta($post_id, 'place_id', true);

    $cid = get_cct_business_data($item_id, 'cid');
    $lat = get_cct_business_data($item_id, 'latitude');
    $lon = get_cct_business_data($item_id, 'longitude');
    $address = get_cct_business_data($item_id, 'address');
    $country = get_cct_business_data($item_id, 'country');

    $website = get_cct_business_data($item_id, 'website');
    $phone = get_cct_business_data($item_id, 'phone');
    $type = get_cct_business_data($item_id, 'type');
     
    // Search Country Cookie
    setcookie('search_country', $country, time() + (86400 * 365), '/');
    $_COOKIE['search_country'] = $country;
 
    $display = false;
    if (!empty($content) && $updated === 'Updated') {
        $display = true;
    }
    
    if (!$display){
        $ret .= '<b>Content will be updated soon</b><br>';
        //$ret.= '<i>To view the updated AI-generated content, please refresh the browser after 30-60 seconds </i><br><br>';

        $info .= $name . ' ' . $address;
        $url = admin_url('admin-ajax.php') . "?action=get_business_info&itemid=$item_id&postid=$post_id&country=$country&info=" . urlencode($info);

    }else{
        $ret.= $content;
        if (trim(strip_tags($content)) == '') {
            $ret.= $address;
        }
    }
    
    if (!empty($cid)){
        $gmap_url = 'https://maps.google.com/?cid=' . $cid;
        $gmap =  '<a href="' . $gmap_url . '" target="_blank" style="display:inline-block;padding:8px 12px;background:#0073aa;color:#fff;text-decoration:none;border-radius:5px;">📍 Google Map</a>';
    }
    if (!empty($lat) AND !empty($lon)){
        $waze_url = 'https://waze.com/ul?ll=' . $lat . ',' . $lon . '&navigate=yes'; // Replace with your coordinates
        $waze = '<a href="' . $waze_url . '" target="_blank" style="display:inline-block;padding:8px 12px;background:#33CC99;color:#fff;text-decoration:none;border-radius:5px;">🧭 Waze</a>';
    }
    //$ret.= '<br><table><tr><td>' . $gmap . '</td><td>' . $waze . '</td></tr></table>';
    $ret .= '<br><br>
    <div class="map-buttons">
        ' . (isset($gmap) ? $gmap : '') . '
        ' . (isset($waze) ? $waze : '') . '
    </div>';


    $ret .= "
    <script>
    (function() {
        function setDefaultCountry() {
            const match = document.cookie.match(/search_country=([^;]+)/);
            if (!match) return;
    
            const country = decodeURIComponent(match[1]);
    
            document.querySelectorAll('.jet-select__control').forEach(select => {
                if (select && [...select.options].some(o => o.value === country)) {
                    if (select.value !== country) {
                        select.value = country;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
    
        // Run when JetEngine filters initialized
        if (window.JetSmartFilters) {
            setDefaultCountry();
        }
    
        document.addEventListener('jet-smart-filters/inited', setDefaultCountry);
    })();
    </script>
    ";
    
    return $ret;
    
}

// DISPLAY BUSINESS POST OLD
add_shortcode('business_display_info_old', 'business_display_info_old_shortcode');

function business_display_info_old_shortcode() {
    if (is_admin() && !wp_doing_ajax()) {
        return '';
    }
    global $wpdb, $post;
    $post_id = get_the_ID();
    $post = get_post($post_id);
    $content = apply_filters('the_content', $post->post_content);
    $updated = get_post_meta($post_id, 'updated', true);
    $item_id = get_post_meta($post_id, 'item_id', true);
    $place_id = get_post_meta($post_id, 'place_id', true);
 
    if ($item_id==''){
        global $wpdb;
        $table_name = $wpdb->prefix . 'jet_cct_business'; // Ensure table prefix is correct
        // Check if record exists and get the ID
        $item_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT _ID FROM {$table_name} WHERE place_id = %s LIMIT 1", // Add LIMIT 1 to ensure single result
                $place_id
            )
        );
        update_post_meta($post_id, 'item_id', $item_id);
       
        //cct_single_post_id
       
        // Check if item_id exists
        if ($item_id) {
            //$ret .= 'ITEMID ' . $item_id;
        } else {
            //return 'No item found for the given place_id.';
        }
    }
    
    $name = get_cct_business_data($item_id, 'name');
    $cid = get_cct_business_data($item_id, 'cid');
    $lat = get_cct_business_data($item_id, 'latitude');
    $lon = get_cct_business_data($item_id, 'longitude');
    $address = get_cct_business_data($item_id, 'address');
    $website = get_cct_business_data($item_id, 'website');
    $phone = get_cct_business_data($item_id, 'phone');
    $type = get_cct_business_data($item_id, 'type');
    
    $gmap_url = 'https://maps.google.com/?cid=' . $cid;
    $waze_url = 'https://waze.com/ul?ll=' . $lat . ',' . $lat . '&navigate=yes'; // Replace with your coordinates

    if (!empty($cid)){
        $gmap =  '<a href="' . $gmap_url . '" target="_blank" style="display:inline-block;padding:8px 12px;background:#0073aa;color:#fff;text-decoration:none;border-radius:5px;">📍 Google Map</a>';
    }
    $waze = '<a href="' . $waze_url . '" target="_blank" style="display:inline-block;padding:8px 12px;background:#33CC99;color:#fff;text-decoration:none;border-radius:5px;">🧭 Waze</a>';

    $ret .= $gmap . ' ' . $waze . '<br><br>' ;

    if ($updated <> 'Updated') { 
        //$ret.= '<br><b>📍 Location : </b>' . $address . '<br><br>';
        $ret.= '📍 ' . $address . '<br><br>';
        $ret.= '<b>Our AI Agent is updating the content...</b><br>';
        //$ret.= '<i>To view the updated AI-generated content, please refresh the browser after 30-60 seconds </i><br><br>';
        $ret.= 'Are you the business owner? <br>Claim your listing today and take control.';
        $info .= $name . ' ' . $address;
        $url = admin_url('admin-ajax.php') . "?action=get_business_info&itemid=$item_id&postid=$post_id&country=$country&info=" . urlencode($info);
    
        // Run AJAX call in the background
        wp_remote_get($url, [
            'timeout' => 0.01,
            'blocking' => false,
            'sslverify' => false // optional: for local dev environments
        ]);
 
    }else{
        if (trim(strip_tags($content)) == '') {
            $ret.= $address;
        }
    }
    
    return $ret;
    
}

 
// Hook for AJAX background crawler
add_action('xwp_ajax_nopriv_get_business_info', 'xget_business_info');
add_action('xwp_ajax_get_business_info', 'xget_business_info');
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

function xget_business_info() {
    if (!isset($_GET['itemid']) || !isset($_GET['info']) || !isset($_GET['postid'])) {
        wp_die('Missing parameters');
    }

    $item_id = floatval($_GET['itemid']);
    $post_id = floatval($_GET['postid']);
    $info = sanitize_text_field($_GET['info']);
    $country = sanitize_text_field($_GET['country'] ?? 'my');

    // Construct search prompt
    $search = 'Information and review of ' . $info;

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
    $price_level = ' Price Level : ' . get_cct_business_data($item_id, 'price_level');
 
    $content_input = $info . $website . $phone . $rating . $rating_count . $opening_hours . $price_level . '<br>' . $response;

    // Generate readable content (if using Gemini or similar AI)
    $content = ask_gemini_business($content_input); // assumes this returns HTML/text
    $content = removeCodeBlockTags($content); 
     
    // UPDATE POST 
    if ($content <> '') { 
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $content ,
        ], true);
        
        if (is_wp_error($result)) {
            error_log('Post update failed: ' . $result->get_error_message());
        }
        update_post_meta($post_id, 'updated', 'Updated');

    }

    wp_die('Done');

}

// UPDATE LOCATION 
add_shortcode('business_update_location', 'business_update_location_shortcode');

function business_update_location_shortcode() {
    // Check if latitude and longitude are in cookies
    $latitude  = isset($_COOKIE['latitude']) ? $_COOKIE['latitude'] : null;
    $longitude = isset($_COOKIE['longitude']) ? $_COOKIE['longitude'] : null;
    $latitude  = number_format($latitude, 5);
    $longitude = number_format($longitude, 5);
    
    ob_start();
    ?>
    <div id="location-section">
        <button style="font-size: 13px;background-color: #D4591E; color: white;" id="updateLocationBtn">Update Location</button>
        <p id="locationDisplay">
            <?php if ($latitude && $longitude): ?>
                Location : <?= esc_html($latitude) ?>,   <?= esc_html($longitude) ?> <br><br>
            <?php else: ?>
                Location not set.
            <?php endif; ?>
        </p>
    </div>

    <script>
        document.getElementById('updateLocationBtn').addEventListener('click', function () {
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
        echo business_nearest_list_shortcode($latitude, $longitude);
    }

    return ob_get_clean();
}



// DISPLAY NEAREST BUSINESS
add_shortcode('business_nearest_listx', 'business_nearest_list_shortcode');
 
function business_nearest_list_shortcode($latitude,$longitude) {
    global $wpdb,$post;
    $curr_id = get_the_ID();

    $user_lat = floatval($_COOKIE['latitude'] ?? 0);
    $user_lng = floatval($_COOKIE['longitude'] ?? 0);
    
    $lat = $latitude;
    $lng = $longitude;
    
    // Get business from CCT table
    $business_table = $wpdb->prefix . "jet_cct_business";

    $query = $wpdb->prepare(
        "SELECT * FROM $business_table WHERE FLOOR(latitude) = %d AND FLOOR(longitude) = %d AND place_url IS NOT NULL",
        $lat,
        $lng
    );
    $results = $wpdb->get_results($query);

    if (empty($results)) {
        return $ret . 'No business found.';
    }

    // Calculate distances and prepare data
    $data = array();
    
    foreach ($results as $item) {
        $post_id = isset($item->post_id) ? $item->post_id : '';
        $name = isset($item->name) ? $item->name : '';
        $address = isset($item->address) ? $item->address : '';
        $country = isset($item->country) ? $item->country : '';
        $type = isset($item->type) ? $item->type : '';
        $page_url = isset($item->page_url) ? $item->page_url : '';
        $lat = isset($item->latitude) ? floatval($item->latitude) : 0;
        $lng = isset($item->longitude) ? floatval($item->longitude) : 0;
        
        // Skip if coordinates are invalid
        if (!$lat || !$lng) {
            continue;
        }
        
        // Calculate distance using Haversine formula
        $theta = $user_lng - $lng;
        $distance = sin(deg2rad($user_lat)) * sin(deg2rad($lat)) + 
                   cos(deg2rad($user_lat)) * cos(deg2rad($lat)) * 
                   cos(deg2rad($theta));
        $distance = acos($distance);
        $distance = rad2deg($distance);
        $distance = $distance * 60 * 1.853159616; // Convert to kilometers
        
        $data[] = array(
            'name'     => $name,
            'post_id'  => $post_id,
            'address'  => $address,
            'type'     => $type,
            'page_url' => $page_url,
            'distance' => $distance
        );
    }
    
    // Sort by distance (nearest first)
    usort($data, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    // Limit to top 20
    $data = array_slice($data, 0, 20);
    
    // Generate plain text output
    //$output = "Nearest Mosques to Your Location:\n\n";
    //$ret .= '<p>Location (' . $user_lat . ',' . $user_lng . ')</p>'; 
    foreach ($data as $item) {
        $post_id = $item['post_id'];
        $post = get_post($post_id);
        $content = apply_filters('the_content', $post->post_content);
        $name = $item['name'];
        $chk = $name . ' ' . $content;
        $non_halal = NonHalal($chk);
         
        if ($post_id<>$curr_id){
            if (!$non_halal){
                $ret .= "<b>" . strtoupper($item['name']) . "</b><br>";
                $ret .= esc_html($item['address']) . "<br>";
                $ret .= "<i>" . esc_html($item['type']) . "</i><br>";
                $ret .= "<b><i>" . number_format($item['distance'], 2) . " km away</i></b><br>";
                $ret .= "<a href='{$item['page_url']}' class='btn-view-business' style='display:inline-block;margin-top:8px;padding:6px 12px;background:#25988B;color:#fff;text-decoration:none;border-radius:4px;font-size:12px;line-height:1.2;'>View Details</a><br><br>";
            }
        }
            
    }
    
    return $ret;
}

// GET FIELD FROM CCT MOSQUE
function xget_cct_business_data($item_id, $field) {
    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT $field FROM wp_jet_cct_business WHERE _ID = %d",
        $item_id
    );
    $result = $wpdb->get_var($query);
    //return $result ? $result : "";
    return ($result !== null) ? $result : null;
}

function NonHalal($text) {
    return preg_match('/\bnon[-\s]?halal\b/i', $text) === 1;
}

// Nearest Business
add_shortcode('business_nearest_listx', 'business_nearest_listx_shortcode');

function business_nearest_listx_shortcode() {
    // Get user latitude and longitude from cookies
    $user_lat = floatval($_COOKIE['latitude'] ?? 0);
    $user_lng = floatval($_COOKIE['longitude'] ?? 0);

    if (!$user_lat || !$user_lng) {
        return "Location data not available.";
    }

    // Query all published business posts
    $query = new WP_Query([
        'post_type'      => 'business',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    if (!$query->have_posts()) {
        return "No business found in the database.";
    }

    $business_list = [];

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        $lat = floatval(get_post_meta($post_id, 'latitude', true));
        $lng = floatval(get_post_meta($post_id, 'longitude', true));
        $address = get_post_meta($post_id, 'address', true);

        if (!$lat || !$lng) continue;

        $distance = calculate_distance($user_lat, $user_lng, $lat, $lng);

        $business_list[] = [
            'name'     => get_the_title(),
            'address'  => esc_html($address),
            'latitude' => $lat,
            'longitude'=> $lng,
            'distance' => $distance,
            'url'      => get_permalink($post_id),
        ];
    }

    wp_reset_postdata();

    // Sort businesses by distance
    usort($business_list, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    // Get the nearest 20 businesses
    $business_list = array_slice($business_list, 0, 20);

    // Output
    $output = "";
    foreach ($business_list as $biz) {
        $output .= "<div style='margin-bottom: 20px; padding: 10px; border-bottom: 1px solid #ddd;'>";
        $output .= "<strong>{$biz['name']}</strong><br>";
        $output .= "{$biz['address']}<br>";
        $output .= "{$biz['distance']} km away<br>";
        $output .= "<a href='{$biz['url']}' class='btn-view-business' style='display:inline-block;margin-top:8px;padding:6px 12px;background:#25988B;color:#fff;text-decoration:none;border-radius:4px;font-size:12px;line-height:1.2;'>View Details</a>";
        $output .= "</div>";
    }

    return $output;
}

// AI BUSINESS REVIEW
add_shortcode('business_ai_review', 'business_ai_review_shortcode');

function business_ai_review_shortcode() {
    global $post;
    $post_id = $post->ID;

    // Get the title
    $title = get_the_title($post_id);
    $address = get_post_meta($post->ID, 'address', true);
 
    // search info on the business
    $search = $title . ' ' . $address;
    $country = 'my';
    
    echo $title;
    return;
    $response = business_search_shortcode($search,$country);
        
    $content = ask_gemini_business($response);
     
    // Update Content 
    $my_post = array(
      'ID'           => $post_id ,
      'post_content' => $content,
     );
     
    // Update the post into the database
    wp_update_post( $my_post );

    
    return $content;

       
    // Get the raw content.
    $raw_content = get_the_content(null, false, $post);

    // Apply the 'the_content' filter.
    $content = apply_filters('the_content', $raw_content);

    // Trim whitespace and check if the content is empty.
    $trimmed_content = trim(strip_tags($content)); //remove html tags, then trim whitespace

    if (empty($trimmed_content)) {
        // Get custom fields (replace with your actual field names)
        $address = get_post_meta($post->ID, 'address', true);
        $phone = get_post_meta($post->ID, 'phone', true);
        $place_id = get_post_meta($post->ID, 'place_id', true);
        $country = get_post_meta($post->ID, 'country', true);
        $telephone = get_post_meta($post->ID, 'phone', true);
        $latitude = get_post_meta($post->ID, 'latitude', true);
        $longitude = get_post_meta($post->ID, 'longitude', true);
        $type = get_post_meta($post->ID, 'type', true);
        $tags = get_post_meta($post->ID, 'tags', true);
        $website = get_post_meta($post->ID, 'website', true);
        $rating = get_post_meta($post->ID, 'rating', true);
        $rating_count = get_post_meta($post->ID, 'rating_count', true);
        $price_level = get_post_meta($post->ID, 'price_level', true);
        $opening_hours = get_post_meta($post->ID, 'opening_hours', true);
         
        
        // info on the business
        $ret .= 'Business : ' . $title . '<br>';
        $ret .= 'Address : ' . $address . '<br>'; 
        $ret .= 'Phone : ' . $phone . '<br>'; 
        //$ret .= 'Google Map Place ID : ' . $place_id . '<br>';
        $ret .= 'Country : ' . $country . '<br>';
        //$ret .= 'Latitude : ' . $latitude . '<br>';
        //$ret .= 'Longitude : ' . $longitude . '<br>'; 
        $ret .= 'Type : ' . $type . '<br>';
        $ret .= 'Tags : ' . $tags . '<br>';
        $ret .= 'Website : ' . $website . '<br>';
        $ret .= 'Ratings : ' . $rating . '<br>';
        $ret .= 'Rating Count : ' . $rating_count . '<br>';
        $ret .= 'Price Level : ' . $price_level . '<br>';
        $ret .= 'Opening Hours : ' . $opening_hours . '<br>';
        
        $content = ask_gemini($ret);
         
        // Update Content 
        $my_post = array(
          'ID'           => $post_id ,
          'post_content' => $content,
         );
    
        // Update the post into the database
        wp_update_post( $my_post );

    }
    
    return $content;
    
}    

// SEARCH BUSINESS ////////
function business_search_shortcode($search,$country) {

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




// ADD NEARBY BUSINESS //////////////////////

add_shortcode('business_add_new', 'business_add_new_shortcode');

function business_add_new_shortcode() {

    ob_start();

    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="data_csv" accept=".csv" required>
        <button type="submit" name="upload_csv">Upload & Process</button>
    </form>

    <?php
    if (isset($_POST['upload_csv']) && !empty($_FILES['data_csv']['tmp_name'])) {
        $file = $_FILES['data_csv']['tmp_name'];

        // Read CSV file
        $csv_data = array_map('str_getcsv', file($file));
        
        // Remove header row if it exists
        if (!empty($csv_data) && strtolower($csv_data[0][0]) == 'latitude') {
            array_shift($csv_data);
        }
 
        $total_records = count($csv_data);
        echo "<h3>Total Records: $total_records</h3>";
        
        $rec = 0;
        if ($total_records > 0) {
            foreach ($csv_data as $row) {
                $rec = $rec + 1;
                if (count($row) < 2) continue; // Skip invalid rows
                
                $latitude = trim($row[0]);
                $longitude = trim($row[1]);
           
                echo '<br><br><b>' . $rec . ' : ' . $latitude . ',' . $longitude . '</b>';      
              
                //serper_nearest_business($latitude, $longitude);
            }
            echo "<h3>Processing Complete!</h3>";
        } else {
            echo "<h3>No valid data found in the uploaded file.</h3>";
        }
    }

    return ob_get_clean();
}



// BUSINESS CONTENT SCRAPPER
add_shortcode('business_content_update', 'business_content_update_shortcode');

function business_content_update_shortcode() {
    $post_id = 9106;
    $image_url = 'https://media-cdn.tripadvisor.com/media/photo-s/09/8d/33/38/mackenzie-rex-restaurant.jpg';
    set_post_thumbnail_from_url( $post_id, $image_url );
    return;
    
    $start = 51;  // Start from 0 (first record)
    $limit = 10; // Number of records to fetch
     
    $args = [
        'post_type'      => 'business',
        'posts_per_page' => $limit,
        'offset'         => $start,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ];

    $query = new WP_Query($args);
    $updated = 0;

    if (!empty($query->posts)) {
        foreach ($query->posts as $post_id) {
            $title = get_the_title($post_id);
            $address = get_post_meta($post_id, 'address', true);
            $country = get_post_meta($post_id, 'country', true);
            $country = get_country_code($country);
            $address = get_post_meta($post_id, 'address', true);
            $phone = get_post_meta($post_id, 'phone', true);
            $telephone = get_post_meta($post_id, 'phone', true);
            $latitude = get_post_meta($post_id, 'latitude', true);
            $longitude = get_post_meta($post_id, 'longitude', true);
            $tags = get_post_meta($post_id, 'tags', true);
            $website = get_post_meta($post_id, 'website', true);
            $rating = get_post_meta($post_id, 'rating', true);
            $rating_count = get_post_meta($post_id, 'rating_count', true);
            $price_level = get_post_meta($post_id, 'price_level', true);
            $opening_hours = get_post_meta($post_id, 'opening_hours', true);
            
            // info on the business
            $info .= 'Business : ' . $title . '<br>';
            $info .= 'Address : ' . $address . '<br>'; 
            $info .= 'Phone : ' . $phone . '<br>'; 
            $info .= 'Country : ' . $country . '<br>';
            $info .= 'Latitude : ' . $latitude . '<br>';
            $info .= 'Longitude : ' . $longitude . '<br>'; 
            $info .= 'Tags : ' . $tags . '<br>';
            $info .= 'Website : ' . $website . '<br>';
            $info .= 'Ratings : ' . $rating . '<br>';
            $info .= 'Rating Count : ' . $rating_count . '<br>';
            $info .= 'Price Level : ' . $price_level . '<br>';
            $info .= 'Opening Hours : ' . $opening_hours . '<br>';
             
            // search info on the business
            $search = $title . ' ' . $address ;
            $result = business_search_shortcode($search,$country);
            $response = $info . '<br>' . $result;
            $content = ask_gemini_business($response);
                 
            // Update Content 
            $my_post = array(
              'ID'           => $post_id ,
              'post_content' => $content,
             );
                 
            // Update the post into the database
            wp_update_post( $my_post );
            
            $updated++;
            $ret.= $content . '<br><br><hr>';
    
        }
    }

    $ret.= "✅ Updated {$updated} business posts with current 'last_update'.";
    return $ret;
    
}

// BUSINESS IMAGE SCRAPPER
add_shortcode('business_image_scrapper', 'business_image_scrapper_shortcode');

function business_image_scrapper_shortcode() {
 
    $start = 55;  // Start from 0 (first record)
    $limit = 5;  // Number of records to fetch
     
    $args = [
        'post_type'      => 'business',
        'posts_per_page' => $limit,
        'offset'         => $start,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ];

    $query = new WP_Query($args);
    $updated = 0;

    if (!empty($query->posts)) {
        foreach ($query->posts as $post_id) {
            $title = get_the_title($post_id);
            $address = get_post_meta($post_id, 'address', true);
            $country = get_post_meta($post_id, 'country', true);
            $country = get_country_code($country);
  
            // search info on the business
            $search = $title . ' ' . $address ;
            $result = business_images_search_shortcode($search,$country);
            
            $image_url = ask_gemini_images($result);
            
            set_post_thumbnail_from_url( $post_id, $image_url );
            
            $updated++;
            $ret.= $post_id . ' ' . $title . '<br>' . $image_url . '<br><br>';
    
        }
    }

    $ret.= "✅ Updated {$updated} business posts with current 'last_update'.";
    return $ret;
    
}

// SEARCH IMAGES ////////
function business_images_search_shortcode($search,$country) {
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://google.serper.dev/images',
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

// UPLOAD IMAGE 
function set_post_thumbnail_from_url( $post_id, $image_url ) {
    // Sanitize the post ID.  Make sure it is an integer.
    $post_id = intval( $post_id );
    if ( $post_id <= 0 ) {
        return new WP_Error( 'invalid_post_id', 'Invalid post ID provided.' );
    }

    // Sanitize the URL
    $image_url = esc_url_raw( $image_url );
    if ( empty( $image_url ) ) {
        return new WP_Error( 'invalid_image_url', 'Invalid image URL provided.' );
    }

    // Check if the image already exists in the media library to avoid duplicates.
    $image_name = wp_basename( $image_url );
    $query_images = get_posts(
        array(
            'post_type'  => 'attachment',
            'fields'     => 'ids',
            'meta_query' => array(
                array(
                    'key'     => '_wp_attached_file',
                    'value'   => $image_name, // Check for filename match.  More robust would be to check full path.
                    'compare' => 'LIKE',
                ),
            ),
        )
    );

    if ( ! empty( $query_images ) ) {
        $attachment_id = $query_images[0]; //get the first ID.
        set_post_thumbnail( $post_id, $attachment_id );
        return $attachment_id; // Return the existing attachment ID.
    }

    // Download the image.  Use download_url() which handles security checks.
    $tmp_file = download_url( $image_url );

    // Check for download errors.
    if ( is_wp_error( $tmp_file ) ) {
        return $tmp_file; // Return the WP_Error object.
    }

    // Set up file array for wp_handle_sideload.
    $file_array = array(
        'name'     => wp_basename( $image_url ), // Use the original filename.
        'tmp_name' => $tmp_file,
    );

    // If you want to add specific headers (if required,  most of the time is not needed).
    $headers = array(
        'Content-Type' => mime_content_type($tmp_file)
    );
    // Sideload the file into the media library.
    $sideload = wp_handle_sideload(
        $file_array,
        array(
            'test_form' => false, // Important:  Disable form test.
            'headers'   => $headers,
        )
    );

    // Check for sideload errors.
    if ( isset( $sideload['error'] ) ) {
        @unlink( $tmp_file ); // Clean up the temporary file.
        return new WP_Error( 'file_sideload_failed', $sideload['error'] );
    }

    // Get the attachment ID from the result.
    $attachment_id = $sideload['file'];
    $attachment_id = wp_insert_attachment(
        array(
            'guid'           => $sideload['url'],
            'post_mime_type' => $sideload['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', wp_basename( $sideload['file'] ) ), //sanitize title
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $attachment_id
    );

     if ( is_wp_error( $attachment_id ) ) {
        @unlink( $tmp_file );
        return $attachment_id;
    }
    $attachment_id = intval($attachment_id);

    // Generate metadata for the attachment.
    wp_update_attachment_metadata(
        $attachment_id,
        wp_generate_attachment_metadata( $attachment_id, $sideload['file'] )
    );
  
    // Set the attachment as the featured image for the post.
    set_post_thumbnail( $post_id, $attachment_id );

    // Clean up the temporary file.
    @unlink( $tmp_file );

    return $attachment_id; // Return the attachment ID.
}

// GET COUNTRY CODE
function get_country_code($country_name) {
    $countries = [
        "Afghanistan" => "AF",
        "Albania" => "AL",
        "Algeria" => "DZ",
        "Andorra" => "AD",
        "Angola" => "AO",
        "Antigua and Barbuda" => "AG",
        "Argentina" => "AR",
        "Armenia" => "AM",
        "Australia" => "AU",
        "Austria" => "AT",
        "Azerbaijan" => "AZ",
        "Bahamas" => "BS",
        "Bahrain" => "BH",
        "Bangladesh" => "BD",
        "Barbados" => "BB",
        "Belarus" => "BY",
        "Belgium" => "BE",
        "Belize" => "BZ",
        "Benin" => "BJ",
        "Bhutan" => "BT",
        "Bolivia" => "BO",
        "Bosnia and Herzegovina" => "BA",
        "Botswana" => "BW",
        "Brazil" => "BR",
        "Brunei" => "BN",
        "Bulgaria" => "BG",
        "Burkina Faso" => "BF",
        "Burundi" => "BI",
        "Cabo Verde" => "CV",
        "Cambodia" => "KH",
        "Cameroon" => "CM",
        "Canada" => "CA",
        "Central African Republic" => "CF",
        "Chad" => "TD",
        "Chile" => "CL",
        "China" => "CN",
        "Colombia" => "CO",
        "Comoros" => "KM",
        "Congo (Congo-Brazzaville)" => "CG",
        "Costa Rica" => "CR",
        "Croatia" => "HR",
        "Cuba" => "CU",
        "Cyprus" => "CY",
        "Czech Republic" => "CZ",
        "Democratic Republic of the Congo" => "CD",
        "Denmark" => "DK",
        "Djibouti" => "DJ",
        "Dominica" => "DM",
        "Dominican Republic" => "DO",
        "Ecuador" => "EC",
        "Egypt" => "EG",
        "El Salvador" => "SV",
        "Equatorial Guinea" => "GQ",
        "Eritrea" => "ER",
        "Estonia" => "EE",
        "Eswatini" => "SZ",
        "Ethiopia" => "ET",
        "Fiji" => "FJ",
        "Finland" => "FI",
        "France" => "FR",
        "Gabon" => "GA",
        "Gambia" => "GM",
        "Georgia" => "GE",
        "Germany" => "DE",
        "Ghana" => "GH",
        "Greece" => "GR",
        "Grenada" => "GD",
        "Guatemala" => "GT",
        "Guinea" => "GN",
        "Guinea-Bissau" => "GW",
        "Guyana" => "GY",
        "Haiti" => "HT",
        "Honduras" => "HN",
        "Hungary" => "HU",
        "Iceland" => "IS",
        "India" => "IN",
        "Indonesia" => "ID",
        "Iran" => "IR",
        "Iraq" => "IQ",
        "Ireland" => "IE",
        "Israel" => "IL",
        "Italy" => "IT",
        "Jamaica" => "JM",
        "Japan" => "JP",
        "Jordan" => "JO",
        "Kazakhstan" => "KZ",
        "Kenya" => "KE",
        "Kiribati" => "KI",
        "Kuwait" => "KW",
        "Kyrgyzstan" => "KG",
        "Laos" => "LA",
        "Latvia" => "LV",
        "Lebanon" => "LB",
        "Lesotho" => "LS",
        "Liberia" => "LR",
        "Libya" => "LY",
        "Liechtenstein" => "LI",
        "Lithuania" => "LT",
        "Luxembourg" => "LU",
        "Madagascar" => "MG",
        "Malawi" => "MW",
        "Malaysia" => "MY",
        "Maldives" => "MV",
        "Mali" => "ML",
        "Malta" => "MT",
        "Marshall Islands" => "MH",
        "Mauritania" => "MR",
        "Mauritius" => "MU",
        "Mexico" => "MX",
        "Micronesia" => "FM",
        "Moldova" => "MD",
        "Monaco" => "MC",
        "Mongolia" => "MN",
        "Montenegro" => "ME",
        "Morocco" => "MA",
        "Mozambique" => "MZ",
        "Myanmar" => "MM",
        "Namibia" => "NA",
        "Nauru" => "NR",
        "Nepal" => "NP",
        "Netherlands" => "NL",
        "New Zealand" => "NZ",
        "Nicaragua" => "NI",
        "Niger" => "NE",
        "Nigeria" => "NG",
        "North Korea" => "KP",
        "North Macedonia" => "MK",
        "Norway" => "NO",
        "Oman" => "OM",
        "Pakistan" => "PK",
        "Palau" => "PW",
        "Palestine State" => "PS",
        "Panama" => "PA",
        "Papua New Guinea" => "PG",
        "Paraguay" => "PY",
        "Peru" => "PE",
        "Philippines" => "PH",
        "Poland" => "PL",
        "Portugal" => "PT",
        "Qatar" => "QA",
        "Romania" => "RO",
        "Russia" => "RU",
        "Rwanda" => "RW",
        "Saint Kitts and Nevis" => "KN",
        "Saint Lucia" => "LC",
        "Saint Vincent and the Grenadines" => "VC",
        "Samoa" => "WS",
        "San Marino" => "SM",
        "Sao Tome and Principe" => "ST",
        "Saudi Arabia" => "SA",
        "Senegal" => "SN",
        "Serbia" => "RS",
        "Seychelles" => "SC",
        "Sierra Leone" => "SL",
        "Singapore" => "SG",
        "Slovakia" => "SK",
        "Slovenia" => "SI",
        "Solomon Islands" => "SB",
        "Somalia" => "SO",
        "South Africa" => "ZA",
        "South Korea" => "KR",
        "South Sudan" => "SS",
        "Spain" => "ES",
        "Sri Lanka" => "LK",
        "Sudan" => "SD",
        "Suriname" => "SR",
        "Sweden" => "SE",
        "Switzerland" => "CH",
        "Syria" => "SY",
        "Taiwan" => "TW",
        "Tajikistan" => "TJ",
        "Tanzania" => "TZ",
        "Thailand" => "TH",
        "Timor-Leste" => "TL",
        "Togo" => "TG",
        "Tonga" => "TO",
        "Trinidad and Tobago" => "TT",
        "Tunisia" => "TN",
        "Turkey" => "TR",
        "Turkmenistan" => "TM",
        "Tuvalu" => "TV",
        "Uganda" => "UG",
        "Ukraine" => "UA",
        "United Arab Emirates" => "AE",
        "United Kingdom" => "GB",
        "United States" => "US",
        "Uruguay" => "UY",
        "Uzbekistan" => "UZ",
        "Vanuatu" => "VU",
        "Vatican City" => "VA",
        "Venezuela" => "VE",
        "Vietnam" => "VN",
        "Yemen" => "YE",
        "Zambia" => "ZM",
        "Zimbabwe" => "ZW",
    ];

    $country_name = ucwords(strtolower(trim($country_name)));

    return $countries[$country_name] ?? null;
}

// UPDATE BUSINESS POST INFO 
add_shortcode('business_update_infox', 'business_update_info_shortcode');
 
function business_update_info_shortcode() {
    global $wpdb, $post;
    $post_id = get_the_ID();
    $post = get_post($post_id);
    $content = apply_filters('the_content', $post->post_content);
    
    $item_id = get_post_meta($post_id, 'item_id', true);
    $name = get_cct_business_data($item_id, 'name');
    $address = get_cct_business_data($item_id, 'address');
    $country = get_cct_business_data($item_id, 'country');

    if ($content == '') {
        $info .= $name . ' ' . $address;
        $url = admin_url('admin-ajax.php') . "?action=get_business_info&itemid=$item_id&postid=$post_id&country=$country&info=" . urlencode($info);
    
        // Run AJAX call in the background
        wp_remote_get($url, [
            'timeout' => 0.01,
            'blocking' => false,
            'sslverify' => false // optional: for local dev environments
        ]);
    }

    return;
}

//////////////////////////////////
// BUSINESS SUMMARY             //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('business_summary_country', 'business_summary_country_shortcode');
}); 
 
function business_summary_country_shortcode() {
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
        SELECT country, COUNT(*) AS business_count
        FROM {$wpdb->prefix}jet_cct_business
        WHERE country IS NOT NULL
          AND country <> ''
        " . $date_query . "  
        GROUP BY country
        ORDER BY business_count DESC
    ");
    
    if ($results) {
        $total_business = 0; // Initialize total count
        $total_country = 0;
        $tbl = '<table>';
        $cnt = 0;
        foreach ($results as $row) {
            $cnt = $cnt + 1;
            $tot = number_format($row->business_count);
            if ($cnt < 300){
                $tbl.= '<tr>';
                $tbl .= "<td>{$row->country}</td><td  style='text-align: right'>{$tot}</td>";
                $tbl.= '</tr>';
            }
            //$ret .= "{$row->country} ({$row->mosque_count}) &#9679; ";
      
            $total_business += $row->business_count; // Add to total count
            $total_country += 1;
        }
        $tbl.= '</table>';
          
        //$summ.= 'Total Mosques : <b>' . number_format($total_mosques) . '<br><br>' ;
        //$summ.= '<b>Total Mosques : ' . $total_mosques . '</b><br><br> ';
        
        //$ret = $summ . $ret;;
     
     } else {
        $ret = "No data found.";
    }
    
    $ret = 'Total Business : <b>' . number_format($total_business) . '</b><br><br>';
    return $ret . $tbl . ob_get_clean();
}

//////////////////////////////////
// BUSINESS SUMMARY BY REGION   //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('business_summary_continent', 'business_summary_continent_shortcode');
}); 

function business_summary_continent_shortcode() {
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
        SELECT continent, COUNT(*) AS business_count
        FROM {$wpdb->prefix}jet_cct_business
        WHERE continent IS NOT NULL
          AND continent <> ''
        " . $date_query . "  
        GROUP BY continent
        ORDER BY business_count DESC
    ");
    
    $total_business = 0;
    $total_regions = 0;

    // Header Info
    echo '<div style="padding: 15px; background: #fff4e6; border-left: 5px solid #fb8c00; border-radius: 4px; margin-bottom:15px;">';
    
    if ($results) {
        foreach ($results as $row) {
            $total_business += $row->business_count; 
            $total_regions += 1;
        }
    }
    echo 'Total Global Businesses: <b>' . number_format($total_business) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if ($results) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Business</th>
                 </tr>';
        
        foreach ($results as $row) {
            $tot = number_format($row->business_count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($row->continent) . "</td>";
            
            // TUKAR DI SINI: Jadikan nombor sebagai butang klik (Drill-down link)
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-drilldown-btn' data-continent='" . esc_attr($row->continent) . "' style='text-decoration: underline; color: #fb8c00; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo "<p>No business regional data found.</p>";
    }

    // --- HTML UNTUK MODAL POPUP ---
    ?>
    <style>
        .m4a-modal-overlay { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .m4a-modal-content { background-color: #fff; margin: 5% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 800px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .m4a-close-modal { color: #aaa; position: absolute; right: 20px; top: 15px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .m4a-close-modal:hover { color: #333; }
        .m4a-modal-loader { text-align: center; padding: 40px; display: none; }
        .m4a-modal-body { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        .m4a-modal-table-wrap { flex: 1; min-width: 300px; max-height: 400px; overflow-y: auto; }
        .m4a-modal-chart-wrap { flex: 1; min-width: 300px; text-align: center; }
    </style>

<div id="m4aDrilldownModal" class="m4a-modal-overlay">
        <div class="m4a-modal-content">
            <span class="m4a-close-modal m4a-business-close">&times;</span>
            <h2 id="m4aModalTitle" style="margin-top:0; color:#fb8c00; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>
            
            <div id="m4aModalLoader" style="text-align:center; padding:50px 0; display:none;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:30px; color:#fb8c00;"></i>
                <p style="margin-top:10px; color:#555;">Compiling data...</p>
            </div>

            <div id="m4aModalBody" class="m4a-modal-body" style="display:none;">
                <div class="m4a-modal-chart-wrap">
                    <h4 style="margin: 0 0 15px 0; color:#444;">Top 10 Countries</h4>
                    <div class="m4a-chart-canvas-box">
                        <canvas id="m4aDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap">
                    <div id="m4aModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("m4aDrilldownModal");
        if (modal && !modal.closest('body > .m4a-modal-overlay')) {
            document.body.appendChild(modal);
        }

        // KEMASKINI DI SINI: Guna class unik untuk business
        const closeBtn = document.querySelector(".m4a-business-close");
        const drillBtns = document.querySelectorAll(".m4a-drilldown-btn");
        let drillChartInstance = null; 

        drillBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aModalTitle").innerText = "Businesses in " + continent;
                
                modal.style.display = "block";
                document.getElementById("m4aModalBody").style.display = "none";
                document.getElementById("m4aModalLoader").style.display = "block";

                const formData = new FormData();
                formData.append('action', 'm4a_get_continent_drilldown');
                formData.append('continent', continent);

                fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById("m4aModalLoader").style.display = "none";
                        document.getElementById("m4aModalBody").style.display = "flex";
                        document.getElementById("m4aModalTableContent").innerHTML = data.data.table_html;

                        const ctx = document.getElementById("m4aDrilldownChart").getContext("2d");
                        
                        if(drillChartInstance != null) { drillChartInstance.destroy(); }

                        drillChartInstance = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: data.data.chart_labels,
                                datasets: [{
                                    data: data.data.chart_data,
                                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#2ecc71', '#e74c3c', '#34495e'],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: { 
                                responsive: true, 
                                maintainAspectRatio: false,
                                plugins: {
                                    datalabels: { display: false },
                                    legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 11} } }
                                }
                            }
                        });
                    }
                });
            });
        });

        // KEMASKINI DI SINI: Gunakan event listener supaya tidak menindih modal lain
        const closeModalFn = function() { modal.style.display = "none"; };
        if(closeBtn) closeBtn.onclick = closeModalFn;
        
        window.addEventListener('click', function(e) { 
            if (e.target == modal) closeModalFn(); 
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

////////////////////////////////////////////////////
// AJAX HANDLER UNTUK DRILL-DOWN (BACKEND)        //
////////////////////////////////////////////////////
add_action('wp_ajax_m4a_get_continent_drilldown', 'm4a_ajax_continent_drilldown_callback');
// Jika mahu benarkan non-login user tengok juga:
// add_action('wp_ajax_nopriv_m4a_get_continent_drilldown', 'm4a_ajax_continent_drilldown_callback');

function m4a_ajax_continent_drilldown_callback() {
    global $wpdb;
    
    $continent = isset($_POST['continent']) ? sanitize_text_field($_POST['continent']) : '';
    
    if (empty($continent)) {
        wp_send_json_error(['message' => 'No continent specified']);
    }

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
        FROM {$wpdb->prefix}jet_cct_business
        WHERE continent = %s AND country IS NOT NULL AND country <> ''
        " . $date_query . "
        GROUP BY country
        ORDER BY count DESC
    ", $continent));

    $chart_labels = [];
    $chart_data = [];
    
    $html = '<table style="width:100%; border-collapse: collapse;">';
    $html .= '<tr style="background:#f1f1f1; text-align:left;">
                <th style="padding:8px; border-bottom:2px solid #ccc;">Country</th>
                <th style="padding:8px; border-bottom:2px solid #ccc; text-align:right;">Total</th>
              </tr>';

    $index = 0;
    foreach ($results as $row) {
        $html .= '<tr>';
        $html .= '<td style="padding:8px; border-bottom:1px solid #eee;">' . esc_html($row->country) . '</td>';
        $html .= '<td style="padding:8px; border-bottom:1px solid #eee; text-align:right; font-weight:bold;">' . intval($row->count) . '</td>';
        $html .= '</tr>';

        // Hanya ambil top 10 untuk pie chart
        if ($index < 10) {
            $chart_labels[] = $row->country;
            $chart_data[] = (int)$row->count;
        }
        $index++;
    }
    $html .= '</table>';

    wp_send_json_success([
        'table_html' => $html,
        'chart_labels' => $chart_labels,
        'chart_data' => $chart_data
    ]);
}