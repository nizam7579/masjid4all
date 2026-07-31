<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Shortcodes {


    public static function init() {


        add_shortcode(
            'niz_login',
            array(
                __CLASS__,
                'login'
            )
        );

        add_shortcode(
            'niz_register',
            array(
                __CLASS__,
                'register'
            )
        );

        add_shortcode(
            'niz_test',
            array(
                __CLASS__,
                'test'
            )
        );
        
        add_shortcode(
            'niz_login_status',
            array(
                __CLASS__,
                'login_status'
            )
        );
        
        add_shortcode(
            'niz_email_verified',
            array(
                __CLASS__,
                'email_verified'
            )
        );
        
        add_shortcode(
            'niz_forgot_password',
            array(
                __CLASS__,
                'forgot_password'
            )
        );
        
        add_shortcode(
            'niz_reset_password',
            array(
                __CLASS__,
                'reset_password'
            )
        );


    }



    public static function register() {
    
    
        if (is_user_logged_in()) {
            wp_redirect(home_url('/member'));
            exit;
        }
    
    
        ob_start();
    
        include NIZ_IDENTITY_PATH .
        'templates/register.php';
    
        return ob_get_clean();
    
    
    }



    /**
     * Login shortcode
     */
    public static function login() {


        if (
            is_user_logged_in()
        ) {


            return '
            <div class="niz-login-success">
                You are already logged in.
            </div>';


        }



        ob_start();


        include NIZ_IDENTITY_PATH .
        'templates/login.php';



        return ob_get_clean();


    }
    
    public static function login_status() {


        if (!is_user_logged_in()) {
    
    
            return '
            <div class="niz-status-card">
    
                <h3>
                    Not Logged In
                </h3>
    
                <p>
                    Please login to access your account.
                </p>
    
            </div>';
    
    
        }
    
    
    
        $user = wp_get_current_user();
    
    
        $fields = array(
    
            'Email Verified' =>
            'niz_email_verified',
    
            'Google Connected' =>
            'niz_google_connected',
    
            'WhatsApp Verified' =>
            'niz_whatsapp_verified',
    
            'Person Verified' =>
            'niz_person_verified',
    
        );
    
    
        ob_start();
    
        ?>
    
        <div class="niz-status-card">
    
    
            <h3>
                Welcome,
                <?php echo esc_html(
                    $user->display_name
                ); ?>
            </h3>
    
    
    
            <p>
                <strong>
                    Username:
                </strong>
    
                <?php echo esc_html(
                    $user->user_login
                ); ?>
    
            </p>
    
     <p>
                <strong>
                    First Name:
                </strong>
    
                <?php echo esc_html(
                    $user->first_name
                ); ?>
    
            </p>
    
            <p>
                <strong>
                    Email:
                </strong>
    
                <?php echo esc_html(
                    $user->user_email
                ); ?>
    
            </p>
    
    
    
            <hr>
    
    
    
            <h4>
                Identity Verification
            </h4>
    
    
    
            <?php foreach ($fields as $label => $meta_key):
    
                $value = get_user_meta(
                    $user->ID,
                    $meta_key,
                    true
                );
    
    
                $verified = (
                    $value === 'Yes'
                );
    
            ?>
    
    
            <p>
    
                <?php echo esc_html($label); ?>
    
                :
    
                <?php echo $verified
                    ? '✅'
                    : '❌';
                ?>
    
    
            </p>
    
    
            <?php endforeach; ?>
    
    
    
            <hr>
    
    
    
            <p>
    
                <strong>
                    Trust Score:
                </strong>
    
    
                <?php echo esc_html(
                    get_user_meta(
                        $user->ID,
                        'niz_trust_score',
                        true
                    )
                ); ?>
    
    
            </p>
    
    
    
            <p>
    
                <strong>
                    Verification Level:
                </strong>
    
    
                <?php echo esc_html(
                    get_user_meta(
                        $user->ID,
                        'niz_verification_level',
                        true
                    )
                ); ?>
    
    
            </p>
            
            
            <p>

                <strong>
                Email Verification:
                </strong>
                
                
                <?php
                
                
                
                $email_verified = get_user_meta($user->ID,'niz_email_verified', true );
                
                if (
                $email_verified === 'Yes'
                ){
                
                echo '✅ Verified';
                
                
                }
                
                else {
                
                
                echo '❌ Not Verified';
                
                
                ?>
                
                
                <br>
                
                
                <button
                class="niz-resend-email"
                >
                
                Resend Verification Email
                
                </button>
                
                
                <span
                id="niz-email-message"
                ></span>
                
                
                <?php
                
                }
                
                
                ?>
                
                </p>
    
    
        </div>
    
    
        <?php
    
    
        return ob_get_clean();
    
    
    }
    
    public static function email_verified() {


        if (
            isset($_GET['niz_verify_status'])
            &&
            $_GET['niz_verify_status'] === 'success'
        ) {
    
    
            return '
            <div class="niz-success-card">
    
                <h2>
                ✅ Email Verified Successfully
                </h2>
    
                <p>
                Your email address has been verified.
                </p>
   
            </div>';
    
        }
    
    
        return '
        <div class="niz-error-card">
    
            <h2>
            Verification Link Invalid
            </h2>
    
            <p>
            Please request a new verification email.
            </p>
    
        </div>';
    
    }
    
    
    public static function forgot_password() {

    
        ob_start();
    
    
        include NIZ_IDENTITY_PATH .
        'templates/forgot-password.php';
    
    
        return ob_get_clean();
    
    }
    
    public static function reset_password() {
        ob_start();
        include NIZ_IDENTITY_PATH .
        'templates/reset-password.php';
        return ob_get_clean();
    }


}