<?php

/*

function gemini_prompt($question) {
    global $wpdb;
    
    $header = "*Question*\n" . $question . "\n\n";
    $question .= '. Please format your answer suitanble to be sent via whatsapp, with bold and line feed'; 
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_GENERAL_API_KEY;
    
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $question]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);

    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
        return $answer;
    } 
    else{
        return 'Invalid response received.';
    }
}



function ask_gemini_faraid($question) { 

    $q = "You are an expert in Islamic inheritance law (faraid). Your task is to calculate the faraid distribution based on the information provided by the user.
    Follow Islamic faraid principles to:
    Identify fixed shares (ashabul furud) such as spouse, parents, daughters, etc.
    Allocate residual shares (‘asabah) if applicable.
    Exclude heirs who are blocked by others (hijab).
    Show the calculation step by step:
    List heirs and their entitlement.
    Apply the correct faraid fractions.
    Calculate the amount each heir receives based on the estate value.
    Output format should be clear and structured:
    Table of heirs, their share fraction, and the final amount.
    Short explanation of the distribution.
    If the case is complex, mention that the user may verify using a certified faraid expert.
    
    Example Output:
    Estate Value: RM100,000
    Wife: 1/8 → RM12,500
    Mother: 1/6 → RM16,666.67
    
    Father: remainder (‘asabah) → RM70,833.33
    ";

    $question = $q . $question;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_GENERAL_API_KEY;
    
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $question]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);
 
    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
        return $answer;
    } else {
        return 'Invalid response received.';
    }
} 

// GEMINI_API_KEY is resolved in keys.php (wp-config constant, then DB option).

function ask_gemini_mosque($question) {
    if (empty($question)) return '';

    // 1. Prepare the Prompt (Simplified for readability)
    $prompt = "Expert content reviewer for mosque directory. Use RAW HTML ONLY. ... Data: " . $question;

    if (!defined('GEMINI_API_KEY')) {
        $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]]
    ]);

    // 2. Setup cURL with more robust options
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json' // Explicitly ask for JSON
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true, // Follow any redirects
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 3. Handle Connection Errors (e.g., DNS, Timeout)
    if ($curlError) {
        return "Connection Error: {$curlError}";
    }

    // 4. Handle API Errors (400, 401, 403, 500)
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'Unknown API Error';
        return "AI Error ($httpCode): $errorMessage";
    }

    // 5. Safe JSON Decoding
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Format Error: Server returned non-JSON content. Check your API key.";
    }

    $answer = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$answer) return 'Empty response from AI';

    // Cleanup and Security Enforcement
    $answer = preg_replace('/```(html)?|```/', '', $answer);
    if (stripos($answer, 'UNDER REVIEW') !== false) {
        return '<b>⚠️ UNDER REVIEW</b>';
    }

    return trim($answer);
}

function ask_gemini_mosquexx($question) {

    if (empty($question)) {
        return '';
    }

    $prompt = "
You are an expert content reviewer for a mosque directory platform.

Generate content in English using RAW HTML ONLY (<h3>, <p>, <b>, <ul>, <li>, <a>).

If the place is NOT a mosque, masjid, surau, or Islamic place of worship, return ONLY:
<b>⚠️ UNDER REVIEW</b>

Rules:
- Do NOT use markdown
- Do NOT explain anything
- Output HTML only
- All URLs must use <a href='' target='_blank'>

Structure:
<p>[Short introduction]</p>
<p>
<b>📍 Address:</b> ...<br>
<b>🌍 Country:</b> ...<br>
<b>🕰️ Prayer Times Availability:</b> ...<br>
<b>🕌 Friday (Jumu'ah) Prayer:</b> ...<br>
<b>📞 Contact:</b> ...<br>
<b>🌐 Website / Social Media:</b> <a href='' target='_blank'>Link</a>
</p>

<h3>Facilities & Services</h3>
<ul><li>...</li></ul>

<h3>Highlights of This Mosque</h3>
<p>...</p>

<h3>Visitors’ Experience</h3>
<p>...</p>

<h3>How to Get There</h3>
<p>...</p>

<h3>Opening Hours</h3>
<p>...</p>

Data:
" . $question;

    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (!$api_key) {
        return 'API key missing';
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return "Curl error: {$error}";
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        return 'AI service unavailable';
    }

    $decoded = json_decode($response, true);

    $answer = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (!$answer) {
        return 'Invalid AI response';
    }

    // Cleanup
    $answer = preg_replace('/```(html)?|```/', '', $answer);

    // Safety enforcement
    if (stripos($answer, 'UNDER REVIEW') !== false) {
        return '<b>⚠️ UNDER REVIEW</b>';
    }

    return trim($answer);
}

function is_gemini_error($content) {
    if (empty($content)) return true;

    $error_signals = [
        'Invalid response',
        'Error:',
        'UNDER REVIEW'
    ];

    foreach ($error_signals as $signal) {
        if (stripos($content, $signal) !== false) {
            return true;
        }
    }

    return false;
}

function ask_gemini_business($data) {
 
    $q .= $data . "<br>";
    $q .= "
    You are an expert reviewer for a Muslim-friendly business directory. Your role is to write an informative and well-structured WordPress post in English based on the provided business data. The goal is to help Muslims easily discover halal and shariah-compliant businesses they can trust and support.
    Important Notes:
    For food-related businesses only (e.g., restaurants, food suppliers), include a Halal Status section.
    For services, instead of Halal Status, indicate whether the service is shariah-compliant.
    Follow the structure and format below exactly. Write in a friendly, professional, and natural tone.
    
    <p>[Write a brief overview of the business (3–5 sentences). Mention the type of business (e.g., restaurant, boutique, clinic), its background (if known), and who it serves.]</p> 
    <p><b>📍 Address:</b> [Insert full address or general area]<br>
    <b>🌍 Country:</b> [Insert Country]<br>
    <b>📞 Contact:</b> [Phone / Email if available]<br>
    <b>🌐 Website / Social Media:</b> [Insert as a link using &lt;a href='...' target='_blank'&gt;URL or page name&lt;/a&gt;]</p> 
   
    <br>
    
    <!-- Only include this section for food-related businesses -->
    <div>
      <h3>Halal Status</h3>
      <p>
        Clearly state the halal status based on available data:
        <ul>
          <li><strong>Halal Certified</strong></li>
          <li><strong>Muslim-owned (assumed halal)</strong></li>
          <li><strong>Muslim-friendly (no pork/alcohol)</strong></li>
          <li><strong>NON-HALAL</strong> (write in bold if applicable)</li>
        </ul>
        Mention the source of information (e.g., halal certificate, self-declared, customer reviews).
      </p>
    </div>
    
    <!-- For service-based businesses only -->
    <div>
      <h3>Shariah Compliance (For Services)</h3>
      <p>
        Indicate whether the service is considered shariah-compliant. Provide any relevant explanation or disclaimer based on available data.
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Specialty</h3>
      <p>
        Describe what the business is best known for. Highlight any signature dishes, standout products, or unique services.
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Price</h3>
      <p>
        Give a general sense of pricing: (e.g., affordable, mid-range, premium). Mention if there are ongoing promotions or seasonal offers. IMPORTANT : If no indication of price, please omit the section on Price
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Visitors' Experience/Review</h3>
      <p>
        Summarize common feedback from customers. Highlight what people like or dislike. Keep the review fair, balanced, and factual.
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Location and Accessibility</h3>
      <p>
        Comment on how easy it is to find or reach the business. Mention nearby landmarks, public transportation options, or parking availability if known.
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Products/Services</h3>
      <p>
        List or briefly describe the main products or services offered by the business.
      </p>
    </div>
    
    <br>
    
    <div>
      <h3>Opening Hours</h3>
      <p>
        State the business hours and days of operation. If available, mention any special hours (e.g., closed on Fridays, open 24/7).
      </p>
    </div>
    ";
    
    //$question = $q . $question;
 //   $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_GENERAL_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_BUSINESS_API_KEY;
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $q]
                ]
            ]
        ]
    ]);
 
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);

    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
        return $answer;
    } else { 
        $msg = 'UNDER REVIEW <br>' ;
        //$msg .= implode(", ", $decoded_response);
        return $msg;
    } 
} 

// REMOVE CODE BLOCL
function removeCodeBlockTags($text) {
    // This will remove ```html, ```php, ```js etc. and closing ```
    return preg_replace('/```[a-z]*\n?|\n?```/', '', $text);
}

// ASK GEMINI IMAGES
function ask_gemini_images($data) {
 
    $q .= $data . "<br>";
    $q .= "You are an AI that selects the best image from a list of image URLs to be used as a banner for a business WordPress post.
    **Input:**
    A list of image URLs, each with its corresponding width and height in pixels. The data is provided in the following format:";
    $q .= "**Task:**
    1.  Analyze the image dimensions (width and height) of each image in the list.
    2.  Select the *single* image that best meets the following criteria:
        * The image width must be greater than 500 pixels.
        * The image must be either landscape or square in aspect ratio.  A landscape image has width > height, and a square image has width = height.
    3.  Return *only* the `imageUrl` of the selected image. Do not return any other data, explanations, or formatting.  If no image meets the criteria, return an empty string.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_GENERAL_API_KEY;
    
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $q]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);
 
    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
        return $answer;
    } else {
        return 'Invalid response received.';
    }
} 

*/
