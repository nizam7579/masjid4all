document.addEventListener('DOMContentLoaded', function() {
    
    function setLoading(button, isLoading, text = 'Please wait...') {
        if (isLoading) {
            button.dataset.original = button.innerText;
            button.innerText = text;
            button.disabled = true;
        } else {
            button.innerText = button.dataset.original;
            button.disabled = false;
        }
    }

    function post(data) {
        return fetch(niz_user_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        }).then(r => r.json());
    }

    function getCookie(name) {
        let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    // FIX 1: Look for your original wrapper class (.niz-user-box) AND the new one
    const loginForms = document.querySelectorAll('.niz-user-box, .niz-user-form-wrapper');

    loginForms.forEach(function(formWrapper) {
        
        // FIX 2: Look for BOTH IDs (#) and Classes (.) so it never fails!
        const phoneEl = formWrapper.querySelector('.niz_user_phone, #niz_user_phone');
        const btn = formWrapper.querySelector('.niz_user_continue, #niz_user_continue');
        const dynamic = formWrapper.querySelector('.niz_user_dynamic, #niz_user_dynamic');
        const msg = formWrapper.querySelector('.niz_user_message, #niz_user_message');

        if (!btn || !phoneEl) return;

        let savedPhone = getCookie('user_phone');
        if (savedPhone) {
            phoneEl.value = savedPhone;
        }
        
        btn.addEventListener('click', async function(e) {
            e.preventDefault(); 
            
            let phone = phoneEl.value.replace(/\D/g, '');
            if (!phone) {
                msg.innerHTML = 'Enter a valid phone number';
                return;
            }
            msg.innerHTML = '';
            setLoading(btn, true, 'Checking...');
            try {
                let res = await post({ action: 'niz_user_check', phone: phone });
                if (!res.success) {
                    msg.innerHTML = res.data || 'Error occurred';
                    return;
                }
                if (res.data.exists) {
                    showLogin(phone);
                } else {
                    showRegister(phone);
                }
            } catch (err) {
                msg.innerHTML = 'Network error. Please try again.';
            } finally {
                setLoading(btn, false);
            }
        });

        function showLogin(phone) {
            dynamic.innerHTML = `
                <br>Please enter your password
                <input type="password" class="niz_user_pass niz-user-input" id="niz_user_pass" placeholder="Password">
                <button type="button" class="niz_user_login_btn niz-user-button" id="niz_user_login_btn">LOGIN</button>
                <div class="niz-user-note"><a href="#" class="niz_user_forgot" id="niz_user_forgot">Forgot password?</a></div>
            `;
            
            // Look for either ID or Class
            const loginBtn = formWrapper.querySelector('.niz_user_login_btn, #niz_user_login_btn');
            
            loginBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                
                let pass = formWrapper.querySelector('.niz_user_pass, #niz_user_pass').value;
                if (!pass) {
                    msg.innerHTML = 'Please enter your password';
                    return;
                }
                setLoading(loginBtn, true, 'Logging in...');
                try {
                    let res = await post({
                        action: 'niz_user_login',
                        phone: phone,
                        password: pass
                    });
                    if (res.success) {
                        location.reload();
                    } else {
                        msg.innerHTML = res.data;
                    }
                } catch (err) {
                    msg.innerHTML = 'Server error.';
                } finally {
                    setLoading(loginBtn, false);
                }
            });
            
            const forgotLink = formWrapper.querySelector('.niz_user_forgot, #niz_user_forgot');
            if (forgotLink) {
                forgotLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    showResetRequest(phone);
                });
            }
        }

        function showResetRequest(phone) {
            dynamic.innerHTML = `
                <div class="niz-user-reset-request">
                    <p><strong>Forgot your password?</strong></p>
                    <p>Please request a new temporary password via WhatsApp.</p>
                    <button type="button" class="niz_user_request_wa_btn niz-user-button" id="niz_user_request_wa_btn">Request Temporary Password</button>
                </div>
            `;
            
            const requestBtn = formWrapper.querySelector('.niz_user_request_wa_btn, #niz_user_request_wa_btn');
            requestBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const waNumber = '60189897579';
                const waMessage = 'send+password';
                const waUrl = `https://wa.me/${waNumber}?text=${waMessage}`;
                window.open(waUrl, '_blank');

                dynamic.innerHTML = '';
                msg.innerHTML = 'Please check your WhatsApp for the temporary password. You can now log in.';
                showLogin(phone);
            });
        }

        function showRegister(phone) {
            dynamic.innerHTML = `
                <div class="niz-user-note"><strong>Welcome! Please register – it's free.</strong></div>
                <input type="text" class="niz_user_name niz-user-input" id="niz_user_name" placeholder="Your full name">
                <button type="button" class="niz_user_register_btn niz-user-button" id="niz_user_register_btn">REGISTER</button>
            `;
            
            const regBtn = formWrapper.querySelector('.niz_user_register_btn, #niz_user_register_btn');
            regBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                
                let name = formWrapper.querySelector('.niz_user_name, #niz_user_name').value.trim();
                if (!name) {
                    msg.innerHTML = 'Please enter your name';
                    return;
                }
                setLoading(regBtn, true, 'Creating account...');
                try {
                    let res = await post({
                        action: 'niz_user_register',
                        phone: phone,
                        name: name
                    });
                    if (res.success) {
                        dynamic.innerHTML = `
                            <div class="niz-user-note"><strong>Congratulations! You are now registered.</strong><br>
                            Check your WhatsApp for the temporary password.</div>
                            <input type="password" class="niz_user_pass niz-user-input" id="niz_user_pass_reg" placeholder="Enter password">
                            <button type="button" class="niz_user_login_after_reg niz-user-button" id="niz_user_login_after_reg">Login</button>
                        `;
                        const loginAfterBtn = formWrapper.querySelector('.niz_user_login_after_reg, #niz_user_login_after_reg');
                        if (loginAfterBtn) {
                            loginAfterBtn.addEventListener('click', async function(e) {
                                e.preventDefault();
                                let pass = formWrapper.querySelector('.niz_user_pass, #niz_user_pass_reg').value;
                                let res2 = await post({
                                    action: 'niz_user_login',
                                    phone: phone,
                                    password: pass
                                });
                                if (res2.success) {
                                    location.reload();
                                } else {
                                    msg.innerHTML = res2.data || 'Login failed';
                                }
                            });
                        }
                    } else {
                        msg.innerHTML = res.data || 'Registration failed. Please try again.';
                    }
                } catch (err) {
                    console.error(err);
                    msg.innerHTML = 'Network error. Please check your connection.';
                } finally {
                    setLoading(regBtn, false);
                }
            });
        }
    });
});

// Logout handler (listening for both ID and Class)
document.body.addEventListener('click', async function(e) {
    const logoutBtn = e.target.closest('#niz_user_logout_btn, .niz_user_logout_btn'); 
    if (!logoutBtn) return;
     
    e.preventDefault();
    e.stopPropagation();
    
    const originalText = logoutBtn.innerText;
    logoutBtn.innerText = 'Logging out...';
    logoutBtn.style.opacity = '0.7';
    logoutBtn.style.pointerEvents = 'none';
    
    try {
        const response = await fetch(niz_user_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=niz_user_logout'
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            logoutBtn.innerText = originalText;
            logoutBtn.style.opacity = '1';
            logoutBtn.style.pointerEvents = 'auto';
        }
    } catch (err) {
        logoutBtn.innerText = originalText;
        console.error('AJAX error', err);
    }
});