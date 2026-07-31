<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Ajax {


    public static function init() {


        add_action(
            'wp_ajax_nopriv_niz_login',
            array(
                __CLASS__,
                'login'
            )
        );


        add_action(
            'wp_ajax_niz_login',
            array(
                __CLASS__,
                'login'
            )
        );


    }



    /**
     * AJAX Login Handler
     */
    public static function login() {


        /**
         * Verify nonce
         */
        if (
            !isset($_POST['nonce'])
            ||
            !wp_verify_nonce(
                $_POST['nonce'],
                'niz_login_nonce'
            )
        ) {


            wp_send_json_error(
                array(
                    'message' => 'Security verification failed.'
                )
            );


        }



        /**
         * Get inputs
         */
        $identifier = isset($_POST['identifier'])
            ? sanitize_text_field(
                $_POST['identifier']
            )
            : '';



        $password = isset($_POST['password'])
            ? $_POST['password']
            : '';



        $remember = isset($_POST['remember'])
            &&
            $_POST['remember'] === 'yes';



        if (
            empty($identifier)
            ||
            empty($password)
        ) {


            wp_send_json_error(
                array(
                    'message' =>
                    'Please enter your login details.'
                )
            );


        }



        /**
         * Authenticate
         */
        $result = Niz_Login::authenticate(
            $identifier,
            $password,
            $remember
        );



        if (
            is_wp_error($result)
        ) {


            wp_send_json_error(
                array(
                    'message' =>
                    $result->get_error_message()
                )
            );


        }



        /**
         * Success
         */
        $redirect = wp_get_referer();
        
        if (!$redirect) {
        
            $redirect = home_url();
        
        }

        wp_send_json_success(
            array(
        
                'message' =>
                'Login successful.',
        
                'redirect' =>
                $redirect
        
            )
        );


    }


}