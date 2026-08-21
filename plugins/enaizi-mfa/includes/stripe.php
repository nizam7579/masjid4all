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
    // Founding Member is deactivated - see mfa_founding_member_enabled() in
    // mfa-core/includes/founding-member.php. Refused here as well as at the
    // grant so that nobody can complete a payment for something that will not
    // be granted. Checked before the Stripe key so a disabled offer reports
    // as unavailable rather than as a misconfiguration.
    if ( function_exists( 'mfa_founding_member_enabled' ) && ! mfa_founding_member_enabled() ) {
        return new WP_Error(
            'founding_member_disabled',
            'Founding Member is not open for sign-ups at the moment.',
            array( 'status' => 503 )
        );
    }

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

        // Shared with /payment-success/'s mfa_render_payment_success_details()
        // (mfa-core/includes/founding-member.php) - see that function's own
        // docblock for why these two paths needed unifying (2026-08-09).
        if ( function_exists( 'mfa_grant_founding_member_benefits' ) ) {
            mfa_grant_founding_member_benefits( $session );
        }
    }

    return new WP_REST_Response( 'Webhook handled successfully', 200 );
}
