<?php

/*

//////
function wapi_webhook_listener_old(WP_REST_Request $request) {
    global $wpdb;

    $input = $request->get_json_params();

    // 1. Log input for debugging
    $log_file = __DIR__ . '/wapi-webhook.log';
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . print_r($input, true) . "\n", FILE_APPEND);

    $entry    = $input['entry'][0] ?? [];
    $changes  = $entry['changes'][0]['value'] ?? [];

    $contacts = $changes['contacts'] ?? [];
    $messages = $changes['messages'] ?? [];

    if (empty($messages)) {
        return rest_ensure_response(['status' => 'no messages found']);
    }

    $msg   = $messages[0];
    $from  = $msg['from'] ?? '';
    $phone = preg_replace('/\D+/', '', $from); // clean phone
    $name  = $contacts[0]['profile']['name'] ?? 'Guest';
    $type  = $msg['type'] ?? 'unknown';
    $content = '';

    // Register user first
    //$user_id = wapi_register_user($phone, $name);

    // Determine message content
    switch ($type) {
        case 'interactive':
            $content = $msg['interactive']['button_reply']['id'] ?? '[Interactive message without button_reply]';
            break;
        case 'text':
            $content = $msg['text']['body'] ?? '';
            break;
        case 'location':
            $lat = $msg['location']['latitude'] ?? null;
            $lng = $msg['location']['longitude'] ?? null;
            $content = "Location: lat=$lat lng=$lng";
            break;
        case 'image':
        case 'document':
        case 'audio':
            $content = $msg[$type]['caption'] ?? '[Media]';
            break;
        default:
            $content = '[Unsupported type]';
    }

    // Save Message
    //wapi_save_message($user_id,$name,$phone,$type,$content,$from);

    // Handle location separately
    if ($type === 'location' && !empty($lat) && !empty($lng)) {
        wapi_nearest_mosque($phone, $lat, $lng);
        return rest_ensure_response(['status' => 'location processed']);
    }

    // Send message to chatbot
    wapi_chatbot($phone, $content);

    return rest_ensure_response(['status' => 'message processed', 'user_id' => $user_id]);
}


// REGISTER USER
function xwapi_register_user($phone, $name) {
    global $wpdb;

    // Clean up phone number: remove +, spaces, and non-digit characters
    $phone = preg_replace('/\D+/', '', $phone);

    // Ensure phone meta key is consistent
    $meta_key = 'user_phone';

    // Check if phone exists in user meta
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
        $meta_key,
        $phone
    ));

    if ($user_id) {
        // Phone exists, return existing user_id
        return (int) $user_id;
    }

    // Generate a unique random login ID
    do {
        $login = wp_generate_password(8, false); // 8-char random string
    } while (username_exists($login));

    // Generate a random password
    $password = wp_generate_password();

    // Create user with email loginid@pw.com
    $email = $login . '@pw.com';
    $user_id = wp_create_user($login, $password, $email);

    if (is_wp_error($user_id)) {
        error_log("WAPI: Failed to create user - " . $user_id->get_error_message());
        return false;
    }

    // Set user meta and names
    wp_update_user([
        'ID'           => $user_id,
        'first_name'   => $name,
        'display_name' => $name,
        'nickname'     => $name
    ]);
    update_user_meta($user_id, $meta_key, $phone);
    update_user_meta($user_id, 'user_status', 'Prospect');

    return $user_id;
}

// Save Message 
function xwapi_save_message($user_id,$name,$phone,$type,$content,$from){
    global $wpdb;
    
    // Convert timestamp → DateTime in MYT
    $timestamp = time();
    $dt = new DateTime("@$timestamp");
    $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
    $datetime = $dt->format('Y-m-d H:i:s');
 
    $user_data = array(
        'user_id'     => $user_id,
        'phone'       => $phone,
        'name'        => $name,
        'message'     => $content,
        'type'        => $type,
        'from'        => $from,
        'cct_created' => $datetime,
    );
    $wpdb->insert('wp_jet_cct_whatsapp', $user_data);
    
    return;
    
} 


// ======================================================
// SEND MESSAGE VIA WAPI
// ======================================================
function xwapi_send_message($phone, $message) {

    $api_url = "https://wapi.flaxxa.com/api/v1/sendmessage";
    $token   = "538851673690a0d83f3c57";

    $payload = [
        "token"   => $token,
        "phone"   => $phone,
        "message" => $message
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return "Curl Error: " . curl_error($ch);
    }
 
    curl_close($ch);

    return $response ?: "No API response";
}

function xwapi_button_message( $phone, $message, $header, $footer, $buttons) {
    $api_url = "https://wapi.flaxxa.com/api/v1/sendmessage";
    $token   = "538851673690a0d83f3c57";
    
    // Ensure buttons is array & limit to 3 max
    if ( ! is_array( $buttons ) ) {
        $buttons = [];
    }
    $buttons = array_slice( $buttons, 0, 3 );

    $payload = [
        'token'   => $token,
        'phone'   => $phone,
        'message' => $message,
        'header'  => $header,
        'footer'  => $footer,
        'buttons' => $buttons
    ];

    $ch = curl_init( $api_url ); // <-- Fixed
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );

    $response = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
        return 'Curl error: ' . curl_error( $ch );
    }
    curl_close( $ch );

    return json_decode( $response, true );
}

function xwapi_ask_gemini($question) {
    
    //- Focus on digital empowerment of mosques, community collaboration, volunteerism, and innovative use of technology.
    //- You may explain general Islamic concepts carefully, but do NOT provide fatwa, legal, or medical advice.
    //- If a request involves rulings or sensitive matters, politely direct the user to qualified experts.
 
    // --- 1. System Prompt Definition ---
    $system_prompt = "
    You are Sofia, an AI Assistant for Masjid4All.
    Persona Details:
    - Female, around 28 years old, Muslim, warm, community-focused.
    - Tone is respectful, encouraging, simple, and professional.
    - You represent Masjid4All as a platform that empowers communities with the mosque at the center.
    - Communication style: clear, modern, supportive, and inspirational.
    - Always adapt language to match the user's input (English or Malay).
    - Start with greetings only when user greet such as Hi, Hello, Salam, Assalamualikum. Response appropriately
    - if user ask about finding mosque, ask user to share your location. How to share your location: On Android, Tap the attachment icon (📎). On iPhone,Tap the plus icon (➕),
    Select Location, and then choose. Send your current location. Try it now and see how easy it is.
    - if user ask about prayer time, please give provide the prayer time.
    - for any questions relating to inheritance, please refer user to https://pewarisan.my, an online inheritance planning solution
    - for any question on faraid, please refer to Faraid Calculator at https://pewarisan.my/faraid
    Response Requirements:
    1. Detect the user’s language and reply in the same language.
    2. Be concise, informative, and motivating.
    3. Use short paragraphs and encourage action where possible.
    4. Do not use ** for bold, just use *.
    5. Avoid debates and controversial topics.
    Now respond as Sofia to the user’s question.
    ";
    
    // --- 2. API Endpoint and Payload (Improved Structure) ---
    // The key is now passed securely as a parameter or loaded from an environment variable.
    $api_key = GEMINI_FLAXXA_API_KEY; // resolved in keys.php
    if (empty($api_key)) {
        return "Error: API Key is missing. Please provide the Gemini API key.";
    }

    //$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;
    // Using separate roles for System and User for better model context
    
    // Define the combined prompt text:
    $combined_prompt_text = $system_prompt . "\n---\n" . $question;
    $payload = json_encode([
        'contents' => [
            [
                // Combine both the system instructions and the user question 
                // under a single 'user' role entry.
                'role' => 'user', 
                'parts' => [
                    ['text' => $combined_prompt_text]
                ]
            ]
        ]
    ]);

    // --- 3. cURL Execution (Added HTTP Status Check) ---
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: cURL failed with message: " . $error_msg;
    }

    curl_close($ch);

    // --- 4. Response Decoding and Error Handling ---
    $decoded_response = json_decode($response, true);
    
    // Check for non-200 HTTP status (API server error, bad request, or auth failure)
    if ($http_code !== 200) {
        $error_detail = isset($decoded_response['error']['message']) ? $decoded_response['error']['message'] : 'Unknown API error.';
        return "Error: API call failed with HTTP status $http_code. Details: $error_detail";
    }

    // Check for the expected text field
    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        return $decoded_response['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($decoded_response['promptFeedback']['blockReason'])) {
        // Handle safety blocks
        $block_reason = $decoded_response['promptFeedback']['blockReason'];
        return "Response Blocked: The query was blocked due to safety settings (Reason: $block_reason).";
    } else {
        // Fallback for unexpected response structure
        return 'Invalid or unexpected response received from the API.';
    }
}


// AI CHATBOT - ILMU
function xwapi_chatbot_ai($phone, $content){
      
    $persona = "You are Sofia, an AI assistant for Masjid4All, a global directory for mosques and Muslim-friendly businesses.
        Your primary role is to:
        Provide accurate, general Islamic guidance based on commonly accepted principles.
        Assist users with mosque locations, Muslim-friendly businesses, and helpful community information.
        Share motivational reminders and beneficial knowledge suitable for Muslims worldwide.

        Communication Style
        Warm, respectful, and compassionate.
        Use simple, clear language.
        Begin responses with “Assalamualaikum” when appropriate.
        Offer guidance in a kind and helpful manner.
        Never use judgmental or harsh language.
        
        What You Can Do
        Answer general questions on:
        Islam, worship (ibadah), ethics, family and community values.
        Halal lifestyle, travel tips, mosque etiquette.
        Assist users using the Masjid4All directory.
        Provide gentle motivation, Islamic reminders, and practical advice.
        
        Boundaries
        Do not discuss politics.
        If asked any political or sensitive political topic, respond by politely declining and redirecting to Islamic or community support topics.
        Example response to political queries:
        “I’m here to assist with Islamic guidance and beneficial community information. I’m unable to discuss political matters.”
        
        Tone Traits
        Friendly
        Respectful
        Knowledgeable
        Supportive
        Neutral and non-argumentative
        
        🧕 Sample Greeting Messages from Sofia
        
        Example 1
        Assalamualaikum 🌸 How may I assist you today? If you’re looking for mosque information, Muslim-friendly services, or Islamic guidance, I’m here to help insha’Allah.
        
        Example 2
        Waalaikumussalam 😊 I’m Sofia from Masjid4All. Let me know how I can assist you—whether it’s about Islamic practices, travel convenience for Muslims, or locating nearby mosques.
        
        Example 3
        Assalamualaikum warahmatullah 💚 I’m here to share beneficial Islamic knowledge and help you find Muslim-friendly facilities globally. How can I support you today?

        Formatting Guidelines
        When applying bold formatting, do not use asterisks. Use native bold styling only (e.g. **text** should be avoided if the platform renders with symbols).
        For bold → type it directly in bold (no * or markdown symbols).
        For italic you may use when necessary, but do not use asterisks for bold/titles.
        Lists should be written cleanly with numbering or bullet points.
        Titles or headings should be clearly separated, either with line breaks or proper spacing.
        Never expose formatting symbols to the user.       
        ";
    
    $q = $persona . ' Please response to this : ' . $content ;
    // API Endpoint
    $url = 'https://api.ytlailabs.tech/v1/chat/completions';

    // Your API Key
    $api_key = YTL_AI_API_KEY; // resolved in keys.php

    // Prepare payload
    $body = array(
        'model' => 'ILMU-text-free-safe',
        'messages' => array(
            array(
                'role' => 'system',
                'content' => $q
            ),
            array(
                'role' => 'user',
                'content' => $q
            )
        ),
        'max_tokens'  => 300,
        'temperature' => 0.7
    );

    // Request arguments
    $args = array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body'    => json_encode( $body ),
        'method'  => 'POST',
        'timeout' => 30
    );

    // Send request
    $response = wp_remote_post( $url, $args );

    // Handle errors
    if ( is_wp_error( $response ) ) {
        return 'Request Error: ' . $response->get_error_message();
    }

    // Parse the JSON response
    $data = json_decode( wp_remote_retrieve_body($response), true );

    // Extract the assistant message content
    if ( isset($data['choices'][0]['message']['content']) ) {
        $text = trim($data['choices'][0]['message']['content']);
        $answer = removeAsterisks($text);
        wapi_send_message($phone, $answer);
    }
} 

// CHATBOT
function xwapi_chatbot($phone, $content) {
    // Normalize input
    $content = strtolower(trim($content));

    // Mapping of commands → callback function
    $commands = [
        'welcome'                                           => 'wapi_chatbot_welcome',
        'welcome to masjid4all. click here to get started'  => 'wapi_chatbot_welcome',
        'get_started'                                       => 'wapi_chatbot_start',
        'about_masjid4all'                                  => 'wapi_chatbot_about',
        'nearest_mosque'                                    => 'wapi_chatbot_mosque',
        'ask_sofia'                                         => 'wapi_chatbot_sofia',
        '/1'                                                => 'wapi_chatbot_start',
        '/2'                                                => 'wapi_chatbot_mosque',
        '/3'                                                => 'wapi_chatbot_sofia',
    ];

    // If input matches a command, call its function
    if (array_key_exists($content, $commands) && function_exists($commands[$content])) {
        call_user_func($commands[$content], $phone, $content);
    } else {
        // AI response fallback
        $answer = wapi_ask_gemini($content);
        wapi_send_message($phone, $answer);
    }

    return;
}

// WELCOME MESSAGE  
function wapi_chatbot_welcome($phone, $content){
    $header = 'Welcome to Masjid4All';
    $footer = 'Click below to join the journey';
 
    $buttons = [
        ['id' => 'get_started', 'title' => 'Get Started']
    ];
    $msg = "*Masjid4All* is a community-driven platform that places the mosque at the heart of empowerment. We are on a bold mission to build the world’s largest digital ecosystem for the global Muslim community.\n\n";
    $msg .= "*OUR MISSION*\n";
    $msg .= "1️⃣ Index 1 million mosques worldwide\n";
    $msg .= "2️⃣ List 2 million businesses\n";
    $msg .= "3️⃣ Engage 10 million members\n\n";
    $msg .= "It’s an ambitious goal — yet with unity, smart technology and collective effort, it is within reach.\n\n" ;
    $msg .= "*Be part of this movement.*\nContribute. Share. Connect.\n\nTogether, we build a lasting digital legacy for the ummah.";
    $message = $msg;
  
    wapi_button_message( $phone, $message, $header, $footer, $buttons);
    return;
}     

// GET STARTED  
function wapi_chatbot_start($phone, $content){
    $header = "Let's Get Started";
    $footer = "Tap below to try it now";

    $buttons = [
        ['id' => 'nearest_mosque',   'title' => 'Get Nearest Mosque'],
    ];

    $msg  = "*Hi, I’m Sofia, your smart AI assistant.*\nI’m here to help you explore *Masjid4All* and make the most of its features!\n\n";
    $msg .= "Ask me anything about Islam — I’m here to give you instant answers.\n\n";
    $msg .= "Our mission is to make AI accessible to everyone, empowering people to easily seek knowledge and deepen their understanding of Islam.\n\n";
    $msg .= "To explore our other services, simply type  and select from the list.\n\n";
    $msg .= "*Wait,  before you start exploring or asking any questions…*\n\n";
    $msg .= "Let me show you one powerful feature first —\n📍 *Try this first* — send your location and I’ll show you list of the nearest mosque right away!\n\n";
 
    wapi_button_message($phone, $msg, $header, $footer, $buttons);
}    


// ABOUT 
function wapi_chatbot_about($phone, $content){
    $msg  = "*About Masjid4All*\n\n";
    $msg .= "OUR MISSION\n";

    wapi_send_message($phone, $msg);
}  

// MOSQUE
function wapi_chatbot_mosque($phone, $content){
    $msg  = "📍 *Nearest Mosque / Business Listing*\n\n";
    $msg .= "Just share your location and I’ll show you the closest mosques and Muslim-friendly businesses around you.\n\n";
    $msg .= "*How to share your location:*\n\n";
    $msg .= "*On Android*\nTap the attachment icon (📎), select Location, and then choose. Send your current location.\n\n";
    $msg .= "*On iPhone*\n,Tap the plus icon (➕), select Location, and then choose. Send your current location.\n\n";
    $msg .= "*Try it and see how easy it is.*\n\n";
    $msg .= "*Share your location now.*\n\n";
    wapi_send_message($phone, $msg);
}  

// ASK SOFIA
function wapi_chatbot_sofia($phone, $content){
    $msg  = "*Hi, I’m Sofia, your friendly AI assistant.*\n\n";
    $msg .= "I'm here to help you learn, explore, and get the most out of this platform. You can ask me about Islam — such as prayer, daily rulings, Quran, hadith, history, or general knowledge — and I’ll do my best to help.\n\n";
    $msg .= "Our aim is to make learning simple and information available whenever you need it. For quick access in the future, please save my number to your contact list.\n\n";
    $msg .= "If you have a question, just ask. Let’s begin this journey together.\n\n";

    wapi_send_message($phone, $msg);
}  




add_action( 'rest_api_init', function () {
 
  	// Register Message
  	register_rest_route( 'register', '/message/', array( 
        'methods'  => 'POST',
        'callback' => 'register_message' 
  	), true );
  	
  	// Claim Message
  	register_rest_route( 'claim', '/message/', array( 
        'methods'  => 'POST',
        'callback' => 'claim_message' 
  	), true );
  	
  	// STRIPE PAYMENT WEBHOOK
  	register_rest_route('stripe', '/webhook/', array(
        'methods'  => 'POST',
        'callback' => 'handle_stripe_webhook'
    ));
    //,'permission_callback' => '__return_true',
      
  	// STRIPE - PREMIUM MEMBERSHIP PAYMENT
  	register_rest_route( 'stripe', '/membership/', array( 
        'methods'  => 'POST',
        'callback' => 'stripe_membership' 
  	), true );
  	
  	// STRIPE - VERIFY BUSINESS PAYMENT
  	register_rest_route( 'stripe', '/verify/', array( 
        'methods'  => 'POST',
        'callback' => 'stripe_verify' 
  	), true );

} );

// REGISTER MESSAGE
function register_message() {
    $data = file_get_contents('php://input');
	$data = json_decode($data, TRUE);

	$step = $data['step'];
    $username = $data['username'];
    // Fetch the user by username
    $user = get_user_by('login', $username);
    
    if ($user) {
        // User found, access user data
        $user_id = $user->ID;
        $item_id = get_user_meta($user_id, 'item_id', true) ;
 
        $name = get_user_meta($user_id, 'first_name', true) ; 
        $phone = get_user_meta($user_id, 'user_phone', true) ;
   }else {
        // User not found
        echo "Phone not registered : " . $phone;
        return;
    }
    
    $wa = "";
    // SEND WA NOTIFICATION
    if ($step==1){
        $wa .= "Assalamualaikum *" . $name . "* 🙏\n\n";
        $wa .= "We believe every mosque can become a powerful center for community empowerment. We need your support to make this happen.\n\n";
 
    }elseif ($step==2){
 
    }elseif ($step==3){

    }elseif ($step==4){

    }elseif ($step==5){

    }elseif ($step==6){
 
    }elseif ($step==7){
 
    }
    
    if ($wa <> ''){
        wapi_send_message($phone, $wa);
    }

    return ;
    
}

// STRIPE PAYMENT WEBHOOK
function handle_stripe_webhook(WP_REST_Request $request) {
    $payload = $request->get_body();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $secret = 'whsec_E84AfRnD7JCTa0o4jLGskHcRdjcXtlz6';
 
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
    } catch (\Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 400);
    }

       
    // Example: Handle successful payment intent
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        $email = $session->customer_email;
        $amount = $session->amount_total / 100;
        $package = $session->metadata['package'] ?? '';
        $user_id = $session->metadata['user_id'] ?? null;
        $user_name = $session->metadata['name'] ?? null;
        
        // Now, update user by email
        //$user = get_user_by('email', $email);
        //if ($user) {
        //    update_user_meta($user->ID, 'stripe_package', $package);
        //    update_user_meta($user->ID, 'payment_amount', $amount);
        //    update_user_meta($user->ID, 'payment_status', 'completed');
        //}
        
        $phone = "60177271844";
        $wa .= "HELLO 2\n";
        $wa .= "Name " . $user_name . "\n";
        $wa .= "Email " . $email . "\n";
        $wa .= "Amount " . $amount . "\n";
        $wa .= "Package " . $package . "\n";
        $media .= '';
        whatsapp_send_message($phone, $wa, $media);

    }

    return new WP_REST_Response(['status' => 'success'], 200);
}

// STRIPE MEMBERSHIP
function stripe_membership() {
    $data = file_get_contents('php://input');
	$data = json_decode($data, TRUE);

	$step = $data['step'];
    $username = $data['username'];
    // Fetch the user by username
    $user = get_user_by('login', $username);

    $phone = "60177271844";
    $wa .= "Hi,\nThank you for upgrading to Premium Membership! ✅\n\n";
    $wa .= "Your payment has been successfully received.\n\n";
    $wa .= "Thank you\n\n*Masjid4All Team*\n\n";

    $media .= '';
    
    whatsapp_send_message($phone, $wa, $media);
    
    return $data;
    
}

// STRIPE VERIFY BUSINESS
function stripe_verify() {
    $data = file_get_contents('php://input');
	$data = json_decode($data, TRUE);

	$step = $data['step'];
    $username = $data['username'];
    // Fetch the user by username
    $user = get_user_by('login', $username);

    $phone = "60177271844";
    $wa .= "We’ve received your Business Verification Fee. \n\nYour listing is now marked as Verified in our directory. 🏅\n";
    $wa .= "This helps build trust and attract more customers to your business!\n\n";
    $wa .= "Thank you\n\n*Masjid4All Team*\n\n";

    $media .= '';
    
    whatsapp_send_message($phone, $wa, $media);
    
    return $data;
    
}




// CLAIM MESSAGE
function claim_message() {
    $data = file_get_contents('php://input');
	$data = json_decode($data, TRUE);

	$step = $data['step'];
    $username = $data['username'];
    // Fetch the user by username
    $user = get_user_by('login', $username);
    
    if ($user) {
        // User found, access user data
        $user_id = $user->ID;
        $name = $user->display_name;
        $category = get_user_meta($user_id, 'user_category', true ); 
        $phone = get_user_meta($user_id, 'user_phone', true ); 
    }else {
        // User not found
        echo "Phone not registered : " . $phone;
        return;
    }
    
    // SEND WA NOTIFICATION
    if ($step==1){ 
        $wa .= "*Claim Business*\n\n";
        $wa .= "Assalamualaikum " . $name . "\n\n";
        //$wa .= "We’re so glad to have you join Masjid4ALL – a growing digital platform that connects you with local masjids, empowers Muslim businesses, and brings Islamic tools and resources to your fingertips.\n\n";
        //$wa .= "🌟 *What you can do here:*\n";
        //$wa .= "- Discover and support local masjid initiatives\n";
        //$wa .= "- Promote your business or service\n";
        //$wa .= "- Use tools like the Faraid Calculator and AI Islamic Advisor\n";
        //$wa .= "- Join our mission to digitally strengthen the ummah\n\n";
        //$wa .= "Together, we’re building a smarter, more connected masjid ecosystem – and you’re now a part of it.\n\n";
        //$wa .= "🔗 *Get started*\n https://masjid4all.com\n\n";
        //$wa .= "Barakallahu feekum,\n*Masjid4ALL Team*\n";

    }elseif ($step==2){
        $wa .= "*Claim - Message 2* 💖\n\n";
        $wa .= "Assalamualaikum " . $name . "\n\n";
        //$wa .= "At Masjid4ALL, we believe the masjid is more than just a physical space – it's the heart of the ummah, online and offline.\n\n";
        //$wa .= "We're building a digital ecosystem for masjid communities, and your support can make all the difference.\n\n";
     
        //$wa .= "💡 *Your contribution goes towards:*\n";
        //$wa .= "- Developing tools like the Faraid Calculator & AI Islamic Advisor\n";
        //$wa .= "- Empowering masjid networks & local businesses\n";
        //$wa .= "- Expanding outreach to underserved communities\n\n";
        //$wa .= "Donate generously and help us grow and serve the ummah better.\n\n";
        //$wa .= "🔗 *💝 Support us today:*\n https://masjid4all.com/donate/\n\n";
        //$wa .= "Barakallahu feekum for your generosity and continued support.\nTogether, let’s build a smarter future for our masjids.\n\n";
        //$wa .= "*Masjid4ALL Team*\n";

    }elseif ($step==3){
        $wa .= "Claim - Message 3\n\n " ;    
        //$wa .= "🌟 *Your Personalized Life & Legacy Plan is Waiting!*\n\n";
        $wa .= "Assalamualaikum & Greetings!\n";
        //$wa .= "Name  : *" . $name . "*\n";
        //$wa .= "Phone : *" . $phone . "*\n\n";
    
    }elseif ($step==4){
        $wa .= "Message 4 - After 3 days (For Testing Only)\n\n " ;
        
        $wa .= "🚀 *Your Future Shouldn’t Wait!*\n\n";
        $wa .= "Assalamualaikum & Greetings!\n";
        $wa .= "Name  : *" . $name . "*\n";
        $wa .= "Phone : *" . $phone . "*\n\n";
        
    }elseif ($step==5){
        $wa .= "Message 5 - After 7 days (For Testing Only)\n\n " ;    
        $wa .= "👨‍👩‍👧‍👦 *Thousands Are Securing Their Legacy – Join Them!*\n\n";
        $wa .= "Assalamualaikum & Greetings!\n";
        $wa .= "Name  : *" . $name . "*\n";
        $wa .= "Phone : *" . $phone . "*\n\n";
 
    }elseif ($step==6){
        $wa .= "Message 6 - After 14 days (For Testing Only)\n\n " ;
        $wa .= "🎁 *Special Bonus: Secure Your Legacy & Earn Rewards!*\n\n";
        $wa .= "Assalamualaikum & Greetings!\n";
        $wa .= "Name  : *" . $name . "*\n";
        $wa .= "Phone : *" . $phone . "*\n\n";
       
    }elseif ($step==7){
        $wa .= "Message 7 - After 30 days (For Testing Only) \n\n" ;
        $wa .= "⏳ *Still Thinking? Your Legacy Won’t Wait!*\n\n";
        $wa .= "Assalamualaikum & Greetings!\n";
        $wa .= "Name  : *" . $name . "*\n";
        $wa .= "Phone : *" . $phone . "*\n\n";
    }
    
    whatsapp_send_message($phone, $wa, $media);
    
    return ;
    
}

*/


