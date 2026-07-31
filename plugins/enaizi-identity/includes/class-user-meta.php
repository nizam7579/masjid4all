<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_User_Meta {


    /**
     * Initialize hooks
     */
    public static function init() {


        /**
         * Create default identity meta
         * when a new user is registered
         */
        add_action(
            'user_register',
            array(
                __CLASS__,
                'create_default_meta'
            )
        );


    }



    /**
     * Create default Enaizi Identity meta
     *
     * @param int $user_id
     */
    public static function create_default_meta($user_id) {


        $defaults = array(

            'niz_email_verified' => 'No',

            'niz_google_connected' => 'No',

            'niz_whatsapp_verified' => 'No',

            'niz_person_verified' => 'No',

            'niz_phone_verified' => 'No',

            'niz_phone_number' => '',

            'niz_trust_score' => 0,

            'niz_verification_level' => 0,

            'niz_login_count' => 0,
            
            'niz_email_verification_token' => '',

            'niz_email_verification_expiry' => '',
            
            'niz_email_verified_at' => '',

        );


        foreach ($defaults as $key => $value) {


            /**
             * Do not overwrite existing values
             */
            if (!metadata_exists(
                'user',
                $user_id,
                $key
            )) {


                update_user_meta(
                    $user_id,
                    $key,
                    $value
                );


            }

        }


    }



    /**
     * Get identity meta
     *
     * @param int $user_id
     * @param string $key
     */
    public static function get($user_id, $key) {


        return get_user_meta(
            $user_id,
            $key,
            true
        );


    }



    /**
     * Update identity meta
     *
     * @param int $user_id
     * @param string $key
     * @param mixed $value
     */
    public static function update(
        $user_id,
        $key,
        $value
    ) {


        return update_user_meta(
            $user_id,
            $key,
            sanitize_text_field($value)
        );


    }



    /**
     * Check verification status
     */
    public static function is_verified(
        $user_id,
        $type
    ) {


        $allowed = array(

            'email' => 'niz_email_verified',

            'phone' => 'niz_phone_verified',

            'whatsapp' => 'niz_whatsapp_verified',

            'person' => 'niz_person_verified',

        );


        if (!isset($allowed[$type])) {

            return false;

        }


        return (
            self::get(
                $user_id,
                $allowed[$type]
            ) === 'Yes'
        );


    }


}