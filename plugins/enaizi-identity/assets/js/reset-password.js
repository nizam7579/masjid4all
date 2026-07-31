jQuery(document).ready(function($){


$('#niz-reset-button').on(
'click',
function(){


let button=$(this);


button.prop(
'disabled',
true
);


button.text(
'Updating...'
);



$.ajax({

url:niz_reset_ajax.ajax_url,

type:'POST',

data:{


action:
'niz_reset_password',


nonce:
$('#niz_reset_nonce').val(),


key:
$('#niz_reset_key').val(),


login:
$('#niz_reset_login').val(),


password:
$('#niz_new_password').val()


},


success:function(response){


if(response.success){


window.location.href =
response.data.redirect;


}
else{


$('#niz-reset-message')
.html(
response.data.message
);


}


}


});


});


});