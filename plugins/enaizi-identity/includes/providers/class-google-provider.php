<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Google_Provider {


    public static function init(){


        add_action(
            'init',
            array(
                __CLASS__,
                'handle_callback'
            )
        );


    }



    public static function login_url(){


        $options =
        get_option(
            'niz_identity_options',
            array()
        );


        if ( ! defined( 'NIZ_GOOGLE_CLIENT_ID' ) || '' === (string) constant( 'NIZ_GOOGLE_CLIENT_ID' ) ) {
            // Google sign-in not configured on this site (the constant is not
            // defined in wp-config). Return empty so the caller hides the
            // button, instead of a fatal "undefined constant" error on PHP 8.
            return '';
        }

        $client_id = NIZ_GOOGLE_CLIENT_ID;


        
        $current_page =
        home_url(
            add_query_arg(
                null,
                null
            )
        );
        
        
        setcookie(
            'niz_google_redirect',
            $current_page,
            time() + 600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
        
        
        $redirect =
        home_url(
            '?niz_google_callback=1'
        );



        return
        'https://accounts.google.com/o/oauth2/v2/auth?'
        .
        http_build_query(

            array(

                'client_id'=>$client_id,

                'redirect_uri'=>$redirect,

                'response_type'=>'code',

                'scope'=>
                'openid email profile',

                'access_type'=>'offline',

                'prompt'=>'select_account'

            )

        );


    }





    public static function handle_callback(){


        if(
            !isset($_GET['niz_google_callback'])
        ){

            return;

        }


        if(
            empty($_GET['code'])
        ){

            return;

        }

        if ( ! defined( 'NIZ_GOOGLE_CLIENT_ID' ) || ! defined( 'NIZ_GOOGLE_CLIENT_SECRET' ) ) {
            return;
        }

        // Nest Step
        $code = sanitize_text_field(
            $_GET['code']
        );
        
        
        
        $response = wp_remote_post(
        
            'https://oauth2.googleapis.com/token',
        
            array(
        
                'body'=>array(
        
                    'code'=>$code,
        
                    'client_id'=>NIZ_GOOGLE_CLIENT_ID,
        
                    'client_secret'=>NIZ_GOOGLE_CLIENT_SECRET,
        
                    'redirect_uri'=>home_url(
                        '?niz_google_callback=1'
                    ),
        
                    'grant_type'=>
                    'authorization_code'
        
                )
        
            )
        
        );
        
        
        
        if(
            is_wp_error($response)
        ){
        
            wp_die(
                'Google login failed.'
            );
        
        }
        
        
        
        $data =
        json_decode(
            wp_remote_retrieve_body($response),
            true
        );
        
        
        
        if(
            empty($data['access_token'])
        ){
        
            wp_die(
                'Unable to authenticate Google.'
            );
        
        }
        
        
        
        $google_response =
        wp_remote_get(
        
            'https://www.googleapis.com/oauth2/v2/userinfo',
        
            array(
        
                'headers'=>array(
        
                    'Authorization'=>
                    'Bearer ' .
                    $data['access_token']
        
                )
        
            )
        
        );
        
        
        
        $google_user =
        json_decode(
        
            wp_remote_retrieve_body(
                $google_response
            ),
        
            true
        
        );
        
        if (
            empty($google_user['email'])
        ) {
        
            wp_die(
                'Google email not available.'
            );
        
        }
        
        
        $email = sanitize_email(
            $google_user['email']
        );
        
        
        $name = sanitize_text_field(
            $google_user['name'] ?? ''
        );
        
        
        $google_id = sanitize_text_field(
            $google_user['id'] ?? ''
        );
        
        
        
        /*
         * Check existing user by email
         */
        $user = get_user_by(
            'email',
            $email
        );
        
        
        
        if ($user) {
        
        
            /*
             * Existing account
             * Link Google
             */
        
            update_user_meta(
                $user->ID,
                'niz_google_connected',
                'Yes'
            );
        
        
            update_user_meta(
                $user->ID,
                'niz_google_id',
                $google_id
            );
        
        
        }
        else {
        
        
            /*
             * New Google user
             */
        
            $username =
            sanitize_user(
                current(
                    explode(
                        '@',
                        $email
                    )
                )
            );
        
        
            /*
             * Ensure username unique
             */
            if (
                username_exists($username)
            ) {
        
                $username .=
                wp_rand(100,999);
        
            }
        
        
        
            $random_password =
            wp_generate_password(
                16,
                true
            );
        
        
        
            $user_id =
            wp_create_user(
        
                $username,
        
                $random_password,
        
                $email
        
            );
        
        
        
            if (
                is_wp_error($user_id)
            ) {
        
                wp_die(
                    'Unable to create account.'
                );
        
            }
        
        
        
            wp_update_user(
        
                array(
        
                    'ID'=>$user_id,
                    'display_name'=>$name,
                    'first_name'=>$name,
        
                )
        
            );
        
        
        
            /*
             * Enaizi identity meta
             */
        
            update_user_meta(
                $user_id,
                'niz_google_connected',
                'Yes'
            );
        
        
            update_user_meta(
                $user_id,
                'niz_google_id',
                $google_id
            );
        
        
            /*
             * Google verified email
             */
        
            update_user_meta(
                $user_id,
                'niz_email_verified',
                'Yes'
            );
        
        
            update_user_meta(
                $user_id,
                'niz_trust_score',
                10
            );


            /*
             * Creates the jet_cct_member row, marks user_status 'member',
             * and awards the Welcome Bonus - the same shared step the
             * email/password and WhatsApp registration paths use
             * (2026-08-08). Previously only the email/password path did
             * this, so a Google sign-up never got a cct row or the bonus.
             */
            if ( function_exists( 'niz_user_complete_registration' ) ) {
                niz_user_complete_registration(
                    $user_id,
                    array(
                        'name'  => $name,
                        'email' => $email,
                    )
                );
            }


            $user =
            get_user_by(
                'id',
                $user_id
            );


        }
        
        
        
        /*
         * Login user
         */
        
        wp_set_current_user(
            $user->ID
        );
        
        
        wp_set_auth_cookie(
            $user->ID
        );
        
        
        
        /*
         * Redirect back to current site
         */
        
        $redirect = wp_get_referer();
        
        
        if (
            empty($redirect)
        ) {
        
            $redirect = home_url();
        
        }
        
        
        $redirect = home_url();


        if (
            isset($_COOKIE['niz_google_redirect'])
            &&
            !empty($_COOKIE['niz_google_redirect'])
        ) {
        
            $redirect = esc_url_raw(
                $_COOKIE['niz_google_redirect']
            );
        
        }
        
        
        // Clear cookie
        
        setcookie(
            'niz_google_redirect',
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
        
        
        wp_safe_redirect(
            $redirect
        );
        
        exit;
        
    }



}