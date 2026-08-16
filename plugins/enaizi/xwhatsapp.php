<?php

/*

//MEMBER - DISPLAY BUSINESS INFO
add_shortcode('whatsapp_get_info', 'whatsapp_get_info_shortcode');
 
function whatsapp_get_info_shortcode() {
    if (!isset($_GET['wa'])) {
        return '<div class="alert">Invalid Info.</div>';
    }
    $phone = (int)$_GET['wa'];    
    $ret = 'Phone : <b>' . $phone . '</b><br>'; 
    
    $user = whatsapp_check_info_shortcode($phone); 
    if (!$user){
        // Get Name 
        global $wpdb;
        $table = $wpdb->prefix . 'jet_cct_phone';
        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM $table WHERE phone = %s LIMIT 1",
            $phone
        ) );
        $ret.= $name . '<br>';
        $ret.= '<p>Not a Member</p>'; 
    }else{
        $ret.= '<b>' . $user->display_name . '</b><br>';
        $ret.= 'MemberID : ' . $user->ID . '<br><br>';
        $ret.= '<a href="' . esc_url( site_url( '/admin/member/update/?pid=' . $user->ID ) ) . '">View Profile</a>';
    } 
     
    return $ret;
}

function whatsapp_check_info_shortcode($phone) {
    global $wpdb;
    
    // Clean up phone number to retain only digits
    $phone = preg_replace('/\D/', '', $phone);
    
    // Query user_id from usermeta
    $user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        'user_phone',
        $phone
    ) );

    if ( $user_id ) {
        return get_userdata( $user_id ); // WP_User object
    }

    return false;
} 

//////////////////////////////////////////
// Send Whatsapp message                //
//////////////////////////////////////////

// SEND USING WAPI - 018-989 7579
function whatsapp_send_message($phone, $message, $header, $footer, $buttons = []) {

    $api_url = "https://wapi.flaxxa.com/api/v1/sendmessage";
    $token   = "538851673690a0d83f3c57"; 
 
    /*
    // sample date
    $phone    = '60177271844';
    $message  = 'Test Message';
    $header   = 'Test Header';
    $footer   = 'Test Footer';
    $buttons  = [
        ["id" => "reg",  "title" => "Register"],
        ["id" => "info", "title" => "More Info"],
        ["id" => "menu", "title" => "Main Menu"]
    ];

    // Limit buttons to max 3
    if (is_array($buttons)) {
        $buttons = array_slice($buttons, 0, 3);
    } else {
        $buttons = [];
    }

    // Ensure each button has id & title
    $validated_buttons = [];
    foreach ($buttons as $btn) {
        if (isset($btn["id"]) && isset($btn["title"])) {
            $validated_buttons[] = [
                "id"    => $btn["id"],
                "title" => $btn["title"]
            ];
        }
    }

    // Build payload
    $data = [
        "token"   => $token,
        "phone"   => $phone,
        "message" => $message,
        "header"  => $header,
        "footer"  => $footer,
    ];

    if (!empty($validated_buttons)) {
        $data["buttons"] = $validated_buttons;
    }

    $json_data = json_encode($data);

    // Init CURL
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return "Curl error: " . curl_error($ch);
    }

    curl_close($ch);

    if (!$response) {
        return "Failed to get response from API.";
    }

    $data = json_decode($response, true);

    if (isset($data["status"]) && $data["status"] === "success") {
        return "Message sent successfully.";
    }

    return "Failed to send message. API Response: " . $response;
}

function whatsapp_send_message_old($phone, $msg, $media = null) {


    /* 
    $api_url = "http://34.142.136.64:3000/send";
    $sender = '601139522795';

    // Input validation
    if (empty($phone) || empty($msg)) {
        return "Error: Phone number and message are required.";
    }

    // Prepare data payload
    $data = array(
        'sender'  => $sender,
        'number'  => $phone,
        'message' => $msg
    );

    // Optionally add media if provided
    if (!empty($media)) {
        $data['media'] = $media;
    }

    // Prepare request arguments
    $args = array(
        'body'        => json_encode($data),
        'headers'     => array('Content-Type' => 'application/json'),
        'method'      => 'POST',
        'data_format' => 'body',
    );

    // Send the request
    $response = wp_remote_post($api_url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        return "Error sending message: " . $response->get_error_message();
    }

    // Optional: decode response and check success status
    //$body = wp_remote_retrieve_body($response);
    //$data = json_decode($body, true);

    //if (isset($data['status']) && $data['status'] === 'success') {
    //    return "WhatsApp message sent successfully!";
    //} else {
    //    return "Failed to send WhatsApp message.";
    //}
    
    return;
    
}


function whatsapp_send_message_new($phone, $msg, $media){

    $ch = curl_init(); 
    $api_url = "http://34.142.136.64:3000/send";
    
    $sender = '601139522795';
    $user_id = 'admin';      // Replace with your actual user ID
    $password = 'egPa yctG 8u0u 97u6 TUbT miQ0';    // Replace with your actual password
    
    $data_to_send = [
        'sender' => $sender,
        'number' => $phone,
        'message' => $msg, 
    ]; 
    
    $json_data = json_encode($data_to_send);
    
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    
    // Add Basic Authentication
    curl_setopt($ch, CURLOPT_USERPWD, "$user_id:$password");
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_message = 'Curl error: ' . curl_error($ch);
        return $error_message;
    } else {
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                return "Message sent successfully.";
            } else {
                return "Failed to send message.";
            }
        } else {
            return "Failed to get response from API.";
        }
    }
    curl_close($ch);
    exit();
}

// WHATSAPP - SEND MESSAGE
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    if ((int) $form->id !== 50) {
        return;
    }

    $phone   = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone');
    $message = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'message'));
    $header  = "Welcome to Masjid4All";
    $footer  = "Thank you";

    $msg  = "*Connecting the Ummah\n\n" ;
    $msg .= "🕌 *Masjid Directory*\n_Looking for a masjid nearby? Find one anytime, anywhere._\n\n";
    $msg .= "🤝 *Business Directory*\n_Discover & support Muslim-friendly businesses — or list your own!_\n\n";
    $msg .= "📲 *Muslim Apps*\n_Access smart, Shariah-compliant solutions — and showcase your own innovations._\n\n";
    $msg .= "💬 *Sofia - Your AI Assistant*\n_Got questions on Masjid4All, Islam, or general knowledge? Ask Sofia anytime_\n\n";
    $msg .= "*Join us in building a global platform for the Ummah. Together, we create lasting impact.*\n";

    $message = $msg;
    
    $buttons = [
        ["id" => "btn1", "title" => "Get Started"],
        ["id" => "btn2", "title" => "Register as Member"],
        ["id" => "btn3", "title" => "Main Menu"]
    ];

    // SEND WHATSAPP
    whatsapp_send_message($phone, $message, $header, $footer, $buttons) ;
    //whatsapp_send_message($phone, $msg, $media);
    
}, 10, 3);

// WHATSAPP - SEND TEMPLATE
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {
    if ((int) $form->id !== 51) {
        return;
    }

    $phone = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'phone');
    $template = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'template'));
    $media = '';
    
    if ($template == '1E'){
        // INTRO - ENGLISH
        $msg  = "*Masjid4All*\nConnecting the Ummah\n\n" ;
        $msg .= "🕌 *Masjid Directory*\n_Looking for a masjid nearby? Find one anytime, anywhere._\nhttps://masjid4all.com/mosque\n\n";
        $msg .= "🤝 *Business Directory*\n_Discover & support Muslim-friendly businesses — or list your own!_\nhttps://masjid4all.com/business\n\n";
        $msg .= "📲 *Muslim Apps*\n_Access smart, Shariah-compliant solutions — and showcase your own innovations._\nhttps://masjid4all.com/apps\n\n";
        $msg .= "💬 *Sofia - Your AI Assistant*\n_Got questions on Masjid4All, Islam, or general knowledge? Ask Sofia anytime_\nhttps://masjid4all.com/ai\n\n";
        $msg .= "*Join us in building a global platform for the Ummah. Together, we create lasting impact.*\n";
        $msg .= "👉 https://masjid4all.com";
        $media = 'https://masjid4all.com/wp-content/uploads/2025/09/Masjid4All-Main-Banner-1.jpg';
    }elseif ($template == '1M'){
        // INTRO - MALAY
        $msg = 'Sending template # ' . $template;
    }
    // SEND WHATSAPP
    
    whatsapp_send_message($phone, $msg, $media);
    
}, 10, 3);

//////////////////////////////////////////
// Check if number is on Whatsapp       //
//////////////////////////////////////////

function whatsapp_check_status($phone){
    
    //https://rapidapi.com/inutil-inutil-default/api/whatsapp-data
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
    	CURLOPT_URL => "https://whatsapp-data.p.rapidapi.com/about?phone=" . $phone,
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_ENCODING => "",
    	CURLOPT_MAXREDIRS => 10,
    	CURLOPT_TIMEOUT => 30,
    	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    	CURLOPT_CUSTOMREQUEST => "GET",
    	CURLOPT_HTTPHEADER => [
    		"x-rapidapi-host: whatsapp-data.p.rapidapi.com",
    		"x-rapidapi-key: 1312e5d598msh239cf8f540fc92ap1a0507jsnb24a3d03021a"
    	],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
    	return "cURL Error #:" . $err;
    } else {
    	return $response;
    }
 }    


function sanitize_phone_number($phone) {
    // Remove spaces, dashes, parentheses, and plus signs
    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    // Optional: remove leading 0 if number starts with 60 (to prevent 6003xxx)
    if (strpos($cleaned, '60') === 0 && substr($cleaned, 2, 1) === '0') {
        $cleaned = '60' . substr($cleaned, 3);
    }

    return $cleaned;
}

// Export CCT Mosque to WP Post
add_action('plugins_loaded', function () {
    add_shortcode('export_cct_mosque', 'export_cct_mosque_shortcode');
});
 
function export_cct_mosque_shortcode($atts) {
    global $wpdb;
 
    $atts = shortcode_atts([
        'offset' => 0,
        'limit' => 1000,
    ], $atts);

    $table_name = $wpdb->prefix . 'jet_cct_mosque';
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table_name} LIMIT %d OFFSET %d", $atts['limit'], $atts['offset']),
        ARRAY_A
    );
    
    if (empty($results)) {
        return 'No records found in Custom Content Type (CCT).';
    }

    $imported = 0;

    foreach ($results as $row) {
        $place_id = trim($row['place_id'] ?? '');
        
        // Skip if place_id is missing (you had this check twice before)
        if (empty($place_id)) {
            continue;
        }

        // Improved duplicate check - search both postmeta and check the CCT source
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'place_id' 
            AND meta_value = %s 
            LIMIT 1",
            $place_id
        ));
        
        if ($exists) {
            continue; // skip duplicates
        }

        // Create post
        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($row['name'] ?? 'Unnamed Mosque'),
            'post_content' => wp_kses_post($row['introduction'] ?? ''),
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ]);
 
        if ($post_id && !is_wp_error($post_id)) {
            // Add post meta safely
            $meta_fields = [
                'place_id'      => $place_id,
                'latitude'      => $row['latitude'] ?? '',
                'longitude'     => $row['longitude'] ?? '',
                'types'         => $row['types'] ?? '',
                'address'       => $row['address'] ?? '',
                'country'       => $row['country'] ?? '',
                'email'         => sanitize_email($row['email'] ?? ''),
                'phone'         => sanitize_text_field($row['phone'] ?? ''),
                'rating'        => floatval($row['rating'] ?? 0),
                'website'       => esc_url_raw($row['website'] ?? ''),
                'opening_hours' => wp_kses_post($row['opening_hours'] ?? ''),
                'thumbnail_url' => esc_url_raw($row['photo_url'] ?? ''),
            ];

            foreach ($meta_fields as $key => $value) {
                if (!empty($value)) { // Only save non-empty values
                    update_post_meta($post_id, $key, $value);
                }
            }

            $imported++;
        }
    }

    return "✅ Import completed. {$imported} mosque(s) added.";
}

*/

