<?php

// PERPLEXITY_API_KEY is resolved in keys.php (wp-config constant, then DB option).

function perplexity_mosquesxx($info) {
    global $wpdb;

    /* ----------------------------------------------------
     * 1. Validation and Setup
     * ---------------------------------------------------- */
    if (empty($info)) {
        return json_encode(['error' => 'Error: missing variables.'], JSON_UNESCAPED_UNICODE);
    }

    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
    if (empty($api_key)) {
        error_log('PERPLEXITY_API_KEY is not defined.');
        return json_encode(['error' => 'Error: Perplexity API Key is not configured.'], JSON_UNESCAPED_UNICODE);
    }
    
    /* ----------------------------------------------------
     * 2. Prepare Mosque Information and Structured Query
     * ---------------------------------------------------- */
    $role = "You are Assistant for Masjid4All, a global directory for mosques.";
    $mosque_info = is_array($info) ? json_encode($info, JSON_UNESCAPED_UNICODE) : $info;
    
    $system_prompt  = $role ?: "You are Assistant for Masjid4All, a global directory for mosques.";
    $system_prompt .= "Do not add any citations. Always respond in valid JSON format only with these exact fields: 
        {
        \"introduction\": \"SEO-friendly brief introduction on the mosque (100-150 chars) starting with main keyword and add interesting facts on the mosque\",
        \"address\": \"full address\",
        \"city\": \"name of city only\",
        \"postcode\": \"postcode only\",
        \"country\": \"country only\",
        \"jumaat_prayer\": \"'Yes' or 'No' for Friday prayer\",
        \"activities\": \"Key activities and services\",
        \"telephone\": \"Contact number or 'Not available'\",
        \"website\": \"Official website URL or ''\",
        \"facebook\": \"Facebook URL or ''\",
        \"instagram\": \"Instagram URL or ''\",
        \"youtube\": \"YouTube URL or ''\",
        \"facilities\": [\"List of facilities\"],
        \"community_role\": \"community role of the mosque\",
        \"public_transport\": \"How to get there, nearest stations/routes or 'No information'\",
        \"additional_info\": \"Other relevant info such as donations, zakat, khairat etc or ''\",

    }";
     
    $question = "Info: {$mosque_info}\n\nProvide complete Masjid4All directory information in the exact JSON format requested. ";
    
    /* ----------------------------------------------------
     * 3. Perplexity API Request with Structured Output
     * ---------------------------------------------------- */
    $url = 'https://api.perplexity.ai/chat/completions';
    
    $data = array(
        'model' => 'sonar',
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
    );
    
    $args = array(
        'method'  => 'POST',
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body'    => json_encode($data),
        'timeout' => 30
    );
    
    $response = wp_remote_post($url, $args);
    
    if (is_wp_error($response)) {
        error_log('Perplexity API Error: ' . $response->get_error_message());
        return json_encode(['error' => 'Failed to connect to Perplexity API.'], JSON_UNESCAPED_UNICODE);
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    if (isset($result['error'])) {
        error_log('Perplexity API Error: ' . $result['error']['message']);
        return json_encode(['error' => $result['error']['message']], JSON_UNESCAPED_UNICODE);
    }
    
    /* ----------------------------------------------------
     * 4. Parse and Validate JSON Response
     * ---------------------------------------------------- */
    if (isset($result['choices'][0]['message']['content'])) {
        $raw_response = trim($result['choices'][0]['message']['content']);
        
        return $raw_response;
        // Try to extract JSON from response (handles markdown code blocks)
        if (preg_match('/\{.*\}/s', $raw_response, $matches)) {
            $json_response = json_decode($matches[0], true);
        } else {
            $json_response = json_decode($raw_response, true);
        }
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_response)) {
            return json_encode($json_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
    
    return json_encode(['error' => 'Invalid response format from API.'], JSON_UNESCAPED_UNICODE);
}

///////////////////////////////////////////////////////////
//***************************/////
// DISPLAY MOSQUE POST INFO 
add_shortcode('mosques_display_infoxx', 'mosques_display_infoxx_shortcode');

function mosques_display_infoxx_shortcode() {
    $post_id = get_the_ID();
    if (!$post_id) return '';

    $item_id = get_post_meta($post_id, 'item_id', true);
    if (!$item_id) return '';

   
    // Fetch data (Assuming get_cct_mosque_data handles escaping/sanitization)
    $data = [
        'cid'     => get_cct_mosque_data($item_id, 'cid'),
        'lat'     => get_cct_mosque_data($item_id, 'latitude'),
        'lon'     => get_cct_mosque_data($item_id, 'longitude'),
        'name'    => get_cct_mosque_data($item_id, 'name'),
        'address' => get_cct_mosque_data($item_id, 'address'),
        'country' => get_cct_mosque_data($item_id, 'country'),
        'status'  => get_cct_mosque_data($item_id, 'business_status'),
    ];

    // 1. Better Cookie Handling (Only set if different to avoid header spam)
    if ($data['country'] && (!isset($_COOKIE['search_country']) || $_COOKIE['search_country'] !== $data['country'])) {
        setcookie('search_country', sanitize_text_field($data['country']), time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
    }

    $content = apply_filters('the_content', get_post_field('post_content', $post_id));
    $output  = '<div class="mosque-info-wrapper">';

    // 2. Navigation Links
    $output .= '<div class="mosque-map-links" style="margin-bottom: 15px;">';
    if ($data['cid']) {
        $google_url = "https://www.google.com/maps?cid=" . esc_attr($data['cid']);
        $output .= sprintf('<a href="%s" target="_blank" class="btn-map btn-google" style="display:inline-block;padding:8px 12px;background:#0073aa;color:#fff;border-radius:5px;text-decoration:none;margin-right:5px;">📍 Google Map</a>', $google_url);
    }
    
    $waze_url = sprintf('https://waze.com/ul?ll=%s,%s&navigate=yes', esc_attr($data['lat']), esc_attr($data['lon']));
    $output .= sprintf('<a href="%s" target="_blank" class="btn-map btn-waze" style="display:inline-block;padding:8px 12px;background:#33CC99;color:#fff;border-radius:5px;text-decoration:none;">遵循 Waze</a>', $waze_url);
    $output .= '</div>';

    // 3. Logic for Update UI
    $has_no_content = empty(trim(strip_tags($content)));
    $needs_update   = $has_no_content || $data['status'] !== 'Updated';
    
   
    //$needs_update   = $has_no_content ;

    //$output .= '<div><strong>Status:</strong> ' . esc_html($data['status']) . '</div>';

    //$output .= '<div>' . esc_html($data['address']) . '</div>';
    //$output .= '<div>Telephone : ' . esc_html($data['phone']) . '</div>';
    //$output .= '<div>Website : ' . esc_html($data['website']) . '</div>';
    //$output .= '<div>Email : ' . esc_html($data['email']) . '</div>';
    //$output .= '<div>Country : ' . esc_html($data['country']) . '</div>';

    if ($needs_update) {
        //$output .= '<div>' . esc_html($data['address']) . '</div><br>';
        //$output .= '<div><strong>Status:<strong> ' . esc_html($data['status']) . '</div>';
        //$output .= '<br><strong>Content to be updated soon<strong>';
        
        $output .= sprintf(
            '<div style="background:#f7f9fb;padding:14px;border-radius:10px;margin-bottom:15px;border-left:4px solid #25988B;">
                <strong>🕌 %s</strong><br>
                <small style="color:#555;">%s, %s</small><br><br>
                <button id="update-mosque-btn" 
                        data-post-id="%d" 
                        data-nonce="%s"
                        style="background:#25988B;color:#fff;padding:10px 16px;border:0;border-radius:8px;font-weight:bold;cursor:pointer;">
                    Help Us Update this Mosque Now
                </button>
                <div id="update-mosque-msg" style="margin-top:10px; font-size:14px;"></div>
            </div>',
            esc_html($data['name']),
            esc_html($data['address']),
            esc_html($data['country']),
            $post_id,
            wp_create_nonce('update_mosque_nonce_' . $post_id)
        );
        
        $output .= 'Our mission is to index and update information for 1 million mosques worldwide. This is an ongoing effort, and the details for this mosque haven’t been updated yet.<br><br>
You can make a difference by triggering the update process for nearby mosques. Your help can bring us closer to achieving our global goal.<br><br>';
 

        // Inline JS (Consider moving to a separate file)
        ob_start(); ?>
        <script>
        document.getElementById('update-mosque-btn')?.addEventListener('click', function() {
            const btn = this;
            const msg = document.getElementById('update-mosque-msg');
            
            btn.disabled = true;
            btn.style.opacity = '0.5';
            msg.innerHTML = '✨ AI is generating content...';

            const params = new URLSearchParams({
                action: 'update_mosque_content',
                post_id: btn.dataset.postId,
                nonce: btn.dataset.nonce
            });

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    msg.style.color = 'green';
                    msg.innerHTML = '✅ Success! Reloading...';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(res.data || 'Unknown error');
                }
            })
            .catch(err => {
                msg.style.color = 'red';
                msg.innerHTML = '❌ ' + err.message;
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        });
        </script>
        <?php
        
    
        $output .= ob_get_clean();

 
    } else {
 
        if (strlen($content)>200){
            $output .= '<div class="mosque-main-content">' . $content . '</div>';
        }else{
            //$output .= '<br><strong>Content to be updated soon<strong>';
        }
    }

    $output .= '</div>';
    
    return $output;
}

// AJAX Handler with Security */
add_action('wp_ajax_update_mosque_contentxx', 'handle_update_mosque_contentxx');
add_action('wp_ajax_nopriv_update_mosque_contentxx', 'handle_update_mosque_contentxx');

function handle_update_mosque_contentxx() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : '';

    // 1. Security Check
    if (!wp_verify_nonce($nonce, 'update_mosque_nonce_' . $post_id)) {
        wp_send_json_error('Security check failed.');
    }

    $item_id = get_post_meta($post_id, 'item_id', true);
    if (!$item_id) {
        wp_send_json_error('Data mapping error.');
    }

    // 2. Fetch required info for AI
    $name    = get_cct_mosque_data($item_id, 'name');
    $address = get_cct_mosque_data($item_id, 'address');

    if (!$name) {
        wp_send_json_error('Insufficient data to generate content.');
    }

    // 3. AI Generation
    $info    = "Name: $name, Address: $address";
    $content = perplexity_mosques($info);

    if (is_gemini_error($content) || empty($content)) {
        wp_send_json_error('The AI was unable to generate content at this time.');
    }

    // 4. Update Post
    $updated = wp_update_post([
        'ID'           => $post_id,
        'post_content' => wp_kses_post($content), // Sanitize HTML from AI
    ], true);

    if (is_wp_error($updated)) {
        wp_send_json_error($updated->get_error_message());
    }

    // 5. Update CCT Status
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'jet_cct_mosque',
        ['business_status' => 'Updated'],
        ['item_id' => $item_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success('Content updated.');
}


