<?php

// Start
function wapi_start($phone){
    $image_url = "https://masjid4all.com/wp-content/uploads/2025/11/Masjid4ALL-Banner-2.jpg";
    $text  = "*Assalamualaikum & Welcome to Masjid4All!*\n\n";
    $text .= "We’re building the world’s largest index of 1 million mosques and Muslim-friendly businesses globally.\n\n";
    $text .= "It’s a huge mission — but with community support, we can make it a reality.\n\n";
    $text .= "Your participation today helps make information accessible for all Muslims, everywhere.\n\n";
    wapi_send_text_image($phone, $text, $image_url, $image_caption);
    sleep(2);
    wapi_start2($phone);
}

function wapi_start2($phone){
    $text  = "Hi! I’m *Sofia*, your friendly AI Chatbot\n\n";
    $text .= "I’ll guide you step-by-step on how you can participate in this exciting journey with *Masjid4All*.\n\n";
    $text .= "Together, we can help every Muslim easily find mosques and Muslim-friendly services—anywhere in the world.\n\n";
    $text .= "👉 *Ready to begin?*\n";
    //$text .= "*Dengan Pewarisan, anda boleh akses:*\n\n";
    //$text .= "- *Kalkulator Faraid* untuk mengetahui waris faraid dan pembahagiannya\n";
    //$text .= "- *Perunding AI* untuk bantu anda merancang secara PERCUMA\n";
    //$text .= "- *Skim KhairatPlus* untuk perlindungan keluarga tersayang\n";
    //$text .= "- *Dokumen Pewarisan* : Pesanan pada Waris, Fail Pewarisan & Wasiat\n";
    //$text .= "- *Proses Urus Harta Pusaka* bagi waris yang telah meninggal dunia\n";
    //$text .= "- *Khidmat Nasihat Pakar* bila anda perlukan panduan lanjut\n\n";
    //$text .= "*Mulakan langkah bijak hari ini.*\n_Semua hanya di hujung jari._\n";
    $buttons = [
        ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Get Started']],
    ];
    wapi_whatsapp_buttons($phone, $text, $buttons);
}
 
function wapi_get_started($phone){
    $text  = "*Great!* 🚀 \n*Let’s take the first step together*\n\n";
    $text .= "To help us expand the *Masjid4All* database, please open this link:\n\n";
    $text .= "https://masjid4all.com/mosque\n\n";
    $text .= "When you use this feature, our system will automatically gather the top 20 mosques closest to your location.\n\n";
    $text .= "If any of them are not indexed yet, we will instantly add them to the *Masjid4All directory*.\n\n";
    $text .= "Your simple action today helps Muslims everywhere find accurate mosque information easily.\n\n*GIVE IT A TRY before moving on to the next steps.*";
     
    $buttons = [
        ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Next Step']],
//        ['type' => 'reply', 'reply' => ['id' => 'btn_2', 'title' => 'Rancang Pewarisan']],
//        ['type' => 'reply', 'reply' => ['id' => 'btn_3', 'title' => 'Jana Pendapatan']],
    ];
    
    wapi_whatsapp_buttons($phone, $text, $buttons);

    //wapi_send_text($phone, $text);
    //sleep(2);
    //wapi_rancang_pewarisan($phone);
    //sleep(2);
    //wapi_pengiraan_faraid($phone);
    //sleep(2);
    //wapi_jana_pendapatan($phone);
    //sleep(10);
    //wapi_check_member($phone);
    //wapi_whatsapp_buttons($phone, $text, $buttons);
}

function wapi_registerxx($phone){
    $member_id = find_memberid_by_phone($phone);
    $user_id = niz_user_field_by_itemid($member_id, 'user_id');
    $text  = "*REGISTRATION*\n\n";
 
    if ($user_id>32323230){
        $text .= "Your temporary password : \n";
        $text .= "12345678\n\n";
        $text .= "Please login and change your password immediately\n\n";
        $text .= "Thank You\n*Masjid4All*\n\n";  
    }else{
        $text .= "You have not registered yet. Please Register\n";
    }

    wapi_send_text($phone, $text);
}   

  

add_shortcode('send_video1_template', function () {

    // When button clicked
    if (isset($_POST['send_video1_template'])) {

        // Phone from URL
        $to = isset($_GET['phone']) ? sanitize_text_field($_GET['phone']) : '';

        // Template details
        $template_name  = 'alya';
        $language_code  = 'ms';

        // Components (video only, no variables)
        $components = [
            [
                "type" => "header",
                "parameters" => [
                    [
                        "type"  => "video",
                        "video" => [
                            "link" => "https://pewarisan.my/wp-content/uploads/2025/12/Video-Alya-Promo.mp4"
                        ]
                    ]
                ] 
            ]
        ];

        // Send template
        wapi_send_template($to, $template_name, $language_code, $components);

        echo "<div style='padding:10px;background:#e1ffe1;margin-top:10px;
                        border-left:4px solid #33a533;'>
                Template 'alya' sent to $to
              </div>";
    }

    ob_start(); ?>
        <form method="post">
            <button type="submit" name="send_video1_template"
                style="padding:10px 20px;background:#0073aa;color:#fff;
                       border:none;border-radius:4px;cursor:pointer;">
                Send Template
            </button>
        </form>
    <?php
    return ob_get_clean();
});

add_shortcode('send_promo1_template', function () {

    // When button clicked
    if (isset($_POST['send_promo1_template'])) {

        // Send Whatsapp Template
        $to = $_GET['phone'];
        $template_name = 'pewarisan_promo1'; 
        $language_code = 'ms';
        $name = ".";
            
        $components = [
            // Header (image)
            [
                "type" => "header",
                "parameters" => [
                    [
                        "type" => "image",
                        "image" => [
                            "link" => "https://pewarisan.my/wp-content/uploads/2025/11/Rancang-Pewarisan-6.jpg"
                        ]
                    ]
                ]
            ],
            // Body (text variables)
            [
                "type" => "body",
                "parameters" => [
                    [
                        "type" => "text",
                        "text" => $name
                    ]
                ]
            ]
        ];
             
        // Call the function
        wapi_send_template($to, $template_name, $language_code, $components);


        echo "<div style='padding:10px;background:#e1ffe1;margin-top:10px;
                        border-left:4px solid #33a533;'>
                Template 'pewarisan_promo1' sent to $to
              </div>";
    }

    ob_start(); ?>
        <form method="post">
            <button type="submit" name="send_promo1_template"
                style="padding:10px 20px;background:#0073aa;color:#fff;
                       border:none;border-radius:4px;cursor:pointer;">
                Send Template
            </button>
        </form>
    <?php
    return ob_get_clean();
});


function wapi_send_mail($subject,$message){
    $to      = 'nizam7579@gmail.com';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail( $to, $subject, $message, $headers );
}
 
function wapi_mula($phone){
    $image_url = "https://pewarisan.my/wp-content/uploads/2025/11/Rancang-Pewarisan-6.jpg";
    $text  = "*SELAMAT DATANG KE PEWARISAN*\n";
    $text .= "_Sistem Rancang dan Urus Harta Pusaka secara Online._\n";
    $text .= "*Mudah, Cepat dan Patuh Syariah*";
    wapi_send_text_image($phone, $text, $image_url, $image_caption);
    sleep(2);
    wapi_mula2($phone);
    $msg = 'Phone : ' . $phone;
    wapi_send_mail('nizam7579@gmail.com','Mula Sekarang',$msg);
}
 

 




function wapi_affiliate($phone) {
    global $wpdb;
    
    $userID = 1496;
    $cctID = get_user_meta($userID, 'member_id', true);
    $prospect = niz_user_field_by_userid($cctID, 'visitors') ?? 0;
    $package = niz_user_field_by_userid($cctID, 'package');
    if ($package=='' || $package=='No Package'){
        $commission = '5%';
    }else{
        $commission = '20%';
    }
    $membercount = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_jet_cct_member WHERE referrer_id_mn = %d",
        $cctID
    ));
    
    $member = $membercount ?? 0;
    $premium = 0;
    global $post;
    $page = $post->post_name;
    
    if ($prospect<11 and $page == 'member'){
        // Start output buffering
        ob_start(); ?>
        
        <b>AFFILIATE PROGRAM</b><br>
        Earn up to 20% commission by sharing Pewarisan with others.<br> No selling or stock needed — just share, help families plan wisely, and grow your income effortlessly.
        <?php 
    }else{
        // Start output buffering
        ob_start(); ?>
        
        <table border="1" cellspacing="0" cellpadding="5" width="100%">
            <tr>
                <td width="70%" align="left">Pelawat</td>
                <td width="180px" align="right"><b><?= $prospect; ?></b></td>
            </tr>
            <tr>
                <td align="left">Ahli</td>
                <td align="right"><b><?= $member; ?></b></td>
            </tr>
            <tr>
                <td align="left">Ahli Premium</td>
                <td align="right"><b><?= $premium; ?></b></td>
            </tr>
            <tr>
                <td align="left">Kadar Komisyen</td>
                <td align="right"><b><?= $commission ; ?></b></td>
            </tr>
        </table>
    
        <?php 
    }
    
    return ob_get_clean();
}


 

function wapi_status($phone){
    $text = 'Status Keahlian';
    $buttons = [
        ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Lupa Password']],
    ];
    wapi_whatsapp_buttons($phone, $text, $buttons);
}
 

function wapi_pertanyaan($phone){
    $to = $phone;
    $text = 'Pertanyaan';
    $buttons = [
        ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Tanya Alya']],
        ['type' => 'reply', 'reply' => ['id' => 'btn_2', 'title' => 'Hubungi Kami']],
        ['type' => 'reply', 'reply' => ['id' => 'btn_3', 'title' => 'Laman Web']],
    ];
    wapi_whatsapp_buttons($to, $text, $buttons);
}
 

function wapi_hubungi($from){
    $text  = "*HUBUNGI KAMI*\n\n";
    $text .= "Alamat\nNo. 22-2, Jalan Prima Setapak 3,\nTaman Setapak, \n53300 Kuala Lumpur,\nMalaysia\n\n";
    enaizi_send_whatsapp_message($from, $text);

}



function wapi_menu($phone){
    $to = $phone;
    $header = 'MENU UTAMA';
    $body = "Untuk memudahan anda akses maklumat yang kami sediakan, sila klik butang dibawah untuk buat pilihan.\n\n_Untuk paparkan kembali Menu ini, taip_ *Menu*";
    $footer = 'Terima kasih';
    $sections = [
            [
                "title" => "Services",
                "rows" => [
                    ["id" => "ahli", "title" => "Ahli", "description" => "Status Ahli / Daftar sebagai Ahli / Lupa Password"],
                    ["id" => "affiliate", "title" => "Jana Pendapatan", "description" => "Jana Pendapatan Sampingan yang berterusan"],
                    ["id" => "video", "title" => "Video", "description" => "Kongsi Video yang menarik dan berguna"],
                    ["id" => "promo", "title" => "Promo", "description" => "Kongsi Video Promosi yang telah kami sediakan"]
                ]
            ]
        ] ; 
    
    wapi_whatsapp_list($to, $header, $body, $footer, $sections);

}


 

