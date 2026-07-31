<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Mailer {


    public static function send(
        $to,
        $subject,
        $html
    ) {


        $options =
        get_option(
            'niz_identity_options',
            array()
        );


        $api_key =
        $options['resend_key']
        ?? '';



        if (!$api_key) {

            return false;

        }



        $from =
        ($options['sender_name']
        ?? 'Enaizi')
        .
        ' <'
        .
        ($options['sender_email']
        ?? '')
        .
        '>';



        $response =
        wp_remote_post(

            'https://api.resend.com/emails',

            array(

                'headers'=>array(

                    'Authorization'=>
                    'Bearer ' . $api_key,

                    'Content-Type'=>
                    'application/json'

                ),


                'body'=>
                wp_json_encode(

                    array(

                        'from'=>$from,

                        'to'=>array($to),

                        'subject'=>$subject,

                        'html'=>$html

                    )

                )

            )

        );



        return !is_wp_error(
            $response
        );


    }


}