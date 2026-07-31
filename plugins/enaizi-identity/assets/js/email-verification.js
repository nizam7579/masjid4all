jQuery(document).ready(function($){


$('.niz-resend-email').on(
'click',
function(){


let button=$(this);


button.prop(
'disabled',
true
);


button.text(
'Sending...'
);



$.ajax({

url:niz_ajax.ajax_url,

type:'POST',

data:{


action:
'niz_resend_verification',


nonce:
niz_email.nonce


},


success:function(response){


if(response.success){


$('#niz-email-message')
.html(
'✅ '+response.data.message
);


}
else{


$('#niz-email-message')
.html(
'❌ '+response.data.message
);


}


},


complete:function(){


button.prop(
'disabled',
false
);


button.text(
'Resend Verification Email'
);


}


});


});


});