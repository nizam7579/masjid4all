<?php

/**
 * Core Stripe Processing Engine for enaizi-mfa
 * Location: /includes/stripe.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. Initialize Stripe SDK
if ( file_exists( plugin_dir_path( __FILE__ ) . 'stripe-php/init.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'stripe-php/init.php';
}


// 1. Register Custom WordPress REST API Routes
add_action( 'rest_api_init', 'mfa_register_stripe_api_routes' );

function mfa_register_stripe_api_routes() {
    register_rest_route( 'mfa/v1', '/create-session', array(
        'methods'             => 'POST',
        'callback'            => 'mfa_handle_create_checkout_session',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'mfa/v1', '/stripe-webhook', array(
        'methods'             => 'POST',
        'callback'            => 'mfa_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ) );
}

/**
 * Endpoint 1: Create Stripe Checkout Session
 */
function mfa_handle_create_checkout_session( WP_REST_Request $request ) {
    // Safely load the Stripe API Key
    if ( ! defined( 'STRIPE_SECRET_KEY' ) ) {
        return new WP_Error( 'missing_key', 'Stripe Secret Key is not defined in wp-config.php', array( 'status' => 500 ) );
    }
    \Stripe\Stripe::setApiKey( STRIPE_SECRET_KEY );

    // Tell PHP to look for 'name' OR 'mfa_name' just in case JS sends it differently
    $name_raw = $request->get_param( 'name' ) ?: $request->get_param( 'mfa_name' );
    $name     = sanitize_text_field( $name_raw );
    
    $email    = sanitize_email( $request->get_param( 'email' ) );
    $whatsapp = sanitize_text_field( $request->get_param( 'whatsapp' ) );

    // Clear out non-numeric characters on backend to guarantee formatting cleanliness
    $whatsapp = preg_replace( '/[^0-9]/', '', $whatsapp );

    // Quick Data Sanitization Integrity Check
    if ( empty( $name ) || empty( $email ) || strlen( $whatsapp ) < 7 ) {
        return new WP_Error( 
            'missing_fields', 
            'Please fill in all required fields with a valid phone number. (Received Name: ' . $name . ', Email: ' . $email . ', Phone: ' . $whatsapp . ')', 
            array( 'status' => 400 ) 
        );
    }

    // Clear out non-numeric characters on backend to guarantee formatting cleanliness
    $whatsapp = preg_replace( '/[^0-9]/', '', $whatsapp );

    // Quick Data Sanitization Integrity Check
    if ( empty( $name ) || empty( $email ) || strlen( $whatsapp ) < 7 ) {
        return new WP_Error( 
            'missing_fields', 
            'Please fill in all required fields with a valid phone number. (Received Name: ' . $name . ', Email: ' . $email . ', Phone: ' . $whatsapp . ')', 
            array( 'status' => 400 ) 
        );
    }

    // Clear out non-numeric characters on backend to guarantee formatting cleanliness
    $whatsapp = preg_replace( '/[^0-9]/', '', $whatsapp );

    // Quick Data Sanitization Integrity Check
    if ( empty( $name ) || empty( $email ) || strlen( $whatsapp ) < 7 ) {
        return new WP_Error( 
            'missing_fields', 
            'Please fill in all required fields with a valid phone number.', 
            array( 'status' => 400 ) 
        );
    }
    
    // Read country cookie directly from the user's browser headers
    $cookie_country = ! empty( $_COOKIE['country'] ) ? strtoupper( sanitize_text_field( $_COOKIE['country'] ) ) : '';

    // Pricing Router Logic using the Cookie data
    if ( $cookie_country === 'MALAYSIA' ) {
        $currency = 'myr';
        $amount   = 130.00; 
    } elseif ( $cookie_country === 'UNITED KINGDOM' ) {
        $currency = 'gbp';
        $amount   = 23.00; 
    } else {
        $currency = 'usd';
        $amount   = 29.90;  
    }

    // Convert to cents safely
    $stripe_amount = round( $amount * 100 ); 

    try {
        $session = \Stripe\Checkout\Session::create([
            'customer_email' => $email,
            'line_items'     => [[
                'price_data' => [
                    'currency'     => $currency,
                    'product_data' => [
                        'name' => 'Lifetime Premium Membership',
                    ],
                    'unit_amount'  => $stripe_amount,
                ],
                'quantity'   => 1,
            ]],
            'mode'        => 'payment',
            
            // Append the dynamic Stripe Session ID to the URL securely
            'success_url' => home_url( '/payment-success/' ) . '?session_id={CHECKOUT_SESSION_ID}',
            
            'cancel_url'  => esc_url( home_url( '/payment-failed/' ) ),
            'metadata'    => [
                'user_name'     => $name,
                'user_email'    => $email,
                'user_whatsapp' => $whatsapp, 
                'description'   => 'Lifetime Premium Membership',
                'country'       => $cookie_country
            ],
        ]);
    
        return rest_ensure_response( array( 'url' => $session->url ) );
    
    } catch ( Exception $e ) {
        return new WP_Error( 'stripe_error', $e->getMessage(), array( 'status' => 500 ) );
    }
}

/**
 * Endpoint 2: Listen for Secure Stripe Webhook Events
 */
function mfa_handle_stripe_webhook( WP_REST_Request $request ) {
    if ( ! defined( 'MFA_STRIPE_WEBHOOK_SECRET' ) ) {
        return new WP_REST_Response( 'Webhook secret not configured', 500 );
    }

    $payload    = $request->get_body();
    $sig_header = $request->get_header( 'stripe-signature' );
    $event      = null;

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, MFA_STRIPE_WEBHOOK_SECRET
        );
    } catch( \Stripe\Exception\SignatureVerificationException $e ) {
        return new WP_REST_Response( 'Invalid signature', 400 );
    }

    if ( $event->type === 'checkout.session.completed' ) {
        $session = $event->data->object;
        
        $metadata = $session->metadata;
        $name     = sanitize_text_field( $metadata->user_name );
        $email    = sanitize_email( $metadata->user_email );
        $whatsapp = sanitize_text_field( $metadata->user_whatsapp );
        $desc     = sanitize_text_field( $metadata->description );
        $country  = sanitize_text_field( $metadata->country );
        
        $stripe_session_id = $session->id;

        // Extract Checkout Currency and Actual Charged Amount
        $session_currency = strtoupper( $session->currency ); 
        $charge_amount    = $session->amount_total / 100;    

        // Multi-Currency Tracking Logic
        if ( $session_currency === 'MYR' ) {
            $amount       = 29.90;          
            $local_amount = $charge_amount; 
        } elseif ( $session_currency === 'GBP' ) {
            $amount       = 29.90;          
            $local_amount = $charge_amount;     
        } else {
            $amount       = $charge_amount; 
            $local_amount = $charge_amount; 
        }

        global $wpdb;

        // JetEngine CCT Payment Table Duplicate Entry Check
        $payment_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT _ID FROM wp_jet_cct_payment WHERE stripe_session_id = %s", 
            $stripe_session_id
        ) );

        if ( ! $payment_exists ) {
            
            // FIXED: Cleaned up JetEngine syntax issues (removed boolean assignment in args, added string quotes to _ID)
            $user_id   = niz_user_register( $whatsapp, $name, true );
            $member_id = niz_user_field_by_userid( $user_id, '_ID' );
            niz_user_update_field( $user_id, 'status', 'Premium Lifetime' );
            niz_user_update_field( $user_id, 'email', $email );
            niz_user_update_field( $user_id, 'country', $country );
            niz_user_update_field( $user_id, 'chk_premium', 'Yes' );
            
            // Issue Barakah Points 
            niz_user_add_points($user_id, 'Welcome Bonus', 50);
            niz_user_add_points($user_id, 'Founding Member Bonus', 1000);
            
            // Issue Platform Credit
            niz_user_add_credit($user_id, 'Founding Member', $amount);
            
            
            // Log transaction record into CCT Payment table
            $wpdb->insert(
                'wp_jet_cct_payment',
                array(
                    'user_id'           => $user_id, 
                    'description'       => $desc, 
                    'amount'            => $amount,            
                    'currency'          => $session_currency,  
                    'local_amount'      => $local_amount,      
                    'stripe_session_id' => $stripe_session_id,
                    'payment_status'    => 'Completed',
                    'paid_at'           => current_time( 'mysql' )
                ),
                array( '%d', '%s', '%f', '%s', '%f', '%s', '%s', '%s' )
            );
        }
    }

    return new WP_REST_Response( 'Webhook handled successfully', 200 );
}
