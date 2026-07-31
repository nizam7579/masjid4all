<?php

class Niz_WA_Intent_AI {

    public static function detect($message) {
        $msg = strtolower(trim($message));

        $rules = [

            'password' => [
                'password',
                'forgot password',
                'reset password',
                'change password',
                'lupa password',
                'kata laluan',
                'tukar password'
            ],

            'register' => [
                'register',
                'signup',
                'sign up',
                'join',
                'daftar',
                'jadi ahli',
                'member'
            ],

            'claim_business' => [
                'claim business',
                'claim listing',
                'claim bisnes',
                'tuntut bisnes'
            ],

            'membership_price' => [
                'price',
                'pricing',
                'membership',
                'harga',
                'yuran'
            ],

            'advertise' => [
                'advertise',
                'advertising',
                'iklan',
                'promote'
            ]

        ];

        foreach ($rules as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($msg, $keyword) !== false) {
                    return [
                        'intent' => $intent,
                        'confidence' => 0.95
                    ];
                }
            }
        }

        return null;
    }
}