<?php

class Niz_WA_Intent {

    private static function confirmation_message($intent) {
        $messages = [
            'password' => 'Do you want to reset your password?',
            'register' => 'Do you want to register as a member?',
            'claim_business' => 'Do you want to claim your business listing?',
            'membership_price' => 'Are you asking about membership pricing?',
            'advertise' => 'Are you interested in advertising with Masjid4All?'
        ];
    
        return $messages[$intent] ?? 'Please confirm your request.';
    }

    public static function process($phone, $message) {
        $msg = trim($message);
        $msg_upper = strtoupper($msg);

        /*
        |--------------------------------------------------------------------------
        | Session confirmation
        |--------------------------------------------------------------------------
        */

        $session = Niz_WA_Session::get($phone);
        if ($session) {
        
            $state = $session['state'] ?? '';
        
            /*
            |--------------------------------------------------------------------------
            | Existing member actions
            |--------------------------------------------------------------------------
            */
        
            if ($state === 'awaiting_existing_member_action') {
        
                if ($msg_upper === 'LOGIN') {
                    Niz_WA_Session::clear($phone);
                    Niz_WA_Actions::login($phone);
                    return;
                }
        
                if ($msg_upper === 'RESET_PASSWORD') {
                    Niz_WA_Session::clear($phone);
                    Niz_WA_Actions::request_password($phone);
                    return;
                }
            }
        
            /*
            |--------------------------------------------------------------------------
            | Password reset confirmation
            |--------------------------------------------------------------------------
            */
        
            if ($state === 'awaiting_password_reset_confirmation') {

                if (in_array($msg_upper, [
                    'YES',
                    'Y',
                    'CONFIRM_YES'
                ])) {
                    Niz_WA_Session::clear($phone);
                    Niz_WA_Actions::reset_password($phone);
                    return;
                }
            
                if (in_array($msg_upper, [
                    'NO',
                    'N',
                    'CONFIRM_NO'
                ])) {
                    Niz_WA_Session::clear($phone);
            
                    Niz_WA_WhatsApp::send_text(
                        $phone,
                        'Password reset cancelled.'
                    );
            
                    return;
                }
            }
        
            /*
            |--------------------------------------------------------------------------
            | Register confirmation
            |--------------------------------------------------------------------------
            */
        
            if ($state === 'awaiting_register_confirmation') {
        
                if (in_array($msg_upper, ['YES', 'CONFIRM_YES'])) {
        
                    Niz_WA_Session::set($phone, [
                        'state' => 'awaiting_name'
                    ]);
        
                    Niz_WA_WhatsApp::send_text(
                        $phone,
                        'Please enter your full name.'
                    );
        
                    return;
                }
        
                if (in_array($msg_upper, ['NO', 'CONFIRM_NO'])) {
                    Niz_WA_Session::clear($phone);
        
                    Niz_WA_WhatsApp::send_text(
                        $phone,
                        'Registration cancelled.'
                    );
        
                    return;
                }
            }
        
            /*
            |--------------------------------------------------------------------------
            | Awaiting name
            |--------------------------------------------------------------------------
            */
        
            if ($state === 'awaiting_name') {
        
                $name = trim($msg);
        
                if (!$name) {
                    Niz_WA_WhatsApp::send_text(
                        $phone,
                        'Please enter a valid full name.'
                    );
                    return;
                }
        
                Niz_WA_Session::clear($phone);
        
                Niz_WA_Actions::complete_registration($phone, $name);
                return;
            }
        
            /*
            |--------------------------------------------------------------------------
            | Legacy confirmation
            |--------------------------------------------------------------------------
            */
        
            if ($state === 'awaiting_confirmation') {
        
                if (in_array($msg_upper, [
                    'YES',
                    'Y',
                    'OK',
                    'CONFIRM',
                    'CONFIRM_YES'
                ])) {
        
                    $func = $session['context']['function'] ?? '';
        
                    if ($func && method_exists('Niz_WA_Actions', $func)) {
                        call_user_func(['Niz_WA_Actions', $func], $phone);
                    }
        
                    Niz_WA_Session::clear($phone);
                    return;
                }
        
                if (in_array($msg_upper, [
                    'NO',
                    'N',
                    'CANCEL',
                    'CONFIRM_NO'
                ])) {
                    Niz_WA_Session::clear($phone);
        
                    Niz_WA_WhatsApp::send_text(
                        $phone,
                        'Okay. Please tell me how I can help.'
                    );
        
                    return;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Exact keyword
        |--------------------------------------------------------------------------
        */

        $keyword = Niz_WA_Keywords::match($msg);

        if (!empty($keyword['matched'])) {
            $func = $keyword['function'];

            if ($func && method_exists('Niz_WA_Actions', $func)) {
                Niz_WA_Actions::$func($phone);
                return;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Intent detection
        |--------------------------------------------------------------------------
        */

        $intent = Niz_WA_Intent_AI::detect($msg);

        if (!empty($intent['intent'])) {

            Niz_WA_Session::set($phone, [
                'state' => 'awaiting_confirmation',
                'function' => $intent['intent']
            ]);

            Niz_WA_WhatsApp::send_buttons(
                $phone,
                self::confirmation_message($intent['intent']),
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
        | FAQ
        |--------------------------------------------------------------------------
        */

        $faq = Niz_WA_FAQ::search($msg);

        if ($faq) {
            Niz_WA_WhatsApp::send_text($phone, $faq);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | AI fallback
        |--------------------------------------------------------------------------
        */

        $reply = Niz_WA_AI::ask($msg);

        if ($reply) {
            Niz_WA_WhatsApp::send_text($phone, $reply);
            return;
        }

        Niz_WA_WhatsApp::send_text(
            $phone,
            'I am not sure I understood. Type HELP.'
        );
    }
}
