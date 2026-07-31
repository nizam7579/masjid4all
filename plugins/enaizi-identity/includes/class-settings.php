<?php

if (!defined('ABSPATH')) {
    exit;
}


class Niz_Settings {


    public static function init() {

        add_action(
            'admin_menu',
            array(
                __CLASS__,
                'add_menu'
            )
        );


        add_action(
            'admin_init',
            array(
                __CLASS__,
                'register_settings'
            )
        );

    }



    /**
     * Add admin menu
     */
    public static function add_menu() {


        add_menu_page(

            'Enaizi Identity',

            'Enaizi Identity',

            'manage_options',

            'niz-identity',

            array(
                __CLASS__,
                'settings_page'
            ),

            'dashicons-admin-network',

            80

        );


    }



    /**
     * Register settings
     */
    public static function register_settings() {


        register_setting(
            'niz_identity_settings',
            'niz_identity_options'
        );


    }



    /**
     * Settings page
     */
    public static function settings_page() {


        $options = get_option(
            'niz_identity_options',
            array()
        );


        ?>

        <div class="wrap">

            <h1>
                Enaizi Identity Settings
            </h1>


            <form method="post" action="options.php">


                <?php

                settings_fields(
                    'niz_identity_settings'
                );


                ?>


                <table class="form-table">


                    <tr>

                        <th>
                            Sender Name
                        </th>


                        <td>

                            <input
                                type="text"
                                name="niz_identity_options[sender_name]"
                                value="<?php echo esc_attr(
                                    $options['sender_name'] ?? 'Enaizi Identity'
                                ); ?>"
                                class="regular-text"
                            >

                        </td>

                    </tr>



                    <tr>

                        <th>
                            Sender Email
                        </th>


                        <td>

                            <input
                                type="email"
                                name="niz_identity_options[sender_email]"
                                value="<?php echo esc_attr(
                                    $options['sender_email'] ?? ''
                                ); ?>"
                                class="regular-text"
                            >

                        </td>

                    </tr>



                    <tr>

                        <th>
                            Resend API Key
                        </th>


                        <td>

                            <input
                                type="password"
                                name="niz_identity_options[resend_key]"
                                value="<?php echo esc_attr(
                                    $options['resend_key'] ?? ''
                                ); ?>"
                                class="regular-text"
                            >

                            <p class="description">
                                Example: re_xxxxxxxxx
                            </p>

                        </td>

                    </tr>



                    <tr>

                        <th>
                            Login Redirect URL
                        </th>


                        <td>

                            <input
                                type="url"
                                name="niz_identity_options[login_redirect]"
                                value="<?php echo esc_attr(
                                    $options['login_redirect'] ?? home_url()
                                ); ?>"
                                class="regular-text"
                            >

                        </td>

                    </tr>
                    
                    <tr>
                        
                        <th>
                        Application Name
                        </th>
                        
                        
                        <td>
                        
                        <input
                        type="text"
                        name="niz_identity_options[app_name]"
                        value="<?php echo esc_attr(
                        $options['app_name'] ?? ''
                        ); ?>"
                        class="regular-text"
                        />
                        
                        </td>
                        
                        </tr>


                </table>


                <?php submit_button(); ?>


            </form>


        </div>


        <?php

    }


}