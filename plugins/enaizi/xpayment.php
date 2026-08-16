<?php
 
add_action( 'rest_api_init', function () {
 
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

/*

add_action('plugins_loaded', function () {
    add_shortcode('payment_donate', 'payment_donate_shortcode');
});

function payment_donate_shortcode() {
    //ob_start();

    // Location variables
    $country = $_COOKIE['country'] ?? '';
    $latitude = floatval($_COOKIE['latitude'] ?? 0);
    $longitude = floatval($_COOKIE['longitude'] ?? 0);
   
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
 
    //echo $country;
    if ($country == 'Malaysia'){
        $url = 'https://donate.stripe.com/3cI6oI2q95nggQq6uNaZi01';
    }elseif ($country == 'United Kingdom'){
        $url = 'https://donate.stripe.com/eVqaEYe8R7voas2aL3aZi02';
    }elseif ($country == 'Indonesia'){
        $url = 'https://donate.stripe.com/4gM6oI0i1cPI57If1jaZi03';
    }elseif ($country == 'Canada'){
        $url = 'https://donate.stripe.com/4gM6oI0i1cPI57If1jaZi04';
    }elseif ($country == 'Australia'){
        $url = 'https://donate.stripe.com/4gM6oI0i1cPI57If1jaZi05';
    }elseif ($country == 'New Zealand'){
        $url = 'https://donate.stripe.com/4gM6oI0i1cPI57If1jaZi05';
    }else{
        $url = 'https://donate.stripe.com/6oU9AUaWFg1U43EaL3aZi00';
    }  
 
    //$ret.= $country . '<br>';
    $ret.= '<a href="' . esc_url($url) .  '" style="padding: 10px 20px; background-color: #0073aa; color: #fff; border-radius: 5px; text-decoration: none;">DONATE/INFAQ NOW</a>';
 
    //wp_redirect($url);
    //exit;
   
    return $ret ; //ob_get_clean();
}

*/ 
