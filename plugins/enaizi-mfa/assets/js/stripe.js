/**
 * Stripe Payment Form Handlers
 * Location: /assets/js/stripe.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('mfa-stripe-payment-form');
    if (!form) return;

    const submitBtn = document.getElementById('mfa-submit-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnSpinner = submitBtn.querySelector('.btn-spinner');
    const errorDiv = document.getElementById('mfa-form-error');

      
    // Safe error reporting utility function (Stops button processing freeze bugs)
    function handleFormUIError(message) {
        submitBtn.disabled = false;
        btnText.textContent = 'Proceed to Secure Payment';
        if (btnSpinner) btnSpinner.style.display = 'none';
        
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        alert('Payment Validation Error: ' + message);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // UI Reset
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        submitBtn.disabled = true;
        btnText.textContent = 'Processing...';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';
        
        // 1. SAFELY extract input values (Falls back to empty string if ID doesn't exist)
        const nameElement = document.getElementById('mfa-name') || document.getElementById('mfa-name');
        const emailElement = document.getElementById('mfa-email') || document.getElementById('email');
        const countryElement = document.getElementById('mfa-country-code');
        const whatsappElement = document.getElementById('mfa-whatsapp') || document.getElementById('whatsapp');
        
        const nameVal = nameElement ? nameElement.value.trim() : '';
        const emailVal = emailElement ? emailElement.value.trim() : '';
        const countryCodeVal = countryElement ? countryElement.value : '60';
        let whatsappVal = whatsappElement ? whatsappElement.value.trim() : '';
        
        // 2. Clean up WhatsApp input down to digits only
        whatsappVal = whatsappVal.replace(/[^0-9]/g, ''); 
        
        // Strip out any leading zero typed by mistake
        if (whatsappVal.startsWith('0')) {
            whatsappVal = whatsappVal.substring(1);
        }
        
        // 3. Combine country code dropdown and cleaned mobile number
        const completeGlobalWhatsApp = countryCodeVal + whatsappVal;
        
        // 4. Validate fields cleanly within standard JS flow boundary loop execution rules
        if (!nameVal || !emailVal || !whatsappVal) {
            handleFormUIError("Name, Email, and WhatsApp number fields are completely required.");
            return; // Halt process execution before hitting Fetch action layout
        }
        
        // 5. Build the delivery payload data tracking object
        const payload = {
            mfa_name: nameVal,        
            email: emailVal,          
            whatsapp: completeGlobalWhatsApp, 
            country_code: countryCodeVal      
        };

        // Fire request to custom WP REST API
        fetch(mfaStripeSettings.api_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': mfaStripeSettings.nonce // Pass authentication token to WordPress
            },
            body: JSON.stringify(payload)
        })
        .then(async response => {
            const isJson = response.headers.get('content-type')?.includes('application/json');
            const data = isJson ? await response.json() : null;

            if (!response.ok) {
                const errorMsg = data?.message || `Server responded with status ${response.status}`;
                throw new Error(errorMsg);
            }
            return data;
        })
        .then(data => {
            if (data && data.url) {
                window.location.href = data.url;
            } else {
                throw new Error('Could not parse secure checkout redirection data.');
            }
        })
        .catch(error => {
            // Send standard server errors to the UI handler wrapper seamlessly
            handleFormUIError(error.message);
        });
    });
});