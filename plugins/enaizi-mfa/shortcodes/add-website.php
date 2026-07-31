<?php

/**
 * 1. niz_add_website
 * 2. mfa_website_submit
 * 3. mfa_website_process
 * 4. mfa_insert_web_cct
 */


// ADD WEBSITE
add_shortcode('niz_add_website', 'niz_add_website_shortcode');
function niz_add_website_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="alert alert-warning">Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to add a website.</div>';
    }

    ob_start();
    ?>
    <div class="niz-add-website-form">
        <h3>Add New Website to Directory</h3>
        <form method="post" action="" id="nizAddWebsiteForm">
            <?php wp_nonce_field('niz_add_website_action', 'niz_add_website_nonce'); ?>
            <div class="form-group">
                <label for="website_url">Website URL:</label>
                <input type="url" name="website_url" id="website_url" class="form-control" required placeholder="https://example.com">
                <small class="form-text text-muted">Enter the full URL including https://</small>
            </div>
            
            <div id="nizFormActions">
                <button type="submit" name="submit_website" id="nizSubmitBtn" class="btn btn-primary">Submit Website</button>
            </div>
        </form>
        <div id="nizFormFeedback"></div>
    </div>

    <script type="text/javascript">
        document.getElementById('nizAddWebsiteForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            var urlInput = document.getElementById('website_url').value;
            var submitBtn = document.getElementById('nizSubmitBtn');
            var feedbackContainer = document.getElementById('nizFormFeedback');
            var nonce = document.getElementById('niz_add_website_nonce').value;
            
            submitBtn.disabled = true;
            submitBtn.innerText = '⏳ Generating content...';
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
            
            feedbackContainer.innerHTML = '<div class="alert alert-info">⏳ Please wait... Connecting and parsing live data for <strong>' + urlInput + '</strong>...</div>';
            
            var formData = new FormData();
            formData.append('action', 'niz_ajax_add_website');
            formData.append('website_url', urlInput);
            formData.append('niz_add_website_nonce', nonce);
            
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(res) {
                feedbackContainer.innerHTML = res.data.message;
                if(res.success) {
                    document.getElementById('nizAddWebsiteForm').reset();
                }
            })
            .catch(function(error) {
                feedbackContainer.innerHTML = '<div class="alert alert-error">❌ Connection timeout or configuration exception occurred.</div>';
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit Website';
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            });
        });
    </script>

    <style>
        .niz-add-website-form { max-width: 500px; margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 1px solid #eaeaea; }
        .niz-add-website-form .form-group { margin-bottom: 15px; }
        .niz-add-website-form label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .niz-add-website-form input[type="url"] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .niz-add-website-form .btn-primary { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .niz-add-website-form .btn-primary:hover { background: #005a87; }
        .alert { padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 14px; line-height: 1.5; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    </style>
    <?php
    return ob_get_clean();
}


// AJAX SUBMIT WEBSITE
add_action('wp_ajax_niz_ajax_add_website', 'mfa_website_submit');
function mfa_website_submit() {
    if (!isset($_POST['niz_add_website_nonce']) || !wp_verify_nonce($_POST['niz_add_website_nonce'], 'niz_add_website_action')) {
        wp_send_json_error(array('message' => '<div class="alert alert-error">Security token expired. Please refresh page.</div>'));
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => '<div class="alert alert-error">Session expired. Please log in again.</div>'));
    }

    $url = isset($_POST['website_url']) ? esc_url_raw(trim($_POST['website_url'])) : '';
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(array('message' => '<div class="alert alert-error">Invalid URL structure. Process aborted.</div>'));
    }

    $base_url = function_exists('cct_trim_url_to_base') ? cct_trim_url_to_base(rtrim($url, '/')) : rtrim($url, '/');

    // Run unified processing core
    $result = mfa_website_process($base_url, false);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => '<div class="alert alert-error">' . $result->get_error_message() . '</div>'));
    }

    wp_send_json_success(array('message' => $result));

}


// PROCESS WEBSITE
function mfa_website_process($base_url, $is_admin_mode = false, $existing_cct_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_cct_web';
    $current_user_id = get_current_user_id();

    // 1. Verify Existing Records
    if (empty($existing_cct_id)) {
        $existing_cct_id = $wpdb->get_var($wpdb->prepare("SELECT _ID FROM {$table_name} WHERE url = %s LIMIT 1", $base_url));
    }

    if ($existing_cct_id && !$is_admin_mode) {
        $post_id = $wpdb->get_var($wpdb->prepare("SELECT cct_single_post_id FROM {$table_name} WHERE _ID = %d", $existing_cct_id));
        $post_title = get_the_title( $post_id );
        $post_url = esc_url(get_permalink($post_id));
        
        if ($post_id) {
            return '<div class="alert alert-info"><b>This website is already in our directory!</b><br>' . $base_url . '<br><br>Click to View<br><a href="' . $post_url . '" target="_self">' . esc_html($post_title) . '</a></div>';
        }
    }
    

    if ($is_admin_mode) {
        return '<div class="alert alert-success">✓ <strong>Processed:</strong> ' . esc_html($base_url) . ' | 📝 <strong>Post ID:</strong> ' . intval($post_id) . '</div>';
    }
    
    // chk url 
    $name = mfa_get_remote_website_title( $base_url );
   
    $msg = '<div class="alert alert-success">';
    if ( str_starts_with( $name, 'Error:' ) ) {
        $msg .= '<b>' . $name . '</b><br>'; 
    }else{
        // ADD CCT AND POST 
        $result = mfa_insert_web_cct( $name, $base_url);
        $item_id = $result['item_id'];
        $post_id = $result['post_id'];
        $slug = 'https://staging.masjid4all.com/web/' . get_post_field( 'post_name', $post_id );
        
        $msg .= '✅ <b>Website added to the directory!</b><br><br>';
        $msg .= 'Please click the link to update the content<br>';
        //$msg .= 'URL : ' . $slug . '<br>';
        $msg .= '<a href="' . $slug . '" target="_blank"><strong>' . $name . '</strong></a>';
        $msg .= '<br>';
     }
    $msg .= '</div>';

    return $msg;
}

// ADD CCT WEB AND POST WEB
function mfa_insert_web_cct( $name, $url ) {
    global $wpdb;
    
    $table_name   = $wpdb->prefix . 'jet_cct_web';
    $current_user = get_current_user_id();
    $clean_name   = sanitize_text_field( $name );
    
    // ==========================================
    // 1. INSERT CCT RECORD
    // ==========================================
    $cct_data = array(
        'name'          => $clean_name,
        'url'           => esc_url_raw( $url ),
        'cct_author_id' => $current_user,
        'cct_created'   => current_time( 'mysql' ),
        'cct_status'    => 'publish'
    );

    $inserted = $wpdb->insert( $table_name, $cct_data, array( '%s', '%s', '%d', '%s', '%s' ) );

    if ( ! $inserted ) {
        return new WP_Error( 'cct_insert_error', $wpdb->last_error );
    }

    $item_id = $wpdb->insert_id; // Get the new CCT _ID

    // ==========================================
    // 2. CREATE THE WORDPRESS POST
    // ==========================================
    $post_data = array(
        'post_title'  => $clean_name,
        'post_type'   => 'web',
        'post_status' => 'publish',
        'post_author' => $current_user,
    );

    // The 'true' parameter tells WP to return a WP_Error object if it fails
    $post_id = wp_insert_post( $post_data, true ); 

    if ( is_wp_error( $post_id ) ) {
        // Optional: If the post fails, delete the CCT record so you don't get ghost data
        $wpdb->delete( $table_name, array( '_ID' => $item_id ), array( '%d' ) );
        return $post_id; 
    }

    // ==========================================
    // 3. LINK THEM TOGETHER
    // ==========================================
    
    // A. Save the CCT item_id to the WP Post Meta
    update_post_meta( $post_id, 'item_id', $item_id );
    update_post_meta( $post_id, 'url', $url );
    
    // B. Save the WP post_id back to the CCT table
    $wpdb->update(
        $table_name,
        array( 'cct_single_post_id' => $post_id ), // What to update
        array( '_ID' => $item_id ),                // WHERE clause
        array( '%d' ),                             // Format of data (%d = integer)
        array( '%d' )                              // Format of WHERE (%d = integer)
    );

    // Return an array with both IDs so you can use them immediately
    return array(
        'item_id' => $item_id,
        'post_id' => $post_id
    );
}

function mfa_get_remote_website_title( $url ) {

    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return 'Error: Invalid URL format.';
    }

    $response = wp_remote_get( $url, array(
        'timeout'     => 15,
        'redirection' => 5,
        'headers'     => array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
            'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        )
    ));

    if ( is_wp_error( $response ) ) {
        return 'Error: ' . $response->get_error_message();
    }

    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 400 ) {
    
        // Fallback: create name from domain
        $domain = parse_url($url, PHP_URL_HOST);
    
        $domain = preg_replace('/^www\./', '', $domain);
    
        $fallback_name = str_replace(
            array('-', '_', '.'),
            ' ',
            $domain
        );
    
        return ucwords($fallback_name);
    }

    $body = wp_remote_retrieve_body( $response );


    // 1. Try OpenGraph site name
    if ( preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)/i', $body, $matches) ) {
        return trim(html_entity_decode($matches[1]));
    }


    // 2. Try application-name
    if ( preg_match('/<meta[^>]+name=["\']application-name["\'][^>]+content=["\']([^"\']+)/i', $body, $matches) ) {
        return trim(html_entity_decode($matches[1]));
    }


    // 3. Try first H1
    if ( preg_match('/<h1[^>]*>(.*?)<\/h1>/ims', $body, $matches) ) {

        $h1 = trim(
            html_entity_decode(
                strip_tags($matches[1])
            )
        );

        if ( strlen($h1) > 3 ) {
            return $h1;
        }
    }


    // 4. Fallback to title
    if ( preg_match('/<title[^>]*>(.*?)<\/title>/ims', $body, $matches) ) {

        $title = trim(
            html_entity_decode(
                strip_tags($matches[1])
            )
        );

        // Remove common SEO suffixes
        $title = preg_replace(
            '/\s*[\|\-–—]\s*(welcome|official website|home|homepage).*$/i',
            '',
            $title
        );

        return trim($title);
    }


    return '';
}

// =========================================================================
// 2. BACKEND ADMIN QUEUE PROCESSOR [niz_user_web_process]
// =========================================================================

add_shortcode( 'niz_user_web_process', 'niz_user_web_process_shortcode' );
function niz_user_web_process_shortcode() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p style="color:red; font-weight:bold;">Permission denied.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_cct_web';

    // Pull exactly one unlinked record flagged as new traffic
    $record = $wpdb->get_row( "
        SELECT _ID, url FROM {$table_name} 
        WHERE url IS NOT NULL AND url != '' 
          AND (status IS NULL OR status = '' OR status = 'New')
        LIMIT 1
    " );

    if ( ! $record ) {
        return '<p style="color:orange; font-size: 18px; text-align: center; padding: 20px; font-weight:bold;">✅ All done! No pending records to process.</p>';
    }

    $base_url = function_exists('cct_trim_url_to_base') ? cct_trim_url_to_base($record->url) : $record->url;

    $result = mfa_website_process($base_url, true, $record->_ID);

    if (is_wp_error($result)) {
        $wpdb->update($table_name, ['status' => 'Failed', 'status_detail' => $result->get_error_message()], ['_ID' => $record->_ID]);
        $output_msg = '<div class="alert alert-error">❌ Error processing ' . esc_html($base_url) . ': ' . esc_html($result->get_error_message()) . '</div>';
    } else {
        $output_msg = $result;
    }

    $remaining_count = $wpdb->get_var( "
        SELECT COUNT(*) FROM {$table_name} 
        WHERE url IS NOT NULL AND url != '' 
          AND (status IS NULL OR status = '' OR status = 'New')
    " );

    $output = '<div style="font-family: monospace; font-size: 14px;">' . $output_msg;
    $output .= '<p style="margin: 10px 0; font-weight:bold; color:#333;">📊 Remaining to process: ' . intval( $remaining_count ) . '</p>';
    $output .= '<p style="color: #155724;">⏳ <strong>Auto-refreshing queue in 1 second...</strong> <span id="countdown">1</span></p>';
    $output .= '</div>';
    
    $output .= '<meta http-equiv="refresh" content="1">';
    $output .= '<script>
        let seconds = 1;
        const interval = setInterval(function() {
            seconds--;
            if(document.getElementById("countdown")) { document.getElementById("countdown").textContent = seconds; }
            if (seconds <= 0) { clearInterval(interval); }
        }, 1000);
    </script>';
    
    return $output;
}


// =========================================================================
// 4. CORE UTILITY HELPER METHODS
// =========================================================================

function niz_create_wordpress_post($record_id, $base_url, $update_fields, $keywords) {
    $post_title = !empty($update_fields['name']) ? $update_fields['name'] : 'Website ' . $base_url;
    
    $post_data = [
        'post_title'   => wp_strip_all_tags($post_title),
        'post_content' => $update_fields['content'],
        'post_excerpt' => wp_strip_all_tags($update_fields['introduction']),
        'post_status'  => 'publish',
        'post_type'    => 'web',
        'post_author'  => get_current_user_id(),
    ];
    
    $new_post_id = wp_insert_post($post_data, true);
    if (is_wp_error($new_post_id)) {
        return $new_post_id;
    }
    
    update_post_meta($new_post_id, 'item_id', $record_id);
    update_post_meta($new_post_id, 'url', $base_url);
    update_post_meta($new_post_id, 'rank_math_focus_keyword', $keywords);
    update_post_meta($new_post_id, '_original_url', $base_url);
    update_post_meta($new_post_id, 'website_category', $update_fields['category']);
    update_post_meta($new_post_id, 'website_country', $update_fields['country']);
    update_post_meta($new_post_id, 'website_whatsapp', $update_fields['whatsapp']);
    update_post_meta($new_post_id, 'website_email', $update_fields['email']);
    update_post_meta($new_post_id, 'website_status', $update_fields['listing_status']);
    //update_post_meta($new_post_id, 'website_status_detail', $update_fields['status_detail']);
    
    return $new_post_id;
}

function cct_trim_url_to_base( $url ) {
    $parsed = parse_url( $url );
    if ( ! $parsed || ! isset( $parsed['host'] ) ) {
        return $url;
    }
    $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
    return $scheme . '://' . $parsed['host'];
}



// =========================================================================
// 5. PERPLEXITY API INTEGRATION
// =========================================================================

/*

function niz_perplexity_web($url) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : get_option('perplexity_api_key', '');
    if (empty($api_key)) {
        return wp_json_encode(['error' => 'Perplexity API key is missing.']);
    }

    // FIXED: Clean URL without markdown formatting
    //$api_url = '[https://api.perplexity.ai/chat/completions](https://api.perplexity.ai/chat/completions)';
    $api_url = 'https://api.perplexity.ai/chat/completions';
    
    $categories = ['Islamic Resources', 'Islamic Bodies', 'Government', 'Education', 'Finance', 'Business & Services', 'Charity & Zakat', 'Health & Wellness', 'Family & Lifestyle', 'Community & Social', 'News & Media', 'Technology & Tools', 'E-Commerce & Shopping', 'Travel & Directory', 'Others'];
    $categories_list = implode(', ', $categories);

    // --- Build the prompt for Perplexity ---
    $prompt = <<<PROMPT
You are an expert website analyst for Islamic content. Analyze the website at this URL: {$url}
Return **only valid JSON** (no extra text, no markdown) exactly in the schema below.
Extract ONLY what is explicitly stated. Do not add information not present. Do not guess or hallucinate.

IMPORTANT: Do not invent information. If the website is about e-vouchers for donations, say that. Do not call it a productivity app unless that's explicitly stated.
IMPORTANT: All content MUST be in English.

Schema:
{
  "Name": "Official name of the organization/website",
  "Introduction": "One short paragraph (max 3 sentences) summarizing the site's purpose and audience.",
  "Category": "One of these: {$categories_list}",
  "keywords": "Comma-separated list of 5-10 SEO keywords relevant to the site. The first keyword MUST be the name.",
  "whatsapp": "WhatsApp number if found, else empty string",
  "email": "Email address if found, else empty string",
  "country": "the country",
  "status": "Approved or Pending or Rejected",
  "status_detail": "Brief reason for the status (e.g., Shariah-compliant, contains music, unclear Islamic rulings)",
  "Content": "Full HTML content for a webpage article. Must be SEO-friendly and informative. Use one tag with the Name as the main heading, followed by Table of Contents. Then create relevant sections based on the website category, such as 'Introduction', 'About the Website', 'Services & Resources', 'Benefits for Muslims', 'Key Features & Highlights', 'Target Audience', 'Country & Language', and 'Getting Started'. The Introduction should be written in paragraph form, while other sections may use bullet points where appropriate. Write at least 600 words.

Write in a neutral directory-style tone that presents factual information about the website. Describe the website directly and confidently without using first-person language ('we', 'our', 'us') and without review-style language such as 'appears to', 'seems to', 'likely', 'may offer', or 'users can find'. Avoid opinions, ratings, recommendations, assessments, or judgments. Focus on providing clear information about the website's purpose, content, services, features, audience, and relevance to Muslims. Use natural keyword placement throughout the article for SEO purposes. Do NOT include the JSON schema in the content."
}

Instructions:
- Browse the website thoroughly (homepage, about, contact, services, etc.).
- For "status": 
    - "Approved" -> default status for standard Islamic websites. Fully aligns with Islamic principles, no obvious violations.
    - "Pending" -> contains controversial, sectarian, or highly ambiguous content requiring a human scholar's expert review. Do NOT use this status just for general caution.
    - "Rejected" -> contains clearly un-Islamic content (e.g., interest-based riba, music, gambling, inappropriate images).
- For "status_detail": Explain your reasoning.
- For "whatsapp" and "email": Only if explicitly published on the site. Do not guess.
- The "Content" field should be a standalone article. Use proper HTML markup. The H1 must be the same as "Name". Use H2 for sections, H3 for subsections if needed. Include clickable table of content after the name, and FAQ as the last section. For sections (other than Introduction), use bullet points. Write in a neutral, informative tone suitable for a Muslim audience.
- For "keywords" field should Comma-separated list of 5-10 SEO keywords relevant to the site.


Now analyze {$url} and output ONLY the JSON object.
PROMPT;

    $request_body = [
        'model' => 'sonar',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise data extraction agent. You output raw JSON only. Never include conversational introductory text or markdown fences.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($request_body),
        'timeout' => 60
    ]); 

    if (is_wp_error($response)) {
        return wp_json_encode(['error' => $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    $raw_content = $data['choices'][0]['message']['content'] ?? '';

    // Better regex to strip out backtick wrapping just in case the model ignored instructions
    $clean_json = preg_replace('/^`{3}(?:json)?\s*|\s*`{3}$/i', '', trim($raw_content));
    
    return $clean_json;
}

*/

// =========================================================================
// 6. DEEPSEEK API INTEGRATION
// =========================================================================

/*

function niz_user_deepseek_web($url) {
    $api_key = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : get_option('deepseek_api_key', '');
    if (empty($api_key)) {
        return wp_json_encode(['error' => 'DeepSeek API key is missing.']);
    }

    $api_url = 'https://api.deepseek.com/v1/chat/completions';

    $categories = ['Prayer & Quran Tools', 'Government & Official Islamic Bodies', 'Islamic Finance & Wealth Planning', 'Charity & Zakat Platforms', 'Islamic Learning & Courses', 'Marriage & Family', 'Mental Health & Counselling', 'News & Community Updates', 'Productivity & Islamic Reminders', 'New Muslim Support'];
    $categories_list = implode(', ', $categories);

    // --- Build the prompt for Perplexity ---
    $prompt = <<<PROMPT
        You are an expert website analyst for Islamic content. Analyze the website at this URL: {$url}
        Return **only valid JSON** (no extra text, no markdown) exactly in the schema below.
        Extract ONLY what is explicitly stated. Do not add information not present. Do not guess or hallucinate.
        
        IMPORTANT: Do not invent information. If the website is about e-vouchers for donations, say that. Do not call it a productivity app unless that's explicitly stated.
        IMPORTANT: All content MUST be in English.
        
        Schema:
        {
          "Name": "Official name of the organization/website",
          "Introduction": "One short paragraph (max 3 sentences) summarizing the site's purpose and audience.",
          "Category": "One of these: {$categories_list}",
          "keywords": "Comma-separated list of 5-10 SEO keywords relevant to the site. The first keyword MUST be the name.",
          "whatsapp": "WhatsApp number if found, else empty string",
          "email": "Email address if found, else empty string",
          "country": "the country",
          "status": "Approved or Pending or Rejected",
          "status_detail": "Brief reason for the status (e.g., Shariah-compliant, contains music, unclear Islamic rulings)",
          "Content": "Full HTML content for a webpage article. Must be SEO-friendly and informative. Use one tag with the Name as the main heading, followed by Table of Contents. Then create relevant sections based on the website category, such as 'Introduction', 'About the Website', 'Services & Resources', 'Benefits for Muslims', 'Key Features & Highlights', 'Target Audience', 'Country & Language', and 'Getting Started'. The Introduction should be written in paragraph form, while other sections may use bullet points where appropriate. Write at least 600 words.
        
        Write in a neutral directory-style tone that presents factual information about the website. Describe the website directly and confidently without using first-person language ('we', 'our', 'us') and without review-style language such as 'appears to', 'seems to', 'likely', 'may offer', or 'users can find'. Avoid opinions, ratings, recommendations, assessments, or judgments. Focus on providing clear information about the website's purpose, content, services, features, audience, and relevance to Muslims. Use natural keyword placement throughout the article for SEO purposes. Do NOT include the JSON schema in the content."
        }
        
        Instructions:
        - Browse the website thoroughly (homepage, about, contact, services, etc.).
        - For "status": 
            - "Approved" -> default status for standard Islamic websites. Fully aligns with Islamic principles, no obvious violations.
            - "Pending" -> contains controversial, sectarian, or highly ambiguous content requiring a human scholar's expert review. Do NOT use this status just for general caution.
            - "Rejected" -> contains clearly un-Islamic content (e.g., interest-based riba, music, gambling, inappropriate images).
        - For "status_detail": Explain your reasoning.
        - For "whatsapp" and "email": Only if explicitly published on the site. Do not guess.
        - The "Content" field should be a standalone article. Use proper HTML markup. The H1 must be the same as "Name". Use H2 for sections, H3 for subsections if needed. Include clickable table of content after the name, and FAQ as the last section. For sections (other than Introduction), use bullet points. Write in a neutral, informative tone suitable for a Muslim audience.
        - For "keywords" field should Comma-separated list of 5-10 SEO keywords relevant to the site.
        
        
        Now analyze {$url} and output ONLY the JSON object.
PROMPT;

    $request_body = [
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a structured data extraction assistant. Always output valid JSON only.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2,
        'response_format' => ['type' => 'json_object']
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($request_body),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        return wp_json_encode(['error' => $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $assistant_message = $data['choices'][0]['message']['content'] ?? '';
    
    return $assistant_message;
}

*/

