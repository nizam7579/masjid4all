<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Assets {


    public static function init() {


        add_action(
            'wp_enqueue_scripts',
            array(
                __CLASS__,
                'enqueue'
            )
        );


    }



    public static function enqueue() {

        wp_enqueue_style(
            'niz-login',
            NIZ_IDENTITY_URL . 'assets/css/login.css',
            array(),
            NIZ_IDENTITY_VERSION
        );
            
        wp_enqueue_script(

            'niz-login',

            NIZ_IDENTITY_URL .
            'assets/js/login.js',

            array('jquery'),

            NIZ_IDENTITY_VERSION,

            true
            
            

        );
        
        wp_enqueue_script(

            'niz-register',

            NIZ_IDENTITY_URL .
            'assets/js/register-v2.js',

            array('jquery'),

            NIZ_IDENTITY_VERSION . '-2',

            true

        );


        wp_localize_script(

            'niz-login',

            'niz_ajax',

            array(

                'ajax_url' => admin_url(
                    'admin-ajax.php'
                )

            )

        );
        
        wp_localize_script(

            'niz-register',
        
            'niz_ajax',
        
            array(
        
                'ajax_url' =>
                admin_url(
                    'admin-ajax.php'
                )
        
            )
        
        );
        
        
        wp_enqueue_script(

            'niz-email-verification',
            
            NIZ_IDENTITY_URL .
            'assets/js/email-verification.js',
            
            array('jquery'),
            
            NIZ_IDENTITY_VERSION,
            
            true
            
            );
            
            
            
            wp_localize_script(
            
            'niz-email-verification',
            
            'niz_email',
            
            array(
            
            'nonce'=>
            wp_create_nonce(
            'niz_resend_email'
            )
            
            )
            
        );
        
        
        wp_enqueue_script(

            'niz-forgot-password',
        
            NIZ_IDENTITY_URL .
            'assets/js/forgot-password.js',
        
            array('jquery'),
        
            NIZ_IDENTITY_VERSION,
        
            true
        
        );
        
        
        wp_localize_script(
        
            'niz-forgot-password',
        
            'niz_ajax',
        
            array(
        
                'ajax_url' =>
                admin_url(
                    'admin-ajax.php'
                )
        
            )
        
        );
        
        wp_enqueue_script(
            
            'niz-reset-password',
            
            NIZ_IDENTITY_URL .
            'assets/js/reset-password.js',
            
            array('jquery'),
            
            NIZ_IDENTITY_VERSION,
            
            true
            
        );
        
        wp_localize_script(
        
            'niz-reset-password',
        
            'niz_reset_ajax',
        
            array(
        
                'ajax_url' =>
                admin_url(
                    'admin-ajax.php'
                )
        
            )
        
        );


    }


}