<?php

$key =
sanitize_text_field(
    $_GET['key'] ?? ''
);


$login =
sanitize_text_field(
    $_GET['login'] ?? ''
);

?>


<div class="niz-reset-box">


<h2>
Create New Password
</h2>


<div id="niz-reset-message"></div>



<form id="niz-reset-form">


<input
type="hidden"
id="niz_reset_key"
value="<?php echo esc_attr($key); ?>"
>


<input
type="hidden"
id="niz_reset_login"
value="<?php echo esc_attr($login); ?>"
>


<input
type="password"
id="niz_new_password"
placeholder="New Password"
>


<button
type="button"
id="niz-reset-button"
>
Update Password
</button>


<input
type="hidden"
id="niz_reset_nonce"
value="<?php echo wp_create_nonce(
'niz_reset_password'
); ?>"
>


</form>


</div>