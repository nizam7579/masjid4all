<?php

/*

add_shortcode('phone_login', function () {

    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        return '<div style="text-align:center;padding:20px;">
                    <h3>Welcome back</h3>
                    <p>' . esc_html($user->display_name) . '</p>
                    <a href="' . wp_logout_url(home_url()) . '">Logout</a>
                </div>';
    }

    // Get saved phone (cookie)
    $saved_phone = isset($_COOKIE['user_phone']) ? esc_js($_COOKIE['user_phone']) : '';

    ob_start(); ?>

<style>
.spinner {
    display:inline-block;
    width:18px;
    height:18px;
    border:2px solid #ccc;
    border-top:2px solid #0073aa;
    border-radius:50%;
    animation:spin 0.7s linear infinite;
    margin-right:6px;
}
@keyframes spin {
    0% {transform:rotate(0deg);}
    100% {transform:rotate(360deg);}
}
.btn-loading {
    opacity:0.7;
    pointer-events:none;
}
</style>

<div style="max-width:400px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:8px;">
    <h3>Login / Register</h3>

    <div id="phoneSection">
        <input type="tel" id="phone" placeholder="e.g. 60123456789"
               style="width:100%;padding:12px;margin-bottom:10px;"
               value="<?php echo $saved_phone; ?>">
        <button id="checkUser" style="width:100%;padding:12px;">Continue</button>
    </div>

    <div id="dynamicSection" style="margin-top:15px;"></div>
    <div id="msg" style="margin-top:10px;"></div>
</div>

<script>
var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

// 🔄 AUTO-DETECT RETURNING USER
document.addEventListener("DOMContentLoaded", function(){

    var savedPhone = "<?php echo $saved_phone; ?>";

    if(savedPhone){
        document.getElementById('phone').value = savedPhone;
        checkUser(savedPhone, true); // auto trigger
    }
});


// STEP 1: BUTTON CLICK
document.getElementById('checkUser').onclick = function () {
    var phone = document.getElementById('phone').value.replace(/\D/g,'');
    checkUser(phone, false);
};


// 🔍 CHECK USER FUNCTION
function checkUser(phone, auto=false){

    var btn = document.getElementById('checkUser');
    var msg = document.getElementById('msg');

    // ⏳ SHOW LOADING
    if(!auto){
        btn.classList.add('btn-loading');
        btn.innerHTML = '<span class="spinner"></span>Checking...';
    }

    msg.innerHTML = '';

    fetch(ajaxurl, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=check_user&phone=" + phone
    })
    .then(res => res.json())
    .then(res => {

        // RESET BUTTON
        btn.classList.remove('btn-loading');
        btn.innerHTML = 'Continue';

        if(!res.success){
            msg.innerHTML = res.data || 'Error';
            return;
        }

        // 💾 SAVE COOKIE
        document.cookie = "user_phone=" + phone + "; path=/; max-age=2592000";

        if(res.data.exists){
            showLoginForm(phone);
        } else {
            sendOTP(phone);
        }
    });
}


// 🔐 LOGIN FORM
function showLoginForm(phone){

    document.getElementById('phoneSection').style.display = 'none';

    document.getElementById('dynamicSection').innerHTML = `
        <p>Phone: <strong>${phone}</strong></p>
        <input type="password" id="password" placeholder="Enter password"
            style="width:100%;padding:12px;margin-bottom:10px;">
        <button id="loginBtn" style="width:100%;padding:12px;">Login</button>
    `;

    document.getElementById('loginBtn').onclick = function(){

        var btn = this;
        var password = document.getElementById('password').value;

        btn.classList.add('btn-loading');
        btn.innerHTML = '<span class="spinner"></span>Logging in...';

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=do_login&phone="+phone+"&password="+password
        })
        .then(res=>res.json())
        .then(res=>{
            if(res.success){
                location.reload();
            }else{
                btn.classList.remove('btn-loading');
                btn.innerHTML = 'Login';
                document.getElementById('msg').innerHTML = "Invalid password";
            }
        });
    };
}


// 📲 SEND OTP (NEW USER)
function sendOTP(phone){

    var msg = document.getElementById('msg');

    msg.innerHTML = '<span class="spinner"></span>Sending OTP...';

    fetch(ajaxurl, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=send_otp&phone=" + phone
    })
    .then(res=>res.json())
    .then(res=>{
        if(res.success){
            showOTPForm(phone);
        }else{
            msg.innerHTML = res.data;
        }
    });
}


// 🔢 OTP FORM
function showOTPForm(phone){

    document.getElementById('phoneSection').style.display = 'none';

    document.getElementById('dynamicSection').innerHTML = `
        <p>Phone: <strong>${phone}</strong></p>
        <input type="text" id="otp" placeholder="Enter OTP"
            style="width:100%;padding:12px;margin-bottom:10px;">
        <button id="verifyBtn" style="width:100%;padding:12px;">Verify OTP</button>
    `;

    document.getElementById('verifyBtn').onclick = function(){

        var btn = this;
        var otp = document.getElementById('otp').value;

        btn.classList.add('btn-loading');
        btn.innerHTML = '<span class="spinner"></span>Verifying...';

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=verify_otp&phone="+phone+"&otp="+otp
        })
        .then(res=>res.json())
        .then(res=>{
            if(res.success){
                location.reload();
            }else{
                btn.classList.remove('btn-loading');
                btn.innerHTML = 'Verify OTP';
                document.getElementById('msg').innerHTML = res.data;
            }
        });
    };
}
</script>

<?php
return ob_get_clean();
});
*/


