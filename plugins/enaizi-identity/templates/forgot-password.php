<div class="niz-forgot-box">


<h2>
Forgot Password
</h2>


<div id="niz-forgot-message"></div>


<form id="niz-forgot-form">


<input
type="email"
id="niz_reset_email"
placeholder="Enter your email"
required
>


<input
type="hidden"
id="niz_forgot_nonce"
value="<?php echo wp_create_nonce(
'niz_forgot_password'
); ?>"
>


<button
type="button"
id="niz-forgot-button"
>
Send Reset Link
</button>


</form>


</div>