console.log('NIZ Register JS Loaded');

jQuery(document).ready(function($){


    $('#niz-register-form').on(
        'submit',
        function(e){


            e.preventDefault();


            let button = $('#niz-register-button');


            button.prop(
                'disabled',
                true
            );


            button.text(
                'Creating account...'
            );


            console.log({

                nonce: $('#niz_register_nonce_field').val(),

                name: $('#niz_name').val(),

                email: $('#niz_email').val()

            });



            $.ajax({

                url: niz_ajax.ajax_url,

                type: 'POST',


                data: {


                    action:
                    'niz_register',


                    nonce:
                    $('#niz_register_nonce_field').val(),


                    name:
                    $('#niz_name').val(),


                    email:
                    $('#niz_email').val(),


                    phone:
                    $('#niz_phone').val(),


                    password:
                    $('#niz_password').val(),


                    confirm_password:
                    $('#niz_confirm_password').val()


                },


                success:function(response){


                    console.log(response);


                    if(response.success){


                        $('#niz-register-message')
                        .html(
                            '<div class="niz-success">'
                            + response.data.message +
                            '</div>'
                        );


                        setTimeout(function(){

                            window.location.href =
                            response.data.redirect;

                        },1000);


                    }
                    else{


                        $('#niz-register-message')
                        .html(
                            '<div class="niz-error">'
                            + response.data.message +
                            '</div>'
                        );


                    }


                },


                error:function(xhr){


                    console.log(
                        xhr.responseText
                    );


                    $('#niz-register-message')
                    .html(
                        '<div class="niz-error">Something went wrong.</div>'
                    );


                },


                complete:function(){


                    button.prop(
                        'disabled',
                        false
                    );


                    button.text(
                        'Create Account'
                    );


                }


            });


        }

    );


});