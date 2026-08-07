<div class="niz-login-wrapper">

<div class="niz-login-box">


<h2 class="niz-login-title">
Welcome to Masjid4All
</h2>


<p class="niz-login-subtitle">
Join thousands of Muslims worldwide
</p>



<a
href="<?php echo esc_url(
    Niz_Google_Provider::login_url()
); ?>"
class="niz-google-login">

<img
src="<?php echo NIZ_IDENTITY_URL; ?>assets/images/google.svg"
width="20"
height="20">

Continue with Google

</a>



<div class="niz-divider">
<span>OR</span>
</div>



<div id="niz-register-message"></div>



<form id="niz-register-form">


<?php

wp_nonce_field(
    'niz_register_nonce',
    'niz_register_nonce_field'
);

?>



<div class="niz-input-group">

<label>
Full Name
</label>


<input
type="text"
name="full_name"
id="niz_name"
placeholder="Enter your full name"
required>

</div>




<div class="niz-input-group">

<label>
Email Address
</label>


<input
type="email"
name="email"
id="niz_email"
placeholder="Enter your email"
required>

</div>




<div class="niz-input-group">

<label>
Password
</label>


<div class="niz-password-wrap">
<input
type="password"
name="password"
id="niz_register_password"
autocomplete="new-password"
required>
<button type="button" class="niz-password-toggle" data-niz-toggle-target="niz_register_password" aria-label="Show password">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
</button>
</div>

</div>




<div class="niz-input-group">

<label>
Confirm Password
</label>


<div class="niz-password-wrap">
<input
type="password"
name="confirm_password"
id="niz_confirm_password"
autocomplete="new-password"
required>
<button type="button" class="niz-password-toggle" data-niz-toggle-target="niz_confirm_password" aria-label="Show password">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
</button>
</div>

</div>




<label class="niz-checkbox">

<input
type="checkbox"
id="niz_accept_terms"
required>

<span>I agree to the <a href="/terms-of-service/" target="_blank">Terms of Service</a> and <a href="/privacy-policy/" target="_blank">Privacy Policy</a></span>

</label>




<button

type="submit"

id="niz-register-button"

class="niz-login-button">


Create Account


</button>



</form>




</div>

</div>