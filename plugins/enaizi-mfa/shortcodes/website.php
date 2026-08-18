<?php

// Prevent direct access
if (!defined('ABSPATH')) exit; 

/**
 * 1. mfa_website_header
 * 2. mfa_website_status
 * 3. mfa_website_info
 * 4. mfa_website_update
 * 5. mfa_website_perplexity
 * 
 * 
 */
 

 
// WEBSITE HEADER 
add_shortcode('mfa_website_header', function($atts) {
    // 1. Define the fallback default image
    $default_url = "https://cdn.masjid4all.com/web/website-owner.webp";
    
    // 2. Check if the current post/page has a featured image
    if ( has_post_thumbnail() ) {
        // Grab the 'full' size URL of the featured image
        $img_url = get_the_post_thumbnail_url(null, 'full');
    } else {
        // Otherwise, use the default CDN image
        $img_url = $default_url;
    }

    // 3. Output the image tag
    return '<img src="' . esc_url($img_url) . '" class="niz-mfa-header-img" style="width: 100%; height: auto; object-fit: cover;">';
});


// WEBSITE STATUS
add_shortcode( 'mfa_website_status', 'mfa_website_status_shortcode' );
function mfa_website_status_shortcode() {
    global $wpdb;
    
    $post_id = get_the_ID();
    $item_id = get_post_meta( $post_id, 'item_id', true );
    $ret = '';
    
    if ( empty( $item_id ) ) {
        return $ret;
    }

    // 1. Determine if the current user is authorized to see this
    $is_authorized = false;

    if ( is_user_logged_in() ) {
        $current_user_id = get_current_user_id();
        
        // Check if Admin or Editor
        if ( current_user_can('editor') || current_user_can('administrator') ) {
            $is_authorized = true;
        } else {
            // Check if user is the listing owner in the CCT table
            $owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
            $is_owner = $wpdb->get_var( $wpdb->prepare( 
                "SELECT user_id FROM {$owner_table} WHERE post_id = %d AND user_id = %d LIMIT 1", 
                $post_id, 
                $current_user_id 
            ) );
            
            if ( $is_owner ) {
                $is_authorized = true;
            }
        }
    }

    // 2. If not authorized, hide the message completely
    if ( ! $is_authorized ) {
        echo '<style>
        .owner {
            display: none !important;
        }
        </style>';
        return '';
    }

    // 3. Fetch status and display if not Approved
    $web_table = $wpdb->prefix . 'jet_cct_web';
    $result = $wpdb->get_row( $wpdb->prepare( "SELECT listing_status FROM {$web_table} WHERE _ID = %d", $item_id ) );
    
    if ( $result && $result->listing_status !== 'Approved' ) {
        // 4. Added the 'owner' class to the div wrapper
        //$ret = '<div class="owner" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; font-size:14px; line-height:1.5;">';
        //$ret .= '<strong>Status:</strong> ' . esc_html( $result->status ) . '<br>' . esc_html($result->status_detail) . '<br><br>';
        //$ret .= '<b>Our team will manually review and assess the website for Islamic compliance and update the status soon.</b>';
        //$ret .= '</div>';
    }
    
    return $ret;
}

// WEBSITE INFO
add_shortcode('mfa_website_info', function() {
    
    global $wpdb;
    
    $post_id = get_the_ID();
    $item_id = get_post_meta( $post_id, 'item_id', true );
    $ret = 'xxxx';
    
    if ( empty( $item_id ) ) {
        return $ret;
    }

    // 1. Determine if the current user is authorized to see this
    $is_authorized = false;

    if ( is_user_logged_in() ) {
        $current_user_id = get_current_user_id();
        
        // Check if Admin or Editor
        if ( current_user_can('editor') || current_user_can('administrator') ) {
            $is_authorized = true;
        } else {
           // Check if user is the listing owner in the CCT table
            $owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
            $is_owner = $wpdb->get_var( $wpdb->prepare( 
                "SELECT user_id FROM {$owner_table} WHERE post_id = %d AND user_id = %d LIMIT 1", 
                $post_id, 
                $current_user_id 
            ) );
            
            if ( $is_owner ) {
                $is_authorized = true;
            }
        }
    }

    // 2. If not authorized, hide the message completely
    if ( ! $is_authorized ) {
        echo '<style>
        .owner {
            display: none !important;
        }
        </style>';
        
    }
    
    $post_id = get_the_ID();
    $item_id = get_post_meta($post_id, 'item_id', true);
    $content = get_the_content();
    $name    = mfa_get_web_field($item_id, 'name');
    $url     = mfa_get_web_field($item_id, 'url');
    $status  = mfa_get_web_field($item_id, 'listing_status');
    $address = mfa_get_web_field($item_id, 'address');
    $city    = mfa_get_web_field($item_id, 'city');
    $country = mfa_get_web_field($item_id, 'country');
    $intro   = mfa_get_web_field($item_id, 'introduction');
    
    if ($status == 'Error') {
    
        $content  = '<h1>' . $name . '</h1>';
        $content .= '<h3>Status : <b>Error</b></h3>';
        $content .= '<p>We were unable to generate content for this website listing.</p>';
        $content .= '<p>The website may be temporarily unavailable, blocking automated access, or does not provide sufficient information.</p>';
    
        //if ($is_authorized) {
        //    $content .= do_shortcode('[mfa_website_update]');
        //}
    
    }elseif ($status == 'Rejected'){
        $content  = '<h1>' . $name . '</h1>';
        $content .= '<h3>Status : <b>Rejected</b></h3>';
        $content .= '<p>After review, we are unable to approve this website listing for inclusion in our directory at this time. Listings may be rejected if the information provided is incomplete, inaccurate, does not meet our directory guidelines, or cannot be sufficiently verified.</p>';
        $content .= '<p>If you are the website owner and believe your website should be included in our directory, please contact us with any additional information or supporting documents. We will be happy to review your submission again.</p>';
    }elseif ($status == 'Pending'){
        $content  = '<h1>' . $name . '</h1>';
        $content .= '<p>' . $address . '</p>';
        $content .= '<h3>Status : <b>Pending</b></h3>';
        $content .= '<p>This website is currently under review by our team to ensure that the information provided is accurate, complete, and meets our directory guidelines.</p>';
        $content .= '<p>The review process may take some time, depending on the volume of submissions. Once the review is complete, your listing will either be approved and published or we may contact you if additional information is required.</p>';
        $content .= '<p>If you are the website owner and would like to provide supporting documents or update your submission, please contact us. We appreciate your patience and look forward to reviewing your listing.</p>';
        
    } elseif ( $status === 'New' || empty( $status ) ) {
        $content  = '<h1>' . $name . '</h1>';
        if (!empty($address)){
            $content .= $address . '<br>' ;
        }
        if (!empty($city)){
            $content .= 'City : ' . $city . '<br>';
        }
        if (!empty($country)){
            $content .= 'Country : ' . $country . '<br>';
        }
        $content .= '<h3>Status : <b>New</b></h3>';
        $content .= '<p>If you are the owner, please Claim Your Website after updating </p>';

    }else{
        // If Approved
        //$biz_status  = mfa_get_web_field($item_id, 'status');
        //if ($biz_status==''){
            //$content = 'Updating..<br><br>' . $content;
            //business_update_content( $post_id );
            
        //}
    }
    
    return $content;
});


// WEBSITE UPDATE
add_shortcode('mfa_website_update', 'mfa_website_update_shortcode');

function mfa_website_update_shortcode() {

    $post_id = get_the_ID();

    if (get_post_type($post_id) !== 'web') {
        return '';
    }

    $nonce = wp_create_nonce('niz_web_update_nonce_' . $post_id);

    ob_start();

    ?>

    <div class="niz-web-update-wrapper">

        <button 
            type="button"
            class="niz-web-update-btn"
            data-post-id="<?php echo esc_attr($post_id); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-ajaxurl="<?php echo admin_url('admin-ajax.php'); ?>">
            
            🔄 Update Content

        </button>

        <div class="niz-web-update-msg"></div>

    </div>

    <?php

    return ob_get_clean();
}


// AI AJAX HANDLER
add_action('wp_ajax_mfa_website_ai_update', 'mfa_website_ai_update_handler');
add_action('wp_ajax_nopriv_mfa_website_ai_update', 'mfa_website_ai_update_handler'); // Allows non-logged-in users

function mfa_website_ai_update_handler() {
    // Check missing variables
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    // Security check to prevent unauthorized spam clicks
    if ( ! wp_verify_nonce($nonce, 'niz_web_update_nonce_' . $post_id) ) {
        wp_send_json_error('Security validation failed.');
    }

    if ( empty($post_id) ) {
        wp_send_json_error('Invalid Post ID.');
    }

    // FIRE THE FUNCTION!
    $result = website_update_content($post_id);

    // Read the response from your AI function and return it to the JavaScript
    if ( is_wp_error($result) ) {
        wp_send_json_error( $result->get_error_message() );
    } else {
        wp_send_json_success( array('message' => $result['message']) );
    }
}



// WEBSITE UPDATE CONTENT 
function website_update_content( $post_id ) {
    global $wpdb;

    error_log('AJAX REQUEST: ' . uniqid());
    error_log('website_update_content() called for Post ' . $post_id);
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
        if (!empty($trace['function'])) {
            error_log('ENAIZI ' . $trace['function']);
        }
    }

    // 1. Validate Post & Get CCT Item ID
    if ( empty( $post_id ) || get_post_type( $post_id ) !== 'web' ) {
        return new WP_Error( 'invalid_post', 'Invalid Post ID or Post Type.' );
    }

    $item_id = get_post_meta( $post_id, 'item_id', true );
    
    if ( empty( $item_id ) ) {
        return new WP_Error( 'missing_cct', 'No linked JetEngine CCT item found for this post.' );
    }

    // 2. Fetch Existing Website Data from CCT Table
    $table_name = $wpdb->prefix . 'jet_cct_web';
    $data   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE _ID = %d", $item_id ), ARRAY_A );

    if ( ! $data ) {
        return new WP_Error( 'cct_not_found', 'Business record not found in the database.' );
    }

    // Decode opening hours if they are stored as JSON strings
    $opening_hours = !empty($data['opening_hours']) ? json_decode($data['opening_hours'], true) : [];

    // 3. Prepare Payload for Perplexity AI
    $raw_info = array(
        'name'         => $data['name'],
        'address'      => $data['address'],
        'email'        => $data['email'],
        'url'          => $data['url']
    );
   
    $old_status = $data['listing_status'];
    $name = $data['name'];
    // Strip out empty values to prevent AI crawler crashes
    $clean_info = array_filter($raw_info, function($value) {
        return $value !== '' && $value !== null && $value !== [];
    });

    // 4. Call the AI Function
    if ( ! function_exists( 'mfa_website_perplexity' ) ) {
        return new WP_Error( 'missing_function', 'mfa_web_perplexity function is missing.' );
    }

    $url = $data['url'];
    
    $schema_data = mfa_extract_schema_data($url);


    $json_raw = mfa_website_perplexity(
        $url,
        $schema_data
    );

    //$json_raw = mfa_website_perplexity( $url ); 
    
error_log('AI RAW RESPONSE START');
error_log($json_raw);
error_log('AI RAW RESPONSE END');

    $ai_data = json_decode($json_raw, true);
error_log('AI JSON DATA');
error_log(print_r($ai_data, true));



    // 5. Validate AI Response (Added json_last_error_msg to see exactly why it failed)
    if (!$ai_data) {
        return new WP_Error('ai_failure', 'AI returned an empty response.');
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('ai_failure', json_last_error_msg());
    }
    
    if (isset($ai_data['error'])) {
        return new WP_Error('ai_failure', $ai_data['error']);
    }
    
    if (empty(trim($ai_data['content']))) {

        $cct_update_data = array(
            'status' => 'Updated',
            'listing_status' => 'Error',
            'status_detail' => 'AI could not extract website content.'
        );
    
        $result = $wpdb->update(
            $table_name,
            $cct_update_data,
            array('_ID' => $item_id)
        );
    
        error_log('AI CONTENT EMPTY - CCT UPDATED: ' . print_r($cct_update_data, true));
        error_log('UPDATE RESULT: ' . $result);
    
        return array(
            'success' => true,
            'message' => $name . ' could not generate content. Listing marked as Error.',
            'status' => 'Error'
        );
    }
    
    

    // 6. Update CCT Table Data

    // Default status if AI does not return one
    $listing_status = !empty($ai_data['status']) 
        ? sanitize_text_field($ai_data['status']) 
        : 'Approved';
    
    $cct_update_data = array(
        'status' => 'Updated',
        'listing_status' => $listing_status
    );

    // $listing_status = $ai_data['status'];

    if ( ! empty( $ai_data['status'] ) ) {
        $cct_update_data['listing_status'] = sanitize_text_field( $ai_data['status'] );
    }
    if ( ! empty( $ai_data['whatsapp'] ) ) {
        $cct_update_data['whatsapp'] = sanitize_text_field( $ai_data['whatsapp'] );
    }
    if ( ! empty( $ai_data['phone'] ) ) {
        $cct_update_data['phone'] = sanitize_text_field( $ai_data['phone'] );
    }
    if ( ! empty( $ai_data['address'] ) ) {
        $cct_update_data['address'] = sanitize_text_field( $ai_data['address'] );
    }
    if ( ! empty( $ai_data['city'] ) ) {
        $cct_update_data['city'] = sanitize_text_field( $ai_data['city'] );
    }
    if ( ! empty( $ai_data['email'] ) ) {
        $cct_update_data['email'] = $ai_data['email'] ;
    }
    if ( ! empty( $ai_data['facebook'] ) ) {
        $cct_update_data['fb'] = $ai_data['facebook'] ;
    }
    
    if ( ! empty( $ai_data['country'] ) ) {
        $cct_update_data['country'] = sanitize_text_field( $ai_data['country'] );
    }
    if ( ! empty( $ai_data['category'] ) ) {
        $cct_update_data['category'] = sanitize_text_field( $ai_data['category'] );
    }
    if ( ! empty( $ai_data['introduction'] ) ) {
        $cct_update_data['introduction'] = sanitize_text_field( $ai_data['introduction'] );
    }

error_log('ITEM ID: ' . $item_id);

$current_record = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT _ID, status, listing_status FROM {$table_name} WHERE _ID=%d",
        $item_id
    ),
    ARRAY_A
);

error_log('CURRENT CCT RECORD: ' . print_r($current_record,true));


    $result = $wpdb->update(
        $table_name,
        $cct_update_data,
        array( '_ID' => $item_id )
    );
    
    error_log('CCT UPDATE RESULT: ' . var_export($result, true));
    error_log('CCT UPDATE DATA: ' . print_r($cct_update_data, true));
    
    if ($result === false) {
        error_log('DB ERROR: ' . $wpdb->last_error);
    }

    // 7. Update WordPress Post Content & SEO Meta
    $post_update = array(
        'ID'           => $post_id,
        'post_content' => wp_kses_post( $ai_data['content'] )
    );
    wp_update_post( $post_update );

    if ( ! empty( $ai_data['title'] ) ) {
        update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $ai_data['title'] ) );
    }
    if ( ! empty( $ai_data['meta_description'] ) ) {
        update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $ai_data['meta_description'] ) );
    }
    if ( ! empty( $ai_data['keywords'] ) ) {
        update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $ai_data['keywords'] ) );
    }
    
    // Barakah Point, if Approved
    if ($old_status === 'New' || $old_status === 'Pending' || empty($old_status)){
        if ($listing_status == 'Approved'){
            // Give Baraqah Point
            $author_id = get_post_field('post_author', $post_id);
            $desc = 'Add Website - ' . $name;
            niz_user_add_points($author_id, $desc, 10);
        }
    }
    

    // 8. Return Success
    return array(
        'success' => true,
        'message' => $name . ' successfully updated.',
        'status'  => isset($cct_update_data['listing_status']) ? $cct_update_data['listing_status'] : 'Pending'
    );
   
    
}


// WEB PERPLEXITY
function mfa_website_perplexity($url,$schema_data=[]) {
    $api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : get_option('perplexity_api_key', '');
    if (empty($api_key)) {
        return wp_json_encode(['error' => 'Perplexity API key is missing.']);
    }

    $api_url = 'https://api.perplexity.ai/chat/completions';
    
    $categories = ['Islamic Resources', 'Islamic Bodies', 'Government', 'Education', 'Finance', 'Business & Services', 'Charity & Zakat', 'Health & Wellness', 'Family & Lifestyle', 'Community & Social', 'News & Media', 'Technology & Tools', 'E-Commerce & Shopping', 'Travel & Directory', 'Others'];
    $categories_list = implode(', ', $categories);

    // --- Build the prompt for Perplexity ---
    $schema_text = json_encode( $schema_data, JSON_PRETTY_PRINT );
$prompt = <<<PROMPT
You are an expert SEO analyst and Islamic website directory editor.

Analyze the website at:

{$url}


Additional structured data extracted from the website:


{$schema_text}

Use this information as a verified source.

Return ONLY valid JSON.
Do NOT use markdown.
Do NOT wrap the JSON inside code fences.
Do NOT include explanations.

Everything must be written in fluent English.

Extract ONLY information explicitly available on the website.
Never invent or assume facts.
If information is missing, return an empty string.

Schema

{
  "name":"",
  "url":"",
  "slug":"",
  "seo_title":"",
  "meta_description":"",
  "excerpt":"",
  "focus_keyword":"",
  "keywords":"",
  "og_title":"",
  "og_description":"",
  "canonical_url":"",
  "schema_type":"WebSite",

  "address":"",
  "city":"",
  "country":"",
  "phone":"",
  "whatsapp":"",
  "email":"",
  "facebook":"",

  "introduction":"",
  "category":"",

  "status":"",
  
  "status_detail":"",

  "content":""
}

Field requirements

name
Official website or organization name.

url
Website URL.

slug
SEO-friendly slug.
Lowercase only.
Use hyphens.
Maximum 60 characters.

seo_title

Maximum 60 characters.

Should contain:
- Website name
- Primary keyword
- Country if relevant

Examples

Masjid Pro Malaysia | Mosque Management Platform

Islamic Finder | Prayer Times & Qibla Direction

meta_description

Maximum 155 characters.

Must include:
- focus keyword
- website purpose
- natural call-to-action

excerpt

40-60 words.

This should summarize the website and can be used as a WordPress excerpt.

focus_keyword

Exactly ONE keyword.

Prefer the official website name.

keywords

Comma-separated.

5-10 keywords.

Rules

- First keyword MUST be the website name.
- Include category keyword.
- Include country if relevant.
- No duplicate keywords.

og_title

Normally same as SEO title.

og_description

Maximum 160 characters.

canonical_url

Use website URL.

introduction

Maximum 3 sentences.

Summarize:
- what it is
- who it serves
- primary purpose

category

Must be exactly one of:

{$categories_list}

status
Always return one value only:
- Approved
- Pending
- Rejected

If no prohibited content is found, always return:
- Approved


Website clearly aligns with Islamic values.

Pending

Only if Islamic position or teachings require expert scholarly review.

Rejected

Only if the website clearly promotes prohibited content such as:

- gambling
- pornography
- riba
- alcohol
- dating
- music streaming
- explicit content
- non-halal financial services

status_detail

One short factual sentence.

Examples

"Islamic educational website."

"Interest-based financial services."

"Requires scholarly review."

Content
- Produce clean, well-formatted HTML.
- Put every HTML block element on its own line.
- Insert a blank line between major block elements.
- Never place an <h2>, <h3>, <ul>, <ol>, or <table> immediately after a paragraph on the same line.
- Always close every HTML tag properly.
- Do not nest block elements inside <p> tags.
- Do not use inline CSS.
- Use semantic HTML only.

At at end of content. Please do not change the content. 
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

Generate a complete SEO article.

Length

800-1500 words.

Use clean HTML only.

Structure

<h1>Website Name</h1>

Display link to the website
Visit Website : (Display the url and open in new tab)

Table of Contents
(with anchor links)

<h2>Introduction</h2>

Write in paragraph form.

<h2>About the Website</h2>

<h2>Products, Services & Resources</h2>

<h2>Key Features</h2>

<h2>Benefits for Muslims</h2>

<h2>Target Audience</h2>

<h2>Country & Languages</h2>

<h2>How to Get Started</h2>

<h2>Frequently Asked Questions</h2>


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

SEO requirements

The focus keyword must appear naturally in:

- SEO title
- H1
- first paragraph
- one H2 heading
- meta description
- excerpt
- conclusion

Use related keywords naturally throughout the article.

Avoid keyword stuffing.

Write in a neutral directory style.

Do NOT:

- use "we", "our", "us"
- write reviews
- give ratings
- speculate
- exaggerate
- recommend unless explicitly stated
- invent services

Use factual language only.

Browse the website thoroughly, including:

- Home
- About
- Contact
- FAQ
- Services
- Products
- Blog
- Footer

Extract only verified information.

Finally, output ONLY the JSON object.
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

    // TEMPORARY instrumentation (2026-08-18): Perplexity returns exact token
    // counts in `usage` and this function was discarding them, leaving the cost
    // of the remaining ~24K queue as arithmetic over character counts. Accumulate
    // them so a small sample yields a measured figure instead. Remove once the
    // sample is taken. It sits in a legacy plugin deliberately - it instruments
    // an existing call rather than adding a feature.
    if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
        $acc = get_option( 'mfa_pplx_usage', array( 'calls' => 0, 'prompt' => 0, 'completion' => 0, 'total' => 0, 'first_usage' => null ) );
        $acc['calls']++;
        $acc['prompt']     += (int) ( $data['usage']['prompt_tokens'] ?? 0 );
        $acc['completion'] += (int) ( $data['usage']['completion_tokens'] ?? 0 );
        $acc['total']      += (int) ( $data['usage']['total_tokens'] ?? 0 );
        if ( empty( $acc['first_usage'] ) ) {
            // Kept verbatim: the response may carry search or tier fields that
            // matter more to the bill than the token counts do.
            $acc['first_usage'] = $data['usage'];
        }
        update_option( 'mfa_pplx_usage', $acc, false );
    }
    
    $raw_content = $data['choices'][0]['message']['content'] ?? '';

    // Better regex to strip out backtick wrapping just in case the model ignored instructions
    $clean_json = preg_replace('/^`{3}(?:json)?\s*|\s*`{3}$/i', '', trim($raw_content));
    
    return $clean_json;
}

// SCHEMA EXTRACTOR
function mfa_extract_schema_data($url){

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'user-agent' => 'Mozilla/5.0'
    ]);

    if (is_wp_error($response)){
        return [];
    }


    $html = wp_remote_retrieve_body($response);

    $result = [];


    // Extract JSON-LD
    preg_match_all(
        '/<script type="application\/ld\+json">(.*?)<\/script>/is',
        $html,
        $matches
    );


    if (!empty($matches[1])) {

        foreach($matches[1] as $json){

            $data = json_decode(trim($json), true);

            if(!$data){
                continue;
            }


            $items = [];

            if(isset($data['@graph'])){
                $items = $data['@graph'];
            }
            else{
                $items[] = $data;
            }


            foreach($items as $item){

                if(
                    isset($item['@type']) &&
                    (
                        $item['@type']=='Organization' ||
                        $item['@type']=='LocalBusiness' ||
                        $item['@type']=='WebSite'
                    )
                ){

                    $result = array_merge($result,$item);

                }

            }

        }

    }


    // Extract OpenGraph
    preg_match_all(
        '/<meta property="og:([^"]+)" content="([^"]*)"/i',
        $html,
        $og
    );


    if(!empty($og[1])){

        foreach($og[1] as $key=>$value){

            $result['og_'.$value]=$og[2][$key];

        }

    }


    return $result;

}


// FALLBACK CONTENT
function mfa_generate_website_fallback_content($ai_data) {

    $name = !empty($ai_data['name']) ? esc_html($ai_data['name']) : 'Website';
    $url  = !empty($ai_data['url']) ? esc_url($ai_data['url']) : '';

    $content = '';

    $content .= '<h1>' . $name . '</h1>';

    if ($url) {
        $content .= '<p>Visit Website: <a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a></p>';
    }

    if (!empty($ai_data['introduction'])) {
        $content .= '<h2>Introduction</h2>';
        $content .= '<p>' . esc_html($ai_data['introduction']) . '</p>';
    }

    $content .= '<h2>About ' . $name . '</h2>';

    if (!empty($ai_data['category'])) {
        $content .= '<p>This website is categorized under ' . esc_html($ai_data['category']) . '.</p>';
    } else {
        $content .= '<p>' . $name . ' is an online platform listed in the Masjid4All website directory.</p>';
    }


    if (!empty($ai_data['country'])) {
        $content .= '<h2>Country</h2>';
        $content .= '<p>' . esc_html($ai_data['country']) . '</p>';
    }


    if (!empty($ai_data['address'])) {
        $content .= '<h2>Address</h2>';
        $content .= '<p>' . esc_html($ai_data['address']) . '</p>';
    }


    if (!empty($ai_data['phone']) || !empty($ai_data['email'])) {

        $content .= '<h2>Contact Information</h2>';

        if (!empty($ai_data['phone'])) {
            $content .= '<p>Phone: ' . esc_html($ai_data['phone']) . '</p>';
        }

        if (!empty($ai_data['email'])) {
            $content .= '<p>Email: ' . esc_html($ai_data['email']) . '</p>';
        }
    }


    $content .= '<h2>Website Information</h2>';
    $content .= '<p>This listing provides information about ' . $name . ' for users searching through the Masjid4All directory.</p>';


    return $content;
}
