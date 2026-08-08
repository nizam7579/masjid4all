<?php
/**
 * Shortcode to display the Stripe Payment Form for masjid4all
 * Location: /shortcode/stripe.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


add_shortcode( 'mfa_stripe_form', 'mfa_render_stripe_payment_form' );

function mfa_render_stripe_payment_form() {
    
    // 1. Enqueue Existing Plugin Assets
    wp_enqueue_style( 'mfa-stripe-css', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/stripe.css', array(), '1.0.0' );
    wp_enqueue_script( 'mfa-stripe-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/stripe.js', array(), '1.0.0', true );

    wp_localize_script( 'mfa-stripe-js', 'mfaStripeSettings', array(
        'api_url' => esc_url_raw( rest_url( 'mfa/v1/create-session' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' )
    ) );

    // 2. Enqueue intl-tel-input Assets
    wp_enqueue_style( 'intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.1/build/css/intlTelInput.css', array(), '24.5.1' );
    wp_enqueue_script( 'intl-tel-input-js', 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.1/build/js/intlTelInput.min.js', array(), '24.5.1', true );
    
    $intl_css_fix = '
        .iti { width: 100%; display: block; }
        .iti__dropdown-content { z-index: 999999 !important; }
        .mfa-whatsapp-input-wrapper { overflow: visible !important; }
        
        .iti__selected-dial-code { 
            color: #000000 !important; 
            background-color: #fff !important; 
        }
        
        .iti--allow-dropdown input[type="tel"], 
        .iti--separate-dial-code input[type="tel"] {
            padding-left: 90px !important; 
        }
    ';
    wp_add_inline_style( 'intl-tel-input-css', $intl_css_fix );

    // 3. Inject Reusable Initialization Script
    $intl_init_script = '
        document.addEventListener("DOMContentLoaded", function() {
            if (window.mfaIntlLoaded) return; 
            window.mfaIntlLoaded = true;

            var phoneInputs = document.querySelectorAll(".mfa-global-phone, #mfa-whatsapp");
            if (phoneInputs.length === 0) return;

            function getCookie(name) {
                var value = "; " + document.cookie;
                var parts = value.split("; " + name + "=");
                if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
                return null;
            }

            var finalIso2 = "my"; 
            try {
                var cookieCountry = getCookie("country"); 
                if (cookieCountry) {
                    cookieCountry = cookieCountry.toLowerCase().trim();
                    var countryData = window.intlTelInput.getCountryData(); 
                    for (var i = 0; i < countryData.length; i++) {
                        if (countryData[i].name.toLowerCase().indexOf(cookieCountry) !== -1) {
                            finalIso2 = countryData[i].iso2;
                            break;
                        }
                    }
                }
            } catch (error) {
                console.error("Could not read country cookie, falling back to default.", error);
            }

            phoneInputs.forEach(function(input) {
                var iti = window.intlTelInput(input, {
                    initialCountry: finalIso2,
                    countrySearch: true,
                    separateDialCode: true,
                    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.1/build/js/utils.js"
                });

                var syncNumber = function() {
                    var fullNumber = iti.getNumber().replace("+", ""); 
                    
                    var loginWrapper = input.closest(".niz-user-form-wrapper");
                    if (loginWrapper) {
                        var hiddenAjaxInput = loginWrapper.querySelector(".niz_user_phone");
                        if (hiddenAjaxInput) {
                            hiddenAjaxInput.value = fullNumber;
                        }
                    }

                    var stripeHiddenInput = document.querySelector("#mfa-whatsapp-hidden");
                    if (stripeHiddenInput && input.id === "mfa-whatsapp") {
                        stripeHiddenInput.value = fullNumber;
                    }
                };

                input.addEventListener("input", syncNumber);
                input.addEventListener("countrychange", syncNumber);
                
                setTimeout(syncNumber, 500); 
            });
        });
    ';
    wp_add_inline_script( 'intl-tel-input-js', $intl_init_script );

    // 4. Generate the Stripe Form Markup
    ob_start();
    ?>
    <div class="mfa-stripe-form-container">
        <form id="mfa-stripe-payment-form">
            <div class="mfa-form-group">
                <label for="mfa-name">Full Name (Nama Penuh)</label>
                <input type="text" id="mfa-name" name="name" placeholder="e.g. Ahmad bin Ali" required />
            </div>

            <div class="mfa-form-group">
                <label for="mfa-email">Email Address</label>
                <input type="email" id="mfa-email" name="email" placeholder="e.g. ahmad@gmail.com" required />
            </div>

            <div class="mfa-form-group">
                <label for="mfa-whatsapp">WhatsApp Number</label>
                <div class="mfa-whatsapp-input-wrapper">
                    <input type="tel" id="mfa-whatsapp" class="mfa-global-phone" required />
                </div>
                
                <input type="hidden" name="whatsapp" id="mfa-whatsapp-hidden">
                
                <small class="mfa-form-help">Select your country code and enter your mobile number.</small>
            </div>

            <div id="mfa-form-error" class="mfa-error-message" style="display: none;"></div>

            <button type="submit" id="mfa-submit-btn" class="mfa-submit-button">
                <span class="btn-text">Proceed to Secure Payment</span>
                <span class="btn-spinner" style="display: none;"></span>
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * Shortcode to display Stripe metadata on the success page
 * Usage: [mfa_payment_success_details]
 */
add_shortcode('mfa_payment_success_details', 'mfa_render_payment_success_details');

function mfa_render_payment_success_details() {
    if ( ! isset( $_GET['session_id'] ) || empty( $_GET['session_id'] ) ) {
        return '<div class="mfa-error-message">No valid payment session found.</div>';
    }

    $session_id = sanitize_text_field( $_GET['session_id'] );

    try {
        // 1. CRITICAL: Initialize your Stripe API Key!
        if ( ! defined( 'STRIPE_SECRET_KEY' ) ) {
            return '<div class="mfa-error-message">Debug Error: Stripe Secret Key is not defined in wp-config.php.</div>';
        }
        $stripe_secret_key = STRIPE_SECRET_KEY;

        if ( ! class_exists( '\Stripe\Stripe' ) ) {
            return '<div class="mfa-error-message">Debug Error: Stripe PHP SDK is not loaded.</div>';
        }
        
        \Stripe\Stripe::setApiKey( $stripe_secret_key );

        // 2. Retrieve the session from Stripe
        $session = \Stripe\Checkout\Session::retrieve( $session_id );
        $metadata = $session->metadata;

        // 3. --- GRANT FOUNDING MEMBER BENEFITS ---
        // Client-side fallback for when the webhook doesn't reach this
        // environment (e.g. a Stripe Dashboard test-mode webhook still
        // pointed at production while testing on staging). Shares the
        // exact same grant logic and idempotency guard as the webhook -
        // mfa_grant_founding_member_benefits() (mfa-core/includes/
        // founding-member.php) - so this can no longer diverge into a
        // separate, incomplete "premium" state the way it used to
        // (previously only ever set an unused mfa_membership_status meta
        // key, never chk_premium/points/credit/payment record).
        if ( $session->payment_status === 'paid' && function_exists( 'mfa_grant_founding_member_benefits' ) ) {
            mfa_grant_founding_member_benefits( $session );
        }

        // 4. --- DISPLAY LOGIC ---
        ob_start();
        ?>
        <div class="mfa-success-details-box" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 5px solid #27ae60;">
            <h2 style="color: #27ae60; margin-top: 0;">Payment Successful!</h2>
            <p>Thank you, <strong><?php echo esc_html( $metadata->user_name ); ?></strong>. Your payment has been processed and your account has been updated.</p>
            
            <h3 style="margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Receipt Details:</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 8px;"><strong>Email:</strong> <?php echo esc_html( $metadata->user_email ); ?></li>
                <li style="margin-bottom: 8px;"><strong>WhatsApp:</strong> +<?php echo esc_html( $metadata->user_whatsapp ); ?></li>
                <li style="margin-bottom: 8px;"><strong>Plan:</strong> <?php echo esc_html( $metadata->description ); ?></li>
                <li style="margin-bottom: 8px;"><strong>Amount Paid:</strong> <?php echo strtoupper( $session->currency ) . ' ' . number_format( $session->amount_total / 100, 2 ); ?></li>
            </ul>
        </div>
        <?php
        return ob_get_clean();

    } catch ( Exception $e ) {
        // If it fails, print the exact error to the screen so you can debug it
        return '<div class="mfa-error-message" style="color: red; padding: 20px; background: #ffebee;">
            <strong>Debug Error:</strong> ' . esc_html( $e->getMessage() ) . '
            <br><br><small>Make sure your Stripe Secret Key is correct and the Stripe SDK is loaded.</small>
        </div>';
    }
}
