<?php

if (!defined('ABSPATH')) {
    exit;
}


class Enaizi_Identity_Loader {


    /**
     * Initialize plugin modules
     */
    public static function init() {


        self::load_dependencies();


        self::initialize_modules();

    }



    /**
     * Load required files
     */
    private static function load_dependencies() {


        $files = array(

            'class-user-meta.php',
            'class-security.php',
            'class-ajax.php',
            'class-shortcodes.php',
            'class-settings.php',
            'class-login.php',
            'class-assets.php',
            'class-register.php',
            'class-email-verification.php',
            'class-mailer.php',
            'class-app-config.php',
            'class-forgot-password.php',
            'class-reset-password.php',
            'providers/class-google-provider.php',

        );


        foreach ($files as $file) {


            $path = NIZ_IDENTITY_PATH .
                    'includes/' .
                    $file;


            if (file_exists($path)) {

                require_once $path;

            }

        }

    }



    /**
     * Initialize classes
     */
    private static function initialize_modules() {


        if (class_exists('Niz_User_Meta')) {

            Niz_User_Meta::init();

        }


        if (class_exists('Niz_Security')) {

            Niz_Security::init();

        }


        if (class_exists('Niz_Ajax')) {

            Niz_Ajax::init();

        }


        if (class_exists('Niz_Shortcodes')) {

            Niz_Shortcodes::init();

        }


        if (class_exists('Niz_Settings')) {

            Niz_Settings::init();

        }

        if (class_exists('Niz_Login')) {
        
            Niz_Login::init();
        
        }
        
        if (class_exists('Niz_Assets')) {
        
            Niz_Assets::init();
        
        }
        
        if (class_exists('Niz_Register')) {
        
            Niz_Register::init();
        
        }
        
        if (class_exists('Niz_Email_Verification')) {
        
            Niz_Email_Verification::init();
        
        }
        
        if (class_exists('Niz_Forgot_Password')) {
        
            Niz_Forgot_Password::init();
        
        }
        if (class_exists('Niz_Reset_Password')) {
    
            Niz_Reset_Password::init();
    
        }
        
        if(class_exists('Niz_Google_Provider')){

            Niz_Google_Provider::init();
        
        }

    }

}