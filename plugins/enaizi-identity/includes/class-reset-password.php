<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Reset_Password {


    public static function init() {


        add_action(
            'wp_ajax_nopriv_niz_reset_password',
            array(
                __CLASS__,
                'reset_password'
            )
        );


    }




    public static function reset_password() {


        check_ajax_referer(
            'niz_reset_password',
            'nonce'
        );



        $login =
        sanitize_text_field(
            $_POST['login'] ?? ''
        );


        $key =
        sanitize_text_field(
            $_POST['key'] ?? ''
        );


        $password =
        $_POST['password'] ?? '';



        if (
            empty($login)
            ||
            empty($key)
            ||
            empty($password)
        ) {


            wp_send_json_error(
                array(
                    'message'=>
                    'Invalid reset request.'
                )
            );


        }



        $user =
        check_password_reset_key(
            $key,
            $login
        );



        if (
            is_wp_error($user)
        ) {


            wp_send_json_error(
                array(
                    'message'=>
                    'This reset link is invalid or expired.'
                )
            );


        }



        if (
            strlen($password) < 8
        ) {


            wp_send_json_error(
                array(
                    'message'=>
                    'Password must be at least 8 characters.'
                )
            );


        }



        reset_password(
            $user,
            $password
        );



        /**
         * Auto login
         */
        wp_set_current_user(
            $user->ID
        );


        wp_set_auth_cookie(
            $user->ID
        );



        wp_send_json_success(
            array(

                'message'=>
                'Password updated successfully.',

                'redirect'=>
                home_url('/member/')

            )
        );


    }


}