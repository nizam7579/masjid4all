<?php
 
// This hook fires when the submission is saved. 
// We use a high priority (20) to ensure the payment status has been updated by the Stripe addon.
add_action('fluentform/submission_inserted', function ($submissionId, $formData, $form) {
    
   $target_form_id = 28;
    if ($form->id != $target_form_id) {
        return;
    }

    // Fetch the submission from the DB to get the LATEST payment status
    $submission = wpFluent()->table('fluentform_submissions')->where('id', $submissionId)->first();

    // Process if it is paid
    if ($submission->payment_status === 'paid') {
        $response = json_decode($submission->response, true);
        
        $name    = $response['name'] ?? 'N/A';
        $user_id = $response['user_id'] ?? 'N/A';
        $email   = $response['email'] ?? 'N/A';
        $phone   = get_field_from_userid($user_id, 'phone');

        // Update  Membership
        update_field_from_userid($user_id, 'status', 'Premium Lifetime Member');
 
        // Send Whatsapp
        
        
        
        // Send Email
        $to = 'nizam7579@gmail.com';
        $subject = "Payment Successful - Form 28";
        $message = "A payment was confirmed.\n\nName: $name\nEmail: $email\nUser ID: $user_id\nStatus: PAID";

        wp_mail($to, $subject, $message);

    }
}, 20, 3);

add_action('fluentform/after_payment_status_change', function ($newStatus, $transaction) {
    // 1. Filter by Form ID (Change 28 to your actual Form ID)
    $target_form_id = 28; 
    if ($transaction->form_id != $target_form_id) {
        return;
    }

    // 2. Fetch the original submission to get Name, Phone, and Email
    $submission = wpFluent()->table('fluentform_submissions')
                            ->where('id', $transaction->submission_id)
                            ->first();

    if ($submission) {
        // Decode the form data
        $form_data = json_decode($submission->response, true);

        // --- CUSTOMER DETAILS ---
        // Note: Ensure 'email', 'phone', 'names' match your form's Name Attributes
        $name  = $form_data['name'] ?? 'N/A';
        $email = $form_data['email'] ?? 'N/A';
        $phone = $form_data['phone'] ?? 'N/A';
        
        // --- PAYMENT DETAILS ---
        $status      = $newStatus;                         // e.g., 'paid', 'pending'
        $total_amount = $transaction->payment_total / 100; // Stripe provides cents, convert to dollars
        $currency    = $transaction->currency;
        $tx_id       = $transaction->transaction_hash;     // The Stripe ID (pi_...)

        // 4. Prepare the Email
        $to = 'nizam7579@gmail.com';
        $subject = "New Payment Received: {$full_name}";
        
        $message = "You have received a new payment through Fluent Forms.\n\n";
        $message .= "--- CUSTOMER DETAILS ---\n";
        $message .= "Name: {$full_name}\n";
        $message .= "Email: {$cust_email}\n";
        $message .= "Phone: {$cust_phone}\n\n";
        
        $message .= "--- PAYMENT DETAILS ---\n";
        $message .= "Amount: {$amount_paid} {$currency}\n";
        $message .= "Status: {$status}\n";
        $message .= "Transaction ID: {$transaction->transaction_hash}\n";
        $message .= "Submission ID: {$transaction->submission_id}\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        // 5. Send the Email
        wp_mail($to, $subject, $message, $headers);
        // --- YOUR CUSTOM LOGIC HERE ---
        // Example: Log to a file to verify it works
        error_log("Payment Update: {$full_name} ({$email}) paid {$total_amount} {$currency}. Status: {$status}");

    }
}, 10, 2);