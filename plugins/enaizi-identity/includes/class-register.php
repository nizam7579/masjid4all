<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Register {


public static function init(){

    
    add_action(
    'wp_ajax_nopriv_niz_register',
    array(
    __CLASS__,
    'register'
    )
    );
    
    
    
    add_action(
    'wp_ajax_niz_register',
    array(
    __CLASS__,
    'register'
    )
    );
    
    
    }
    
    
    
    
    // REGISTER USER
    public static function register(){

        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'niz_register_nonce' ) ){
            wp_send_json_error(
                array(
                'message'=>'Security verification failed.'
                )
            );
        }

        $name     = sanitize_text_field($_POST['name'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '' );
        $phone    = sanitize_text_field($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        
        if(empty($name) || empty($email) || empty($password) ){
            wp_send_json_error(
            array(
                'message'=>'Please complete all required fields.'
                )
            );
        }

        if(!is_email($email)){
            wp_send_json_error(
                array(
                    'message'=>'Please enter a valid email.'
                )
            );
        }
        
        if($password !== $confirm){
            wp_send_json_error(
                array(
                'message'=>'Passwords do not match.'
                )
            );
        }

        if(email_exists($email)){
            wp_send_json_error(
            array(
                'message'=>'Email already registered.'
                )
            );
        }
        
        $username = sanitize_user(current(explode('@', $email )));

        if(username_exists($username)){
            $username .= wp_rand(100,999);
        }
        
        $user_data = [
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'first_name'   => $name,
            'display_name' => $name,         // Can be a full name, nickname, etc.
            'role'         => 'subscriber'   // Optional: defaults to the default site role
        ];
        
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            wp_send_json_error(
                array(
                    'message' => $user_id->get_error_message()
                )
            );
        }
        
        /*
        
        $user_id = wp_create_user(
            $username,
            $password,
            $email
        );
        
        if(is_wp_error($user_id)){
            wp_send_json_error(
                array(
                'message'=>$user_id->get_error_message()
                )
            );
        }
        
        wp_update_user(
            array(
                'ID' => $user_id,
                'display_name' => $name,
                'first_name'   => $name
            )
        );
        
        // optional explicit meta update
        update_user_meta(
            $user_id,
            'first_name',
            $name
        );
        
        */
        
        
        update_user_meta($user_id, 'niz_email_verified', 'No' );
        update_user_meta($user_id, 'niz_google_connected', 'No' );
        update_user_meta($user_id, 'niz_whatsapp_verified', 'No' );
        
        if(!empty($phone)){
            update_user_meta($user_id, 'user_phone', $phone );
        }

        $token = Niz_Email_Verification::generate_token($user_id  );

        Niz_Email_Verification::send_email(
            $user_id,
            $token
        );

        // Creates the jet_cct_member row, syncs name/email, marks
        // user_status 'member', and awards the Welcome Bonus - the same
        // shared step Google sign-up and WhatsApp registration also use
        // (2026-08-08), so all three paths behave identically instead of
        // only this one remembering to award points.
        if ( function_exists( 'niz_user_complete_registration' ) ) {
            niz_user_complete_registration( $user_id, array( 'name' => $name, 'email' => $email, 'route' => 'web' ) );
        } elseif ( function_exists( 'mfa_award_points' ) ) {
            mfa_award_points( $user_id, 'Welcome Bonus', 50 );
        }

        wp_set_current_user($user_id);
        
        wp_set_auth_cookie($user_id );

        // Return to whichever page the registration form was submitted
        // from (add-mosque, add-business, add-website, member, ...) instead
        // of always landing on /member/ - matches how login's redirect
        // already behaves (class-ajax.php).
        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = home_url( '/member/' );
        }

        wp_send_json_success(
            array(
            'message'=>'Registration successful. Please verify your email.',
            'redirect'=>$redirect
            )
        );

    }


}