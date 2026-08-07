<div class="niz-login-wrapper">

    <div class="niz-login-box">


        <h2 class="niz-login-title">
            Welcome Back
        </h2>


        <p class="niz-login-subtitle">
            Sign in to continue to Masjid4All
        </p>



        <a 
            href="<?php echo esc_url(
                Niz_Google_Provider::login_url()
            ); ?>"
            class="niz-google-login">

            <span class="niz-google-icon">
                <img
                    src="<?php echo NIZ_IDENTITY_URL; ?>assets/images/google.svg"
                    alt="Google"
                    width="20"
                    height="20">
            </span>

            Continue with Google

        </a>



        <div class="niz-divider">

            <span>
                OR
            </span>

        </div>



        <div id="niz-login-message"></div>



        <form id="niz-login-form">


            <?php wp_nonce_field(
                'niz_login_nonce',
                'niz_nonce'
            ); ?>


            <div class="niz-input-group">

                <label>
                    Your Email Address
                </label>

                <input
                    type="email"
                    name="identifier"
                    id="niz_identifier"
                    placeholder="Enter your email address"
                    value="<?php

                    echo isset($_COOKIE['niz_last_login'])
                        ? esc_attr(
                            $_COOKIE['niz_last_login']
                        )
                        : '';

                    ?>"
                >

            </div>



            <div class="niz-input-group">

                <label>
                    Password
                </label>


                <div class="niz-password-wrap">
                <input
                    type="password"
                    name="password"
                    id="niz_password"
                    placeholder="Enter your password"
                >
                <button type="button" class="niz-password-toggle" data-niz-toggle-target="niz_password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
                </div>

            </div>




            <div class="niz-login-options">

                <label class="niz-remember">

                    <input
                        type="checkbox"
                        name="remember"
                        value="yes"
                    >

                    Remember me

                </label>



                <a href="/forgot-password/">

                    Forgot Password?

                </a>


            </div>




            <button
                type="submit"
                id="niz-login-button"
                class="niz-login-button"
            >

                Login

            </button>



        </form>



    </div>

</div>