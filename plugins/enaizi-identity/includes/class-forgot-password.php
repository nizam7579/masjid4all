<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Forgot_Password {


    public static function init() {


        add_action(
            'wp_ajax_nopriv_niz_forgot_password',
            array(
                __CLASS__,
                'send_reset_email'
            )
        );


    }



    public static function send_reset_email() {


        check_ajax_referer(
            'niz_forgot_password',
            'nonce'
        );



        $email =
        sanitize_email(
            $_POST['email'] ?? ''
        );



        if (
            empty($email)
            ||
            !is_email($email)
        ) {


            wp_send_json_error(
                array(
                    'message'=>
                    'Please enter a valid email address.'
                )
            );

        }



        $user =
        get_user_by(
            'email',
            $email
        );



        if (
            !$user
        ) {


            // Do not reveal if email exists
            wp_send_json_success(
                array(
                    'message'=>
                    'If this email exists, a reset link has been sent.'
                )
            );


        }



        /**
         * Generate WP reset key
         */
        $key =
        get_password_reset_key(
            $user
        );



        if (
            is_wp_error($key)
        ) {


            wp_send_json_error(
                array(
                    'message'=>
                    'Unable to generate reset link.'
                )
            );


        }



        $reset_url =
        add_query_arg(

            array(

                'key'=>$key,

                'login'=>
                rawurlencode(
                    $user->user_login
                )

            ),

            home_url(
                '/reset-password/'
            )

        );



        $html = '

        <h2>
        Reset Your Password
        </h2>


        <p>
        We received a request to reset your password.
        </p>


        <p>
        Click the button below:
        </p>


        <p>

        <a href="'.$reset_url.'"
        style="
        padding:12px 20px;
        background:#1a73e8;
        color:white;
        text-decoration:none;
        border-radius:5px;
        ">

        Reset Password

        </a>

        </p>


        <p>
        If you did not request this, ignore this email.
        </p>

        ';



        Niz_Mailer::send(

            $user->user_email,

            'Reset your password',

            $html

        );



        wp_send_json_success(
            array(
                'message'=>
                'If this email exists, a reset link has been sent.'
            )
        );


    }


}