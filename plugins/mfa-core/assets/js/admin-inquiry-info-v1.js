// mfa-core admin-inquiry-info-v1.js — AJAX submit for the WhatsApp reply
// form on [mfa_admin_inquiry_info] (/admin/inquiry/info/), only rendered
// when the inquiry's WhatsApp conversation is still inside the 24h window.
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.mfa-admin-inquiry-whatsapp-form' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var msg = form.querySelector( '[data-mfa-form-message]' );
		var btn = form.querySelector( 'button[type="submit"]' );
		var textarea = form.querySelector( 'textarea[name="message"]' );

		var data = new FormData( form );
		data.append( 'action', 'mfa_admin_inquiry_reply_whatsapp' );

		if ( msg ) {
			msg.textContent = '';
			msg.classList.remove( 'is-success' );
		}
		if ( btn ) {
			btn.disabled = true;
		}

		fetch( form.dataset.ajaxurl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				if ( msg ) {
					if ( result.success ) {
						msg.textContent = result.data.message;
						msg.classList.add( 'is-success' );
						if ( textarea ) {
							textarea.value = '';
						}
					} else {
						msg.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
					}
				}
				if ( btn ) {
					btn.disabled = false;
				}
			} )
			.catch( function () {
				if ( msg ) {
					msg.textContent = 'Network error. Please try again.';
				}
				if ( btn ) {
					btn.disabled = false;
				}
			} );
	} );
} )();
