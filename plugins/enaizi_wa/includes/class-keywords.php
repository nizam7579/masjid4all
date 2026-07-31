<?php

class Niz_WA_Keywords {

    public static function match($message) {
        $msg = strtolower(trim($message));
        

        foreach (self::get_rules() as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                $keyword = strtolower(trim($keyword));

                if (strpos($msg, $keyword) !== false) {
                    return [
                        'matched' => true,
                        'function' => $rule['function']
                    ];
                }
            }
        }

        return [
            'matched' => false
        ];
    }

    private static function get_rules() {
        return [

            [
                'function' => 'start',
                'keywords' => [
                    'start',
                    'help',
                    'menu'
                ]
            ],

            [
                'function' => 'register',
                'keywords' => [
                    'register'
                ]
            ],

            [
                'function' => 'request_password',
                'keywords' => [
                    'request password',
                    'forgot password',
                    'password',
                    'reset password',
                    'kata laluan'
                ]
            ]

        ];
    }
}