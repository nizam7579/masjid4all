document.addEventListener('DOMContentLoaded', function () {

    console.log('MFA website-update.js loaded');
    const updateBtns = document.querySelectorAll('.niz-web-update-btn');
    console.log('Buttons found:', updateBtns.length);

    if (!updateBtns.length) {
        return;
    }

    updateBtns.forEach(function (btn) {

        btn.addEventListener('click', async function (e) {

            e.preventDefault();

            // Prevent double click / duplicate AJAX
            if (this.dataset.processing === 'yes') {
                return;
            }

            this.dataset.processing = 'yes';


            const postId  = this.dataset.postId;
            const nonce   = this.dataset.nonce;
            const ajaxUrl = this.dataset.ajaxurl;

            const msgDiv = this.nextElementSibling;
            const originalText = this.innerText;


            // Loading state
            this.innerText = 'Generating content. Please wait...';
            this.disabled = true;
            this.style.opacity = '0.7';
            this.style.cursor = 'wait';

            msgDiv.innerHTML = '';


            try {

                const formData = new FormData();

                formData.append('action', 'mfa_website_ai_update');
                formData.append('post_id', postId);
                formData.append('nonce', nonce);


                console.log('Sending AJAX:', {
                    postId,
                    ajaxUrl
                });


                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                });


                const result = await response.json();


                console.log('AJAX Response:', result);


                if (result.success) {

                    msgDiv.innerHTML =
                        '<span style="color:#155724;background:#d4edda;padding:8px 12px;border-radius:4px;display:inline-block;">' +
                        '✅ ' + result.data.message +
                        '. Refreshing page...' +
                        '</span>';


                    this.innerText = 'Success!';
                    this.style.background = '#28a745';


                    setTimeout(function () {
                        location.reload();
                    }, 2000);


                } else {

                    msgDiv.innerHTML =
                        '<span style="color:#721c24;background:#f8d7da;padding:8px 12px;border-radius:4px;display:inline-block;">' +
                        '⚠️ ' + (result.data || 'Unknown error') +
                        '</span>';


                    this.innerText = originalText;
                    this.disabled = false;
                    this.style.opacity = '1';
                    this.style.cursor = 'pointer';

                    this.dataset.processing = 'no';
                }


            } catch (error) {


                console.error('AJAX Error:', error);


                msgDiv.innerHTML =
                    '<span style="color:#721c24;background:#f8d7da;padding:8px 12px;border-radius:4px;display:inline-block;">' +
                    '⚠️ Network error. Please check your connection.' +
                    '</span>';


                this.innerText = originalText;
                this.disabled = false;
                this.style.opacity = '1';
                this.style.cursor = 'pointer';

                this.dataset.processing = 'no';

            }

        });

    });

});