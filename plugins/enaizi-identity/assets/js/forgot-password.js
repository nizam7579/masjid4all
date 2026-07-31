jQuery(document).ready(function($){


    $('#niz-forgot-button').on(
        'click',
        function(e){


            e.preventDefault();


            let button = $(this);


            button.prop(
                'disabled',
                true
            );


            button.text(
                'Sending...'
            );



            $.ajax({

                url: niz_ajax.ajax_url,

                type: 'POST',

                data: {


                    action:
                    'niz_forgot_password',


                    nonce:
                    $('#niz_forgot_nonce').val(),


                    email:
                    $('#niz_reset_email').val()


                },


                success:function(response){


                    if(response.success){


                        $('#niz-forgot-message')
                        .html(
                            '<div class="niz-success">'
                            +
                            response.data.message
                            +
                            '</div>'
                        );


                        $('#niz-forgot-form')[0].reset();


                    }
                    else{


                        $('#niz-forgot-message')
                        .html(

                            '<div class="niz-error">'
                            +
                            response.data.message
                            +
                            '</div>'

                        );


                    }


                },


                error:function(xhr){


                    console.log(
                        xhr.responseText
                    );


                    $('#niz-forgot-message')
                    .html(

                        '<div class="niz-error">'
                        +
                        'Something went wrong.'
                        +
                        '</div>'

                    );


                },


                complete:function(){


                    button.prop(
                        'disabled',
                        false
                    );


                    button.text(
                        'Send Reset Link'
                    );


                }


            });


        }


    );


});