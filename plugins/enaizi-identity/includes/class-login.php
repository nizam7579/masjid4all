<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Login {


    public static function init() {

        // Future login hooks

    }



    /**
     * Authenticate user
     */
    public static function authenticate(
        $identifier,
        $password,
        $remember = false
    ) {


        if (!is_email($identifier)) {

            return new WP_Error(
                'invalid_email',
                'Please enter a valid email address.'
            );

        }


        $user = self::get_user_by_identifier(
            $identifier
        );


        if (!$user) {

            return new WP_Error(
                'invalid_login',
                'Invalid login details.'
            );

        }


        $creds = array(

            'user_login'    => $user->user_login,

            'user_password' => $password,

            'remember'      => $remember

        );


        $login = wp_signon(
            $creds,
            is_ssl()
        );


        if (is_wp_error($login)) {

            return new WP_Error(
                'invalid_login',
                'Invalid login details.'
            );

        }
 
        wp_set_current_user(
            $login->ID
        );
        
        wp_set_auth_cookie(
            $login->ID,
            $remember
        );

        self::update_login_meta(
            $login->ID
        );


        self::set_login_cookie(
            $identifier
        );


        return $login;


    }




    /**
     * Find user by email. Login is email/Google only (2026-08-08 decision) -
     * this used to also try a phone-number path (usermeta key
     * 'niz_phone_number', then a jet_cct_member.phone fallback), but that
     * never reliably worked (nothing ever wrote 'niz_phone_number', and the
     * cct fallback wasn't kept in sync with later WhatsApp verification) -
     * removed rather than fixed, since phone-based login is out of scope now.
     */
    private static function get_user_by_identifier(
        $identifier
    ) {


        $identifier = sanitize_text_field(
            $identifier
        );


        if (!is_email($identifier)) {

            return false;

        }


        return get_user_by(
            'email',
            $identifier
        );


    }





    /**
     * Update login information
     */
    private static function update_login_meta(
        $user_id
    ) {


        $count = (int)
            get_user_meta(
                $user_id,
                'niz_login_count',
                true
            );


        update_user_meta(
            $user_id,
            'niz_login_count',
            $count + 1
        );


        update_user_meta(
            $user_id,
            'niz_last_login',
            current_time('mysql')
        );


        // $count was read before the increment above, so 0 here means this
        // is genuinely this user's first login - not a session/auth change,
        // just a side effect after wp_signon() has already succeeded.
        // "Welcome Bonus" matches the description string the legacy
        // niz_user_info/niz_user_welcome shortcodes already use for this
        // same concept, so mfa_award_points()'s per-user dedup blocks a
        // double award if one of those shortcodes also fires for this user.
        if ($count === 0 && function_exists('mfa_award_points')) {

            mfa_award_points(
                $user_id,
                'Welcome Bonus',
                50
            );

        }


    }





    /**
     * Store last login identifier
     */
    private static function set_login_cookie(
        $identifier
    ) {


        setcookie(

            'niz_last_login',

            sanitize_text_field(
                $identifier
            ),

            time() + (
                30 * DAY_IN_SECONDS
            ),

            COOKIEPATH,

            COOKIE_DOMAIN,

            is_ssl(),

            true

        );


    }


}