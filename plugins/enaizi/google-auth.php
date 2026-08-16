<?php

// 1. Fungsi Utama: Proses Log Masuk Google (Logik Baharu)
function process_google_auth($email, $name) {
    
    // Semak jika pengguna sudah wujud melalui emel
    $user = get_user_by('email', $email);

    if ($user) {
        // KES 1: Pengguna sudah ada emel dalam sistem.
        // Kita tarik nombor telefon mereka dari CCT untuk tujuan Set Cookie
        global $wpdb;
        $phone = $wpdb->get_var($wpdb->prepare("SELECT phone FROM wp_jet_cct_member WHERE user_id = %d", $user->ID));
        
        do_custom_user_login($user->ID, $phone);
        return ['status' => 'success', 'message' => 'Berjaya log masuk.'];
    }

    // KES 2: Emel tidak dijumpai (Belum berdaftar). Batalkan log masuk.
    return [
        'status' => 'unregistered', 
        'message' => 'Akaun tidak dijumpai. Sila daftar akaun dan sahkan nombor telefon anda terlebih dahulu.'
    ];
}

// 2. Fungsi Set Cookie dan Log Masuk WP
function do_custom_user_login($user_id, $phone) {
    // Seragamkan format cookie telefon
    if (!empty($phone) && $phone[0] !== '+') {
        $phone = '+' . $phone;
    }
    
    // Set Cookies
    setcookie("phone", $phone, time() + YEAR_IN_SECONDS, "/", '', is_ssl(), true);
    $_COOKIE['phone'] = $phone;
    
    // Arahkan WordPress untuk log masuk pengguna ini
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
}

// 3. Daftar fungsi AJAX untuk WordPress
add_action('wp_ajax_custom_google_login', 'handle_ajax_google_login');
add_action('wp_ajax_nopriv_custom_google_login', 'handle_ajax_google_login');

function handle_ajax_google_login() {
    $email = sanitize_email($_POST['email']);
    $nama = sanitize_text_field($_POST['nama']);

    if (empty($email)) {
        wp_send_json(['status' => 'error', 'message' => 'Emel tidak sah.']);
        wp_die();
    }

    $keputusan = process_google_auth($email, $nama);

    wp_send_json($keputusan);
    wp_die();
}

// 4. Skrip Frontend (Google Identity & Logik Modal)
add_action('wp_footer', function() {
    // 1. Skrip rasmi Google
    echo '<script src="https://accounts.google.com/gsi/client" async defer></script>';
    
    // 2. Skrip Custom JavaScript kita
    ?>
    <script>
        function parseJwt(token) {
            var base64Url = token.split('.')[1];
            var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        }

        window.urusLogMasukGoogle = function(response) {
            const dataPengguna = parseJwt(response.credential);
            const email = dataPengguna.email;
            const nama = dataPengguna.name;

            hantarKeBackend(email, nama); 
        };

        function hantarKeBackend(email, nama) {
            jQuery.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'custom_google_login',
                    email: email,
                    nama: nama
                },
                success: function(res) {
                    if (res.status === 'success') {
                        // Login Berjaya
                        window.location.href = '/member'; 
                    } 
                    else if (res.status === 'unregistered') {
                        // KES: Pengguna belum berdaftar / Tiada phone
                        alert(res.message);
                        
                        // ARAHAN ARKITEK: Cetuskan Modal Pendaftaran di sini!
                        // Sila tukar '.open-modal-register-auto' kepada class/ID butang Kadence Modal anda yang sebenar
                        let modalTriggerBtn = document.querySelector('.open-modal-register-auto');
                        if (modalTriggerBtn) {
                            modalTriggerBtn.click();
                        } else {
                            console.error('Butang modal tidak dijumpai. Sila periksa CSS class.');
                        }
                    }
                },
                error: function(err) {
                    alert('Ralat sistem berlaku. Sila cuba sebentar lagi.');
                }
            });
        }
    </script>
    <?php
});

// 5. Suntik CSS khusus (Kekal tiada perubahan)
add_action('wp_head', function() {
    ?>
    <style>
        .separator {
            display: flex; align-items: center; text-align: center; color: #888;
            margin: 20px 0; font-weight: bold; font-size: 14px; text-transform: uppercase;
        }
        .separator::before, .separator::after {
            content: ''; flex: 1; border-bottom: 1px solid #ddd;
        }
        .separator:not(:empty)::before { margin-right: .75em; }
        .separator:not(:empty)::after { margin-left: .75em; }
    </style>
    <?php
});