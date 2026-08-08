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



        if ( is_wp_error( $response ) ) {
            error_log( 'Niz_Mailer::send failed (connection error): ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            // wp_remote_post() only returns a WP_Error for connection-level
            // failures - Resend rejecting the request (bad API key, sender
            // domain not verified/authorized, etc.) comes back as a normal
            // HTTP response with a 4xx/5xx code, which the old `!is_wp_error()`
            // check treated as success. That silently swallowed real send
            // failures - e.g. a 403 "API key not authorized to send from
            // this domain" - while every caller kept reporting success.
            error_log( 'Niz_Mailer::send failed (HTTP ' . $code . '): ' . wp_remote_retrieve_body( $response ) );
            return false;
        }

        return true;


    }


}