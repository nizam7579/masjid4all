<?php

class Niz_WA_Actions {

    public static function start($phone) {
        Niz_WA_WhatsApp::send_text(
            $phone,
            "Welcome to Masjid4All.\n\nHow can I help you?"
        );
    }

    public static function login($phone) {
        Niz_WA_WhatsApp::send_text(
            $phone,
            "You can login here:\nhttps://staging.masjid4all.com/member"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Request Password
    |--------------------------------------------------------------------------
    */

    public static function request_password($phone) {
        $user_id = niz_user_check($phone);
    
        /*
        |--------------------------------------------------------------------------
        | Not registered
        |--------------------------------------------------------------------------
        */
    
        if (!$user_id) {
            Niz_WA_Session::set($phone, [
                'state' => 'awaiting_register_confirmation'
            ]);
    
            Niz_WA_WhatsApp::send_buttons(
                $phone,
                "We could not find your account.\nWould you like to register as a free member?",
                [
                    [
                        'id' => 'CONFIRM_YES',
                        'title' => 'YES'
                    ],
                    [
                        'id' => 'CONFIRM_NO',
                        'title' => 'NO'
                    ]
                ]
            );
    
            return;
        }
    
        $status = get_user_meta($user_id, 'user_status', true);
    
        /*
        |--------------------------------------------------------------------------
        | Prospect
        |--------------------------------------------------------------------------
        */
    
        if ($status === 'prospect') {
            Niz_WA_Session::set($phone, [
                'state' => 'awaiting_register_confirmation'
            ]);
    
            Niz_WA_WhatsApp::send_buttons(
                $phone,
                "Your membership is not activated yet.\nWould you like to complete your free registration?",
                [
                    [
                        'id' => 'CONFIRM_YES',
                        'title' => 'YES'
                    ],
                    [
                        'id' => 'CONFIRM_NO',
                        'title' => 'NO'
                    ]
                ]
            );
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Member / Premium
        |--------------------------------------------------------------------------
        */
    
        Niz_WA_Session::set($phone, [
            'state' => 'awaiting_password_reset_confirmation'
        ]);
    
        Niz_WA_WhatsApp::send_buttons(
            $phone,
            "Do you want to reset your password?",
            [
                [
                    'id' => 'CONFIRM_YES',
                    'title' => 'YES'
                ],
                [
                    'id' => 'CONFIRM_NO',
                    'title' => 'NO'
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    public static function reset_password($phone) {
        $password = niz_user_password($phone);

        if (!$password) {
            Niz_WA_WhatsApp::send_text(
                $phone,
                "Unable to reset password. Please try again."
            );
            return;
        }

        Niz_WA_WhatsApp::send_text(
            $phone,
            "Your temporary password is: {$password}\n\nLogin here:\nhttps://staging.masjid4all.com/member\n\nPlease change your password after login."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    public static function register($phone) {
        $user_id = niz_user_check($phone);

        if ($user_id) {
            $info = niz_user_info($user_id);
            $name = $info['name'] ?? 'Member';

            Niz_WA_Session::set($phone, [
                'state' => 'awaiting_existing_member_action'
            ]);

            Niz_WA_WhatsApp::send_buttons(
                $phone,
                "Hi {$name}, you are already registered.\nWhat would you like to do?",
                [
                    [
                        'id' => 'LOGIN',
                        'title' => 'LOGIN'
                    ],
                    [
                        'id' => 'RESET_PASSWORD',
                        'title' => 'RESET PASSWORD'
                    ]
                ]
            );

            return;
        }

        Niz_WA_Session::set($phone, [
            'state' => 'awaiting_register_confirmation'
        ]);

        Niz_WA_WhatsApp::send_buttons(
            $phone,
            "Welcome to Masjid4All.\nWould you like to register as a free member?",
            [
                [
                    'id' => 'CONFIRM_YES',
                    'title' => 'YES'
                ],
                [
                    'id' => 'CONFIRM_NO',
                    'title' => 'NO'
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Registration
    |--------------------------------------------------------------------------
    */

    public static function complete_registration($phone, $name) {
        $user_id = niz_user_register($phone, $name);

        if (!$user_id) {
            Niz_WA_WhatsApp::send_text(
                $phone,
                "Registration failed. Please try again."
            );
            return;
        }

        $password = niz_user_password($phone);

        if (!$password) {
            Niz_WA_WhatsApp::send_text(
                $phone,
                "Registration successful.\nPlease reset your password after login."
            );
            return;
        }

        Niz_WA_WhatsApp::send_text(
            $phone,
            "Registration successful.\n\nYour temporary password is: {$password}\n\nLogin here:\nhttps://staging.masjid4all.com/member\n\nPlease change your password after login."
        );
    }
}