<?php

/*

//WHATSAPP VIEW - FORM DATA
add_action('fluentform/submission_inserted', 'faraid_calc_whatsapp', 20, 3);
function faraid_calc_whatsapp($entryId, $formData, $form) {
    $targetFormId = 52; // Update with your Fluent Form ID

    if ($form->id != $targetFormId) {
        return;
    }
    
    $type = $formData['type'];
    $deceased = $formData['deceased'];
    $phone = $formData['phone'];
    // SAVE PHONE TO COOKIES
    setcookie('phone', $phone, time() + (86400 * 365), '/'); // 365-day cookie

    $data = [
        'language'  => 'E',  // Assuming language is fixed
        'value'     => $formData['asset'],  // Ensure asset value is set
        'phone'     => $formData['phone'],
        'for'       => $formData['for'],
        //'name'      => $formData['name'],
    
        'F'     => $formData['f'],
        'M'     => $formData['m'],
        'H'     => $formData['h'],
        'W'     => $formData['w'],
        'S'     => $formData['s'],
        'D'     => $formData['d'],
        
        'FF'    => $formData['ff'],
        'FM'    => $formData['fm'],
        'MM'    => $formData['mm'],
        'SS'    => $formData['ss'],
        'SD'    => $formData['sd'],
        
        'FB'    => $formData['fb'],
        'FS'    => $formData['fs'],
        'MB'    => $formData['mb'],
        'MS'    => $formData['ms'],
        'PB'    => $formData['pb'],
        'PS'    => $formData['ps'],

        'SFB'   => $formData['sfb'],
        'SPB'   => $formData['spb'],
        'FFB'   => $formData['ffb'],
        'FPB'   => $formData['fpb'],
        'SFFB'  => $formData['sffb'],
        'SFPB'  => $formData['sfpb'],
    ];

    // API endpoint
    $url = "https://demo.pewarisan.my/wp-json/faraid/masjidforall";
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Convert data to JSON
    $jsonData = json_encode($data);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => 'cURL Error: ' . $error_msg];
    }
    
    // Close cURL session
    curl_close($ch);
}

/*
//Testing API
add_action('fluentform/submission_inserted', 'testing_api', 20, 3);
function testing_api($entryId, $formData, $form) {
    $targetFormId = 23;

    if ($form->id != $targetFormId) {
        return;
    }
    
    $name = $formData['name'];
    $phone = $formData['phone'];
    
    $wa .= "Thank you " . $name . " \n*masjid4all.com*\n";
    
    whatsapp_send_message($phone, $wa, $media);

}
*/