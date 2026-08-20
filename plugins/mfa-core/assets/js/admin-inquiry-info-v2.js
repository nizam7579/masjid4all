// mfa-core admin-inquiry-info-v2.js — AJAX submit for the unified reply
// form on [mfa_admin_inquiry_info] (/admin/inquiry/info/), plus the
// "Generate with AI" button.
//
// One "Reply Message" textarea, up to two submit buttons ("Reply via
// Email" / "Send via WhatsApp") - which AJAX action fires depends on which
// button was actually clicked (e.submitter), not just which handler is
// registered.
//
// The AI button only ever FILLS the textarea. It deliberately shares no
// code path with sending: a generated reply always passes through a human
// pressing one of the send buttons afterwards.
( function () {
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mfa-admin-inquiry-ai-btn' );
		if ( ! btn ) {
			return;
		}

		var form = btn.closest( '.mfa-admin-inquiry-reply-form' );
		if ( ! form ) {
			return;
		}

		var textarea = form.querySelector( 'textarea[name="message"]' );
		var note = form.querySelector( '[data-mfa-ai-note]' );

		if ( textarea && textarea.value.trim() &&
			! window.confirm( 'Replace what you have already typed with an AI draft?' ) ) {
			return;
		}

		var original = btn.textContent;
		btn.disabled = true;
		btn.textContent = 'Generating…';
		if ( note ) {
			note.textContent = '';
			note.classList.remove( 'is-error' );
		}

		var data = new FormData();
		data.append( 'action', 'mfa_admin_inquiry_ai_draft' );
		data.append( 'id', form.querySelector( '[name="id"]' ).value );
		data.append( 'nonce', form.querySelector( '[name="nonce"]' ).value );
		data.append( 'channel', btn.dataset.channel || 'email' );

		fetch( form.dataset.ajaxurl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				if ( result.success ) {
					if ( textarea ) {
						textarea.value = result.data.draft;
						textarea.focus();
					}
					if ( note ) {
						note.textContent = result.data.message;
					}
				} else if ( note ) {
					note.textContent = ( result.data && result.data.message ) || 'Could not generate a draft.';
					note.classList.add( 'is-error' );
				}
				btn.disabled = false;
				btn.textContent = original;
			} )
			.catch( function () {
				if ( note ) {
					note.textContent = 'Network error. Please try again.';
					note.classList.add( 'is-error' );
				}
				btn.disabled = false;
				btn.textContent = original;
			} );
	} );

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
