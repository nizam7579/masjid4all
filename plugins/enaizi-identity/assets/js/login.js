jQuery(document).ready(function($){


    $('#niz-login-form').on(
        'submit',
        function(e){

            e.preventDefault();


            let button =
            $('#niz-login-button');


            button.prop(
                'disabled',
                true
            );


            button.text(
                'Logging in...'
            );


            $.ajax({

                url:
                niz_ajax.ajax_url,


                type:
                'POST',


                data: {

                    action:
                    'niz_login',


                    nonce:
                    $('#niz_nonce')
                    .val(),


                    identifier:
                    $('#niz_identifier')
                    .val(),


                    password:
                    $('#niz_password')
                    .val(),


                    remember:
                    $('input[name="remember"]')
                    .is(':checked')
                    ? 'yes'
                    : 'no'

                },


                success:function(response){


                    if(response.success){


                        window.location.href =
                        response.data.redirect;


                    }
                    else{


                        $('#niz-login-message')
                        .html(
                            response.data.message
                        );


                    }


                    button.prop(
                        'disabled',
                        false
                    );


                    button.text(
                        'Login'
                    );


                }


            });


        }
    );


});