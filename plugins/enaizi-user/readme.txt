ENAIZI USER MANAGEMENT


** Features ** 

✅ User login (WhatsApp number + password)

✅ User registration (auto‑generated password sent via WhatsApp)

✅ Forgot password (reset password via WhatsApp)

✅ Display user info (shortcode + callable function)

✅ Phone number validation

✅ Extensible shortcode & function prefixes (niz_user_)



** Shortcodes **

[niz_user_login] – displays the login/registration form.
[niz_user_info] – shows current user’s profile (only for logged‑in users).


** Functions available for other plugins **

1. Check User 
   $user_id = niz_user_check($phone) 
   if empty, not a member 
   
2. Register New User 
   $user_id = niz_user_register($phone, $name) 
   if empty, already registered 
   
3. Get user_info from user_id 
   $info = niz_user_info($user_id) 
   $name = $info['name'] 
   $phone = $info['name'] 
   if empty, not a member 
   
4. Send password 
   $password = niz_user_password($phone) 
   if empty, not a member,



** Call functions from other plugins **

// Send Whatsapp Text





** Future additions **

You can easily extend the plugin by adding :
- new public functions to user-functions.php, 
- new AJAX handlers in ajax-handlers.php, 
- new shortcodes in shortcodes.php. 

The asset files are already separated, ready for more CSS/JS as needed.

