<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_App_Config {


    /**
     * Get current application settings
     */
    public static function get() {


        return array(

            'name' =>
            get_option(
                'niz_app_name',
                'Enaizi'
            ),


            'email' =>
            get_option(
                'niz_app_email',
                get_option(
                    'admin_email'
                )
            ),


            'logo' =>
            get_option(
                'niz_app_logo',
                ''
            ),


            'url' =>
            home_url()


        );


    }


}