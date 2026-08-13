// mfa-core contact-form-v1.js — AJAX submit for [mfa_contact_form]
// (replaces FluentForm 8 on /contact-us/). No reload needed on success -
// unlike the business/website update forms, nothing else on this page
// depends on fresh server data, so the form is just replaced with a
// confirmation message.
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '#mfa-contact-form' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var msg = form.querySelector( '[data-mfa-form-message]' );
		var btn = form.querySelector( '.mfa-modal-submit' );
		var ajaxUrl = form.dataset.ajaxurl;

		var data = new FormData( form );
		data.append( 'action', 'mfa_contact_submit' );

		if ( msg ) {
			msg.textContent = '';
			msg.classList.remove( 'is-success' );
		}
		if ( btn ) {
			btn.disabled = true;
		}

		fetch( ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					form.innerHTML = '<p class="mfa-modal-message is-success">' + result.data.message + '</p>';
				} else if ( msg ) {
					msg.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
					if ( btn ) {
						btn.disabled = false;
					}
					if ( window.turnstile ) {
						window.turnstile.reset();
					}
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
