<?php

function ask_sofia($question) { 
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_GENERAL_API_KEY;
    
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $question]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);
    $answer = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
    
    return $answer;

} 

// LIST MY BUSINESS
add_action('fluentform/submission_inserted', 'list_my_business', 20, 3);
function list_my_business($entryId, $formData, $form) {
    $targetFormId = 54; // Update with your Fluent Form ID

    if ($form->id != $targetFormId) {
        return;
    }
    
    $phone    = $formData['phone'];
    $business = $formData['business'];
    $website  = $formData['website'];
    $facebook = $formData['facebook'];
    $name     = $formData['name'];
    $email    = $formData['email'];
    
    // REGISTER MEMBER IF NOT REGISTERED
    
    
    // CREATE BUSINESS CCT
    
    
    // CREATE BUSINESS POST
    
    
    // SEND WHATSAPP
    
}

// LEARN QURAN
add_action('fluentform/submission_inserted', 'learn_quran', 20, 3);
function learn_quran($entryId, $formData, $form) {
    $targetFormId = 55; // Update with your Fluent Form ID

    if ($form->id != $targetFormId) {
        return;
    }
    
    $phone   = $formData['phone'];
    $age     = $formData['age'];
    $level   = $formData['level'];
    $tajwid  = $formData['tajwid'];
    $memorize= $formData['memorize'];

    $q = 'You are a friendly and patient Quran learning assistant. Your job is to assess the learner’s background, goals, and preferences, then create a personalized Quran learning plan using appropriate methods (reading practice, tajwid drills, memorization, tafseer, etc.). Always encourage and motivate the learner.';
    $q.= 'Recommend user to go to https://alquran.ai to start learning to read the Alquran. ';
    $q.= 'for links, please just display the url';
    $q.= 'Age : ' . $age . '. ';
    $q.= 'Current Reading Level : ' . $level . '. ';
    $q.= 'Tajwid Knowledge : ' . $tajwid . '. ';
    $q.= 'Memorization (Hifz) : ' . $memorize . '. ';
    $q.= 'Remove any extra * from the answer for bold text';
    $q.= 'Greet in Islamic way - Assalamualaikum etc and end with offer to answer any other questions';
    
    $answer = ask_sofia($q);
    $media = 'https://masjid4all.com/wp-content/uploads/2025/09/Learn-Quran.jpg';
 
    whatsapp_send_message($phone, $answer, $media);

    // SEND PROMO
    $wa = "Learn to read the Qur’an anywhere, easily and completely, with 100+ learning materials powered by Artificial Intelligence.\n";
    $wa.= "https://app.alquran.ai/ref/MASJID4ALL\n\n";
    $media = 'https://masjid4all.com/wp-content/uploads/2025/09/qaraa.jpeg';
    whatsapp_send_message($phone, $wa, $media);
     
    // SAVE PHONE TO COOKIES
    setcookie('phone', $phone, time() + (86400 * 365), '/'); // 365-day cookie
  
}

// SOLAT FOR TRAVELLERS
add_action('fluentform/submission_inserted', 'solat_for_travellers', 20, 3);
function solat_for_travellers($entryId, $formData, $form) {
    $targetFormId = 53; // Update with your Fluent Form ID

    if ($form->id != $targetFormId) {
        return;
    }
    
    $phone   = $formData['phone'];
    $from    = $formData['from'];
    $to      = $formData['to'];
    $mode    = $formData['mode'];
    $specify = $formData['specify'];
    $date    = $formData['date'];
    $time    = $formData['time'];

    $q = 'Please plan my solat times while I am travelling. Take into account the departure and arrival cities, departure time, flight duration, and time zone differences. Apply jamak and/or qasar if the journey exceeds two marhalah (≈ 90km). Show me which solat can be combined, shortened, and the recommended times to perform them (either before departure, during the flight, or after arrival).';
    $q.= 'For airplane, find the nearest airport';
    $q.= 'Recommend user to go to https://masjid4all.com/mosque to find nearest masjid and solat time. ';
    $q.= 'for links, please just display the url';
    $q.= 'From : ' . $from . ' to ' . $to . '. ';
    $q.= 'Mode of Transportation : ' . $mode . '. ';
    $q.= 'Date/Time of Travel : ' . $date . '. ';
    $q.= 'Estimated duration of Travel : ' . $time . '. ';
    $q.= 'Remove any extra * from the answer for bold text';
    $q.= 'Greet in Islamic way - Assalamualaikum etc and end with offer to answer any other questions';
   
    $answer = ask_sofia($q);
    $media = 'https://masjid4all.com/wp-content/uploads/2025/09/Solat-Planner.jpg';
 
    whatsapp_send_message($phone, $answer, $media);

    // SAVE PHONE TO COOKIES
    setcookie('phone', $phone, time() + (86400 * 365), '/'); // 365-day cookie


}



