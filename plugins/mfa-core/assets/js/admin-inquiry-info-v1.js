// mfa-core admin-inquiry-info-v1.js — AJAX submit for the unified reply
// form on [mfa_admin_inquiry_info] (/admin/inquiry/info/). One "Reply
// Message" textarea, up to two submit buttons ("Reply via Email" / "Send
// via WhatsApp") - which AJAX action fires depends on which button was
// actually clicked (e.submitter), not just which handler is registered.
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.mfa-admin-inquiry-reply-form' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var channel = e.submitter ? e.submitter.value : '';
		var ajaxAction = 'email' === channel ? 'mfa_admin_inquiry_reply_email' : 'mfa_admin_inquiry_reply_whatsapp';

		var msg = form.querySelector( '[data-mfa-form-message]' );
		var buttons = form.querySelectorAll( 'button[type="submit"]' );
		var textarea = form.querySelector( 'textarea[name="message"]' );

		var data = new FormData( form );
		data.append( 'action', ajaxAction );

		if ( msg ) {
			msg.textContent = '';
			msg.classList.remove( 'is-success' );
		}
		buttons.forEach( function ( b ) { b.disabled = true; } );

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
						// Reload so the Status badge picks up the "Replied" update
						// the AJAX handler just made — same pattern as the business
						// update form's own success reload.
						setTimeout( function () { location.reload(); }, 1200 );
						return;
					}
					msg.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
				}
				buttons.forEach( function ( b ) { b.disabled = false; } );
			} )
			.catch( function () {
				if ( msg ) {
					msg.textContent = 'Network error. Please try again.';
				}
				buttons.forEach( function ( b ) { b.disabled = false; } );
			} );
	} );
} )();
